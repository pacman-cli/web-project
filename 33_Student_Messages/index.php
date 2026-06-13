<?php
// 33_Student_Messages/index.php
// Student message portal with instructor + peer chat threads (polling-supported)

require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/design-system.php';
require_once __DIR__ . '/../config/csrf.php';
requireRole('student');

$pdo = require_once __DIR__ . '/../config/db.php';
$studentId = $_SESSION['user_id'];
$studentName = $_SESSION['name'];

try {
    // 1. Fetch instructor threads (courses student is enrolled in)
    $instrStmt = $pdo->prepare("
        SELECT c.id as course_id, c.title as course_title,
               u.id as partner_id, u.name as partner_name, 'instructor' as partner_role
        FROM courses c
        JOIN enrollments e ON c.id = e.course_id
        JOIN instructor_assignments ia ON c.id = ia.course_id
        JOIN users u ON ia.instructor_id = u.id
        WHERE e.student_id = :sid AND e.status = 'approved'
        ORDER BY c.title ASC
    ");
    $instrStmt->execute(['sid' => $studentId]);
    $instructorThreads = $instrStmt->fetchAll();

    // 2. Fetch peer threads (other students in the same approved courses)
    $peerStmt = $pdo->prepare("
        SELECT DISTINCT c.id as course_id, c.title as course_title,
               u.id as partner_id, u.name as partner_name, 'student' as partner_role
        FROM courses c
        JOIN enrollments e1 ON c.id = e1.course_id
        JOIN enrollments e2 ON c.id = e2.course_id
        JOIN users u ON e2.student_id = u.id
        WHERE e1.student_id = :sid AND e1.status = 'approved'
          AND e2.status = 'approved'
          AND u.id != :sid2
        ORDER BY c.title ASC, u.name ASC
    ");
    $peerStmt->execute(['sid' => $studentId, 'sid2' => $studentId]);
    $peerThreads = $peerStmt->fetchAll();

    // 3. Merge all threads
    $allThreads = array_merge($instructorThreads, $peerThreads);

    // 4. Set active thread from URL params
    $activeCourseId = intval($_GET['course_id'] ?? 0);
    $activePartnerId = intval($_GET['partner_id'] ?? 0);

    if (($activeCourseId <= 0 || $activePartnerId <= 0) && !empty($allThreads)) {
        $activeCourseId = intval($allThreads[0]['course_id']);
        $activePartnerId = intval($allThreads[0]['partner_id']);
    }

    $activeThread = null;
    $conversations = [];

    foreach ($allThreads as $t) {
        $cId = $t['course_id'];
        $pId = $t['partner_id'];

        // Get last message in thread
        $msgStmt = $pdo->prepare("
            SELECT id, message_text, created_at, sender_id
            FROM chat_messages
            WHERE course_id = :cid
              AND ((sender_id = :me AND receiver_id = :them) 
                OR (sender_id = :them2 AND receiver_id = :me2))
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $msgStmt->execute(['cid' => $cId, 'me' => $studentId, 'them' => $pId, 'them2' => $pId, 'me2' => $studentId]);
        $lastMsg = $msgStmt->fetch();

        // Get unread count
        $unreadStmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM chat_messages
            WHERE course_id = :cid
              AND sender_id = :sid
              AND receiver_id = :rid
              AND is_read = FALSE
        ");
        $unreadStmt->execute(['cid' => $cId, 'sid' => $pId, 'rid' => $studentId]);
        $unreadCount = intval($unreadStmt->fetchColumn());

        $conversations[] = [
            'course_id'       => $cId,
            'course_title'    => $t['course_title'],
            'partner_id'      => $pId,
            'partner_name'    => $t['partner_name'],
            'partner_role'    => $t['partner_role'],
            'last_message'    => $lastMsg ? $lastMsg['message_text'] : 'No messages yet',
            'last_message_time' => $lastMsg ? date('g:i A', strtotime($lastMsg['created_at'])) : '',
            'unread_count'    => $unreadCount,
        ];

        if ($cId === $activeCourseId && $pId === $activePartnerId) {
            $activeThread = end($conversations);
        }
    }

    // Fetch student profile
    $profileStmt = $pdo->prepare("SELECT experience_level FROM students WHERE user_id = :sid");
    $profileStmt->execute(['sid' => $studentId]);
    $studentProfile = $profileStmt->fetch();
    $experienceLevel = $studentProfile ? $studentProfile['experience_level'] : 'beginner';

} catch (Exception $e) {
    error_log('DB error: ' . $e->getMessage()); die('A database error occurred. Please try again later.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php lms_head('Messages', 'student'); ?>
</head>
<body class="bg-surface font-body-md text-on-surface">

<?php lms_sidebar('student', '/33_Student_Messages/index.php'); ?>

<?php lms_topbar('student', 'Messages'); ?>

<!-- Main Content -->
<main id="lms-main-content" class="lms-main">
    <div class="flex-grow flex overflow-hidden">
        <!-- Conversation List (Left Pane) -->
        <section class="w-[380px] border-r border-outline-variant bg-surface-container-lowest flex flex-col">
            <div class="p-md border-b border-outline-variant">
                <h2 class="font-h2 text-h2 text-on-surface mb-xs">Conversations</h2>
                <p class="text-xs text-on-surface-variant">Chat with instructors and classmates</p>
            </div>
            <div class="flex-1 overflow-y-auto">
                <?php if (empty($conversations)): ?>
                    <p class="p-lg text-center text-on-surface-variant">No conversations yet. Enroll in a courses to start chatting.</p>
                <?php else: ?>
                    <?php foreach ($conversations as $t): ?>
                        <a href="?course_id=<?= $t['course_id'] ?>&partner_id=<?= $t['partner_id'] ?>" class="block p-md border-b border-outline-variant transition-colors <?= ($t['course_id'] === $activeCourseId && $t['partner_id'] === $activePartnerId) ? 'bg-primary/5 border-l-4 border-primary' : 'hover:bg-surface-container-low' ?>">
                            <div class="flex gap-sm">
                                <div class="w-10 h-10 rounded-full <?= $t['partner_role'] === 'instructor' ? 'bg-primary/10 text-primary' : 'bg-tertiary/10 text-tertiary' ?> flex items-center justify-center font-bold flex-shrink-0">
                                    <?= htmlspecialchars(substr($t['partner_name'], 0, 2)) ?>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex justify-between items-baseline mb-xs">
                                        <div class="flex items-center gap-xs min-w-0">
                                            <h4 class="font-label-md text-on-surface font-semibold truncate"><?= htmlspecialchars($t['partner_name']) ?></h4>
                                            <span class="text-[9px] px-1 py-[1px] rounded <?= $t['partner_role'] === 'instructor' ? 'bg-primary/10 text-primary' : 'bg-tertiary/10 text-tertiary' ?> font-bold uppercase flex-shrink-0"><?= $t['partner_role'] === 'instructor' ? 'Instr' : 'Student' ?></span>
                                        </div>
                                        <span class="text-[10px] text-secondary flex-shrink-0 ml-xs"><?= $t['last_message_time'] ?></span>
                                    </div>
                                    <p class="text-xs text-on-surface-variant truncate font-semibold"><?= htmlspecialchars($t['course_title']) ?></p>
                                    <p class="text-xs text-on-surface-variant truncate mt-0.5"><?= htmlspecialchars($t['last_message']) ?></p>
                                    <?php if ($t['unread_count'] > 0): ?>
                                        <div class="mt-xs flex justify-end">
                                            <span class="bg-primary text-on-primary text-[10px] font-bold px-1.5 py-0.5 rounded-full"><?= $t['unread_count'] ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- Active Chat Window (Right Pane) -->
        <section class="flex-grow flex flex-col bg-surface-bright">
            <?php if ($activeThread): ?>
                <!-- Chat Header -->
                <div class="h-[72px] px-md border-b border-outline-variant flex items-center justify-between bg-surface-container-lowest flex-shrink-0">
                    <div class="flex items-center gap-sm">
                        <div class="w-10 h-10 rounded-full <?= $activeThread['partner_role'] === 'instructor' ? 'bg-primary/10 text-primary' : 'bg-tertiary/10 text-tertiary' ?> flex items-center justify-center font-bold">
                            <?= htmlspecialchars(substr($activeThread['partner_name'], 0, 2)) ?>
                        </div>
                        <div>
                            <h3 class="font-label-md text-on-surface font-bold"><?= htmlspecialchars($activeThread['partner_name']) ?></h3>
                            <p class="text-xs text-secondary"><?= htmlspecialchars($activeThread['course_title']) ?> · <?= $activeThread['partner_role'] === 'instructor' ? 'Instructor' : 'Classmate' ?></p>
                        </div>
                    </div>
                </div>

                <!-- Chat Messages History -->
                <div id="chat-messages" class="flex-1 overflow-y-auto p-lg flex flex-col gap-md" aria-live="polite">
                    <!-- Dynamic Messages will load here -->
                </div>

                <!-- Chat Input Footer -->
                <div class="p-md bg-surface-container-lowest border-t border-outline-variant flex-shrink-0">
                    <form id="chat-form" onsubmit="sendMessage(event)" class="max-w-[1000px] mx-auto flex items-center gap-sm bg-surface p-xs border border-outline-variant rounded-xl shadow-sm">
                        <input type="hidden" name="course_id" value="<?= $activeCourseId ?>"/>
                        <input type="hidden" name="receiver_id" value="<?= $activePartnerId ?>"/>
                        <label for="chat-input" class="sr-only">Message</label>
                        <input required autocomplete="off" name="message_text" id="chat-input" class="flex-1 bg-transparent border-none focus:ring-0 text-body-md py-sm" placeholder="Type a message..." type="text"/>
                        <button type="submit" aria-label="Send message" class="w-[44px] h-[44px] bg-primary text-on-primary rounded-lg flex items-center justify-center hover:brightness-110 active:scale-95 transition-all shadow-md">
                            <span class="material-symbols-outlined" aria-hidden="true">send</span>
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div class="flex-1 flex items-center justify-center text-on-surface-variant font-body-lg">
                    Select a conversation to start chatting.
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>

<?php if ($activeThread): ?>
<script>
    let lastMessageId = 0;
    const courseId = <?= $activeCourseId ?>;
    const partnerId = <?= $activePartnerId ?>;
    const currentUserId = <?= $studentId ?>;

    function fetchMessages() {
        fetch(`/api/chat/fetch_messages.php?course_id=${courseId}&partner_id=${partnerId}&last_message_id=${lastMessageId}`)
        .then(res => res.json())
        .then(res => {
            if (res.success && res.data.length > 0) {
                const chatArea = document.getElementById('chat-messages');
                res.data.forEach(msg => {
                    const isSelf = parseInt(msg.sender_id) === currentUserId;
                    const msgDiv = document.createElement('div');
                    
                    if (isSelf) {
                        msgDiv.className = "flex justify-end gap-sm";
                        msgDiv.innerHTML = `
                            <div class="bg-primary p-sm rounded-xl rounded-br-none text-on-primary max-w-[80%] shadow-md">
                                <p class="text-body-md">${escapeHTML(msg.message_text)}</p>
                                <span class="text-[10px] text-on-primary/70 mt-xs block text-right">${formatTime(msg.created_at)}</span>
                            </div>
                        `;
                    } else {
                        msgDiv.className = "flex gap-sm max-w-[80%]";
                        msgDiv.innerHTML = `
                            <div class="bg-surface-container-high p-sm rounded-xl rounded-bl-none text-on-surface-variant shadow-sm">
                                <p class="text-body-md">${escapeHTML(msg.message_text)}</p>
                                <span class="text-[10px] text-secondary mt-xs block">${formatTime(msg.created_at)}</span>
                            </div>
                        `;
                    }
                    chatArea.appendChild(msgDiv);
                    lastMessageId = Math.max(lastMessageId, parseInt(msg.id));
                });
                chatArea.scrollTop = chatArea.scrollHeight;
            }
        })
        .catch(err => console.error('Polling error:', err));
    }

    function sendMessage(event) {
        event.preventDefault();
        const input = document.getElementById('chat-input');
        const text = input.value.trim();
        if (!text) return;

        fetch('/api/chat/send_message.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': '<?= csrf_token() ?>'
            },
            body: JSON.stringify({
                course_id: courseId,
                receiver_id: partnerId,
                message_text: text
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                input.value = '';
                fetchMessages();
            } else {
                alert(data.error || 'Failed to send message.');
            }
        })
        .catch(err => console.error('Send error:', err));
    }

    function escapeHTML(str) {
        return str.replace(/[&<>'"]/g, 
            tag => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                "'": '&#39;',
                '"': '&quot;'
            }[tag] || tag)
        );
    }

    function formatTime(dateTimeStr) {
        const date = new Date(dateTimeStr.replace(/-/g, "/"));
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    // Start Polling
    fetchMessages();
    setInterval(fetchMessages, 3000);
</script>
<?php endif; ?>
</body>
</html>

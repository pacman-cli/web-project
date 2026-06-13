<?php
// 30_Instructor_Messages/index.php
// Instructor message portal — view student conversations per course

require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/design-system.php';
require_once __DIR__ . '/../config/csrf.php';
requireRole('instructor');

$pdo = require_once __DIR__ . '/../config/db.php';
$instructorId = $_SESSION['user_id'];
$instructorName = $_SESSION['name'];

try {
    // Fetch courses the instructor teaches, with enrolled students
    $coursesStmt = $pdo->prepare("
        SELECT c.id as course_id, c.title as course_title
        FROM courses c
        JOIN instructor_assignments ia ON c.id = ia.course_id
        WHERE ia.instructor_id = :iid
        ORDER BY c.title ASC
    ");
    $coursesStmt->execute(['iid' => $instructorId]);
    $instructorCourses = $coursesStmt->fetchAll();

    // Build conversation list: each (course, student) pair is a thread
    $activeCourseId = intval($_GET['course_id'] ?? 0);
    $activeStudentId = intval($_GET['student_id'] ?? 0);

    $conversations = [];
    $activeThread = null;

    foreach ($instructorCourses as $c) {
        $cId = $c['course_id'];

        $studentsStmt = $pdo->prepare("
            SELECT u.id as student_id, u.name as student_name, u.email as student_email
            FROM users u
            JOIN enrollments e ON u.id = e.student_id
            WHERE e.course_id = :cid AND e.status = 'approved'
            ORDER BY u.name ASC
        ");
        $studentsStmt->execute(['cid' => $cId]);
        $enrolledStudents = $studentsStmt->fetchAll();

        foreach ($enrolledStudents as $s) {
            $sId = $s['student_id'];

            $msgStmt = $pdo->prepare("
                SELECT id, message_text, created_at, sender_id
                FROM chat_messages
                WHERE course_id = :course_id
                  AND ((sender_id = :instructor_id AND receiver_id = :student_id)
                    OR (sender_id = :student_id AND receiver_id = :instructor_id))
                ORDER BY created_at DESC LIMIT 1
            ");
            $msgStmt->execute([
                'course_id' => $cId,
                'instructor_id' => $instructorId,
                'student_id' => $sId
            ]);
            $lastMsg = $msgStmt->fetch();

            $unreadStmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM chat_messages
                WHERE course_id = :cid AND sender_id = :sid AND receiver_id = :iid AND is_read = FALSE
            ");
            $unreadStmt->execute(['cid' => $cId, 'sid' => $sId, 'iid' => $instructorId]);
            $unreadCount = intval($unreadStmt->fetchColumn());

            $thread = [
                'course_id' => $cId,
                'course_title' => $c['course_title'],
                'student_id' => $sId,
                'student_name' => $s['student_name'],
                'student_email' => $s['student_email'],
                'last_message' => $lastMsg ? $lastMsg['message_text'] : 'No messages yet',
                'last_message_time' => $lastMsg ? date('g:i A', strtotime($lastMsg['created_at'])) : '',
                'unread_count' => $unreadCount
            ];
            $conversations[] = $thread;

            if ($cId === $activeCourseId && $sId === $activeStudentId) {
                $activeThread = $thread;
            }
        }
    }

    // Auto-select first thread
    if (!$activeThread && !empty($conversations)) {
        $activeThread = $conversations[0];
        $activeCourseId = $activeThread['course_id'];
        $activeStudentId = $activeThread['student_id'];
    }

} catch (Exception $e) {
    error_log('DB error: ' . $e->getMessage());
    die('A database error occurred. Please try again later.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php lms_head('Messages', 'instructor'); ?>
</head>
<body class="bg-surface font-body-md text-on-surface">

<?php lms_sidebar('instructor', '/30_Instructor_Messages/index.php'); ?>
<?php lms_topbar('instructor', 'Messages'); ?>

<main id="lms-main-content" class="lms-main">
    <div class="flex-grow flex overflow-hidden">
        <!-- Conversation List -->
        <section class="w-[380px] border-r border-outline-variant bg-surface-container-lowest flex flex-col">
            <div class="p-md border-b border-outline-variant">
                <h2 class="font-h2 text-h2 text-on-surface mb-xs">Conversations</h2>
                <p class="text-xs text-on-surface-variant">Chat with students about their courses</p>
            </div>
            <div class="flex-1 overflow-y-auto">
                <?php if (empty($conversations)): ?>
                    <p class="p-lg text-center text-on-surface-variant">No students enrolled in your courses yet.</p>
                <?php else: ?>
                    <?php foreach ($conversations as $t): ?>
                        <a href="?course_id=<?= $t['course_id'] ?>&student_id=<?= $t['student_id'] ?>"
                           class="block p-md border-b border-outline-variant transition-colors <?= ($t['course_id'] === $activeCourseId && $t['student_id'] === $activeStudentId) ? 'bg-primary/5 border-l-4 border-primary' : 'hover:bg-surface-container-low' ?>">
                            <div class="flex gap-sm">
                                <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center font-bold text-primary flex-shrink-0">
                                    <?= htmlspecialchars(substr($t['student_name'], 0, 2)) ?>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex justify-between items-baseline mb-xs">
                                        <h4 class="font-label-md text-on-surface font-semibold truncate"><?= htmlspecialchars($t['student_name']) ?></h4>
                                        <span class="text-[10px] text-secondary"><?= $t['last_message_time'] ?></span>
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

        <!-- Chat Window -->
        <section class="flex-grow flex flex-col bg-surface-bright">
            <?php if ($activeThread): ?>
                <div class="h-[72px] px-md border-b border-outline-variant flex items-center justify-between bg-surface-container-lowest flex-shrink-0">
                    <div class="flex items-center gap-sm">
                        <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center font-bold text-primary">
                            <?= htmlspecialchars(substr($activeThread['student_name'], 0, 2)) ?>
                        </div>
                        <div>
                            <h3 class="font-label-md text-on-surface font-bold"><?= htmlspecialchars($activeThread['student_name']) ?></h3>
                            <p class="text-xs text-secondary"><?= htmlspecialchars($activeThread['course_title']) ?> Student</p>
                        </div>
                    </div>
                </div>

                <div id="chat-messages" class="flex-1 overflow-y-auto p-lg flex flex-col gap-md" aria-live="polite"></div>

                <div class="p-md bg-surface-container-lowest border-t border-outline-variant flex-shrink-0">
                    <form id="chat-form" onsubmit="sendMessage(event)" class="max-w-[1000px] mx-auto flex items-center gap-sm bg-surface p-xs border border-outline-variant rounded-xl shadow-sm">
                        <input type="hidden" name="course_id" value="<?= $activeCourseId ?>"/>
                        <input type="hidden" name="receiver_id" value="<?= $activeStudentId ?>"/>
                        <label for="chat-input" class="sr-only">Message</label>
                        <input required autocomplete="off" name="message_text" id="chat-input" class="flex-1 bg-transparent border-none focus:ring-0 text-body-md py-sm" placeholder="Type a message…" type="text"/>
                        <button type="submit" class="w-[44px] h-[44px] bg-primary text-on-primary rounded-lg flex items-center justify-center hover:brightness-110 active:scale-95 transition-all shadow-md" aria-label="Send message">
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
    const partnerId = <?= $activeStudentId ?>;
    const currentUserId = <?= $_SESSION['user_id'] ?>;

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
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?= csrf_token() ?>' },
            body: JSON.stringify({ course_id: courseId, receiver_id: partnerId, message_text: text })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                input.value = '';
                fetchMessages();
            }
        })
        .catch(err => console.error('Send error:', err));
    }

    function escapeHTML(str) {
        return str.replace(/[&<>'"]/g, tag => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[tag] || tag);
    }

    function formatTime(dateTimeStr) {
        const date = new Date(dateTimeStr.replace(/-/g, "/"));
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    fetchMessages();
    setInterval(fetchMessages, 3000);
</script>
<?php endif; ?>
</body>
</html>

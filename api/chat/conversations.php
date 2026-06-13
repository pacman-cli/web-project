<?php
// api/chat/conversations.php
// Returns conversation list with unread counts for the current user (instructor or student)

header('Content-Type: application/json');

require_once __DIR__ . '/../middleware.php';
requireAuth();

$pdo = require_once __DIR__ . '/../../config/db.php';
$userId = $_SESSION['user_id'];
$role = $_SESSION['role'];

try {
    $conversations = [];

    if ($role === 'instructor') {
        // Courses the instructor teaches
        $coursesStmt = $pdo->prepare("
            SELECT c.id as course_id, c.title as course_title
            FROM courses c
            JOIN instructor_assignments ia ON c.id = ia.course_id
            WHERE ia.instructor_id = :iid
            ORDER BY c.title ASC
        ");
        $coursesStmt->execute(['iid' => $userId]);
        $courses = $coursesStmt->fetchAll();

        foreach ($courses as $c) {
            $cId = $c['course_id'];

            // Enrolled students
            $studentsStmt = $pdo->prepare("
                SELECT u.id, u.name, u.email
                FROM users u
                JOIN enrollments e ON u.id = e.student_id
                WHERE e.course_id = :cid AND e.status = 'approved'
                ORDER BY u.name ASC
            ");
            $studentsStmt->execute(['cid' => $cId]);
            $students = $studentsStmt->fetchAll();

            foreach ($students as $s) {
                $conversations[] = buildConversation($pdo, $cId, $c['course_title'], $userId, $s['id'], $s['name'], $s['email'], 'student');
            }

            // Colleague instructors
            $colleaguesStmt = $pdo->prepare("
                SELECT u.id, u.name, u.email
                FROM users u
                JOIN instructor_assignments ia ON u.id = ia.instructor_id
                WHERE ia.course_id = :cid AND u.id != :iid
                ORDER BY u.name ASC
            ");
            $colleaguesStmt->execute(['cid' => $cId, 'iid' => $userId]);
            $colleagues = $colleaguesStmt->fetchAll();

            foreach ($colleagues as $col) {
                $conversations[] = buildConversation($pdo, $cId, $c['course_title'], $userId, $col['id'], $col['name'], $col['email'], 'instructor');
            }
        }
    } elseif ($role === 'student') {
        // Courses the student is enrolled in
        $coursesStmt = $pdo->prepare("
            SELECT c.id as course_id, c.title as course_title
            FROM courses c
            JOIN enrollments e ON c.id = e.course_id
            WHERE e.student_id = :sid AND e.status = 'approved'
            ORDER BY c.title ASC
        ");
        $coursesStmt->execute(['sid' => $userId]);
        $courses = $coursesStmt->fetchAll();

        foreach ($courses as $c) {
            $cId = $c['course_id'];

            // Assigned instructors
            $instructorsStmt = $pdo->prepare("
                SELECT u.id, u.name, u.email
                FROM users u
                JOIN instructor_assignments ia ON u.id = ia.instructor_id
                WHERE ia.course_id = :cid
                ORDER BY u.name ASC
            ");
            $instructorsStmt->execute(['cid' => $cId]);
            $instructors = $instructorsStmt->fetchAll();

            foreach ($instructors as $inst) {
                $conversations[] = buildConversation($pdo, $cId, $c['course_title'], $userId, $inst['id'], $inst['name'], $inst['email'], 'instructor');
            }

            // Peer students
            $peersStmt = $pdo->prepare("
                SELECT u.id, u.name, u.email
                FROM users u
                JOIN enrollments e ON u.id = e.student_id
                WHERE e.course_id = :cid AND e.status = 'approved' AND u.id != :sid
                ORDER BY u.name ASC
            ");
            $peersStmt->execute(['cid' => $cId, 'sid' => $userId]);
            $peers = $peersStmt->fetchAll();

            foreach ($peers as $p) {
                $conversations[] = buildConversation($pdo, $cId, $c['course_title'], $userId, $p['id'], $p['name'], $p['email'], 'student');
            }
        }
    }

    // Sort by last message time descending (most recent first), conversations with no messages at the end
    usort($conversations, function ($a, $b) {
        $aTime = $a['last_message_time_raw'] ?? '0000-00-00 00:00:00';
        $bTime = $b['last_message_time_raw'] ?? '0000-00-00 00:00:00';
        if ($aTime === $bTime) return 0;
        return $aTime > $bTime ? -1 : 1;
    });

    echo json_encode(['success' => true, 'data' => $conversations]);

} catch (Exception $e) {
    error_log('Conversations fetch error: ' . $e->getMessage());
    sendJSONError('Failed to load conversations.', 500);
}

/**
 * Build a single conversation record with last message + unread count.
 */
function buildConversation($pdo, $courseId, $courseTitle, $currentUserId, $partnerId, $partnerName, $partnerEmail, $partnerRole) {
    // Last message
    $msgStmt = $pdo->prepare("
        SELECT id, message_text, created_at, sender_id
        FROM chat_messages
        WHERE course_id = :cid
          AND ((sender_id = :uid AND receiver_id = :pid) OR (sender_id = :pid AND receiver_id = :uid))
        ORDER BY created_at DESC LIMIT 1
    ");
    $msgStmt->execute(['cid' => $courseId, 'uid' => $currentUserId, 'pid' => $partnerId]);
    $lastMsg = $msgStmt->fetch();

    // Unread count
    $unreadStmt = $pdo->prepare("
        SELECT COUNT(*) FROM chat_messages
        WHERE course_id = :cid AND sender_id = :pid AND receiver_id = :uid AND is_read = FALSE
    ");
    $unreadStmt->execute(['cid' => $courseId, 'pid' => $partnerId, 'uid' => $currentUserId]);
    $unreadCount = intval($unreadStmt->fetchColumn());

    return [
        'course_id'         => $courseId,
        'course_title'      => $courseTitle,
        'partner_id'        => $partnerId,
        'partner_name'      => $partnerName,
        'partner_email'     => $partnerEmail,
        'partner_role'      => $partnerRole,
        'last_message'      => $lastMsg ? $lastMsg['message_text'] : 'No messages yet',
        'last_message_time' => $lastMsg ? date('g:i A', strtotime($lastMsg['created_at'])) : '',
        'last_message_time_raw' => $lastMsg ? $lastMsg['created_at'] : '0000-00-00 00:00:00',
        'unread_count'      => $unreadCount,
    ];
}

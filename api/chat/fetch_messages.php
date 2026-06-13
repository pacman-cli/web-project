<?php
// api/chat/fetch_messages.php
// API endpoint to fetch chat messages within a course thread, supporting incremental polling updates

header('Content-Type: application/json');

require_once __DIR__ . '/../middleware.php';
requireAuth();

$pdo = require_once __DIR__ . '/../../config/db.php';

$courseId = intval($_GET['course_id'] ?? 0);
$partnerId = intval($_GET['partner_id'] ?? 0);
$lastMessageId = intval($_GET['last_message_id'] ?? 0);
$currentUserId = $_SESSION['user_id'];

if ($courseId <= 0 || $partnerId <= 0) {
    sendJSONError('course_id and partner_id are required parameters.');
}

try {
    // 1. Verify current user belongs to the course
    if ($_SESSION['role'] === 'student') {
        $check = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = :sid AND course_id = :cid AND status = 'approved'");
        $check->execute(['sid' => $currentUserId, 'cid' => $courseId]);
        if (!$check->fetch()) {
            sendJSONError('Unauthorized: You are not enrolled in this course thread.', 403);
        }
    } elseif ($_SESSION['role'] === 'instructor') {
        $check = $pdo->prepare("SELECT course_id FROM instructor_assignments WHERE instructor_id = :iid AND course_id = :cid");
        $check->execute(['iid' => $currentUserId, 'cid' => $courseId]);
        if (!$check->fetch()) {
            sendJSONError('Unauthorized: You are not assigned to teach this course thread.', 403);
        }
    }

    // 2. Verify partner belongs to the course with valid role relationship
    if ($_SESSION['role'] === 'student') {
        // Partner can be an instructor assigned to this course OR another enrolled student
        $isInstructor = $pdo->prepare("
            SELECT 1 FROM instructor_assignments 
            WHERE instructor_id = :pid AND course_id = :cid
        ");
        $isInstructor->execute(['pid' => $partnerId, 'cid' => $courseId]);

        $isPeer = $pdo->prepare("
            SELECT 1 FROM enrollments 
            WHERE student_id = :pid AND course_id = :cid AND status = 'approved'
        ");
        $isPeer->execute(['pid' => $partnerId, 'cid' => $courseId]);

        if (!$isInstructor->fetch() && !$isPeer->fetch()) {
            sendJSONError('Unauthorized: You can only chat with instructors or students in this course.', 403);
        }
    } elseif ($_SESSION['role'] === 'instructor') {
        $partnerStudentCheck = $pdo->prepare("
            SELECT 1 FROM enrollments 
            WHERE student_id = :pid AND course_id = :cid AND status = 'approved'
        ");
        $partnerStudentCheck->execute(['pid' => $partnerId, 'cid' => $courseId]);

        $partnerInstructorCheck = $pdo->prepare("
            SELECT 1 FROM instructor_assignments 
            WHERE instructor_id = :pid AND course_id = :cid
        ");
        $partnerInstructorCheck->execute(['pid' => $partnerId, 'cid' => $courseId]);

        if (!$partnerStudentCheck->fetch() && !$partnerInstructorCheck->fetch()) {
            sendJSONError('Unauthorized: You can only chat with students or other instructors assigned to this course.', 403);
        }
    }

    // 3. Mark unread messages from partner as read
    $readStmt = $pdo->prepare("
        UPDATE chat_messages 
        SET is_read = TRUE 
        WHERE course_id = :course_id 
          AND sender_id = :partner_id 
          AND receiver_id = :current_user_id 
          AND is_read = FALSE
    ");
    $readStmt->execute([
        'course_id' => $courseId,
        'partner_id' => $partnerId,
        'current_user_id' => $currentUserId
    ]);

    // 4. Fetch conversation messages
    $queryStr = "
        SELECT id, course_id, sender_id, receiver_id, message_text, is_read, created_at
        FROM chat_messages
        WHERE course_id = ?
          AND ((sender_id = ? AND receiver_id = ?) 
            OR (sender_id = ? AND receiver_id = ?))
    ";

    $params = [$courseId, $currentUserId, $partnerId, $partnerId, $currentUserId];

    if ($lastMessageId > 0) {
        $queryStr .= " AND id > ?";
        $params[] = $lastMessageId;
    }

    $queryStr .= " ORDER BY created_at ASC";

    $stmt = $pdo->prepare($queryStr);
    $stmt->execute($params);
    $messages = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data' => $messages
    ]);

} catch (Exception $e) {
    error_log('Chat fetch_messages error: ' . $e->getMessage());
    sendJSONError('An error occurred while fetching messages.', 500);
}

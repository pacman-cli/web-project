<?php
// api/chat/send_message.php
// API endpoint to send chat messages within specific course threads

header('Content-Type: application/json');

require_once __DIR__ . '/../middleware.php';
requireAuth();
require_once __DIR__ . '/../../config/csrf.php';
require_csrf();

$pdo = require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONError('Request method not supported. Only POST is allowed.', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$courseId = intval($input['course_id'] ?? 0);
$receiverId = intval($input['receiver_id'] ?? 0);
$messageText = trim($input['message_text'] ?? '');
$senderId = $_SESSION['user_id'];
$senderRole = $_SESSION['role'];

if ($courseId <= 0 || $receiverId <= 0 || empty($messageText)) {
    sendJSONError('course_id, receiver_id, and message_text are required.');
}

if ($receiverId === $senderId) {
    sendJSONError('Cannot send a message to yourself.');
}

try {
    // 1. Thread validation: verify sender is active in course
    if ($senderRole === 'student') {
        $check = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = :sid AND course_id = :cid AND status = 'approved'");
        $check->execute(['sid' => $senderId, 'cid' => $courseId]);
        if (!$check->fetch()) {
            sendJSONError('Unauthorized: You are not enrolled in this course thread.', 403);
        }
    } elseif ($senderRole === 'instructor') {
        $check = $pdo->prepare("SELECT course_id FROM instructor_assignments WHERE instructor_id = :iid AND course_id = :cid");
        $check->execute(['iid' => $senderId, 'cid' => $courseId]);
        if (!$check->fetch()) {
            sendJSONError('Unauthorized: You are not assigned to teach this course thread.', 403);
        }
    }

    // 2. Verify receiver belongs to the same course with valid role relationship
    if ($senderRole === 'student') {
        // Student can message instructors assigned to this course OR other enrolled students
        $isInstructor = $pdo->prepare("
            SELECT 1 FROM instructor_assignments 
            WHERE instructor_id = :rid AND course_id = :cid
        ");
        $isInstructor->execute(['rid' => $receiverId, 'cid' => $courseId]);

        $isPeer = $pdo->prepare("
            SELECT 1 FROM enrollments 
            WHERE student_id = :rid AND course_id = :cid AND status = 'approved'
        ");
        $isPeer->execute(['rid' => $receiverId, 'cid' => $courseId]);

        if (!$isInstructor->fetch() && !$isPeer->fetch()) {
            sendJSONError('Unauthorized: You can only message instructors or students in this course.', 403);
        }
    } elseif ($senderRole === 'instructor') {
        // Instructor can message students enrolled (approved) in this course OR other assigned instructors
        $recvStudentCheck = $pdo->prepare("
            SELECT 1 FROM enrollments 
            WHERE student_id = :rid AND course_id = :cid AND status = 'approved'
        ");
        $recvStudentCheck->execute(['rid' => $receiverId, 'cid' => $courseId]);

        $recvInstructorCheck = $pdo->prepare("
            SELECT 1 FROM instructor_assignments 
            WHERE instructor_id = :rid AND course_id = :cid
        ");
        $recvInstructorCheck->execute(['rid' => $receiverId, 'cid' => $courseId]);

        if (!$recvStudentCheck->fetch() && !$recvInstructorCheck->fetch()) {
            sendJSONError('Unauthorized: You can only message students or other instructors assigned to this course.', 403);
        }
    }

    // 3. Insert message
    $stmt = $pdo->prepare("
        INSERT INTO chat_messages (course_id, sender_id, receiver_id, message_text) 
        VALUES (:course_id, :sender_id, :receiver_id, :message_text)
    ");
    $stmt->execute([
        'course_id' => $courseId,
        'sender_id' => $senderId,
        'receiver_id' => $receiverId,
        'message_text' => $messageText
    ]);

    $messageId = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Message sent successfully.',
        'data' => [
            'id' => $messageId,
            'course_id' => $courseId,
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'message_text' => $messageText,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);

} catch (Exception $e) {
    error_log('Chat send_message error: ' . $e->getMessage());
    sendJSONError('An error occurred while sending the message.', 500);
}

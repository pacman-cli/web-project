<?php
// admin/enrollments.php
// Admin operations for reviewing, approving, and rejecting student enrollments

header('Content-Type: application/json');

require_once __DIR__ . '/../api/middleware.php';
requireRole('admin');

$pdo = require_once __DIR__ . '/../config/db.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        try {
            $stmt = $pdo->query("
                SELECT e.id, e.status, e.rejection_reason, e.resume_path, e.enrolled_at, e.reviewed_at,
                       u_student.id as student_id, u_student.name as student_name, u_student.email as student_email,
                       c.id as course_id, c.title as course_title,
                       u_reviewer.name as reviewer_name
                FROM enrollments e
                JOIN users u_student ON e.student_id = u_student.id
                JOIN courses c ON e.course_id = c.id
                LEFT JOIN users u_reviewer ON e.reviewed_by = u_reviewer.id
                ORDER BY e.enrolled_at DESC
            ");
            $enrollments = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $enrollments]);
        } catch (Exception $e) {
            error_log('Enrollments query failed: ' . $e->getMessage());
            sendJSONError('Failed to fetch enrollment records.', 500);
        }
        break;

    case 'POST':
        require_once __DIR__ . '/../config/csrf.php';
        require_csrf();

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $enrollment_id = intval($input['enrollment_id'] ?? 0);
        $status = trim($input['status'] ?? '');
        $rejection_reason = trim($input['rejection_reason'] ?? '');
        $reviewer_id = $_SESSION['user_id'];

        if ($enrollment_id <= 0 || !in_array($status, ['approved', 'rejected'])) {
            sendJSONError('Valid Enrollment ID and status (approved/rejected) are required.');
        }

        try {
            $stmt = $pdo->prepare("
                UPDATE enrollments 
                SET status = :status, 
                    rejection_reason = :rejection_reason, 
                    reviewed_by = :reviewed_by, 
                    reviewed_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->execute([
                'status' => $status,
                'rejection_reason' => $status === 'rejected' ? $rejection_reason : null,
                'reviewed_by' => $reviewer_id,
                'id' => $enrollment_id
            ]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => "Enrollment status updated to {$status}."]);
            } else {
                sendJSONError('Enrollment record not found or no changes made.', 404);
            }
        } catch (Exception $e) {
            error_log('Enrollment update failed: ' . $e->getMessage());
            sendJSONError('Failed to update enrollment.', 500);
        }
        break;

    default:
        sendJSONError('Method not allowed.', 405);
        break;
}

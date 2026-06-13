<?php
// student/certificates.php
// Triggers validation check for course graduation and tracks certificate hash generation

header('Content-Type: application/json');

require_once __DIR__ . '/../api/middleware.php';
requireRole('student');

$pdo = require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/cert_helper.php';
require_once __DIR__ . '/../config/pdf_cert.php';
require_once __DIR__ . '/../config/csrf.php';

$studentId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONError('Request method not supported. Only POST is allowed.', 405);
}

require_csrf();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $courseId = intval($input['course_id'] ?? 0);
    if ($courseId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Valid Course ID is required.']);
        exit;
    }

    // 1. Verify student approved enrollment
    $verifyStmt = $pdo->prepare("
        SELECT id FROM enrollments 
        WHERE student_id = :student_id AND course_id = :course_id AND status = 'approved'
    ");
    $verifyStmt->execute(['student_id' => $studentId, 'course_id' => $courseId]);
    if (!$verifyStmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied: You are not actively enrolled in this course.']);
        exit;
    }

    // 2. Calculate Assignment completion Progress
    $assignStmt = $pdo->prepare("SELECT COUNT(*) FROM assignments WHERE course_id = :course_id");
    $assignStmt->execute(['course_id' => $courseId]);
    $totalAssignments = intval($assignStmt->fetchColumn());

    $subStmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM submissions s
        JOIN assignments a ON s.assignment_id = a.id
        WHERE a.course_id = :course_id AND s.student_id = :student_id
    ");
    $subStmt->execute(['course_id' => $courseId, 'student_id' => $studentId]);
    $submittedAssignments = intval($subStmt->fetchColumn());

    $progressPercent = 0;
    if ($totalAssignments > 0) {
        $progressPercent = ($submittedAssignments / $totalAssignments) * 100;
    } else {
        $progressPercent = 100; // No assignments exists
    }

    // 3. Calculate Attendance Rate
    $attStmt = $pdo->prepare("
        SELECT status FROM attendance a
        JOIN schedules s ON a.schedule_id = s.id
        WHERE a.student_id = :student_id AND s.course_id = :course_id
    ");
    $attStmt->execute(['student_id' => $studentId, 'course_id' => $courseId]);
    $attendanceRecords = $attStmt->fetchAll(PDO::FETCH_COLUMN);

    $totalAttendanceSessions = count($attendanceRecords);
    $presentCount = 0;
    $excusedCount = 0;

    foreach ($attendanceRecords as $status) {
        if ($status === 'present') {
            $presentCount++;
        } elseif ($status === 'excused') {
            $excusedCount++;
        }
    }

    $attendanceRate = 100;
    if ($totalAttendanceSessions > 0) {
        $denominator = $totalAttendanceSessions - $excusedCount;
        if ($denominator > 0) {
            $attendanceRate = ($presentCount / $denominator) * 100;
        }
    }

    // 4. Validate requirements (Progress >= 90% and Attendance >= 80%)
    if ($progressPercent < 90.0 || $attendanceRate < 80.0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Graduation requirements not met yet.',
            'status' => [
                'current_assignment_progress' => round($progressPercent) . '% (Required: >=90%)',
                'current_attendance_rate' => round($attendanceRate) . '% (Required: >=80%)'
            ]
        ]);
        exit;
    }

    // 5. Check if certificate already generated
    $certCheck = $pdo->prepare("SELECT * FROM certificates WHERE student_id = :student_id AND course_id = :course_id");
    $certCheck->execute(['student_id' => $studentId, 'course_id' => $courseId]);
    $existingCert = $certCheck->fetch();

    if ($existingCert) {
        echo json_encode([
            'success' => true,
            'message' => 'Certificate retrieved successfully.',
            'certificate' => [
                'certificate_hash' => $existingCert['certificate_hash'],
                'file_path' => $existingCert['file_path'],
                'issued_at' => $existingCert['issued_at']
            ]
        ]);
        exit;
    }

    // 6. Generate certificate
    $certHash = cert_generate_hash($studentId, $courseId);
    $filePath = "/uploads/certificates/cert_" . $certHash . ".pdf";

    $certDir = __DIR__ . '/../uploads/certificates/';
    if (!file_exists($certDir)) {
        mkdir($certDir, 0777, true);
    }

    // Fetch names for the PDF
    $nameStmt = $pdo->prepare("SELECT name FROM users WHERE id = :sid");
    $nameStmt->execute(['sid' => $studentId]);
    $sName = $nameStmt->fetchColumn() ?: 'Student';

    $courseStmt = $pdo->prepare("SELECT title FROM courses WHERE id = :cid");
    $courseStmt->execute(['cid' => $courseId]);
    $cName = $courseStmt->fetchColumn() ?: 'Course';

    // Fetch instructor name for the course
    $instStmt = $pdo->prepare("
        SELECT u.name FROM users u
        JOIN instructor_assignments ia ON u.id = ia.instructor_id
        WHERE ia.course_id = :cid LIMIT 1
    ");
    $instStmt->execute(['cid' => $courseId]);
    $iName = $instStmt->fetchColumn() ?: 'Instructor';

    // Generate real PDF
    $pdfContent = generate_certificate_pdf($sName, $cName, $iName, date('F d, Y'), $certHash);
    file_put_contents($certDir . "cert_" . $certHash . ".pdf", $pdfContent);

    $stmt = $pdo->prepare("
        INSERT INTO certificates (student_id, course_id, certificate_hash, file_path) 
        VALUES (:student_id, :course_id, :certificate_hash, :file_path)
    ");
    $stmt->execute([
        'student_id' => $studentId,
        'course_id' => $courseId,
        'certificate_hash' => $certHash,
        'file_path' => $filePath
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Congratulations! Course certificate issued successfully.',
        'certificate' => [
            'certificate_hash' => $certHash,
            'file_path' => $filePath,
            'issued_at' => date('Y-m-d H:i:s')
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to process certificate.']);
}

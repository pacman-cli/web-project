<?php
// api/enrollment_resume.php
// Serves enrollment resume PDFs to admins for review

require_once __DIR__ . '/../config/auth_guard.php';
requireAuth();

// Only admins can view enrollment resumes
if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    die('Access denied: Admin only.');
}

$pdo = require_once __DIR__ . '/../config/db.php';

$enrollmentId = intval($_GET['id'] ?? 0);
if ($enrollmentId <= 0) {
    http_response_code(400);
    die('Invalid enrollment ID.');
}

try {
    $stmt = $pdo->prepare("SELECT resume_path, student_id FROM enrollments WHERE id = :id");
    $stmt->execute(['id' => $enrollmentId]);
    $enrollment = $stmt->fetch();

    if (!$enrollment) {
        http_response_code(404);
        die('Enrollment record not found.');
    }

    if (empty($enrollment['resume_path'])) {
        http_response_code(404);
        die('No resume uploaded for this enrollment.');
    }

    $absolutePath = realpath(__DIR__ . '/..' . $enrollment['resume_path']);
    if ($absolutePath === false || strpos($absolutePath, realpath(__DIR__ . '/..')) !== 0) {
        http_response_code(403);
        die('Access denied.');
    }

    if (!file_exists($absolutePath)) {
        http_response_code(404);
        die('Resume file not found on server.');
    }

    // Serve the PDF
    header('Content-Type: application/pdf');
    header('Content-Length: ' . filesize($absolutePath));
    header('Content-Disposition: inline; filename="enrollment_' . $enrollmentId . '_resume.pdf"');
    header('X-Content-Type-Options: nosniff');

    ob_clean();
    flush();
    readfile($absolutePath);
    exit;

} catch (Exception $e) {
    error_log('Enrollment resume fetch error: ' . $e->getMessage());
    http_response_code(500);
    die('Server error.');
}

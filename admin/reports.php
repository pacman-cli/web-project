<?php
// admin/reports.php
// Admin analytics reports endpoints compiling system performance stats

header('Content-Type: application/json');

require_once __DIR__ . '/../api/middleware.php';
requireRole('admin');

$pdo = require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSONError('Method not allowed.', 405);
}

try {
    $reports = [];

    // 1. General Metrics Counts
    $metricsQuery = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM users WHERE role = 'student') as total_students,
            (SELECT COUNT(*) FROM users WHERE role = 'instructor') as total_instructors,
            (SELECT COUNT(*) FROM courses) as total_courses,
            (SELECT COUNT(*) FROM enrollments WHERE status = 'approved') as active_enrollments,
            (SELECT COUNT(*) FROM enrollments WHERE status = 'pending') as pending_enrollments
    ");
    $reports['general_metrics'] = $metricsQuery->fetch();

    // 2. Course Popularity (Enrollments per Course)
    $coursesQuery = $pdo->query("
        SELECT c.id, c.title, c.difficulty, c.price, COUNT(e.id) as enrollment_count
        FROM courses c
        LEFT JOIN enrollments e ON c.id = e.course_id AND e.status = 'approved'
        GROUP BY c.id
        ORDER BY enrollment_count DESC
        LIMIT 10
    ");
    $reports['course_popularity'] = $coursesQuery->fetchAll();

    // 3. Department / Instrument Loads (Courses per Instrument Category)
    $instrumentLoadsQuery = $pdo->query("
        SELECT i.name as instrument_name, COUNT(c.id) as course_count
        FROM instruments i
        LEFT JOIN courses c ON i.id = c.instrument_id
        GROUP BY i.id
        ORDER BY course_count DESC
    ");
    $reports['instrument_loads'] = $instrumentLoadsQuery->fetchAll();

    // 4. Feedback & Ratings Summary (Avg rating per course)
    $ratingsQuery = $pdo->query("
        SELECT c.title as course_title, ROUND(AVG(rf.rating), 1) as avg_rating, COUNT(rf.id) as review_count
        FROM courses c
        JOIN ratings_feedback rf ON c.id = rf.course_id
        GROUP BY c.id
        ORDER BY avg_rating DESC
    ");
    $reports['course_ratings'] = $ratingsQuery->fetchAll();

    echo json_encode([
        'success' => true,
        'reports' => $reports
    ]);

} catch (Exception $e) {
    error_log('Reports error: ' . $e->getMessage());
    sendJSONError('Failed to generate reports.', 500);
}

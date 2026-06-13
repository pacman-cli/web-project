<?php
// 15_Reports_Analytics/index.php
// Dynamic Reports & Analytics portal for Admins

require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/design-system.php';
requireRole('admin'); // Protect via role guard

$pdo = require_once __DIR__ . '/../config/db.php';

try {
    // 1. General Metrics Counts
    $metricsQuery = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM users WHERE role = 'student') as total_students,
            (SELECT COUNT(*) FROM users WHERE role = 'instructor') as total_instructors,
            (SELECT COUNT(*) FROM courses) as total_courses,
            (SELECT COUNT(*) FROM enrollments WHERE status = 'approved') as active_enrollments,
            (SELECT COUNT(*) FROM enrollments WHERE status = 'pending') as pending_enrollments,
            (SELECT COUNT(*) FROM certificates) as issued_certificates
    ");
    $metrics = $metricsQuery->fetch();

    // 2. Course Popularity (Enrollments per Course)
    $coursesQuery = $pdo->query("
        SELECT c.id, c.title, c.difficulty, c.price, COUNT(e.id) as enrollment_count
        FROM courses c
        LEFT JOIN enrollments e ON c.id = e.course_id AND e.status = 'approved'
        GROUP BY c.id
        ORDER BY enrollment_count DESC
        LIMIT 5
    ");
    $coursePopularity = $coursesQuery->fetchAll();

    // 3. Instrument Popularity percentage helper
    $instrumentLoadsQuery = $pdo->query("
        SELECT i.name as instrument_name, COUNT(c.id) as course_count
        FROM instruments i
        LEFT JOIN courses c ON i.id = c.instrument_id
        GROUP BY i.id
        ORDER BY course_count DESC
    ");
    $instrumentLoads = $instrumentLoadsQuery->fetchAll();

    // 4. Low Attendance Students (< 75%)
    // Attendance rate = present / total. Find students where rate < 75
    $lowAttendanceQuery = $pdo->query("
        SELECT u.id as student_id, u.name as student_name, c.title as course_title,
               ROUND((COUNT(CASE WHEN a.status = 'present' THEN 1 END) / GREATEST(COUNT(a.id), 1)) * 100) as attendance_rate,
               u_inst.name as instructor_name
        FROM attendance a
        JOIN schedules s ON a.schedule_id = s.id
        JOIN courses c ON s.course_id = c.id
        JOIN users u ON a.student_id = u.id
        LEFT JOIN instructor_assignments ia ON c.id = ia.course_id
        LEFT JOIN users u_inst ON ia.instructor_id = u_inst.id
        GROUP BY a.student_id, c.id, u.name, c.title, u_inst.name
        HAVING attendance_rate < 75
        LIMIT 5
    ");
    $lowAttendance = $lowAttendanceQuery->fetchAll();

    // 5. Instructor Workload
    $instructorWorkloadQuery = $pdo->query("
        SELECT u.name as instructor_name, COUNT(ia.course_id) as course_count,
               (SELECT COUNT(e.id) FROM enrollments e WHERE e.course_id IN (SELECT ia2.course_id FROM instructor_assignments ia2 WHERE ia2.instructor_id = u.id) AND e.status = 'approved') as student_count
        FROM users u
        LEFT JOIN instructor_assignments ia ON u.id = ia.instructor_id
        WHERE u.role = 'instructor'
        GROUP BY u.id
        ORDER BY student_count DESC
        LIMIT 5
    ");
    $instructorWorkload = $instructorWorkloadQuery->fetchAll();

} catch (Exception $e) {
    error_log('DB error: ' . $e->getMessage()); die('A database error occurred. Please try again later.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php lms_head('Reports & Analytics', 'admin'); ?>
</head>
<body class="bg-background text-on-surface font-body-md">
<?php lms_sidebar('admin', '/15_Reports_Analytics/index.php'); ?>
<?php lms_topbar('admin', 'Reports & Analytics'); ?>

<!-- Main Content Wrapper -->
<main id="lms-main-content" class="lms-main">

    <!-- Page Content -->
    <div class="page-content">
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row md:items-end justify-between mb-lg gap-md">
            <div>
                <h2 class="text-h1 font-h1 text-on-surface">Reports &amp; Analytics</h2>
                <p class="text-body-lg text-secondary">Monitor enrollment, attendance, and academic performance across all departments.</p>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-md mb-lg">
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-md shadow-sm">
                <div class="flex items-center justify-between mb-2">
                    <span class="material-symbols-outlined text-primary bg-primary-container/20 p-2 rounded-lg" aria-hidden="true">group</span>
                </div>
                <p class="text-label-md text-secondary">Total Students</p>
                <h3 class="text-h2 font-h1 mt-1"><?= intval($metrics['total_students']) ?></h3>
            </div>
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-md shadow-sm">
                <div class="flex items-center justify-between mb-2">
                    <span class="material-symbols-outlined text-primary bg-primary-container/20 p-2 rounded-lg" aria-hidden="true">person</span>
                </div>
                <p class="text-label-md text-secondary">Active Instructors</p>
                <h3 class="text-h2 font-h1 mt-1"><?= intval($metrics['total_instructors']) ?></h3>
            </div>
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-md shadow-sm">
                <div class="flex items-center justify-between mb-2">
                    <span class="material-symbols-outlined text-primary bg-primary-container/20 p-2 rounded-lg" aria-hidden="true">menu_book</span>
                </div>
                <p class="text-label-md text-secondary">Active Courses</p>
                <h3 class="text-h2 font-h1 mt-1"><?= intval($metrics['total_courses']) ?></h3>
            </div>
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-md shadow-sm">
                <div class="flex items-center justify-between mb-2">
                    <span class="material-symbols-outlined text-primary bg-primary-container/20 p-2 rounded-lg" aria-hidden="true">pending_actions</span>
                </div>
                <p class="text-label-md text-secondary">Pending Enrollments</p>
                <h3 class="text-h2 font-h1 mt-1"><?= intval($metrics['pending_enrollments']) ?></h3>
            </div>
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-md shadow-sm">
                <div class="flex items-center justify-between mb-2">
                    <span class="material-symbols-outlined text-primary bg-primary-container/20 p-2 rounded-lg" aria-hidden="true">verified</span>
                </div>
                <p class="text-label-md text-secondary">Issued Certificates</p>
                <h3 class="text-h2 font-h1 mt-1"><?= intval($metrics['issued_certificates']) ?></h3>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-md mb-lg">
            <!-- Course-wise Enrollment -->
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-lg shadow-sm">
                <h4 class="text-h3 font-h3 mb-lg">Top Enrolled Courses</h4>
                <div class="space-y-6">
                    <?php if (empty($coursePopularity)): ?>
                        <p class="text-secondary">No enrollments yet.</p>
                    <?php else: ?>
                        <?php foreach ($coursePopularity as $c): ?>
                            <div>
                                <div class="flex justify-between text-body-md mb-2">
                                    <span><?= htmlspecialchars($c['title']) ?> (<?= htmlspecialchars($c['difficulty']) ?>)</span>
                                    <span class="font-bold"><?= intval($c['enrollment_count']) ?> Students</span>
                                </div>
                                <div class="w-full bg-surface-container rounded-full h-2.5">
                                    <div class="bg-primary h-2.5 rounded-full" role="progressbar" aria-valuenow="<?= min(100, intval($c['enrollment_count']) * 10) ?>" aria-valuemin="0" aria-valuemax="100" style="width: <?= min(100, intval($c['enrollment_count']) * 10) ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Instrument Breakdown -->
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-lg shadow-sm">
                <h4 class="text-h3 font-h3 mb-lg">Courses per Category</h4>
                <div class="space-y-6">
                    <?php if (empty($instrumentLoads)): ?>
                        <p class="text-secondary font-medium">No instrument categories found.</p>
                    <?php else: ?>
                        <?php foreach ($instrumentLoads as $inst): ?>
                            <div>
                                <div class="flex justify-between text-body-md mb-2">
                                    <span><?= htmlspecialchars($inst['instrument_name']) ?></span>
                                    <span class="font-bold"><?= intval($inst['course_count']) ?> Courses</span>
                                </div>
                                <div class="w-full bg-surface-container rounded-full h-2.5">
                                    <div class="bg-secondary h-2.5 rounded-full" role="progressbar" aria-valuenow="<?= min(100, intval($inst['course_count']) * 20) ?>" aria-valuemin="0" aria-valuemax="100" style="width: <?= min(100, intval($inst['course_count']) * 20) ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-md mb-lg">
            <!-- Low Attendance Students -->
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl shadow-sm overflow-hidden">
                <div class="px-lg py-md bg-surface-container-low border-b border-outline-variant flex justify-between items-center">
                    <h4 class="text-h3 font-h3">Low Attendance Warning (&lt; 75%)</h4>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-surface-container-low">
                            <tr>
                                <th class="px-lg py-3 text-label-sm text-secondary uppercase tracking-wider">Student</th>
                                <th class="px-lg py-3 text-label-sm text-secondary uppercase tracking-wider">Course</th>
                                <th class="px-lg py-3 text-label-sm text-secondary uppercase tracking-wider">Attendance %</th>
                                <th class="px-lg py-3 text-label-sm text-secondary uppercase tracking-wider">Instructor</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            <?php if (empty($lowAttendance)): ?>
                                <tr>
                                    <td colspan="4" class="px-lg py-4 text-center text-secondary">All students have good attendance records!</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($lowAttendance as $la): ?>
                                    <tr>
                                        <td class="px-lg py-4 font-bold text-body-md"><?= htmlspecialchars($la['student_name']) ?></td>
                                        <td class="px-lg py-4 text-body-md text-secondary"><?= htmlspecialchars($la['course_title']) ?></td>
                                        <td class="px-lg py-4 text-body-md font-bold text-error"><?= intval($la['attendance_rate']) ?>%</td>
                                        <td class="px-lg py-4 text-body-md"><?= htmlspecialchars($la['instructor_name'] ?: 'TBD') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Instructor Workload Table -->
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl shadow-sm overflow-hidden">
                <div class="px-lg py-md bg-surface-container-low border-b border-outline-variant flex justify-between items-center">
                    <h4 class="text-h3 font-h3">Faculty Student Load</h4>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-surface-container-low">
                            <tr>
                                <th class="px-lg py-3 text-label-sm text-secondary uppercase tracking-wider">Instructor</th>
                                <th class="px-lg py-3 text-label-sm text-secondary uppercase tracking-wider">Assigned Courses</th>
                                <th class="px-lg py-3 text-label-sm text-secondary uppercase tracking-wider">Total Enrolled Students</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            <?php if (empty($instructorWorkload)): ?>
                                <tr>
                                    <td colspan="3" class="px-lg py-4 text-center text-secondary">No instructors found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($instructorWorkload as $iw): ?>
                                    <tr>
                                        <td class="px-lg py-4 font-bold text-body-md"><?= htmlspecialchars($iw['instructor_name']) ?></td>
                                        <td class="px-lg py-4 text-body-md text-secondary"><?= intval($iw['course_count']) ?> Courses</td>
                                        <td class="px-lg py-4 text-body-md font-semibold text-primary"><?= intval($iw['student_count']) ?> Students</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>

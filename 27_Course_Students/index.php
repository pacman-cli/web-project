<?php
// 27_Course_Students/index.php
// Roster of students enrolled in a specific course assigned to the instructor

require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/design-system.php';
requireRole('instructor');

$pdo = require_once __DIR__ . '/../config/db.php';
$instructorId = $_SESSION['user_id'];
$instructorName = $_SESSION['name'];

$courseId = intval($_GET['course_id'] ?? 0);

try {
    // If no course is specified, fetch the first assigned course as default
    if ($courseId <= 0) {
        $firstCourseStmt = $pdo->prepare("
            SELECT course_id FROM instructor_assignments 
            WHERE instructor_id = :instructor_id LIMIT 1
        ");
        $firstCourseStmt->execute(['instructor_id' => $instructorId]);
        $firstCourse = $firstCourseStmt->fetch();
        if ($firstCourse) {
            $courseId = intval($firstCourse['course_id']);
        }
    }

    $courseTitle = "No Active Course";
    $students = [];

    if ($courseId > 0) {
        // 1. Verify instructor is assigned to this course
        $verifyStmt = $pdo->prepare("
            SELECT c.title FROM courses c
            JOIN instructor_assignments ia ON c.id = ia.course_id
            WHERE ia.instructor_id = :instructor_id AND c.id = :course_id
        ");
        $verifyStmt->execute(['instructor_id' => $instructorId, 'course_id' => $courseId]);
        $courseData = $verifyStmt->fetch();

        if ($courseData) {
            $courseTitle = $courseData['title'];

            // 2. Fetch approved enrolled students with progress details
            $studentsStmt = $pdo->prepare("
                SELECT u.id, u.name, u.email, e.enrolled_at, s.experience_level,
                       (SELECT COUNT(*) FROM submissions sub 
                        JOIN assignments a ON sub.assignment_id = a.id 
                        WHERE sub.student_id = u.id AND a.course_id = ?) as submission_count,
                       (SELECT COUNT(*) FROM assignments a WHERE a.course_id = ?) as total_assignments,
                       (SELECT COUNT(*) FROM attendance att 
                        JOIN schedules sch ON att.schedule_id = sch.id 
                        WHERE att.student_id = u.id AND sch.course_id = ? AND att.status = 'present') as attendance_count,
                       (SELECT COUNT(*) FROM attendance att 
                        JOIN schedules sch ON att.schedule_id = sch.id 
                        WHERE att.student_id = u.id AND sch.course_id = ?) as total_attendance_classes
                FROM users u
                JOIN students s ON u.id = s.user_id
                JOIN enrollments e ON u.id = e.student_id
                WHERE e.course_id = ? AND e.status = 'approved'
                ORDER BY u.name ASC
            ");
            $studentsStmt->execute([$courseId, $courseId, $courseId, $courseId, $courseId]);
            $students = $studentsStmt->fetchAll();
        }
    }

    // Fetch list of all assigned courses for selection
    $assignedCoursesStmt = $pdo->prepare("
        SELECT c.id, c.title
        FROM courses c
        JOIN instructor_assignments ia ON c.id = ia.course_id
        WHERE ia.instructor_id = :instructor_id
        ORDER BY c.title ASC
    ");
    $assignedCoursesStmt->execute(['instructor_id' => $instructorId]);
    $assignedCourses = $assignedCoursesStmt->fetchAll();

} catch (Exception $e) {
    error_log('DB error: ' . $e->getMessage()); die('A database error occurred. Please try again later.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php lms_head('Course Students', 'instructor'); ?>
</head>
<body class="bg-background text-on-background min-h-screen">

<?php lms_sidebar('instructor', '/27_Course_Students/index.php'); ?>
<?php lms_topbar('instructor', 'Course Students', 'Search students…'); ?>

<main id="lms-main-content" class="lms-main">
    <div class="page-content">
        <!-- Page Header -->
        <div class="flex flex-col md:flex-row md:items-end justify-between mb-lg gap-md">
            <div>
                <h2 class="font-h1 text-h1 text-on-background mb-1">Course Students</h2>
                <p class="font-body-lg text-body-lg text-secondary">Active Course: <span class="text-primary font-semibold"><?= htmlspecialchars($courseTitle) ?></span></p>
            </div>
            <div>
                <label for="course-select" class="sr-only">Select course</label>
                <select id="course-select" onchange="changeCourse()" autocomplete="off" class="bg-surface-container-lowest border border-outline-variant px-md pr-10 py-2 rounded-lg font-label-md text-label-md text-on-surface-variant focus:outline-none focus:ring-2 focus:ring-primary/20 cursor-pointer">
                    <?php foreach ($assignedCourses as $ac): ?>
                        <option value="<?= $ac['id'] ?>" <?= $ac['id'] == $courseId ? 'selected' : '' ?>><?= htmlspecialchars($ac['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Management Table Card -->
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl shadow-[0_2px_4px_rgba(0,0,0,0.04)] overflow-hidden">
            <div class="px-md py-md border-b border-outline-variant flex justify-between items-center">
                <p class="text-secondary font-label-md text-label-md" id="roster-count">Showing <?= count($students) ?> students</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-surface">
                        <tr>
                            <th class="px-md py-3 font-label-sm text-label-sm text-secondary uppercase tracking-wider">Name</th>
                            <th class="px-md py-3 font-label-sm text-label-sm text-secondary uppercase tracking-wider">Email</th>
                            <th class="px-md py-3 font-label-sm text-label-sm text-secondary uppercase tracking-wider">Enrolled At</th>
                            <th class="px-md py-3 font-label-sm text-label-sm text-secondary uppercase tracking-wider">Level</th>
                            <th class="px-md py-3 font-label-sm text-label-sm text-secondary uppercase tracking-wider">Submissions</th>
                            <th class="px-md py-3 font-label-sm text-label-sm text-secondary uppercase tracking-wider">Attendance</th>
                        </tr>
                    </thead>
                    <tbody id="student-tbody" class="divide-y divide-outline-variant">
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="6" class="px-md py-8 text-center text-on-surface-variant">No students enrolled in this course.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($students as $stu): ?>
                                <tr class="hover:bg-surface-container-low transition-colors student-row">
                                    <td class="px-md py-4 font-semibold text-on-surface student-name"><?= htmlspecialchars($stu['name']) ?></td>
                                    <td class="px-md py-4 text-on-surface-variant"><?= htmlspecialchars($stu['email']) ?></td>
                                    <td class="px-md py-4 text-on-surface-variant"><?= date('M d, Y', strtotime($stu['enrolled_at'])) ?></td>
                                    <td class="px-md py-4 text-on-surface-variant uppercase text-xs font-semibold"><?= htmlspecialchars($stu['experience_level']) ?></td>
                                    <td class="px-md py-4 font-body-md text-body-md text-secondary">
                                        <?= intval($stu['submission_count']) ?> / <?= intval($stu['total_assignments']) ?>
                                    </td>
                                    <td class="px-md py-4">
                                        <?php if ($stu['total_attendance_classes'] > 0): ?>
                                            <?php 
                                            $percent = round(($stu['attendance_count'] / $stu['total_attendance_classes']) * 100); 
                                            $badgeClass = $percent >= 80 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                                            ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $badgeClass ?>">
                                                <?= $percent ?>% (<?= $stu['attendance_count'] ?>/<?= $stu['total_attendance_classes'] ?>)
                                            </span>
                                        <?php else: ?>
                                            <span class="text-outline text-xs">No records</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script>
    function changeCourse() {
        const val = document.getElementById('course-select').value;
        if (val) {
            window.location.search = '?course_id=' + val;
        }
    }

    function searchStudent() {
        const query = document.getElementById('student-search').value.toLowerCase();
        const rows = document.querySelectorAll('.student-row');
        let count = 0;
        rows.forEach(row => {
            const name = row.querySelector('.student-name').innerText.toLowerCase();
            if (name.includes(query)) {
                row.classList.remove('hidden');
                count++;
            } else {
                row.classList.add('hidden');
            }
        });
        document.getElementById('roster-count').innerText = 'Showing ' + count + ' student' + (count === 1 ? '' : 's');
    }
</script>
</body>
</html>

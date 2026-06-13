<?php
// 39_Student_My_Courses/index.php
// Student portal for viewing enrolled courses and opening syllabus/lesson materials

require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/design-system.php';
requireRole('student');

$pdo = require_once __DIR__ . '/../config/db.php';
$studentId = $_SESSION['user_id'];
$studentName = $_SESSION['name'];

try {
    // 1. Fetch student's approved enrollments
    $coursesStmt = $pdo->prepare("
        SELECT c.id, c.title, c.description, c.difficulty, i.name as instrument_name
        FROM courses c
        JOIN enrollments e ON c.id = e.course_id
        LEFT JOIN instruments i ON c.instrument_id = i.id
        WHERE e.student_id = :student_id AND e.status = 'approved'
        ORDER BY c.title ASC
    ");
    $coursesStmt->execute(['student_id' => $studentId]);
    $courses = $coursesStmt->fetchAll();

    $activeCourses = [];
    $totalAttendanceSum = 0;
    $attendanceCoursesCount = 0;

    foreach ($courses as $course) {
        $courseId = $course['id'];

        // Get Instructor details
        $instStmt = $pdo->prepare("
            SELECT u.name 
            FROM users u
            JOIN instructor_assignments ia ON u.id = ia.instructor_id
            WHERE ia.course_id = :course_id
            LIMIT 1
        ");
        $instStmt->execute(['course_id' => $courseId]);
        $instructorName = $instStmt->fetchColumn() ?: 'TBD';

        // Calculate progress
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
            $progressPercent = round(($submittedAssignments / $totalAssignments) * 100);
        } else {
            $progressPercent = 100;
        }

        // Attendance rate for this course
        $attStmt = $pdo->prepare("
            SELECT status, COUNT(*) as cnt 
            FROM attendance a
            JOIN schedules s ON a.schedule_id = s.id
            WHERE a.student_id = :student_id AND s.course_id = :course_id
            GROUP BY status
        ");
        $attStmt->execute(['student_id' => $studentId, 'course_id' => $courseId]);
        $attCounts = $attStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $present = intval($attCounts['present'] ?? 0);
        $absent = intval($attCounts['absent'] ?? 0);
        $totalAtt = $present + $absent;
        $courseAttendancePercent = $totalAtt > 0 ? round(($present / $totalAtt) * 100) : 100;

        $totalAttendanceSum += $courseAttendancePercent;
        $attendanceCoursesCount++;

        // Next class day and time
        $schedStmt = $pdo->prepare("
            SELECT day_of_week, start_time 
            FROM schedules 
            WHERE course_id = :course_id 
            LIMIT 1
        ");
        $schedStmt->execute(['course_id' => $courseId]);
        $schedule = $schedStmt->fetch();
        $nextClassText = 'TBD';
        if ($schedule) {
            $nextClassText = $schedule['day_of_week'] . 's @ ' . date('g:i A', strtotime($schedule['start_time']));
        }

        $course['instructor_name'] = $instructorName;
        $course['progress_percent'] = $progressPercent;
        $course['attendance_percent'] = $courseAttendancePercent;
        $course['next_class'] = $nextClassText;
        $activeCourses[] = $course;
    }

    $avgAttendanceRate = $attendanceCoursesCount > 0 ? round($totalAttendanceSum / $attendanceCoursesCount) : 100;

    // Fetch student profile info from existing columns only
    $gpa = 0.00;
    $practiceHours = 0;
    $scholarshipStatus = 'N/A';
    $experienceLevel = 'beginner';

    $profileStmt = $pdo->prepare("SELECT experience_level FROM students WHERE user_id = :student_id");
    $profileStmt->execute(['student_id' => $studentId]);
    $studentProfile = $profileStmt->fetch();
    if ($studentProfile) {
        $experienceLevel = $studentProfile['experience_level'];
    }

    // Calculate GPA from graded submissions + quiz attempts
    $gpaStmt = $pdo->prepare("
        SELECT AVG(score_percent)
        FROM (
            SELECT (s.points_earned / a.max_points) * 100 as score_percent
            FROM submissions s
            JOIN assignments a ON s.assignment_id = a.id
            WHERE s.student_id = :sid1 AND s.status = 'graded' AND a.max_points > 0
            UNION ALL
            SELECT (qa.score / qa.total_points) * 100 as score_percent
            FROM quiz_attempts qa
            WHERE qa.student_id = :sid2 AND qa.completed_at IS NOT NULL AND qa.total_points > 0
        ) combined_scores
    ");
    $gpaStmt->execute(['sid1' => $studentId, 'sid2' => $studentId]);
    $avgPercent = $gpaStmt->fetchColumn();

    if ($avgPercent !== null && $avgPercent !== false) {
        $avgPercent = floatval($avgPercent);
        if ($avgPercent >= 93) $gpa = 4.00;
        elseif ($avgPercent >= 90) $gpa = 3.70;
        elseif ($avgPercent >= 87) $gpa = 3.30;
        elseif ($avgPercent >= 83) $gpa = 3.00;
        elseif ($avgPercent >= 80) $gpa = 2.70;
        elseif ($avgPercent >= 77) $gpa = 2.30;
        elseif ($avgPercent >= 73) $gpa = 2.00;
        elseif ($avgPercent >= 70) $gpa = 1.70;
        elseif ($avgPercent >= 60) $gpa = 1.00;
        else $gpa = 0.00;
    }

    // Calculate practice hours from lesson participation
    $hoursStmt = $pdo->prepare("SELECT SUM(watched_duration) FROM lesson_participation WHERE student_id = :sid");
    $hoursStmt->execute(['sid' => $studentId]);
    $totalSeconds = $hoursStmt->fetchColumn();
    if ($totalSeconds) {
        $practiceHours = round(intval($totalSeconds) / 3600);
    }
    if ($practiceHours < 1) {
        $attStmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE student_id = :sid AND status = 'present'");
        $attStmt->execute(['sid' => $studentId]);
        $presentClasses = intval($attStmt->fetchColumn());
        $practiceHours = 10 + ($presentClasses * 2);
    }

} catch (Exception $e) {
    error_log('DB error: ' . $e->getMessage()); die('A database error occurred. Please try again later.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php lms_head('My Courses', 'student'); ?>
</head>
<body class="bg-background text-on-surface">
<?php lms_sidebar('student', '/39_Student_My_Courses/index.php'); ?>

<?php lms_topbar('student', 'My Courses'); ?>

<!-- Main Content Area -->
<main id="lms-main-content" class="lms-main">
    <div class="max-w-container-max mx-auto p-lg">
        <!-- Page Header Section -->
        <div class="mb-lg flex justify-between items-end">
            <div>
                <h1 class="font-h1 text-h1 text-on-surface mb-xs">Welcome back, <?= htmlspecialchars($studentName) ?></h1>
                <p class="font-body-lg text-body-lg text-on-surface-variant">You have <?= count($activeCourses) ?> active courses. Keep up the great practice!</p>
            </div>
            <div class="flex gap-base">
                <span class="px-md py-2 bg-primary-fixed text-on-primary-fixed rounded-full font-label-md text-label-md flex items-center gap-xs">
                    <span class="material-symbols-outlined text-sm" aria-hidden="true">trending_up</span> <?= $avgAttendanceRate ?>% Average Attendance
                </span>
            </div>
        </div>

        <!-- Course Grid -->
        <div id="courses-grid" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-lg">
            <?php if (empty($activeCourses)): ?>
                <div class="col-span-full bg-surface-container-lowest border border-outline-variant rounded-xl p-lg text-center text-on-surface-variant">
                    You are not enrolled in any courses yet.
                </div>
            <?php else: ?>
                <?php foreach ($activeCourses as $c): ?>
                    <div class="course-card bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden card-shadow flex flex-col group transition-all duration-300 hover:shadow-lg" data-title="<?= htmlspecialchars(strtolower($c['title'])) ?>">
                        <div class="h-40 w-full relative">
                            <img class="w-full h-full object-cover" alt="Course Image" src="https://lh3.googleusercontent.com/aida-public/AB6AXuDvJUK5SVg3t7GZWfOkcQXjmSXyOhy6P5JAgAbhxT3B6N0Bj5SnGvrHUa6FwUBmxOklMuRAc5IrxAfKRN8hjeu8XojBgnp-JPmYwEpQYWWPqR_OrAZxQVrbjeU88xypxfqdvYvz-pofUyyd58Ma_LTAqfoEgagh9DPdp_BPprrhshzqoOTi3Sn1JcQa5Shj4fDuHlpkD36FDqB3YgrxX280Okow0VCGN1mlEHdVgM58nJTNbDALZHqQJ9vpeD4l-EzItiTCcWaJfNo"/>
                            <div class="absolute top-4 left-4">
                                <span class="bg-primary/90 backdrop-blur-sm text-white px-3 py-1 rounded-full font-label-sm text-label-sm uppercase tracking-wider"><?= htmlspecialchars($c['instrument_name'] ?? 'General') ?></span>
                            </div>
                        </div>
                        <div class="p-lg flex-1 flex flex-col">
                            <div class="mb-md">
                                <h3 class="font-h3 text-h3 text-on-surface mb-xs group-hover:text-primary transition-colors"><?= htmlspecialchars($c['title']) ?></h3>
                                <p class="font-body-md text-body-md text-on-surface-variant flex items-center gap-xs">
                                    <span class="material-symbols-outlined text-sm" aria-hidden="true">person</span> Instructor: <?= htmlspecialchars($c['instructor_name']) ?>
                                </p>
                            </div>
                            <div class="space-y-md mb-lg">
                                <div>
                                    <div class="flex justify-between items-center mb-xs">
                                        <span class="font-label-md text-label-md text-on-surface-variant">Course Progress</span>
                                        <span class="font-label-md text-label-md font-bold text-primary"><?= $c['progress_percent'] ?>%</span>
                                    </div>
                                    <div class="h-2 w-full bg-surface-container rounded-full overflow-hidden" role="progressbar" aria-valuenow="<?= $c['progress_percent'] ?>" aria-valuemin="0" aria-valuemax="100">
                                        <div class="h-full bg-primary rounded-full" style="width: <?= $c['progress_percent'] ?>%"></div>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-md border-t border-outline-variant pt-md">
                                    <div>
                                        <p class="font-label-sm text-label-sm text-on-surface-variant uppercase mb-xs">Attendance</p>
                                        <p class="font-body-lg text-body-lg font-bold text-on-surface"><?= $c['attendance_percent'] ?>%</p>
                                    </div>
                                    <div>
                                        <p class="font-label-sm text-label-sm text-on-surface-variant uppercase mb-xs">Next Class</p>
                                        <p class="font-body-md text-body-md font-bold text-on-surface"><?= htmlspecialchars($c['next_class']) ?></p>
                                    </div>
                                </div>
                            </div>
                            <a href="<?= BASE_URL ?>/16_Lesson_Materials/index.php?course_id=<?= $c['id'] ?>" class="w-full py-3 bg-primary text-white rounded-lg font-body-md font-semibold hover:bg-primary-container transition-colors mt-auto flex justify-center items-center gap-base group/btn">
                                Open Materials
                                <span class="material-symbols-outlined text-sm group-hover/btn:translate-x-1 transition-transform" aria-hidden="true">arrow_forward</span>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Bento-style Browse Card -->
            <div class="bg-primary/5 border border-primary/20 rounded-xl p-lg flex flex-col justify-center items-center text-center card-shadow xl:row-span-1">
                <div class="w-16 h-16 bg-primary-container/20 text-primary rounded-full flex items-center justify-center mb-md">
                    <span class="material-symbols-outlined text-3xl" aria-hidden="true">library_add</span>
                </div>
                <h3 class="font-h3 text-h3 text-primary mb-base">Explore Electives</h3>
                <p class="font-body-md text-body-md text-on-surface-variant mb-lg max-w-[200px]">Interested in learning a new instrument or expanding your theory knowledge?</p>
                <a href="<?= BASE_URL ?>/42_Public_Course_Catalog/index.php" class="px-md py-2 border-2 border-primary text-primary rounded-lg font-body-md font-bold hover:bg-primary hover:text-white transition-all">
                    Browse Catalog
                </a>
            </div>
        </div>

        <!-- Footer Stats / Legend -->
        <div class="mt-xl grid grid-cols-1 md:grid-cols-4 gap-md">
            <div class="bg-surface-container-low p-md rounded-lg flex items-center gap-md">
                <span class="material-symbols-outlined text-primary p-2 bg-primary/10 rounded-lg" aria-hidden="true">history_edu</span>
                <div>
                    <p class="font-label-sm text-label-sm text-on-surface-variant uppercase">Current GPA</p>
                    <p class="font-body-lg text-body-lg font-bold"><?= number_format($gpa, 2) ?></p>
                </div>
            </div>
            <div class="bg-surface-container-low p-md rounded-lg flex items-center gap-md">
                <span class="material-symbols-outlined text-primary p-2 bg-primary/10 rounded-lg" aria-hidden="true">task_alt</span>
                <div>
                    <p class="font-label-sm text-label-sm text-on-surface-variant uppercase">Completed Courses</p>
                    <p class="font-body-lg text-body-lg font-bold"><?= count($activeCourses) ?> Active</p>
                </div>
            </div>
            <div class="bg-surface-container-low p-md rounded-lg flex items-center gap-md">
                <span class="material-symbols-outlined text-primary p-2 bg-primary/10 rounded-lg" aria-hidden="true">timer</span>
                <div>
                    <p class="font-label-sm text-label-sm text-on-surface-variant uppercase">Practice Hours</p>
                    <p class="font-body-lg text-body-lg font-bold"><?= $practiceHours ?>h</p>
                </div>
            </div>
            <div class="bg-surface-container-low p-md rounded-lg flex items-center gap-md">
                <span class="material-symbols-outlined text-primary p-2 bg-primary/10 rounded-lg" aria-hidden="true">stars</span>
                <div>
                    <p class="font-label-sm text-label-sm text-on-surface-variant uppercase">Scholarship Status</p>
                    <p class="font-body-lg text-body-lg font-bold"><?= htmlspecialchars($scholarshipStatus) ?></p>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
    function filterCourses() {
        const query = document.getElementById('topbar-search').value.toLowerCase();
        const cards = document.querySelectorAll('.course-card');
        cards.forEach(card => {
            const title = card.getAttribute('data-title');
            if (title.includes(query)) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    }
</script>
</body>
</html>

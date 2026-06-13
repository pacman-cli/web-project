<?php
// 41_Student_Schedules/index.php
// Student portal for reviewing approved course schedules

require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/design-system.php';
requireRole('student');

$pdo = require_once __DIR__ . '/../config/db.php';
$studentId = $_SESSION['user_id'];
$studentName = $_SESSION['name'];

$daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

try {
    $coursesStmt = $pdo->prepare("
        SELECT c.id, c.title
        FROM courses c
        JOIN enrollments e ON c.id = e.course_id
        WHERE e.student_id = :student_id AND e.status = 'approved'
        ORDER BY c.title ASC
    ");
    $coursesStmt->execute(['student_id' => $studentId]);
    $courses = $coursesStmt->fetchAll();

    $selectedCourseId = intval($_GET['course_id'] ?? 0);
    if ($selectedCourseId <= 0 && !empty($courses)) {
        $selectedCourseId = intval($courses[0]['id']);
    }

    $scheduleWhere = "
        e.student_id = :student_id
        AND e.status = 'approved'
    ";
    $scheduleParams = ['student_id' => $studentId];

    if ($selectedCourseId > 0) {
        $scheduleWhere .= " AND c.id = :course_id";
        $scheduleParams['course_id'] = $selectedCourseId;
    }

    $stmt = $pdo->prepare("
        SELECT s.id, s.day_of_week, s.start_time, s.end_time, s.location_type, s.location_detail,
               c.id as course_id, c.title as course_title, u.name as instructor_name
        FROM schedules s
        JOIN courses c ON s.course_id = c.id
        JOIN enrollments e ON c.id = e.course_id
        LEFT JOIN users u ON s.instructor_id = u.id
        WHERE {$scheduleWhere}
        ORDER BY FIELD(s.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
                 s.start_time ASC
    ");
    $stmt->execute($scheduleParams);
    $schedules = $stmt->fetchAll();

    $groupedSchedules = [];
    foreach ($daysOfWeek as $day) {
        $groupedSchedules[$day] = [];
    }
    foreach ($schedules as $sched) {
        $groupedSchedules[$sched['day_of_week']][] = $sched;
    }

} catch (Exception $e) {
    error_log('DB error: ' . $e->getMessage());
    die('A database error occurred. Please try again later.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php lms_head('My Schedules', 'student'); ?>
</head>
<body class="bg-background text-on-surface">

<?php lms_sidebar('student', '/41_Student_Schedules/index.php'); ?>
<?php lms_topbar('student', 'My Schedules'); ?>

<main id="lms-main-content" class="lms-main">
    <div class="max-w-container-max mx-auto p-lg space-y-lg">
        <section class="flex flex-col md:flex-row md:items-end justify-between gap-md">
            <div>
                <h1 class="font-h1 text-h1 text-on-surface">My Schedules</h1>
                <p class="font-body-lg text-on-surface-variant">Review approved class times, locations, and instructor links.</p>
            </div>
            <form method="GET" class="min-w-[260px]">
                <label class="block font-label-md text-on-surface-variant mb-2">Course Filter</label>
                <select name="course_id" onchange="this.form.submit()" class="w-full bg-surface border border-outline-variant rounded-lg px-md py-sm font-body-md focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                    <option value="0">All enrolled courses</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?= intval($course['id']) ?>" <?= $selectedCourseId === intval($course['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($course['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </section>

        <section class="grid grid-cols-1 md:grid-cols-3 gap-md">
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-lg">
                <p class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">Courses</p>
                <p class="font-h2 text-h2 text-on-surface"><?= count($courses) ?></p>
            </div>
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-lg">
                <p class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">Scheduled Sessions</p>
                <p class="font-h2 text-h2 text-on-surface"><?= count($schedules) ?></p>
            </div>
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-lg">
                <p class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">Next Step</p>
                <p class="font-body-lg text-on-surface">Check Materials after class.</p>
            </div>
        </section>

        <section class="space-y-md">
            <div class="flex items-center justify-between">
                <h2 class="font-h2 text-h2 text-on-surface">Weekly Sessions</h2>
                <div class="flex gap-md">
                    <a href="/35_Student_Attendance/index.php<?= $selectedCourseId > 0 ? '?course_id=' . intval($selectedCourseId) : '' ?>" class="text-primary font-label-md text-label-md hover:underline">
                        Mark Attendance
                    </a>
                    <a href="/16_Lesson_Materials/index.php<?= $selectedCourseId > 0 ? '?course_id=' . intval($selectedCourseId) : '' ?>" class="text-primary font-label-md text-label-md hover:underline">
                        Open Materials
                    </a>
                </div>
            </div>

            <?php if (empty($schedules)): ?>
                <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-xl text-center text-on-surface-variant">
                    No class schedules found for your current enrollments.
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-md">
                    <?php foreach ($daysOfWeek as $day): ?>
                        <?php if (!empty($groupedSchedules[$day])): ?>
                            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-lg space-y-md">
                                <div class="flex items-center justify-between">
                                    <h3 class="font-h3 text-h3 text-on-surface"><?= htmlspecialchars($day) ?></h3>
                                    <span class="text-xs font-semibold uppercase tracking-wider text-primary"><?= count($groupedSchedules[$day]) ?> session<?= count($groupedSchedules[$day]) !== 1 ? 's' : '' ?></span>
                                </div>

                                <div class="space-y-sm">
                                    <?php foreach ($groupedSchedules[$day] as $sched): ?>
                                        <article class="rounded-xl border border-outline-variant bg-surface p-md">
                                            <div class="flex items-start justify-between gap-md">
                                                <div class="min-w-0">
                                                    <p class="font-label-sm text-label-sm text-primary uppercase tracking-wider"><?= htmlspecialchars($sched['course_title']) ?></p>
                                                    <h4 class="font-body-lg font-semibold text-on-surface truncate"><?= htmlspecialchars($sched['instructor_name'] ?: 'Instructor TBD') ?></h4>
                                                    <p class="font-body-md text-on-surface-variant">
                                                        <?= date('g:i A', strtotime($sched['start_time'])) ?> - <?= date('g:i A', strtotime($sched['end_time'])) ?>
                                                    </p>
                                                    <p class="font-label-md text-label-md text-on-surface-variant mt-1">
                                                        <?= htmlspecialchars($sched['location_type'] === 'online' ? 'Online session' : 'Physical session') ?>:
                                                        <?= htmlspecialchars($sched['location_detail']) ?>
                                                    </p>
                                                </div>

                                                <?php if ($sched['location_type'] === 'online'): ?>
                                                    <a href="<?= htmlspecialchars($sched['location_detail']) ?>" target="_blank" class="shrink-0 px-md py-xs rounded-lg bg-primary text-on-primary font-label-md text-label-md hover:opacity-90">
                                                        Join
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>

</body>
</html>

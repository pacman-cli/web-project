<?php
// 40_Student_Dashboard/index.php — Lyra Academy Student Dashboard
require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/design-system.php';
requireRole('student');

$pdo = require_once __DIR__ . '/../config/db.php';
$studentId   = $_SESSION['user_id'];
$studentName = $_SESSION['name'];

// ── Metrics ───────────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id=:sid AND status='approved'");
$stmt->execute(['sid' => $studentId]);
$enrolledCoursesCount = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM schedules s JOIN enrollments e ON s.course_id=e.course_id WHERE e.student_id=? AND e.status='approved'");
$stmt->execute([$studentId]);
$upcomingClassesCount = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM assignments a JOIN enrollments e ON a.course_id=e.course_id LEFT JOIN submissions s ON a.id=s.assignment_id AND s.student_id=? WHERE e.student_id=? AND e.status='approved' AND s.id IS NULL");
$stmt->execute([$studentId, $studentId]);
$pendingAssignmentsCount = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT AVG((s.points_earned/a.max_points)*100) FROM submissions s JOIN assignments a ON s.assignment_id=a.id WHERE s.student_id=? AND s.status='graded' AND a.max_points>0");
$stmt->execute([$studentId]);
$avgScore = $stmt->fetchColumn();
$avgScoreFormatted = $avgScore !== null ? round($avgScore).'%' : 'N/A';

$stmt = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM attendance WHERE student_id=:sid GROUP BY status");
$stmt->execute(['sid' => $studentId]);
$attendanceCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$present         = intval($attendanceCounts['present']  ?? 0);
$absent          = intval($attendanceCounts['absent']   ?? 0);
$totalAttendance = $present + $absent;
$attendanceRateFormatted = $totalAttendance > 0 ? round(($present/$totalAttendance)*100).'%' : '100%';

// ── Active Courses ────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT c.id, c.title, c.description, c.difficulty, i.name as instrument_name FROM courses c JOIN enrollments e ON c.id=e.course_id LEFT JOIN instruments i ON c.instrument_id=i.id WHERE e.student_id=:sid AND e.status='approved' ORDER BY c.title ASC");
$stmt->execute(['sid' => $studentId]);
$courses = $stmt->fetchAll();

$activeCourses = [];
foreach ($courses as $course) {
    $cid = $course['id'];
    $instStmt = $pdo->prepare("SELECT u.name FROM users u JOIN instructor_assignments ia ON u.id=ia.instructor_id WHERE ia.course_id=:cid LIMIT 1");
    $instStmt->execute(['cid' => $cid]);
    $instructorName = $instStmt->fetchColumn() ?: 'TBD';

    $schedStmt = $pdo->prepare("SELECT day_of_week, start_time FROM schedules WHERE course_id=:cid LIMIT 1");
    $schedStmt->execute(['cid' => $cid]);
    $schedule = $schedStmt->fetch();
    $nextLesson = $schedule ? $schedule['day_of_week'].'s @ '.date('g:i A', strtotime($schedule['start_time'])) : 'No schedule set';

    $aStmt = $pdo->prepare("SELECT COUNT(*) FROM assignments WHERE course_id=:cid");
    $aStmt->execute(['cid' => $cid]);
    $totalAssignments = intval($aStmt->fetchColumn());

    $sStmt = $pdo->prepare("SELECT COUNT(*) FROM submissions s JOIN assignments a ON s.assignment_id=a.id WHERE a.course_id=:cid AND s.student_id=:sid");
    $sStmt->execute(['cid' => $cid, 'sid' => $studentId]);
    $submittedAssignments = intval($sStmt->fetchColumn());

    // Quiz average for this course
    $quizStmt = $pdo->prepare("
        SELECT COALESCE(AVG(qa.score * 100.0 / GREATEST(qa.total_points, 1)), 0)
        FROM quiz_attempts qa
        JOIN quizzes q ON qa.quiz_id = q.id
        WHERE q.course_id = :cid AND qa.student_id = :sid AND qa.completed_at IS NOT NULL AND qa.total_points > 0
    ");
    $quizStmt->execute(['cid' => $cid, 'sid' => $studentId]);
    $quizAvg = round(floatval($quizStmt->fetchColumn()));

    // Lesson participation count
    $partStmt = $pdo->prepare("
        SELECT COUNT(*) FROM lesson_participation lp
        JOIN class_sessions cs ON lp.class_id = cs.id
        WHERE cs.course_id = :cid AND lp.student_id = :sid AND lp.completed = 1
    ");
    $partStmt->execute(['cid' => $cid, 'sid' => $studentId]);
    $completedLessons = intval($partStmt->fetchColumn());

    $totalSessionsStmt = $pdo->prepare("SELECT COUNT(*) FROM class_sessions WHERE course_id = :cid AND status = 'completed'");
    $totalSessionsStmt->execute(['cid' => $cid]);
    $totalLessons = intval($totalSessionsStmt->fetchColumn());

    // Weighted progress: assignments 60%, quizzes 25%, participation 15%
    $aProgress = $totalAssignments > 0 ? ($submittedAssignments / $totalAssignments) : 0;
    $qProgress = $quizAvg / 100;
    $pProgress = $totalLessons > 0 ? ($completedLessons / $totalLessons) : 0;
    $weightedProgress = round(($aProgress * 60 + $qProgress * 25 + $pProgress * 15));

    $course['instructor_name'] = $instructorName;
    $course['next_lesson']     = $nextLesson;
    $course['progress_percent'] = $weightedProgress;
    $course['quiz_avg'] = $quizAvg;
    $course['completed_lessons'] = $completedLessons;
    $course['total_lessons'] = $totalLessons;
    $activeCourses[] = $course;
}

// ── Recent Activity ───────────────────────────────────────────────────────────
$activityStmt = $pdo->prepare("
    (SELECT 'grade' as type, a.title as detail, s.points_earned as val1, a.max_points as val2, s.graded_at as activity_date
     FROM submissions s JOIN assignments a ON s.assignment_id=a.id WHERE s.student_id=? AND s.status='graded')
    UNION ALL
    (SELECT 'material' as type, m.title as detail, NULL, NULL, m.uploaded_at
     FROM materials m JOIN enrollments e ON m.course_id=e.course_id WHERE e.student_id=? AND e.status='approved')
    UNION ALL
    (SELECT 'attendance' as type, c.title as detail, NULL, NULL, a.marked_at
     FROM attendance a JOIN schedules s ON a.schedule_id=s.id JOIN courses c ON s.course_id=c.id WHERE a.student_id=?)
    UNION ALL
    (SELECT 'quiz' as type, q.title as detail, qa.score as val1, qa.total_points as val2, qa.completed_at
     FROM quiz_attempts qa JOIN quizzes q ON qa.quiz_id=q.id WHERE qa.student_id=? AND qa.completed_at IS NOT NULL)
    ORDER BY activity_date DESC LIMIT 5
");
$activityStmt->execute([$studentId, $studentId, $studentId, $studentId]);
$recentActivities = $activityStmt->fetchAll();

function time_elapsed_string($datetime) {
    if (!$datetime) return 'some time ago';
    $now  = new DateTime;
    $ago  = new DateTime($datetime);
    $diff = $now->diff($ago);
    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;
    $parts = ['y'=>'year','m'=>'month','w'=>'week','d'=>'day','h'=>'hour','i'=>'minute','s'=>'second'];
    foreach ($parts as $k => &$v) {
        if ($diff->$k) { $v = $diff->$k.' '.$v.($diff->$k>1?'s':''); } else { unset($parts[$k]); }
    }
    $parts = array_slice($parts, 0, 1);
    return $parts ? implode(', ', $parts).' ago' : 'just now';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php lms_head('Student Dashboard', 'student'); ?>
</head>
<body class="bg-background text-on-surface">

<?php lms_sidebar('student', '/40_Student_Dashboard/index.php'); ?>
<?php lms_topbar('student', 'Student Dashboard', 'Search courses, lessons, or files…'); ?>

<!-- Main Content -->
<main id="lms-main-content" class="lms-main">
    <div class="page-content">

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="text-h1 text-on-surface mb-xs">Welcome back, <?= htmlspecialchars($studentName) ?> 👋</h1>
                <p class="text-body-lg text-outline">You have <?= (int)$upcomingClassesCount ?> active class schedule<?= $upcomingClassesCount != 1 ? 's' : '' ?>.</p>
            </div>
            <div class="flex gap-sm">
                <a href="/42_Public_Course_Catalog/index.php" class="btn btn-secondary" style="background:rgba(106,26,140,.08);color:#6a1a8c">
                    <span class="material-symbols-outlined" aria-hidden="true">explore</span>
                    Browse Catalog
                </a>
            </div>
        </div>

        <!-- Metric Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-md mb-lg">
            <div class="stat-card">
                <div class="stat-card-icon" style="background:rgba(106,26,140,.1)">
                    <span class="material-symbols-outlined icon-fill" style="color:#6a1a8c" aria-hidden="true">menu_book</span>
                </div>
                <p class="stat-card-value"><?= (int)$enrolledCoursesCount ?></p>
                <p class="stat-card-label">Enrolled Courses</p>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon" style="background:rgba(0,61,155,.1)">
                    <span class="material-symbols-outlined icon-fill" style="color:#003d9b" aria-hidden="true">event</span>
                </div>
                <p class="stat-card-value"><?= (int)$upcomingClassesCount ?></p>
                <p class="stat-card-label">Upcoming Classes</p>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon" style="background:rgba(186,26,26,.1)">
                    <span class="material-symbols-outlined icon-fill" style="color:#ba1a1a" aria-hidden="true">assignment_late</span>
                </div>
                <p class="stat-card-value"><?= (int)$pendingAssignmentsCount ?></p>
                <p class="stat-card-label">Pending Assignments</p>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon" style="background:rgba(59,67,88,.1)">
                    <span class="material-symbols-outlined icon-fill" style="color:#3b4358" aria-hidden="true">analytics</span>
                </div>
                <p class="stat-card-value"><?= htmlspecialchars($avgScoreFormatted) ?></p>
                <p class="stat-card-label">Average Score</p>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon" style="background:rgba(26,107,60,.1)">
                    <span class="material-symbols-outlined icon-fill" style="color:#1a6b3c" aria-hidden="true">check_circle</span>
                </div>
                <p class="stat-card-value"><?= htmlspecialchars($attendanceRateFormatted) ?></p>
                <p class="stat-card-label">Attendance Rate</p>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-lg">

            <!-- Active Courses -->
            <section class="lg:col-span-2">
                <div class="flex items-center justify-between mb-md">
                    <h2 class="section-title mb-0">Active Courses</h2>
                    <a href="/39_Student_My_Courses/index.php" class="text-label-md font-semibold hover:underline" style="color:#6a1a8c">View All →</a>
                </div>

                <?php if (empty($activeCourses)): ?>
                    <div class="lms-card px-md py-lg text-center text-outline">
                        <span class="material-symbols-outlined text-4xl mb-2 block" aria-hidden="true">school</span>
                        <p class="text-body-md mb-sm">You're not enrolled in any approved courses yet.</p>
                        <a href="/42_Public_Course_Catalog/index.php" class="btn btn-primary btn-sm" style="background:#6a1a8c">Browse Course Catalog</a>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-md">
                        <?php foreach ($activeCourses as $course): ?>
                            <div class="lms-card overflow-hidden flex flex-col">
                                <!-- Course Header -->
                                <div class="h-36 relative flex items-center justify-center" style="background:linear-gradient(135deg,#6a1a8c,#3b1060)">
                                    <div class="absolute inset-0 opacity-20" style="background:url('data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 100 100\"><circle cx=\"50\" cy=\"50\" r=\"40\" fill=\"none\" stroke=\"white\" stroke-width=\"1\" opacity=\"0.5\"/></svg>') center/cover"></div>
                                    <div class="relative text-center px-md">
                                         <span class="material-symbols-outlined icon-fill text-white text-4xl opacity-70" aria-hidden="true">music_note</span>
                                        <p class="text-white font-semibold text-sm mt-1 leading-tight"><?= htmlspecialchars($course['title']) ?></p>
                                    </div>
                                    <span class="absolute top-3 left-3 badge badge-neutral text-xs"><?= htmlspecialchars($course['instrument_name'] ?? 'General') ?></span>
                                </div>
                                <!-- Course Body -->
                                <div class="px-md py-md flex-1 flex flex-col gap-sm">
                                    <div>
                                        <h3 class="font-semibold text-on-surface text-sm"><?= htmlspecialchars($course['title']) ?></h3>
                                        <p class="text-body-md text-outline">Instructor: <?= htmlspecialchars($course['instructor_name']) ?></p>
                                    </div>
                                    <div class="flex items-center gap-xs text-outline text-body-md">
                                         <span class="material-symbols-outlined" style="font-size:15px" aria-hidden="true">schedule</span>
                                        <span><?= htmlspecialchars($course['next_lesson']) ?></span>
                                    </div>
                                    <!-- Progress -->
                                    <div>
                                        <div class="flex justify-between text-label-md mb-xs">
                                            <span class="text-outline">Progress</span>
                                            <span class="font-semibold" style="color:#6a1a8c"><?= $course['progress_percent'] ?>%</span>
                                        </div>
                                        <div class="progress-track" role="progressbar" aria-valuenow="<?= $course['progress_percent'] ?>" aria-valuemin="0" aria-valuemax="100">
                                            <div class="progress-fill" style="width:<?= $course['progress_percent'] ?>%;background:#6a1a8c"></div>
                                        </div>
                                    </div>
                                    <a href="/39_Student_My_Courses/index.php?course_id=<?= $course['id'] ?>"
                                       class="btn w-full mt-auto" style="background:#6a1a8c;color:#fff">
                                        Open Course
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Right Column -->
            <div class="space-y-md">
                <!-- Recent Activity -->
                <section class="lms-card">
                    <div class="px-md py-md border-b border-outline-variant">
                        <h2 class="section-title mb-0">Recent Activity</h2>
                    </div>
                    <div class="divide-y divide-outline-variant/40">
                        <?php if (empty($recentActivities)): ?>
                            <div class="px-md py-lg text-center text-outline text-body-md">No recent activity found.</div>
                        <?php else: ?>
                            <?php foreach ($recentActivities as $activity): ?>
                                <div class="flex items-start gap-sm px-md py-sm hover:bg-surface-container-low transition-colors">
                                    <?php if ($activity['type'] === 'grade'): ?>
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0" style="background:rgba(106,26,140,.1)">
                                             <span class="material-symbols-outlined icon-fill text-sm" style="color:#6a1a8c" aria-hidden="true">grade</span>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-body-md text-on-surface font-medium truncate"><?= htmlspecialchars($activity['detail']) ?> graded</p>
                                            <p class="text-label-md text-outline"><?= htmlspecialchars($activity['val1']) ?>/<?= htmlspecialchars($activity['val2']) ?> pts · <?= time_elapsed_string($activity['activity_date']) ?></p>
                                        </div>
                                    <?php elseif ($activity['type'] === 'material'): ?>
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0" style="background:rgba(0,61,155,.1)">
                                             <span class="material-symbols-outlined icon-fill text-sm" style="color:#003d9b" aria-hidden="true">play_circle</span>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-body-md text-on-surface font-medium truncate">New material: <?= htmlspecialchars($activity['detail']) ?></p>
                                            <p class="text-label-md text-outline"><?= time_elapsed_string($activity['activity_date']) ?></p>
                                        </div>
                                    <?php elseif ($activity['type'] === 'attendance'): ?>
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0" style="background:rgba(26,107,60,.1)">
                                             <span class="material-symbols-outlined icon-fill text-sm" style="color:#1a6b3c" aria-hidden="true">verified_user</span>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-body-md text-on-surface font-medium truncate">Attendance: <?= htmlspecialchars($activity['detail']) ?></p>
                                            <p class="text-label-md text-outline"><?= time_elapsed_string($activity['activity_date']) ?></p>
                                        </div>
                                    <?php elseif ($activity['type'] === 'quiz'): ?>
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0" style="background:rgba(186,26,26,.1)">
                                             <span class="material-symbols-outlined icon-fill text-sm" style="color:#ba1a1a" aria-hidden="true">quiz</span>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-body-md text-on-surface font-medium truncate"><?= htmlspecialchars($activity['detail']) ?> quiz</p>
                                            <p class="text-label-md text-outline"><?= htmlspecialchars(intval($activity['val1'])) ?>/<?= htmlspecialchars(intval($activity['val2'])) ?> pts · <?= time_elapsed_string($activity['activity_date']) ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Quick Links -->
                <section class="lms-card">
                    <div class="px-md py-md border-b border-outline-variant">
                        <h2 class="section-title mb-0">Quick Links</h2>
                    </div>
                    <div class="px-md py-md space-y-sm">
                        <a href="/37_Student_Assignments_1/index.php" class="btn btn-secondary w-full justify-start gap-sm" style="background:rgba(106,26,140,.08);color:#6a1a8c">
                            <span class="material-symbols-outlined" aria-hidden="true">assignment</span> View Assignments
                        </a>
                        <a href="/41_Student_Schedules/index.php" class="btn btn-secondary w-full justify-start gap-sm" style="background:rgba(106,26,140,.08);color:#6a1a8c">
                            <span class="material-symbols-outlined" aria-hidden="true">calendar_month</span> My Schedules
                        </a>
                        <a href="/16_Lesson_Materials/index.php" class="btn btn-secondary w-full justify-start gap-sm" style="background:rgba(106,26,140,.08);color:#6a1a8c">
                            <span class="material-symbols-outlined" aria-hidden="true">folder_open</span> Course Materials
                        </a>
                        <a href="/38_Student_Quizzes/index.php" class="btn btn-secondary w-full justify-start gap-sm" style="background:rgba(106,26,140,.08);color:#6a1a8c">
                            <span class="material-symbols-outlined" aria-hidden="true">quiz</span> Take Quizzes
                        </a>
                        <a href="/35_Student_Attendance/index.php" class="btn btn-secondary w-full justify-start gap-sm" style="background:rgba(106,26,140,.08);color:#6a1a8c">
                            <span class="material-symbols-outlined" aria-hidden="true">how_to_reg</span> My Attendance
                        </a>
                        <a href="/33_Student_Messages/index.php" class="btn btn-ghost w-full justify-start gap-sm">
                            <span class="material-symbols-outlined" aria-hidden="true">chat</span> Messages
                        </a>
                        <a href="/34_Student_Certificates/index.php" class="btn btn-ghost w-full justify-start gap-sm">
                            <span class="material-symbols-outlined" aria-hidden="true">workspace_premium</span> My Certificates
                        </a>
                    </div>
                </section>
            </div>
        </div>
    </div>
</main>
</body>
</html>

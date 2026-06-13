<?php
// 35_Student_Attendance/index.php
// Student attendance details and history tracker

require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/design-system.php';
requireRole('student');

$pdo = require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/csrf.php';
$studentId = $_SESSION['user_id'];
$studentName = $_SESSION['name'];

try {
    // 1. Fetch enrolled courses
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

    $records = [];
    $attendanceRate = 100;
    $metrics = [
        'total_sessions' => 0,
        'present' => 0,
        'absent' => 0,
        'excused' => 0
    ];

    if ($selectedCourseId > 0) {
        // 2. Fetch attendance logs for selected course
        $stmt = $pdo->prepare("
            SELECT a.status, a.date, s.day_of_week, s.start_time, s.end_time, s.location_detail, u.name as instructor_name
            FROM attendance a
            JOIN schedules s ON a.schedule_id = s.id
            JOIN users u ON s.instructor_id = u.id
            WHERE a.student_id = :student_id AND s.course_id = :course_id
            ORDER BY a.date DESC
        ");
        $stmt->execute([
            'student_id' => $studentId,
            'course_id' => $selectedCourseId
        ]);
        $records = $stmt->fetchAll();

        // 3. Calculate metrics
        $totalSessions = count($records);
        $presentCount = 0;
        $absentCount = 0;
        $excusedCount = 0;

        foreach ($records as $record) {
            if ($record['status'] === 'present') {
                $presentCount++;
            } elseif ($record['status'] === 'absent') {
                $absentCount++;
            } elseif ($record['status'] === 'excused') {
                $excusedCount++;
            }
        }

        if ($totalSessions > 0) {
            $denominator = $totalSessions - $excusedCount;
            if ($denominator > 0) {
                $attendanceRate = round(($presentCount / $denominator) * 100);
            }
        }

        $metrics = [
            'total_sessions' => $totalSessions,
            'present' => $presentCount,
            'absent' => $absentCount,
            'excused' => $excusedCount
        ];
    }

    // Fetch student experience level
    $profileStmt = $pdo->prepare("SELECT experience_level FROM students WHERE user_id = :student_id");
    $profileStmt->execute(['student_id' => $studentId]);
    $studentProfile = $profileStmt->fetch();
    $experienceLevel = $studentProfile ? $studentProfile['experience_level'] : 'beginner';

} catch (Exception $e) {
    error_log('DB error: ' . $e->getMessage()); die('A database error occurred. Please try again later.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php lms_head('My Attendance', 'student'); ?>
</head>
<body class="bg-background text-on-surface">

<?php lms_sidebar('student', '/35_Student_Attendance/index.php'); ?>

<?php lms_topbar('student', 'My Attendance'); ?>

<!-- Main Content Canvas -->
<main id="lms-main-content" class="lms-main">
    <div class="p-lg max-w-container-max mx-auto">
        <!-- Header Section -->
        <div class="mb-lg flex flex-col md:flex-row md:items-start justify-between gap-md">
            <div>
                <h2 class="font-h2 text-h2 text-on-surface">Attendance Overview</h2>
                <p class="font-body-md text-body-md text-secondary">Track your consistency and musical progress at Lyra Academy.</p>
            </div>
            <div class="w-full md:w-64">
                <label for="course-select" class="sr-only">Select Course</label>
                <select id="course-select" onchange="changeCourse()" class="w-full bg-surface border border-outline-variant rounded-lg px-3 py-2 text-sm text-on-surface focus:ring-2 focus:ring-primary/50">
                    <option value="">All courses</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $c['id'] == $selectedCourseId ? 'selected' : '' ?>><?= htmlspecialchars($c['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Top Section: Overall Attendance Summary -->
        <section class="bg-surface-container-lowest border border-outline-variant rounded-xl p-lg shadow-sm mb-lg flex flex-col md:flex-row items-center gap-lg">
            <div class="relative w-32 h-32 flex items-center justify-center">
                <!-- Circular Progress SVG -->
                <svg class="w-full h-full -rotate-90">
                    <circle class="text-surface-container-high" cx="64" cy="64" fill="transparent" r="58" stroke="currentColor" stroke-width="10"></circle>
                    <circle class="text-primary rounded-full" cx="64" cy="64" fill="transparent" r="58" stroke="currentColor" stroke-dasharray="364.4" stroke-dashoffset="<?= (364.4 - (364.4 * ($attendanceRate / 100))) ?>" stroke-width="10"></circle>
                </svg>
                <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <span class="font-h2 text-h2 text-primary"><?= $attendanceRate ?>%</span>
                    <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Present</span>
                </div>
            </div>
            <div class="flex-1">
                <h3 class="font-h3 text-h3 text-on-surface mb-xs">Keep up the great consistency!</h3>
                <p class="font-body-md text-body-md text-secondary max-w-2xl">
                    Maintaining a high presence in your course modules ensures complete training with sheet music, audio tracks, and instructor guidance.
                </p>
                <div class="mt-md flex gap-lg">
                    <div>
                        <p class="font-label-sm text-label-sm text-secondary uppercase mb-xs">Total Marked Sessions</p>
                        <p class="font-h3 text-h3 text-on-surface"><?= $metrics['total_sessions'] ?></p>
                    </div>
                    <div class="w-px h-10 bg-outline-variant"></div>
                    <div>
                        <p class="font-label-sm text-label-sm text-secondary uppercase mb-xs">Present</p>
                        <p class="font-h3 text-h3 text-on-surface"><?= $metrics['present'] ?></p>
                    </div>
                    <div class="w-px h-10 bg-outline-variant"></div>
                    <div>
                        <p class="font-label-sm text-label-sm text-secondary uppercase mb-xs">Absent</p>
                        <p class="font-h3 text-h3 text-on-surface"><?= $metrics['absent'] ?></p>
                    </div>
                    <div class="w-px h-10 bg-outline-variant"></div>
                    <div>
                        <p class="font-label-sm text-label-sm text-secondary uppercase mb-xs">Excused</p>
                        <p class="font-h3 text-h3 text-on-surface"><?= $metrics['excused'] ?></p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Main Content Area: Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-lg">
            <!-- Left Column (2/3 width): Attendance Logs -->
            <div class="lg:col-span-2 space-y-lg">
                <div class="bg-surface-container-lowest border border-outline-variant rounded-xl shadow-sm overflow-hidden">
                    <div class="p-md border-b border-outline-variant bg-surface-container-low/50">
                        <h4 class="font-h3 text-h3 text-on-surface">Marked Sessions Calendar</h4>
                    </div>
                    <div class="p-md divide-y divide-outline-variant">
                        <?php if (empty($records)): ?>
                            <div class="p-lg text-center text-on-surface-variant font-body-md">
                                No attendance records found for this course.
                            </div>
                        <?php else: ?>
                            <?php foreach ($records as $r): ?>
                                <div class="py-md flex items-center justify-between">
                                    <div>
                                        <p class="font-body-md text-on-surface font-semibold"><?= date('F d, Y', strtotime($r['date'])) ?> (<?= $r['day_of_week'] ?>)</p>
                                        <p class="text-xs text-on-surface-variant">Class Time: <?= date('g:i A', strtotime($r['start_time'])) ?> - <?= date('g:i A', strtotime($r['end_time'])) ?> • Location: <?= htmlspecialchars($r['location_detail']) ?></p>
                                    </div>
                                    <div class="flex items-center gap-md">
                                        <?php if ($r['status'] === 'present'): ?>
                                            <span class="px-3 py-1 rounded-full bg-green-100 text-green-700 text-xs font-semibold uppercase">Present</span>
                                        <?php elseif ($r['status'] === 'absent'): ?>
                                            <span class="px-3 py-1 rounded-full bg-red-100 text-red-700 text-xs font-semibold uppercase">Absent</span>
                                        <?php else: ?>
                                            <span class="px-3 py-1 rounded-full bg-surface-container-high text-secondary text-xs font-semibold uppercase">Excused</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column (1/3 width): Practice Streaks & Metrics -->
            <div class="space-y-lg">
                <div class="bg-surface-container-lowest border border-outline-variant p-md rounded-xl">
                    <div class="flex items-center gap-sm mb-sm">
                        <div class="p-2 bg-secondary-container text-primary rounded-lg">
                            <span class="material-symbols-outlined" aria-hidden="true">timer</span>
                        </div>
                        <h5 class="font-label-md text-label-md text-on-surface font-semibold">Average Attendance</h5>
                    </div>
                    <p class="font-h2 text-h2 text-on-surface"><?= $attendanceRate ?>%</p>
                    <p class="font-label-sm text-label-sm text-secondary">Keep the momentum going!</p>
                </div>
                <div class="bg-surface-container-lowest border border-outline-variant p-md rounded-xl">
                    <div class="flex items-center gap-sm mb-sm">
                        <div class="p-2 bg-tertiary-fixed text-tertiary rounded-lg">
                            <span class="material-symbols-outlined" aria-hidden="true">trending_up</span>
                        </div>
                        <h5 class="font-label-md text-label-md text-on-surface font-semibold">Present Sessions</h5>
                    </div>
                    <p class="font-h2 text-h2 text-on-surface"><?= $metrics['present'] ?> Sessions</p>
                    <p class="font-label-sm text-label-sm text-secondary">Out of <?= $metrics['total_sessions'] ?> total sessions</p>
                </div>
            </div>
        </div>

        <!-- Lesson Participation -->
        <?php if ($selectedCourseId > 0): ?>
        <div class="mt-lg">
            <div class="flex items-center justify-between mb-md">
                <h3 class="font-h3 text-h3 text-on-surface">Lesson Participation</h3>
            </div>
            <div id="participation-list" class="bg-surface-container-lowest border border-outline-variant rounded-xl shadow-sm overflow-hidden" aria-live="polite">
                <div class="text-center text-on-surface-variant text-body-md py-8">Loading participation data...</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Absence Request Banner -->
        <div class="mt-lg p-lg bg-primary-container text-on-primary-container rounded-xl flex items-center justify-between">
            <div>
                <h4 class="font-h3 text-h3 mb-xs">Need to request an absence?</h4>
                <p class="font-body-md text-body-md opacity-90">Please contact your instructor directly at least 48 hours in advance for excused leave.</p>
            </div>
            <a href="/33_Student_Messages/index.php" class="bg-white text-primary-container px-lg py-sm rounded-lg font-label-md text-label-md font-bold shadow-sm hover:bg-surface-container-low transition-all">
                Send Message
            </a>
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

    <?php if ($selectedCourseId > 0): ?>
    function loadParticipation() {
        const container = document.getElementById('participation-list');
        if (!container) return;

        fetch('/student/participation.php?course_id=<?= $selectedCourseId ?>')
            .then(r => r.json())
            .then(d => {
                if (!d.success || !d.data || d.data.length === 0) {
                    container.innerHTML = '<div class="text-center text-on-surface-variant text-body-md py-8">No completed class sessions found for this course.</div>';
                    return;
                }
                container.innerHTML = '<div class="divide-y divide-outline-variant">' +
                    d.data.map(s => {
                        const completed = s.completed == 1;
                        return `<div class="px-md py-md flex items-center justify-between">
                            <div>
                                <p class="font-body-md text-on-surface font-semibold">${esc(s.title || 'Lesson Session')}</p>
                                <p class="text-xs text-on-surface-variant">${new Date(s.date).toLocaleDateString()} · ${s.start_time ? s.start_time.substring(0,5) : ''} - ${s.end_time ? s.end_time.substring(0,5) : ''}</p>
                            </div>
                            <div>
                                ${completed
                                    ? '<span class="px-3 py-1 rounded-full bg-green-100 text-green-700 text-xs font-semibold">Completed ✓</span>'
                                    : `<button onclick="completeSession(${s.session_id})" class="px-3 py-1 rounded-full bg-primary/10 text-primary text-xs font-semibold hover:bg-primary/20 transition-colors">Mark Completed</button>`
                                }
                            </div>
                        </div>`;
                    }).join('') + '</div>';
            })
            .catch(() => {
                if (container) container.innerHTML = '<div class="text-center text-error py-8">Failed to load participation data.</div>';
            });
    }

    function completeSession(sessionId) {
        fetch('/student/participation.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'complete_session', class_id: sessionId, csrf_token: '<?= csrf_token() ?>' })
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) loadParticipation();
        })
        .catch(() => {});
    }

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s || '';
        return d.innerHTML;
    }

    document.addEventListener('DOMContentLoaded', loadParticipation);
    <?php endif; ?>
</script>
</body>
</html>

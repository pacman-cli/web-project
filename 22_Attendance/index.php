<?php
// 22_Attendance/index.php
// Instructor portal for marking student attendance

require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/design-system.php';
require_once __DIR__ . '/../config/csrf.php';
requireRole('instructor');

$pdo = require_once __DIR__ . '/../config/db.php';
$instructorId = $_SESSION['user_id'];
$instructorName = $_SESSION['name'];

$courseId = intval($_GET['course_id'] ?? 0);
$date = trim($_GET['date'] ?? date('Y-m-d'));
$scheduleId = intval($_GET['schedule_id'] ?? 0);

try {
    // 1. Fetch assigned courses
    $coursesStmt = $pdo->prepare("
        SELECT c.id, c.title
        FROM courses c
        JOIN instructor_assignments ia ON c.id = ia.course_id
        WHERE ia.instructor_id = :instructor_id
        ORDER BY c.title ASC
    ");
    $coursesStmt->execute(['instructor_id' => $instructorId]);
    $courses = $coursesStmt->fetchAll();

    // If no course selected, pick first
    if ($courseId <= 0 && !empty($courses)) {
        $courseId = intval($courses[0]['id']);
    }

    // 2. Fetch schedules for the selected course
    $schedules = [];
    if ($courseId > 0) {
        $schedulesStmt = $pdo->prepare("
            SELECT s.id, s.start_time, s.end_time, s.day_of_week, s.location_detail
            FROM schedules s
            WHERE s.course_id = :course_id AND s.instructor_id = :instructor_id
            ORDER BY FIELD(s.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), s.start_time ASC
        ");
        $schedulesStmt->execute(['course_id' => $courseId, 'instructor_id' => $instructorId]);
        $schedules = $schedulesStmt->fetchAll();
    }

    // If no schedule selected, pick first schedule of the course
    if ($scheduleId <= 0 && !empty($schedules)) {
        $scheduleId = intval($schedules[0]['id']);
    }

    // 3. Fetch students and their current attendance records
    $students = [];
    $selectedSchedule = null;
    if ($scheduleId > 0) {
        // Find selected schedule details
        foreach ($schedules as $s) {
            if (intval($s['id']) === $scheduleId) {
                $selectedSchedule = $s;
                break;
            }
        }

        $studentsStmt = $pdo->prepare("
            SELECT u.id as student_id, u.name as student_name, u.email as student_email,
                   att.status as attendance_status
            FROM users u
            JOIN students s ON u.id = s.user_id
            JOIN enrollments e ON u.id = e.student_id AND e.course_id = :course_id
            LEFT JOIN attendance att ON u.id = att.student_id AND att.schedule_id = :schedule_id AND att.date = :date
            WHERE e.status = 'approved'
            ORDER BY u.name ASC
        ");
        $studentsStmt->execute([
            'course_id' => $courseId,
            'schedule_id' => $scheduleId,
            'date' => $date
        ]);
        $students = $studentsStmt->fetchAll();
    }

} catch (Exception $e) {
    error_log('DB error: ' . $e->getMessage()); die('A database error occurred. Please try again later.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php lms_head('Attendance', 'instructor'); ?>
</head>
<body class="bg-background text-on-background">

<?php lms_sidebar('instructor', '/22_Attendance/index.php'); ?>

<?php lms_topbar('instructor', 'Attendance'); ?>

<!-- Main Body Content -->
<main id="lms-main-content" class="lms-main">
    <!-- Header Section -->
    <div class="mb-lg">
        <h2 class="font-h1 text-h1 text-on-surface">Record Attendance</h2>
        <p class="font-body-md text-body-md text-secondary mt-xs">Record and review lesson participation</p>
    </div>

    <!-- Filters -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-md mb-lg">
        <div class="flex flex-col gap-xs">
            <label for="course-select" class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Course</label>
            <select id="course-select" onchange="reloadPage()" class="border border-outline-variant rounded-lg p-sm bg-surface-container-lowest text-body-md focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                <option value="">Select a course...</option>
                <?php foreach ($courses as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $c['id'] == $courseId ? 'selected' : '' ?>><?= htmlspecialchars($c['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex flex-col gap-xs">
            <label for="date-select" class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Date</label>
            <input id="date-select" onchange="reloadPage()" class="border border-outline-variant rounded-lg p-sm bg-surface-container-lowest text-body-md focus:border-primary focus:ring-1 focus:ring-primary outline-none" type="date" value="<?= htmlspecialchars($date) ?>"/>
        </div>
        <div class="flex flex-col gap-xs">
            <label for="schedule-select" class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Schedule Session</label>
            <select id="schedule-select" onchange="reloadPage()" class="border border-outline-variant rounded-lg p-sm bg-surface-container-lowest text-body-md focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                <option value="">Select a session...</option>
                <?php foreach ($schedules as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $s['id'] == $scheduleId ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['day_of_week']) ?>: <?= date('h:i A', strtotime($s['start_time'])) ?> - <?= date('h:i A', strtotime($s['end_time'])) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="flex flex-col lg:flex-row gap-lg">
        <!-- Session Details (33%) -->
        <div class="w-full lg:w-1/3">
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-lg shadow-sm">
                <h3 class="font-h3 text-h3 text-on-surface mb-md">Session Details</h3>
                <div class="space-y-sm">
                    <div class="flex justify-between py-2 border-b border-outline-variant">
                        <span class="text-secondary font-body-md">Session Date</span>
                        <span class="font-semibold text-on-surface font-body-md"><?= date('M d, Y', strtotime($date)) ?></span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-outline-variant">
                        <span class="text-secondary font-body-md">Session Time</span>
                        <span class="font-semibold text-on-surface font-body-md">
                            <?= $selectedSchedule ? date('h:i A', strtotime($selectedSchedule['start_time'])) . ' - ' . date('h:i A', strtotime($selectedSchedule['end_time'])) : 'N/A' ?>
                        </span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-outline-variant">
                        <span class="text-secondary font-body-md">Location</span>
                        <span class="font-semibold text-on-surface font-body-md">
                            <?= $selectedSchedule ? htmlspecialchars($selectedSchedule['location_detail']) : 'N/A' ?>
                        </span>
                    </div>
                    <div class="flex justify-between py-2">
                        <span class="text-secondary font-body-md">Enrolled Students</span>
                        <span class="font-semibold text-primary font-body-md"><?= count($students) ?> Students</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance Table (66%) -->
        <div class="w-full lg:w-2/3">
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl shadow-sm flex flex-col h-full overflow-hidden">
                <div class="p-md bg-surface-container-low border-b border-outline-variant flex justify-between items-center">
                    <h3 class="font-h3 text-h3 text-on-surface font-bold">Attendance Roster</h3>
                    <div>
                        <button onclick="markAll('present')" class="px-3 py-1 bg-surface-container-lowest border border-outline-variant rounded-full text-label-sm font-label-sm text-on-surface-variant hover:bg-surface-container-high transition-colors">
                            Mark All Present
                        </button>
                    </div>
                </div>
                <div class="flex-1 overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-surface-container-low">
                            <tr>
                                <th class="px-md py-3 font-label-sm text-label-sm text-secondary uppercase tracking-wider">Student Name</th>
                                <th class="px-md py-3 font-label-sm text-label-sm text-secondary uppercase tracking-wider">Status Selection</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            <?php if ($scheduleId <= 0): ?>
                                <tr>
                                    <td colspan="2" class="px-md py-8 text-center text-on-surface-variant">Please select a schedule session above to mark attendance.</td>
                                </tr>
                            <?php elseif (empty($students)): ?>
                                <tr>
                                    <td colspan="2" class="px-md py-8 text-center text-on-surface-variant">No students enrolled in this course.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($students as $stu): ?>
                                    <tr class="hover:bg-surface-container-low transition-colors student-attendance-row" data-student-id="<?= $stu['student_id'] ?>">
                                        <td class="px-md py-4">
                                            <span class="font-semibold text-on-surface font-body-md"><?= htmlspecialchars($stu['student_name']) ?></span>
                                            <p class="text-xs text-outline"><?= htmlspecialchars($stu['student_email']) ?></p>
                                        </td>
                                        <td class="px-md py-4">
                                            <div class="flex bg-surface-container-low p-1 rounded-lg w-fit attendance-btn-group">
                                                <button onclick="setStatus(this, 'present')" class="px-3 py-1.5 rounded-md text-xs font-semibold btn-present <?= $stu['attendance_status'] === 'present' ? 'bg-primary text-on-primary' : 'text-secondary hover:text-on-surface' ?>">Present</button>
                                                <button onclick="setStatus(this, 'absent')" class="px-3 py-1.5 rounded-md text-xs font-semibold btn-absent <?= $stu['attendance_status'] === 'absent' ? 'bg-error text-white' : 'text-secondary hover:text-on-surface' ?>">Absent</button>
                                                <button onclick="setStatus(this, 'excused')" class="px-3 py-1.5 rounded-md text-xs font-semibold btn-excused <?= $stu['attendance_status'] === 'excused' ? 'bg-secondary text-on-secondary' : 'text-secondary hover:text-on-surface' ?>">Excused</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($scheduleId > 0 && !empty($students)): ?>
                    <div class="p-md bg-surface-container-low border-t border-outline-variant flex justify-end">
                        <button onclick="saveAttendance()" class="bg-primary text-on-primary px-xl py-3 rounded-lg font-label-md text-label-md font-semibold hover:opacity-95 active:opacity-90 shadow-sm transition-all">
                            Save Attendance
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
    const studentStatusMap = {};

    // Initialize map with current statuses
    document.addEventListener("DOMContentLoaded", () => {
        const rows = document.querySelectorAll(".student-attendance-row");
        rows.forEach(row => {
            const studentId = row.getAttribute("data-student-id");
            let initialStatus = "";
            if (row.querySelector(".btn-present").classList.contains("bg-primary")) {
                initialStatus = "present";
            } else if (row.querySelector(".btn-absent").classList.contains("bg-error")) {
                initialStatus = "absent";
            } else if (row.querySelector(".btn-excused").classList.contains("bg-secondary")) {
                initialStatus = "excused";
            }
            studentStatusMap[studentId] = initialStatus;
        });
    });

    function reloadPage() {
        const courseId = document.getElementById("course-select").value;
        const date = document.getElementById("date-select").value;
        const scheduleId = document.getElementById("schedule-select").value;
        window.location.search = `?course_id=${courseId}&date=${date}&schedule_id=${scheduleId}`;
    }

    function setStatus(btn, status) {
        const row = btn.closest(".student-attendance-row");
        const studentId = row.getAttribute("data-student-id");
        studentStatusMap[studentId] = status;

        // Reset all buttons in the row
        const btns = row.querySelectorAll(".attendance-btn-group button");
        btns.forEach(b => {
            b.className = "px-3 py-1.5 rounded-md text-xs font-semibold text-secondary hover:text-on-surface";
        });

        // Highlight active one
        if (status === 'present') {
            btn.className = "px-3 py-1.5 rounded-md text-xs font-semibold bg-primary text-on-primary";
        } else if (status === 'absent') {
            btn.className = "px-3 py-1.5 rounded-md text-xs font-semibold bg-error text-white";
        } else if (status === 'excused') {
            btn.className = "px-3 py-1.5 rounded-md text-xs font-semibold bg-secondary text-on-secondary";
        }
    }

    function markAll(status) {
        const rows = document.querySelectorAll(".student-attendance-row");
        rows.forEach(row => {
            const studentId = row.getAttribute("data-student-id");
            studentStatusMap[studentId] = status;
            
            // Trigger UI update
            const btn = row.querySelector(`.btn-${status}`);
            if (btn) {
                setStatus(btn, status);
            }
        });
    }

    function saveAttendance() {
        const scheduleId = <?= $scheduleId ?>;
        const date = "<?= htmlspecialchars($date) ?>";

        const attendanceList = [];
        for (const [studentId, status] of Object.entries(studentStatusMap)) {
            if (status) {
                attendanceList.push({
                    student_id: parseInt(studentId),
                    status: status
                });
            }
        }

        if (attendanceList.length === 0) {
            alert("Please select status for at least one student.");
            return;
        }

        fetch("/instructor/attendance.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-Token": "<?= csrf_token() ?>"
            },
            body: JSON.stringify({
                schedule_id: scheduleId,
                date: date,
                attendance: attendanceList
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert("Attendance recorded successfully!");
                window.location.reload();
            } else {
                alert(data.error || "Failed to record attendance.");
            }
        })
        .catch(err => {
            console.error(err);
            alert("Error saving attendance.");
        });
    }
</script>
</body>
</html>

<?php
// 22_Attendance/index.php
// Instructor portal for marking student attendance — fully dynamic (AJAX-driven)

require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/design-system.php';
require_once __DIR__ . '/../config/csrf.php';
requireRole('instructor');

$pdo = require_once __DIR__ . '/../config/db.php';
$instructorId = $_SESSION['user_id'];

try {
    $coursesStmt = $pdo->prepare("
        SELECT c.id, c.title
        FROM courses c
        JOIN instructor_assignments ia ON c.id = ia.course_id
        WHERE ia.instructor_id = :instructor_id
        ORDER BY c.title ASC
    ");
    $coursesStmt->execute(['instructor_id' => $instructorId]);
    $courses = $coursesStmt->fetchAll();
} catch (Exception $e) {
    error_log('DB error: ' . $e->getMessage());
    die('A database error occurred. Please try again later.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php lms_head('Attendance', 'instructor'); ?>
<style>
    @keyframes toast-in { from { opacity:0; transform:translateY(12px); } to { opacity:1; transform:translateY(0); } }
    @keyframes toast-out { from { opacity:1; } to { opacity:0; transform:translateY(12px); } }
    .toast-enter { animation: toast-in .25s ease-out; }
    .toast-exit { animation: toast-out .25s ease-in forwards; }
</style>
</head>
<body class="bg-background text-on-background">

<?php lms_sidebar('instructor', '/22_Attendance/index.php'); ?>
<?php lms_topbar('instructor', 'Attendance'); ?>

<main id="lms-main-content" class="lms-main">
    <div class="mb-lg">
        <h2 class="font-h1 text-h1 text-on-surface">Record Attendance</h2>
        <p class="font-body-md text-body-md text-secondary mt-xs">Select a course, date, and schedule to mark student attendance</p>
    </div>

    <!-- Filters -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-md mb-lg">
        <div class="flex flex-col gap-xs">
            <label for="course-select" class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Course</label>
            <select id="course-select" class="border border-outline-variant rounded-lg p-sm bg-surface-container-lowest text-body-md focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                <option value="">Select a course...</option>
                <?php foreach ($courses as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex flex-col gap-xs">
            <label for="date-select" class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Date</label>
            <input id="date-select" type="date" value="<?= date('Y-m-d') ?>" class="border border-outline-variant rounded-lg p-sm bg-surface-container-lowest text-body-md focus:border-primary focus:ring-1 focus:ring-primary outline-none"/>
        </div>
        <div class="flex flex-col gap-xs">
            <label for="schedule-select" class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Schedule Session</label>
            <select id="schedule-select" class="border border-outline-variant rounded-lg p-sm bg-surface-container-lowest text-body-md focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                <option value="">Select a session...</option>
            </select>
        </div>
    </div>

    <div class="flex flex-col lg:flex-row gap-lg">
        <!-- Session Details -->
        <div class="w-full lg:w-1/3">
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-lg shadow-sm">
                <h3 class="font-h3 text-h3 text-on-surface mb-md">Session Details</h3>
                <div id="session-details-body" class="space-y-sm">
                    <p class="text-secondary font-body-md">Select a course and schedule to view details.</p>
                </div>
            </div>
        </div>

        <!-- Attendance Roster -->
        <div class="w-full lg:w-2/3">
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl shadow-sm flex flex-col h-full overflow-hidden">
                <div class="p-md bg-surface-container-low border-b border-outline-variant flex justify-between items-center">
                    <h3 class="font-h3 text-h3 text-on-surface font-bold">Attendance Roster</h3>
                    <div id="mark-all-container"></div>
                </div>
                <div id="roster-body" class="flex-1 overflow-x-auto p-md text-center text-on-surface-variant">
                    Select a schedule to mark attendance.
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Toast Container -->
<div id="toast-container" class="fixed bottom-4 right-4 z-[200] space-y-sm"></div>

<script>
const CSRF_TOKEN = '<?= csrf_token() ?>';
const TODAY = '<?= date('Y-m-d') ?>';

let currentScheduleId = 0;
let currentDate = TODAY;
let studentStatusMap = {};
let currentScheduleData = null;

document.addEventListener('DOMContentLoaded', () => {
    const courseSelect = document.getElementById('course-select');
    const dateInput = document.getElementById('date-select');

    courseSelect.addEventListener('change', onCourseChange);
    dateInput.addEventListener('change', onDateChange);
    document.getElementById('schedule-select').addEventListener('change', onScheduleChange);

    if (courseSelect.value) {
        loadSchedules(parseInt(courseSelect.value));
    }
});

function onCourseChange() {
    const courseId = document.getElementById('course-select').value;
    currentScheduleId = 0;
    currentScheduleData = null;
    studentStatusMap = {};
    updateSessionDetailsEmpty();
    updateRosterEmpty('Select a schedule to mark attendance.');
    document.getElementById('mark-all-container').innerHTML = '';
    if (courseId) {
        loadSchedules(parseInt(courseId));
    } else {
        document.getElementById('schedule-select').innerHTML = '<option value="">Select a session...</option>';
    }
}

function onDateChange() {
    currentDate = document.getElementById('date-select').value || TODAY;
    if (currentScheduleId > 0) {
        loadStudents(currentScheduleId, currentDate);
    }
}

function onScheduleChange() {
    const val = document.getElementById('schedule-select').value;
    currentScheduleId = val ? parseInt(val) : 0;
    if (currentScheduleId > 0) {
        loadStudents(currentScheduleId, currentDate);
    } else {
        updateSessionDetailsEmpty();
        updateRosterEmpty('Select a schedule to mark attendance.');
        document.getElementById('mark-all-container').innerHTML = '';
    }
}

function loadSchedules(courseId) {
    const select = document.getElementById('schedule-select');
    select.innerHTML = '<option value="">Loading sessions...</option>';

    fetch(BASE_URL + `/instructor/attendance.php?action=schedules&course_id=${courseId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.data.length > 0) {
                let html = '<option value="">Select a session...</option>';
                data.data.forEach(s => {
                    html += `<option value="${s.id}">${escapeHtml(s.day_of_week)}: ${formatTime(s.start_time)} - ${formatTime(s.end_time)}</option>`;
                });
                select.innerHTML = html;
                currentScheduleId = data.data[0].id;
                select.value = currentScheduleId;
                loadStudents(currentScheduleId, currentDate);
            } else {
                select.innerHTML = '<option value="">No sessions found</option>';
                updateRosterEmpty('No schedule sessions found for this course.');
                updateSessionDetailsEmpty();
            }
        })
        .catch(err => {
            console.error(err);
            select.innerHTML = '<option value="">Error loading sessions</option>';
            showToast('Failed to load schedule sessions.', 'error');
        });
}

function loadStudents(scheduleId, date) {
    const rosterBody = document.getElementById('roster-body');
    rosterBody.innerHTML = '<div class="py-8 text-center text-secondary"><span class="material-symbols-outlined animate-spin mr-2" aria-hidden="true">progress_activity</span>Loading students...</div>';

    fetch(BASE_URL + `/instructor/attendance.php?schedule_id=${scheduleId}&date=${date}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                renderRoster(data.data);
                updateSessionDetails(scheduleId, data.data.length);
            } else {
                rosterBody.innerHTML = `<div class="py-8 text-center text-error">${escapeHtml(data.error || 'Failed to load students.')}</div>`;
            }
        })
        .catch(err => {
            console.error(err);
            rosterBody.innerHTML = '<div class="py-8 text-center text-error">Network error loading students.</div>';
        });
}

function renderRoster(students) {
    const rosterBody = document.getElementById('roster-body');
    const markAllContainer = document.getElementById('mark-all-container');
    studentStatusMap = {};

    if (students.length === 0) {
        rosterBody.innerHTML = '<div class="py-8 text-center text-on-surface-variant">No students enrolled in this course.</div>';
        markAllContainer.innerHTML = '';
        return;
    }

    markAllContainer.innerHTML = `<button onclick="markAll('present')" class="px-3 py-1 bg-surface-container-lowest border border-outline-variant rounded-full text-label-sm font-label-sm text-on-surface-variant hover:bg-surface-container-high transition-colors">Mark All Present</button>`;

    let html = `<table class="w-full text-left border-collapse">
        <thead class="bg-surface-container-low"><tr>
            <th class="px-md py-3 font-label-sm text-label-sm text-secondary uppercase tracking-wider">Student Name</th>
            <th class="px-md py-3 font-label-sm text-label-sm text-secondary uppercase tracking-wider">Status Selection</th>
        </tr></thead>
        <tbody class="divide-y divide-outline-variant">`;

    students.forEach(stu => {
        const status = stu.attendance_status || '';
        studentStatusMap[stu.student_id] = status;

        const pCls = status === 'present' ? 'bg-primary text-on-primary' : 'text-secondary hover:text-on-surface';
        const aCls = status === 'absent'  ? 'bg-error text-white'       : 'text-secondary hover:text-on-surface';
        const eCls = status === 'excused' ? 'bg-secondary text-on-secondary' : 'text-secondary hover:text-on-surface';

        html += `<tr class="hover:bg-surface-container-low transition-colors" data-student-id="${stu.student_id}">
            <td class="px-md py-4">
                <span class="font-semibold text-on-surface font-body-md">${escapeHtml(stu.student_name)}</span>
                <p class="text-xs text-outline">${escapeHtml(stu.student_email)}</p>
            </td>
            <td class="px-md py-4">
                <div class="flex bg-surface-container-low p-1 rounded-lg w-fit attendance-btn-group">
                    <button onclick="setStatus(this,'present')" class="px-3 py-1.5 rounded-md text-xs font-semibold ${pCls} btn-present">Present</button>
                    <button onclick="setStatus(this,'absent')"  class="px-3 py-1.5 rounded-md text-xs font-semibold ${aCls} btn-absent">Absent</button>
                    <button onclick="setStatus(this,'excused')" class="px-3 py-1.5 rounded-md text-xs font-semibold ${eCls} btn-excused">Excused</button>
                </div>
            </td>
        </tr>`;
    });

    html += `</tbody></table>
        <div class="p-md bg-surface-container-low border-t border-outline-variant flex justify-end">
            <button onclick="saveAttendance()" class="bg-primary text-on-primary px-xl py-3 rounded-lg font-label-md text-label-md font-semibold hover:opacity-95 active:opacity-90 shadow-sm transition-all">
                Save Attendance
            </button>
        </div>`;

    rosterBody.innerHTML = html;
}

function updateSessionDetails(scheduleId, studentCount) {
    const container = document.getElementById('session-details-body');
    if (!currentScheduleData) {
        fetchScheduleDetail(scheduleId, studentCount);
        return;
    }
    renderDetails(studentCount);
}

function fetchScheduleDetail(scheduleId, studentCount) {
    const courseId = document.getElementById('course-select').value;
    fetch(BASE_URL + `/instructor/attendance.php?action=schedules&course_id=${courseId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                currentScheduleData = data.data.find(s => s.id === scheduleId) || null;
            }
            renderDetails(studentCount);
        })
        .catch(() => renderDetails(studentCount));
}

function renderDetails(studentCount) {
    const container = document.getElementById('session-details-body');
    const dateStr = currentDate ? new Date(currentDate + 'T00:00:00').toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' }) : 'N/A';
    const timeStr = currentScheduleData ? `${formatTime(currentScheduleData.start_time)} - ${formatTime(currentScheduleData.end_time)}` : 'N/A';
    const location = currentScheduleData ? escapeHtml(currentScheduleData.location_detail) : 'N/A';

    container.innerHTML = `
        <div class="flex justify-between py-2 border-b border-outline-variant">
            <span class="text-secondary font-body-md">Session Date</span>
            <span class="font-semibold text-on-surface font-body-md">${dateStr}</span>
        </div>
        <div class="flex justify-between py-2 border-b border-outline-variant">
            <span class="text-secondary font-body-md">Session Time</span>
            <span class="font-semibold text-on-surface font-body-md">${timeStr}</span>
        </div>
        <div class="flex justify-between py-2 border-b border-outline-variant">
            <span class="text-secondary font-body-md">Location</span>
            <span class="font-semibold text-on-surface font-body-md">${location}</span>
        </div>
        <div class="flex justify-between py-2">
            <span class="text-secondary font-body-md">Enrolled Students</span>
            <span class="font-semibold text-primary font-body-md">${studentCount} Students</span>
        </div>`;
}

function updateSessionDetailsEmpty() {
    document.getElementById('session-details-body').innerHTML = '<p class="text-secondary font-body-md">Select a course and schedule to view details.</p>';
}

function updateRosterEmpty(msg) {
    document.getElementById('roster-body').innerHTML = `<div class="py-8 text-center text-on-surface-variant">${msg}</div>`;
}

function setStatus(btn, status) {
    const row = btn.closest('tr');
    const studentId = row.getAttribute('data-student-id');
    studentStatusMap[studentId] = status;

    row.querySelectorAll('.attendance-btn-group button').forEach(b => {
        b.className = 'px-3 py-1.5 rounded-md text-xs font-semibold text-secondary hover:text-on-surface';
    });

    if (status === 'present')  btn.className = 'px-3 py-1.5 rounded-md text-xs font-semibold bg-primary text-on-primary btn-present';
    if (status === 'absent')   btn.className = 'px-3 py-1.5 rounded-md text-xs font-semibold bg-error text-white btn-absent';
    if (status === 'excused')  btn.className = 'px-3 py-1.5 rounded-md text-xs font-semibold bg-secondary text-on-secondary btn-excused';
}

function markAll(status) {
    document.querySelectorAll('#roster-body tr[data-student-id]').forEach(row => {
        const studentId = row.getAttribute('data-student-id');
        studentStatusMap[studentId] = status;
        const btn = row.querySelector(`.btn-${status}`);
        if (btn) setStatus(btn, status);
    });
}

function saveAttendance() {
    if (!currentScheduleId) {
        showToast('Please select a schedule first.', 'error');
        return;
    }

    const attendanceList = [];
    for (const [studentId, status] of Object.entries(studentStatusMap)) {
        if (status) {
            attendanceList.push({ student_id: parseInt(studentId), status: status });
        }
    }

    if (attendanceList.length === 0) {
        showToast('Please mark status for at least one student.', 'error');
        return;
    }

    const saveBtn = document.querySelector('#roster-body button[onclick="saveAttendance()"]');
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';
    }

    fetch(BASE_URL + '/instructor/attendance.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
        body: JSON.stringify({ schedule_id: currentScheduleId, date: currentDate, attendance: attendanceList })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Attendance recorded successfully!', 'success');
            loadStudents(currentScheduleId, currentDate);
        } else {
            showToast(data.error || 'Failed to record attendance.', 'error');
        }
    })
    .catch(err => {
        console.error(err);
        showToast('Network error saving attendance.', 'error');
    })
    .finally(() => {
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save Attendance';
        }
    });
}

function formatTime(timeStr) {
    if (!timeStr) return '';
    const [h, m] = timeStr.split(':');
    const hr = parseInt(h);
    const ampm = hr >= 12 ? 'PM' : 'AM';
    const h12 = hr % 12 || 12;
    return `${h12}:${m} ${ampm}`;
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    const bg = type === 'success' ? 'bg-primary text-on-primary' : type === 'error' ? 'bg-error text-white' : 'bg-surface-container-high text-on-surface';
    const icon = type === 'success' ? 'check_circle' : type === 'error' ? 'error' : 'info';

    toast.className = `${bg} px-md py-sm rounded-lg shadow-lg flex items-center gap-sm toast-enter`;
    toast.innerHTML = `<span class="material-symbols-outlined text-[20px]" aria-hidden="true">${icon}</span><span class="font-body-md">${escapeHtml(message)}</span>`;
    container.appendChild(toast);

    setTimeout(() => {
        toast.classList.remove('toast-enter');
        toast.classList.add('toast-exit');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}
</script>
</body>
</html>

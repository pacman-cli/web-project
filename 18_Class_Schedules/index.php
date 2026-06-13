<?php
// 18_Class_Schedules/index.php
// Instructor portal for scheduling class hours

require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/design-system.php';
require_once __DIR__ . '/../config/csrf.php';
requireRole('instructor');

$pdo = require_once __DIR__ . '/../config/db.php';
$instructorId = $_SESSION['user_id'];
$instructorName = $_SESSION['name'];

try {
    // 1. Fetch assigned courses for dropdown
    $coursesStmt = $pdo->prepare("
        SELECT c.id, c.title
        FROM courses c
        JOIN instructor_assignments ia ON c.id = ia.course_id
        WHERE ia.instructor_id = :instructor_id
        ORDER BY c.title ASC
    ");
    $coursesStmt->execute(['instructor_id' => $instructorId]);
    $courses = $coursesStmt->fetchAll();

    // 2. Fetch all schedules
    $schedulesStmt = $pdo->prepare("
        SELECT s.*, c.title as course_title
        FROM schedules s
        JOIN courses c ON s.course_id = c.id
        WHERE s.instructor_id = :instructor_id
        ORDER BY FIELD(s.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), s.start_time ASC
    ");
    $schedulesStmt->execute(['instructor_id' => $instructorId]);
    $schedules = $schedulesStmt->fetchAll();

    // Group schedules by day of week
    $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    $groupedSchedules = [];
    foreach ($daysOfWeek as $day) {
        $groupedSchedules[$day] = [];
    }
    foreach ($schedules as $s) {
        $groupedSchedules[$s['day_of_week']][] = $s;
    }

} catch (Exception $e) {
    error_log('DB error: ' . $e->getMessage()); die('A database error occurred. Please try again later.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php lms_head('Class Schedules', 'instructor'); ?>
</head>
<body class="text-on-surface">

<?php lms_sidebar('instructor', '/18_Class_Schedules/index.php'); ?>

<?php lms_topbar('instructor', 'Class Schedules'); ?>

<!-- Main Content -->
<main id="lms-main-content" class="lms-main">
    <div class="max-w-container-max mx-auto">
        <!-- Page Header -->
        <div class="flex flex-col md:flex-row md:items-end justify-between mb-lg gap-md">
            <div>
                <h2 class="font-h1 text-h1 text-on-surface">Class Schedules</h2>
                <p class="font-body-md text-body-md text-on-surface-variant">Manage lesson timings and upcoming sessions</p>
            </div>
            <div class="flex items-center gap-sm">
                <button onclick="openScheduleModal()" class="flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-lg font-label-md text-label-md font-semibold hover:opacity-90 transition-opacity">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">calendar_add_on</span>
                    Create Schedule
                </button>
            </div>
        </div>

        <!-- Schedule Weekly Grid Layout -->
        <div class="grid grid-cols-1 md:grid-cols-7 gap-md mb-lg">
            <?php foreach ($daysOfWeek as $day): ?>
                <div class="bg-white border border-outline-variant rounded-xl shadow-sm p-sm flex flex-col min-h-[300px]">
                    <div class="text-center font-bold text-label-md text-primary bg-primary-container/10 py-1.5 rounded-lg mb-sm uppercase tracking-wider">
                        <?= htmlspecialchars(substr($day, 0, 3)) ?>
                    </div>
                    <div class="space-y-sm flex-1">
                        <?php if (empty($groupedSchedules[$day])): ?>
                            <div class="h-full flex items-center justify-center text-center text-xs text-outline italic">
                                No classes
                            </div>
                        <?php else: ?>
                            <?php foreach ($groupedSchedules[$day] as $s): ?>
                                <div class="bg-surface-container-low border border-outline-variant p-sm rounded-lg hover:border-primary transition-all relative group"
                                     data-id="<?= $s['id'] ?>"
                                     data-course-id="<?= $s['course_id'] ?>"
                                     data-day="<?= $s['day_of_week'] ?>"
                                     data-start="<?= $s['start_time'] ?>"
                                     data-end="<?= $s['end_time'] ?>"
                                     data-location-type="<?= $s['location_type'] ?>"
                                     data-location-detail="<?= htmlspecialchars($s['location_detail']) ?>">
                                    <div class="font-bold text-xs text-primary leading-tight pr-4">
                                        <?= htmlspecialchars($s['course_title']) ?>
                                    </div>
                                    <div class="text-[10px] text-on-surface-variant mt-1">
                                        <?= date('h:i A', strtotime($s['start_time'])) ?> - <?= date('h:i A', strtotime($s['end_time'])) ?>
                                    </div>
                                    <div class="text-[9px] text-outline mt-1 truncate">
                                        <?= htmlspecialchars($s['location_type'] === 'online' ? 'Online: Zoom' : $s['location_detail']) ?>
                                    </div>
                                    <?php if ($s['location_type'] === 'online'): ?>
                                        <a href="<?= htmlspecialchars($s['location_detail']) ?>" target="_blank" class="mt-2 text-[10px] text-primary font-semibold hover:underline block">
                                            Join Meeting
                                        </a>
                                    <?php endif; ?>
                                    
                                    <div class="absolute top-1 right-1 flex gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity bg-white/80 rounded-md px-1">
                                        <button onclick="openEditModal(<?= $s['id'] ?>)" class="text-on-surface-variant hover:text-primary transition-colors p-0.5" aria-label="Edit schedule">
                                            <span class="material-symbols-outlined text-[14px]" aria-hidden="true">edit</span>
                                        </button>
                                        <button onclick="deleteSchedule(<?= $s['id'] ?>)" class="text-on-surface-variant hover:text-error transition-colors p-0.5" aria-label="Delete schedule">
                                            <span class="material-symbols-outlined text-[14px]" aria-hidden="true">delete</span>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</main>

<!-- Create Schedule Modal -->
<div id="schedule-modal" class="fixed inset-0 bg-inverse-surface/40 backdrop-blur-sm z-[100] flex items-center justify-center p-md hidden">
    <div class="bg-surface-container-lowest w-full max-w-[500px] rounded-xl shadow-xl overflow-hidden animate-in fade-in zoom-in duration-300 border border-outline-variant">
        <!-- Modal Header -->
        <div class="px-lg py-md border-b border-outline-variant flex justify-between items-center">
            <h2 id="modal-title" class="font-h2 text-h2 text-on-surface">Create New Schedule</h2>
            <button onclick="closeScheduleModal()" class="text-secondary hover:text-on-surface transition-colors" aria-label="Close schedule dialog">
                <span class="material-symbols-outlined" aria-hidden="true">close</span>
            </button>
        </div>
        <!-- Modal Form Content -->
        <form id="schedule-form" onsubmit="handleSaveSchedule(event)" class="p-lg space-y-md">
            <input type="hidden" id="edit-id" name="id" value="0"/>
            <!-- Course Selection -->
            <div class="space-y-xs">
                <label for="course-select-form" class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Course</label>
                    <div class="relative">
                        <select required name="course_id" id="course-select-form" class="w-full bg-surface-container-lowest border border-outline-variant rounded-lg px-md py-sm font-body-md text-body-md focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all outline-none appearance-none">
                            <option value="">Select a course…</option>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="material-symbols-outlined absolute right-md top-1/2 -translate-y-1/2 pointer-events-none text-secondary" aria-hidden="true">expand_more</span>
                </div>
            </div>
            <!-- Weekday Selection -->
            <div class="space-y-xs">
                <label for="day-select" class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Weekday</label>
                <div class="relative">
                    <select required name="day_of_week" id="day-select" class="w-full bg-surface-container-lowest border border-outline-variant rounded-lg px-md py-sm font-body-md text-body-md focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all outline-none appearance-none">
                        <option value="">Select a day…</option>
                        <option value="Monday">Monday</option>
                        <option value="Tuesday">Tuesday</option>
                        <option value="Wednesday">Wednesday</option>
                        <option value="Thursday">Thursday</option>
                        <option value="Friday">Friday</option>
                        <option value="Saturday">Saturday</option>
                        <option value="Sunday">Sunday</option>
                    </select>
                    <span class="material-symbols-outlined absolute right-md top-1/2 -translate-y-1/2 pointer-events-none text-secondary" aria-hidden="true">expand_more</span>
                </div>
            </div>
            <!-- Time Grid -->
            <div class="grid grid-cols-2 gap-lg">
                <div class="space-y-xs">
                    <label for="start-time" class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Start Time</label>
                    <input required name="start_time" id="start-time" class="w-full border border-outline-variant rounded-lg px-md py-sm font-body-md focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all outline-none" type="time"/>
                </div>
                <div class="space-y-xs">
                    <label for="end-time" class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">End Time</label>
                    <input required name="end_time" id="end-time" class="w-full border border-outline-variant rounded-lg px-md py-sm font-body-md focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all outline-none" type="time"/>
                </div>
            </div>
            <!-- Location Details -->
            <div class="grid grid-cols-2 gap-lg">
                <div class="space-y-xs">
                    <label for="location_type" class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Platform / Location</label>
                    <select required id="location_type" name="location_type" onchange="toggleLocationFields()" class="w-full border border-outline-variant rounded-lg px-md py-sm font-body-md focus:border-primary focus:ring-4 focus:ring-primary/10 outline-none appearance-none bg-white">
                        <option value="online">Online (Zoom/Meet)</option>
                        <option value="physical">Physical Room</option>
                    </select>
                </div>
                <div class="space-y-xs">
                    <label for="location_detail" id="location_detail_label" class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Meeting Link</label>
                     <input required id="location_detail" name="location_detail" autocomplete="off" class="w-full border border-outline-variant rounded-lg px-md py-sm font-body-md focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all outline-none" placeholder="https://zoom.us/j/…" type="text"/>
                </div>
            </div>

            <div id="schedule-error" class="text-error text-body-md font-semibold hidden" aria-live="polite"></div>

            <!-- Modal Footer -->
            <div class="px-lg py-md border-t border-outline-variant bg-surface-container-low flex justify-end gap-md">
                <button type="button" onclick="closeScheduleModal()" class="px-md py-sm font-label-md text-label-md text-secondary hover:text-on-surface transition-colors">
                    Cancel
                </button>
                <button type="submit" class="bg-primary text-on-primary px-lg py-sm font-label-md text-label-md rounded-lg hover:opacity-90 shadow-sm transition-all">
                    Save Schedule
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openScheduleModal() {
        document.getElementById('edit-id').value = '0';
        document.getElementById('modal-title').textContent = 'Create New Schedule';
        document.getElementById('schedule-form').reset();
        document.getElementById('schedule-error').classList.add('hidden');
        toggleLocationFields();
        document.getElementById('schedule-modal').classList.remove('hidden');
    }

    function openEditModal(id) {
        const card = document.querySelector(`[data-id="${id}"]`);
        if (!card) return;

        document.getElementById('edit-id').value = id;
        document.getElementById('modal-title').textContent = 'Edit Schedule';
        document.getElementById('schedule-error').classList.add('hidden');

        const form = document.getElementById('schedule-form');
        form.course_id.value = card.dataset.courseId;
        form.day_of_week.value = card.dataset.day;
        form.start_time.value = card.dataset.start;
        form.end_time.value = card.dataset.end;
        form.location_type.value = card.dataset.locationType;
        form.location_detail.value = card.dataset.locationDetail;

        toggleLocationFields();
        document.getElementById('schedule-modal').classList.remove('hidden');
    }

    function closeScheduleModal() {
        document.getElementById('schedule-modal').classList.add('hidden');
    }

    function toggleLocationFields() {
        const type = document.getElementById('location_type').value;
        const label = document.getElementById('location_detail_label');
        const input = document.getElementById('location_detail');
        if (type === 'online') {
            label.innerText = 'Meeting Link';
            input.placeholder = 'https://zoom.us/j/…';
        } else {
            label.innerText = 'Room Detail';
            input.placeholder = 'e.g., Studio 102';
        }
    }

    function handleSaveSchedule(event) {
        event.preventDefault();
        const form = document.getElementById('schedule-form');
        const editId = parseInt(document.getElementById('edit-id').value);
        const data = {
            id: editId,
            course_id: form.course_id.value,
            day_of_week: form.day_of_week.value,
            start_time: form.start_time.value,
            end_time: form.end_time.value,
            location_type: form.location_type.value,
            location_detail: form.location_detail.value
        };

        const errorDiv = document.getElementById('schedule-error');
        errorDiv.classList.add('hidden');

        fetch('/instructor/schedules.php', {
            method: editId > 0 ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?= csrf_token() ?>' },
            body: JSON.stringify(data)
        })
        .then(res => res.json())
        .then(resData => {
            if (resData.success) {
                window.location.reload();
            } else {
                errorDiv.innerText = resData.error || 'Failed to save schedule.';
                errorDiv.classList.remove('hidden');
            }
        })
        .catch(err => {
            console.error(err);
            errorDiv.innerText = 'Network error or server failed.';
            errorDiv.classList.remove('hidden');
        });
    }

    function deleteSchedule(id) {
        if (!confirm('Are you sure you want to delete this class schedule?')) return;

        fetch('/instructor/schedules.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?= csrf_token() ?>' },
            body: JSON.stringify({ id: id })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert(data.error || 'Failed to delete schedule.');
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error deleting schedule.');
        });
    }
</script>
</body>
</html>

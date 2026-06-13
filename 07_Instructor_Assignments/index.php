<?php
// 07_Instructor_Assignments/index.php
// Dynamic Instructor Course Assignments portal for Admins

require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/design-system.php';
require_once __DIR__ . '/../config/csrf.php';
requireRole('admin'); // Protect via role guard

$pdo = require_once __DIR__ . '/../config/db.php';

// Fetch all assignments
try {
    $stmt = $pdo->query("
        SELECT ia.assigned_at, u.id as instructor_id, u.name as instructor_name, 
               c.id as course_id, c.title as course_title, inst.name as instrument_name
        FROM instructor_assignments ia
        JOIN users u ON ia.instructor_id = u.id
        JOIN courses c ON ia.course_id = c.id
        LEFT JOIN instruments inst ON c.instrument_id = inst.id
        ORDER BY c.title ASC, u.name ASC
    ");
    $assignments = $stmt->fetchAll();

    // Fetch all active instructors
    $instStmt = $pdo->query("SELECT id, name FROM users WHERE role = 'instructor' AND status = 'active' ORDER BY name ASC");
    $instructors = $instStmt->fetchAll();

    // Fetch all courses
    $courseStmt = $pdo->query("SELECT id, title FROM courses ORDER BY title ASC");
    $courses = $courseStmt->fetchAll();

} catch (Exception $e) {
    error_log('DB error: ' . $e->getMessage()); die('A database error occurred. Please try again later.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php lms_head('Instructor Assignments', 'admin'); ?>
</head>
<body class="bg-background text-on-background min-h-screen">
<?php lms_sidebar('admin', '/07_Instructor_Assignments/index.php'); ?>

<?php lms_topbar('admin', 'Instructor Assignments'); ?>

<!-- Main Content Canvas -->
<main id="lms-main-content" class="lms-main">
    <div class="p-lg max-w-container-max mx-auto">
        <!-- Header Section -->
        <div class="mb-lg flex flex-col md:flex-row md:items-end justify-between gap-md">
            <div>
                <h2 class="text-h1 font-h1 text-on-background">Instructor Assignments</h2>
                <p class="text-body-lg text-secondary mt-xs">Assign instructors to course offerings</p>
            </div>
            <button id="assign-btn" class="bg-primary text-on-primary px-md py-sm rounded-lg font-body-md font-semibold flex items-center gap-xs hover:bg-primary-container transition-colors shadow-sm">
                <span class="material-symbols-outlined" aria-hidden="true">add</span>
                Assign Instructor
            </button>
        </div>

        <!-- Filter & Action Bar -->
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-md mb-md flex flex-wrap items-center gap-md shadow-[0_2px_4px_rgba(0,0,0,0.04)]">
            <div class="flex-1 min-w-[240px] relative">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline" aria-hidden="true">search</span>
                <input id="search-input" class="w-full border-outline-variant border rounded-lg pl-10 py-2 text-body-md focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none" placeholder="Search by instructor or course…" type="text"/>
            </div>
        </div>

        <!-- Data Table Card -->
        <div class="bg-surface-container-lowest border border-outline-variant rounded-lg overflow-hidden shadow-[0_2px_4px_rgba(0,0,0,0.04)]">
            <table class="w-full text-left border-collapse">
                <thead class="bg-surface-container-low border-b border-outline-variant">
                    <tr>
                        <th class="px-md py-sm text-label-sm font-label-sm text-secondary uppercase tracking-wider">Course Title</th>
                        <th class="px-md py-sm text-label-sm font-label-sm text-secondary uppercase tracking-wider">Instrument</th>
                        <th class="px-md py-sm text-label-sm font-label-sm text-secondary uppercase tracking-wider">Instructor</th>
                        <th class="px-md py-sm text-label-sm font-label-sm text-secondary uppercase tracking-wider">Assigned Date</th>
                        <th class="px-md py-sm text-label-sm font-label-sm text-secondary uppercase tracking-wider text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant" id="assignment-list">
                    <?php if (empty($assignments)): ?>
                        <tr>
                            <td colspan="5" class="px-md py-md text-center text-secondary">No assignments yet. Link an instructor.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($assignments as $asg): ?>
                            <tr class="hover:bg-surface transition-colors assignment-row">
                                <td class="px-md py-md text-body-md font-semibold text-on-surface"><?= htmlspecialchars($asg['course_title']) ?></td>
                                <td class="px-md py-md">
                                    <span class="bg-blue-50 text-blue-700 px-2 py-1 rounded-full text-[10px] font-bold uppercase tracking-tight"><?= htmlspecialchars($asg['instrument_name'] ?: 'None') ?></span>
                                </td>
                                <td class="px-md py-md">
                                    <div class="flex items-center gap-sm">
                                        <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold text-xs">
                                            <?= htmlspecialchars(substr($asg['instructor_name'], 0, 2)) ?>
                                        </div>
                                        <span class="text-body-md font-medium text-on-surface"><?= htmlspecialchars($asg['instructor_name']) ?></span>
                                    </div>
                                </td>
                                <td class="px-md py-md text-body-md text-secondary"><?= date('F j, Y', strtotime($asg['assigned_at'])) ?></td>
                                <td class="px-md py-md text-right space-x-base">
                                    <button onclick="removeAssignment(<?= $asg['instructor_id'] ?>, <?= $asg['course_id'] ?>)" class="p-1.5 text-secondary hover:text-error hover:bg-error/5 rounded-lg transition-all"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">delete</span></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Modal Overlay for Add/Edit -->
<div id="modal-container" class="fixed inset-0 z-[100] flex items-center justify-center hidden">
    <!-- Backdrop -->
    <button class="absolute inset-0 bg-on-background/40 backdrop-blur-sm w-full h-full" onclick="closeModal()" aria-label="Close modal"></button>
    <!-- Modal Container -->
    <div class="relative bg-surface-container-lowest w-full max-w-md max-h-[90vh] overflow-y-auto rounded-xl shadow-xl flex flex-col">
        <!-- Modal Header -->
        <div class="px-xl py-md border-b border-outline-variant flex justify-between items-center bg-surface-container-low">
            <h2 class="text-h2 font-h2 text-on-surface">Assign Instructor</h2>
            <button onclick="closeModal()" class="p-xs hover:bg-surface-container-high rounded-full transition-colors text-secondary" aria-label="Close modal">
                <span class="material-symbols-outlined" aria-hidden="true">close</span>
            </button>
        </div>
        <!-- Modal Body / Form -->
        <form id="assign-form" class="p-xl space-y-md">
            <div id="error-message" class="hidden bg-error-container/20 border border-error/30 text-error text-sm p-4 rounded-lg" aria-live="polite"></div>

            <div class="flex flex-col gap-xs">
                <label for="form-course" class="text-label-md font-label-md text-on-surface-variant">Select Course</label>
                <select id="form-course" name="course_id" required class="px-md py-sm rounded-lg border border-outline-variant focus:border-primary focus:ring-1 focus:ring-primary outline-none text-body-md bg-surface-bright">
                    <option value="">Choose Course…</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex flex-col gap-xs">
                <label for="form-instructor" class="text-label-md font-label-md text-on-surface-variant">Select Instructor</label>
                <select id="form-instructor" name="instructor_id" required class="px-md py-sm rounded-lg border border-outline-variant focus:border-primary focus:ring-1 focus:ring-primary outline-none text-body-md bg-surface-bright">
                    <option value="">Choose Instructor…</option>
                    <?php foreach ($instructors as $inst): ?>
                        <option value="<?= $inst['id'] ?>"><?= htmlspecialchars($inst['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
        <!-- Modal Footer -->
        <div class="px-xl py-md border-t border-outline-variant flex justify-end gap-sm bg-surface-container-low">
            <button onclick="closeModal()" class="px-md py-sm border border-outline text-secondary rounded-lg font-label-md text-label-md hover:bg-surface-container-high transition-all">Cancel</button>
            <button onclick="submitForm()" class="px-md py-sm bg-primary text-on-primary rounded-lg font-label-md text-label-md hover:bg-primary-container transition-all shadow-sm">Save Assignment</button>
        </div>
    </div>
</div>

<script>
    const modal = document.getElementById('modal-container');
    const form = document.getElementById('assign-form');
    const errorDiv = document.getElementById('error-message');

    document.getElementById('assign-btn').addEventListener('click', () => {
        form.reset();
        errorDiv.classList.add('hidden');
        modal.classList.remove('hidden');
    });

    function closeModal() {
        modal.classList.add('hidden');
    }

    function submitForm() {
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const data = {
            instructor_id: document.getElementById('form-instructor').value,
            course_id: document.getElementById('form-course').value
        };

        fetch(BASE_URL + '/admin/assignments.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?= csrf_token() ?>' },
            body: JSON.stringify(data)
        })
        .then(res => res.json())
        .then(resData => {
            if (resData.success) {
                location.reload();
            } else {
                errorDiv.innerText = resData.error || 'Assignment failed.';
                errorDiv.classList.remove('hidden');
            }
        })
        .catch(err => {
            errorDiv.innerText = 'Network error: ' + err.message;
            errorDiv.classList.remove('hidden');
        });
    }

    function removeAssignment(instructorId, courseId) {
        if (confirm('Are you sure you want to remove this instructor assignment?')) {
            fetch(BASE_URL + '/admin/assignments.php', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?= csrf_token() ?>' },
                body: JSON.stringify({ instructor_id: instructorId, course_id: courseId })
            })
            .then(res => res.json())
            .then(resData => {
                if (resData.success) {
                    location.reload();
                } else {
                    alert(resData.error || 'Removal failed.');
                }
            })
            .catch(err => alert('Network error: ' + err.message));
        }
    }

    // Client-side search filtering
    document.getElementById('search-input').addEventListener('input', (e) => {
        const query = e.target.value.toLowerCase();
        document.querySelectorAll('.assignment-row').forEach(row => {
            const text = row.innerText.toLowerCase();
            if (text.includes(query)) {
                row.classList.remove('hidden');
            } else {
                row.classList.add('hidden');
            }
        });
    });
</script>
</body>
</html>

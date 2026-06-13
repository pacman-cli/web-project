<?php
// 05_Course_Management/index.php
// Dynamic Course Management portal for Admins

require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/design-system.php';
require_once __DIR__ . '/../config/csrf.php';
requireRole('admin'); // Protect via role guard

$pdo = require_once __DIR__ . '/../config/db.php';

// Fetch all courses
try {
    $stmt = $pdo->query("
        SELECT c.*, i.name as instrument_name,
               (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status = 'approved') as enrolled_count
        FROM courses c
        LEFT JOIN instruments i ON c.instrument_id = i.id
        ORDER BY c.title ASC
    ");
    $courses = $stmt->fetchAll();

    // Fetch instruments for selection dropdown
    $instStmt = $pdo->query("SELECT id, name FROM instruments ORDER BY name ASC");
    $instruments = $instStmt->fetchAll();
} catch (Exception $e) {
    error_log('DB error: ' . $e->getMessage()); die('A database error occurred. Please try again later.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php lms_head('Course Management', 'admin'); ?>
</head>
<body class="bg-background text-on-background">
<?php lms_sidebar('admin', '/05_Course_Management/index.php'); ?>

<?php lms_topbar('admin', 'Course Management'); ?>

<!-- Main Content -->
<main id="lms-main-content" class="lms-main">
    <div class="p-lg max-w-container-max mx-auto">
        <!-- Page Header & Top Actions -->
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-md mb-lg">
            <div>
                <h2 class="text-h1 font-h1 text-primary mb-1">Course Management</h2>
                <p class="text-body-lg font-body-lg text-secondary">Manage course offerings and lesson structures</p>
            </div>
            <button id="add-course-btn" class="bg-primary text-on-primary px-md py-2 rounded-lg font-body-md font-medium hover:bg-primary-container transition-all flex items-center gap-sm shadow-sm">
                <span class="material-symbols-outlined text-md" aria-hidden="true">add</span>
                Add Course
            </button>
        </div>

        <!-- Bento Grid Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-md mb-lg">
            <div class="bg-surface-container-lowest p-md rounded-xl border border-outline-variant shadow-sm">
                <p class="text-label-md font-label-md text-secondary mb-xs uppercase tracking-wider">Total Courses</p>
                <div class="flex items-center justify-between">
                    <span class="text-h2 font-h2 text-primary"><?= count($courses) ?></span>
                    <span class="text-label-sm font-label-sm px-2 py-1 bg-green-100 text-green-700 rounded-full">Live Catalog</span>
                </div>
            </div>
            <div class="bg-surface-container-lowest p-md rounded-xl border border-outline-variant shadow-sm">
                <p class="text-label-md font-label-md text-secondary mb-xs uppercase tracking-wider">System Security</p>
                <div class="flex items-center justify-between">
                    <span class="text-h2 font-h2 text-primary">PDO</span>
                    <span class="text-label-sm font-label-sm px-2 py-1 bg-blue-100 text-blue-700 rounded-full">Prepared SQL</span>
                </div>
            </div>
            <div class="bg-surface-container-lowest p-md rounded-xl border border-outline-variant shadow-sm">
                <p class="text-label-md font-label-md text-secondary mb-xs uppercase tracking-wider">Active Term</p>
                <div class="flex items-center justify-between">
                    <span class="text-h2 font-h2 text-primary">Spring</span>
                    <span class="text-label-sm font-label-sm text-secondary italic">2026/2027</span>
                </div>
            </div>
        </div>

        <!-- Courses Table Card -->
        <div class="bg-surface-container-lowest rounded-xl border border-outline-variant shadow-sm overflow-hidden">
            <div class="px-md py-sm border-b border-outline-variant bg-surface-container-low/30">
                <h3 class="text-label-sm font-label-sm text-secondary uppercase tracking-widest">Active Course Offerings</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-surface-container-low">
                        <tr>
                            <th class="px-md py-sm font-label-sm text-label-sm text-secondary">COURSE ID</th>
                            <th class="px-md py-sm font-label-sm text-label-sm text-secondary">COURSE TITLE</th>
                            <th class="px-md py-sm font-label-sm text-label-sm text-secondary">INSTRUMENT</th>
                            <th class="px-md py-sm font-label-sm text-label-sm text-secondary">LEVEL</th>
                            <th class="px-md py-sm font-label-sm text-label-sm text-secondary">PRICE ($)</th>
                            <th class="px-md py-sm font-label-sm text-label-sm text-secondary text-center">ENROLLED</th>
                            <th class="px-md py-sm font-label-sm text-label-sm text-secondary">STATUS</th>
                            <th class="px-md py-sm font-label-sm text-label-sm text-secondary text-right">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant" id="course-list">
                        <?php if (empty($courses)): ?>
                            <tr>
                                <td colspan="8" class="px-md py-md text-center text-secondary">No courses found. Create one.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($courses as $c): ?>
                                <tr class="hover:bg-surface-container-lowest/50 transition-colors group course-row">
                                    <td class="px-md py-md font-label-md text-primary">#CR-<?= $c['id'] ?></td>
                                    <td class="px-md py-md">
                                        <p class="font-body-md font-semibold text-on-surface"><?= htmlspecialchars($c['title']) ?></p>
                                        <p class="text-label-sm text-secondary truncate max-w-xs"><?= htmlspecialchars($c['description']) ?></p>
                                    </td>
                                    <td class="px-md py-md">
                                        <span class="px-2 py-1 rounded-full bg-blue-50 text-blue-700 text-label-sm font-medium border border-blue-100"><?= htmlspecialchars($c['instrument_name'] ?: 'None') ?></span>
                                    </td>
                                    <td class="px-md py-md font-body-md text-secondary uppercase text-xs"><?= htmlspecialchars($c['difficulty']) ?></td>
                                    <td class="px-md py-md font-body-md text-secondary"><?= number_format($c['price'], 2) ?></td>
                                    <td class="px-md py-md text-center font-body-md text-on-surface"><?= intval($c['enrolled_count']) ?></td>
                                    <td class="px-md py-md">
                                        <?php if ($c['status'] === 'published'): ?>
                                            <span class="inline-flex items-center gap-1.5 text-green-600 font-label-sm">
                                                <span class="w-1.5 h-1.5 rounded-full bg-green-600"></span> Published
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1.5 text-secondary font-label-sm">
                                                <span class="w-1.5 h-1.5 rounded-full bg-secondary"></span> Draft
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-md py-md text-right space-x-base">
                                        <button onclick="editCourse(<?= htmlspecialchars(json_encode($c)) ?>)" class="p-1.5 text-secondary hover:text-primary hover:bg-surface-container-high rounded-lg transition-colors" aria-label="Edit course"><span class="material-symbols-outlined text-md" aria-hidden="true">edit</span></button>
                                        <button onclick="deleteCourse(<?= $c['id'] ?>)" class="p-1.5 text-secondary hover:text-error hover:bg-error-container/30 rounded-lg transition-colors" aria-label="Delete course"><span class="material-symbols-outlined text-md" aria-hidden="true">delete</span></button>
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

<!-- Modal Overlay for Add/Edit -->
<div id="modal-container" class="fixed inset-0 bg-on-background/40 backdrop-blur-[2px] z-50 flex items-center justify-center p-md hidden">
    <!-- Backdrop dismiss -->
    <button class="absolute inset-0 w-full h-full" onclick="closeModal()" aria-label="Close modal"></button>
    <!-- Add Course Modal -->
    <div class="relative bg-surface-container-lowest w-full max-w-[640px] rounded-xl shadow-[0_8px_32px_rgba(0,0,0,0.12)] border border-outline-variant flex flex-col overflow-hidden">
        <!-- Modal Header -->
        <div class="px-lg py-md border-b border-outline-variant flex justify-between items-center">
            <div>
                <h2 id="modal-title" class="text-h2 font-h2 text-on-surface">Add New Course</h2>
                <p class="text-body-md font-body-md text-secondary">Configure curriculum details and enrollment limits.</p>
            </div>
            <button onclick="closeModal()" class="text-on-surface-variant hover:text-error transition-colors" aria-label="Close modal">
                <span class="material-symbols-outlined" aria-hidden="true">close</span>
            </button>
        </div>
        <!-- Modal Content (Scrollable Form) -->
        <form id="course-form" class="p-lg space-y-md overflow-y-auto max-h-[70vh]">
            <input type="hidden" id="form-action" name="action" value="add"/>
            <input type="hidden" id="form-id" name="id" value=""/>

            <div id="error-message" class="hidden bg-error-container/20 border border-error/30 text-error text-sm p-4 rounded-lg" aria-live="polite"></div>

            <div class="space-y-xs">
                <label for="form-title" class="text-label-sm font-label-sm text-on-surface uppercase tracking-wider">Course Title</label>
                <input id="form-title" name="title" required class="w-full px-base py-sm border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none text-body-md transition-all" placeholder="e.g. Advanced Jazz Theory" type="text" autocomplete="off"/>
            </div>
            <div class="grid grid-cols-2 gap-md">
                <div class="space-y-xs">
                    <label for="form-instrument" class="text-label-sm font-label-sm text-on-surface uppercase tracking-wider">Instrument Category</label>
                    <select id="form-instrument" name="instrument_id" class="w-full px-base py-sm border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none text-body-md bg-white">
                        <option value="">No category</option>
                        <?php foreach ($instruments as $inst): ?>
                            <option value="<?= $inst['id'] ?>"><?= htmlspecialchars($inst['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="space-y-xs">
                    <label for="form-difficulty" class="text-label-sm font-label-sm text-on-surface uppercase tracking-wider">Difficulty Level</label>
                    <select id="form-difficulty" name="difficulty" class="w-full px-base py-sm border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none text-body-md bg-white">
                        <option value="beginner">Beginner</option>
                        <option value="intermediate">Intermediate</option>
                        <option value="advanced">Advanced</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-md">
                <div class="space-y-xs">
                    <label for="form-price" class="text-label-sm font-label-sm text-on-surface uppercase tracking-wider">Price ($)</label>
                    <input id="form-price" name="price" required class="w-full px-base py-sm border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none text-body-md transition-all" type="number" step="0.01" value="0.00" autocomplete="off"/>
                </div>
                <div class="space-y-xs">
                    <label class="text-label-sm font-label-sm text-on-surface uppercase tracking-wider">Publish Status</label>
                    <div class="flex gap-md mt-sm">
                        <label class="flex items-center gap-xs cursor-pointer">
                            <input id="status-draft" name="status" type="radio" value="draft" checked class="text-primary focus:ring-primary"/>
                            <span class="text-body-md">Draft</span>
                        </label>
                        <label class="flex items-center gap-xs cursor-pointer">
                            <input id="status-published" name="status" type="radio" value="published" class="text-primary focus:ring-primary"/>
                            <span class="text-body-md">Published</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="space-y-xs">
                <label for="form-description" class="text-label-sm font-label-sm text-on-surface uppercase tracking-wider">Course Description</label>
                <textarea id="form-description" name="description" class="w-full px-base py-sm border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none text-body-md transition-all resize-none" placeholder="Detailed syllabus overview and prerequisites…" rows="4"></textarea>
            </div>
        </form>
        <!-- Modal Footer -->
        <div class="px-lg py-md border-t border-outline-variant bg-surface-container-low flex justify-end gap-md">
            <button onclick="closeModal()" class="px-md py-base rounded-lg border border-outline text-secondary font-body-md text-body-md hover:bg-surface-container-high transition-colors">Cancel</button>
            <button onclick="submitForm()" class="px-xl py-base rounded-lg bg-primary text-on-primary font-body-md text-body-md hover:bg-primary-container transition-colors shadow-sm">Save Course</button>
        </div>
    </div>
</div>

<script>
    const modal = document.getElementById('modal-container');
    const form = document.getElementById('course-form');
    const formAction = document.getElementById('form-action');
    const formId = document.getElementById('form-id');
    const modalTitle = document.getElementById('modal-title');
    const errorDiv = document.getElementById('error-message');

    document.getElementById('add-course-btn').addEventListener('click', () => {
        form.reset();
        formAction.value = 'add';
        formId.value = '';
        modalTitle.innerText = 'Add New Course';
        errorDiv.classList.add('hidden');
        modal.classList.remove('hidden');
    });

    function editCourse(c) {
        form.reset();
        formAction.value = 'edit';
        formId.value = c.id;
        modalTitle.innerText = 'Edit Course';
        document.getElementById('form-title').value = c.title;
        document.getElementById('form-instrument').value = c.instrument_id || '';
        document.getElementById('form-difficulty').value = c.difficulty;
        document.getElementById('form-price').value = c.price;
        document.getElementById('form-description').value = c.description || '';
        
        if (c.status === 'published') {
            document.getElementById('status-published').checked = true;
        } else {
            document.getElementById('status-draft').checked = true;
        }

        errorDiv.classList.add('hidden');
        modal.classList.remove('hidden');
    }

    function closeModal() {
        modal.classList.add('hidden');
    }

    function submitForm() {
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const data = {
            action: formAction.value,
            id: formId.value,
            title: document.getElementById('form-title').value,
            instrument_id: document.getElementById('form-instrument').value,
            difficulty: document.getElementById('form-difficulty').value,
            price: document.getElementById('form-price').value,
            description: document.getElementById('form-description').value,
            status: document.querySelector('input[name="status"]:checked').value
        };

        fetch('/admin/courses.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?= csrf_token() ?>' },
            body: JSON.stringify(data)
        })
        .then(res => res.json())
        .then(resData => {
            if (resData.success) {
                location.reload();
            } else {
                errorDiv.innerText = resData.error || 'Operation failed.';
                errorDiv.classList.remove('hidden');
            }
        })
        .catch(err => {
            errorDiv.innerText = 'Network error: ' + err.message;
            errorDiv.classList.remove('hidden');
        });
    }

    function deleteCourse(id) {
        if (confirm('Are you sure you want to delete this course? All related enrollments and assignments will be deleted.')) {
            fetch('/admin/courses.php', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?= csrf_token() ?>' },
                body: JSON.stringify({ id: id })
            })
            .then(res => res.json())
            .then(resData => {
                if (resData.success) {
                    location.reload();
                } else {
                    alert(resData.error || 'Failed to delete course.');
                }
            })
            .catch(err => alert('Network error: ' + err.message));
        }
    }

    // Client-side search filtering
    document.getElementById('topbar-search').addEventListener('input', (e) => {
        const query = e.target.value.toLowerCase();
        document.querySelectorAll('.course-row').forEach(row => {
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

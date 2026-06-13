<?php
// 01_Instructor_Management/index.php
// Dynamic Instructor Management portal for Admins

require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/design-system.php';
require_once __DIR__ . '/../config/csrf.php';
requireRole('admin'); // Protect via role guard

$pdo = require_once __DIR__ . '/../config/db.php';

// Fetch all instructors
try {
    $stmt = $pdo->query("
        SELECT u.id, u.name, u.email, u.status, u.created_at,
               i.bio, i.specialization, i.hourly_rate, i.hire_date
        FROM users u
        JOIN instructors i ON u.id = i.user_id
        WHERE u.role = 'instructor'
        ORDER BY u.name ASC
    ");
    $instructors = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('DB error: ' . $e->getMessage()); die('A database error occurred. Please try again later.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php lms_head('Instructor Management', 'admin'); ?>
</head>
<body class="bg-background text-on-surface">
<?php lms_sidebar('admin', '/01_Instructor_Management/index.php'); ?>
<?php lms_topbar('admin', 'Instructor Management', 'Search instructors…'); ?>

<main id="lms-main-content" class="lms-main">
    <div class="page-content">
        <!-- Page Header -->
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-md mb-lg">
            <div>
                <h2 class="font-h1 text-h1 text-on-surface">Instructor Management</h2>
                <p class="font-body-lg text-body-lg text-secondary">Manage teaching staff and teaching assignments</p>
            </div>
            <div class="flex items-center gap-sm">
                <button id="add-instructor-btn" class="flex items-center gap-xs bg-primary text-on-primary px-md py-sm rounded-lg font-label-md text-label-md hover:bg-primary-container transition-all shadow-sm">
                    <span class="material-symbols-outlined text-[20px]" aria-hidden="true">add</span>
                    Add Instructor
                </button>
            </div>
        </div>

        <!-- Dashboard Stats Row (Mini Bento Grid) -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-md mb-lg">
            <div class="bg-surface-container-lowest p-md rounded-xl border border-outline-variant shadow-[0_2px_4px_rgba(0,0,0,0.04)]">
                <p class="text-label-md font-label-md text-secondary mb-xs">Total Staff</p>
                <p class="text-h2 font-h2 text-on-surface"><?= count($instructors) ?></p>
                <div class="mt-sm flex items-center text-label-sm text-primary">
                    <span class="material-symbols-outlined text-[16px]" aria-hidden="true">trending_up</span>
                    <span class="ml-1">Active Profiles</span>
                </div>
            </div>
            <div class="bg-surface-container-lowest p-md rounded-xl border border-outline-variant shadow-[0_2px_4px_rgba(0,0,0,0.04)]">
                <p class="text-label-md font-label-md text-secondary mb-xs">Roles Enforced</p>
                <p class="text-h2 font-h2 text-on-surface">RBAC</p>
                <div class="mt-sm flex items-center text-label-sm text-secondary">
                    <span class="material-symbols-outlined text-[16px]" aria-hidden="true">verified_user</span>
                    <span class="ml-1">Secure Signatures</span>
                </div>
            </div>
            <div class="bg-surface-container-lowest p-md rounded-xl border border-outline-variant shadow-[0_2px_4px_rgba(0,0,0,0.04)]">
                <p class="text-label-md font-label-md text-secondary mb-xs">Specializations</p>
                <p class="text-h2 font-h2 text-on-surface">Live</p>
                <div class="mt-sm flex items-center text-label-sm text-secondary">
                    <span class="material-symbols-outlined text-[16px]" aria-hidden="true">category</span>
                    <span class="ml-1">Dynamic matching</span>
                </div>
            </div>
            <div class="bg-surface-container-lowest p-md rounded-xl border border-outline-variant shadow-[0_2px_4px_rgba(0,0,0,0.04)]">
                <p class="text-label-md font-label-md text-secondary mb-xs">Server Check</p>
                <p class="text-h2 font-h2 text-[#15803d]">OK</p>
                <div class="mt-sm flex items-center text-label-sm text-green-700">
                    <span class="material-symbols-outlined text-[16px]" aria-hidden="true">check_circle</span>
                    <span class="ml-1">PDO connection healthy</span>
                </div>
            </div>
        </div>

        <!-- Instructor Table Container -->
        <div class="bg-surface-container-lowest rounded-xl border border-outline-variant shadow-[0_2px_4px_rgba(0,0,0,0.04)] overflow-hidden">
            <div class="px-md py-sm border-b border-outline-variant flex justify-between items-center bg-surface-container-low">
                <h3 class="font-h3 text-h3 text-on-surface">Instructor Directory</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-surface-container-low border-b border-outline-variant">
                        <tr>
                            <th class="px-md py-sm font-label-sm text-label-sm text-secondary tracking-wider">ID</th>
                            <th class="px-md py-sm font-label-sm text-label-sm text-secondary tracking-wider">NAME</th>
                            <th class="px-md py-sm font-label-sm text-label-sm text-secondary tracking-wider">EMAIL</th>
                            <th class="px-md py-sm font-label-sm text-label-sm text-secondary tracking-wider">SPECIALIZATION</th>
                            <th class="px-md py-sm font-label-sm text-label-sm text-secondary tracking-wider">STATUS</th>
                            <th class="px-md py-sm font-label-sm text-label-sm text-secondary tracking-wider text-right">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant" id="instructor-list">
                        <?php if (empty($instructors)): ?>
                            <tr>
                                <td colspan="6" class="px-md py-md text-center text-secondary">No instructors found. Add one to get started.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($instructors as $inst): ?>
                                <tr class="hover:bg-surface-container transition-colors group instructor-row">
                                    <td class="px-md py-md font-body-md text-body-md text-secondary">#INS-<?= $inst['id'] ?></td>
                                    <td class="px-md py-md">
                                        <div class="flex items-center gap-sm">
                                            <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold text-xs">
                                                <?= htmlspecialchars(substr($inst['name'], 0, 2)) ?>
                                            </div>
                                            <span class="font-body-md text-body-md font-semibold text-on-surface"><?= htmlspecialchars($inst['name']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-md py-md font-body-md text-body-md text-secondary"><?= htmlspecialchars($inst['email']) ?></td>
                                    <td class="px-md py-md">
                                        <span class="px-base py-[2px] rounded-full bg-secondary-container text-on-secondary-fixed-variant font-label-md text-[10px] uppercase tracking-wider"><?= htmlspecialchars($inst['specialization'] ?: 'General') ?></span>
                                    </td>
                                    <td class="px-md py-md">
                                        <?php if ($inst['status'] === 'active'): ?>
                                            <span class="flex items-center gap-xs text-primary font-label-md text-label-md">
                                                <span class="w-2 h-2 rounded-full bg-primary"></span> Active
                                            </span>
                                        <?php else: ?>
                                            <span class="flex items-center gap-xs text-outline font-label-md text-label-md">
                                                <span class="w-2 h-2 rounded-full bg-outline"></span> Inactive
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-md py-md text-right space-x-base">
                                        <button onclick="viewInstructor(<?= htmlspecialchars(json_encode($inst)) ?>)" class="p-xs hover:bg-surface-container-high rounded transition-colors text-secondary hover:text-primary" aria-label="View instructor details"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">visibility</span></button>
                                        <button onclick="editInstructor(<?= htmlspecialchars(json_encode($inst)) ?>)" class="p-xs hover:bg-surface-container-high rounded transition-colors text-secondary hover:text-primary" aria-label="Edit instructor"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">edit</span></button>
                                        <button onclick="deleteInstructor(<?= $inst['id'] ?>)" class="p-xs hover:bg-surface-container-high rounded transition-colors text-secondary hover:text-error" aria-label="Delete instructor"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">delete</span></button>
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
<div id="modal-container" class="fixed inset-0 z-[100] flex items-center justify-center hidden">
    <!-- Backdrop -->
    <button class="absolute inset-0 bg-on-background/40 backdrop-blur-sm w-full h-full" onclick="closeModal()" aria-label="Close modal"></button>
    <!-- Modal Container -->
    <div class="relative bg-surface-container-lowest w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-xl shadow-xl flex flex-col">
        <!-- Modal Header -->
        <div class="px-xl py-md border-b border-outline-variant flex justify-between items-center bg-surface-container-low">
            <h2 id="modal-title" class="text-h2 font-h2 text-on-surface">Add New Instructor</h2>
            <button onclick="closeModal()" class="p-xs hover:bg-surface-container-high rounded-full transition-colors text-secondary" aria-label="Close modal">
                <span class="material-symbols-outlined" aria-hidden="true">close</span>
            </button>
        </div>
        <!-- Modal Body / Form -->
        <form id="instructor-form" class="p-xl space-y-md">
            <input type="hidden" id="form-action" name="action" value="add"/>
            <input type="hidden" id="form-id" name="id" value=""/>

            <div id="error-message" class="hidden bg-error-container/20 border border-error/30 text-error text-sm p-4 rounded-lg" aria-live="polite"></div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-md">
                <div class="flex flex-col gap-xs">
                    <label for="form-name" class="text-label-md font-label-md text-on-surface-variant">Full Name</label>
                    <input id="form-name" name="name" required class="px-md py-sm rounded-lg border border-outline-variant focus:border-primary focus:ring-1 focus:ring-primary outline-none text-body-md bg-surface-bright" placeholder="e.g. Johannes Brahms" type="text" autocomplete="name"/>
                </div>
                <div class="flex flex-col gap-xs">
                    <label for="form-email" class="text-label-md font-label-md text-on-surface-variant">Email Address</label>
                    <input id="form-email" name="email" required class="px-md py-sm rounded-lg border border-outline-variant focus:border-primary focus:ring-1 focus:ring-primary outline-none text-body-md bg-surface-bright" placeholder="j.brahms@lyra.edu" type="email" autocomplete="email"/>
                </div>
                <div class="flex flex-col gap-xs" id="password-field-wrapper">
                    <label for="form-password" class="text-label-md font-label-md text-on-surface-variant">Password</label>
                    <input id="form-password" name="password" class="px-md py-sm rounded-lg border border-outline-variant focus:border-primary focus:ring-1 focus:ring-primary outline-none text-body-md bg-surface-bright" placeholder="Account password" type="password" autocomplete="new-password"/>
                </div>
                <div class="flex flex-col gap-xs">
                    <label for="form-specialization" class="text-label-md font-label-md text-on-surface-variant">Specialization</label>
                    <input id="form-specialization" name="specialization" class="px-md py-sm rounded-lg border border-outline-variant focus:border-primary focus:ring-1 focus:ring-primary outline-none text-body-md bg-surface-bright" placeholder="e.g. Piano & Theory" type="text" autocomplete="off"/>
                </div>
                <div class="flex flex-col gap-xs">
                    <label for="form-rate" class="text-label-md font-label-md text-on-surface-variant">Hourly Rate ($)</label>
                    <input id="form-rate" name="hourly_rate" class="px-md py-sm rounded-lg border border-outline-variant focus:border-primary focus:ring-1 focus:ring-primary outline-none text-body-md bg-surface-bright" placeholder="50.00" type="number" step="0.01" autocomplete="off"/>
                </div>
                <div class="flex flex-col gap-xs">
                    <label for="form-hire-date" class="text-label-md font-label-md text-on-surface-variant">Hire Date</label>
                    <input id="form-hire-date" name="hire_date" class="px-md py-sm rounded-lg border border-outline-variant focus:border-primary focus:ring-1 focus:ring-primary outline-none text-body-md bg-surface-bright" type="date" value="<?= date('Y-m-d') ?>" autocomplete="off"/>
                </div>
            </div>
            <div class="flex flex-col gap-xs">
                <label for="form-bio" class="text-label-md font-label-md text-on-surface-variant">Instructor Bio</label>
                <textarea id="form-bio" name="bio" class="px-md py-sm rounded-lg border border-outline-variant focus:border-primary focus:ring-1 focus:ring-primary outline-none text-body-md bg-surface-bright resize-none" placeholder="Brief professional background…" rows="3"></textarea>
            </div>
            <div class="flex items-center justify-between p-md bg-surface-container-low rounded-xl border border-outline-variant">
                <div>
                    <p class="text-body-md font-semibold text-on-surface">Account Status</p>
                    <p class="text-label-sm text-secondary">Set instructor account state</p>
                </div>
                <div class="flex gap-md">
                    <label class="flex items-center gap-xs cursor-pointer">
                        <input id="status-active" name="status" type="radio" value="active" checked class="text-primary focus:ring-primary"/>
                        <span class="text-body-md">Active</span>
                    </label>
                    <label class="flex items-center gap-xs cursor-pointer">
                        <input id="status-inactive" name="status" type="radio" value="inactive" class="text-primary focus:ring-primary"/>
                        <span class="text-body-md">Inactive</span>
                    </label>
                </div>
            </div>
        </form>
        <!-- Modal Footer -->
        <div class="px-xl py-md border-t border-outline-variant flex justify-end gap-sm bg-surface-container-low">
            <button onclick="closeModal()" class="px-md py-sm border border-outline text-secondary rounded-lg font-label-md text-label-md hover:bg-surface-container-high transition-all">Cancel</button>
            <button onclick="submitForm()" class="px-md py-sm bg-primary text-on-primary rounded-lg font-label-md text-label-md hover:bg-primary-container transition-all shadow-sm">Save Instructor</button>
        </div>
    </div>
</div>

<!-- Instructor Detail Modal -->
<div id="detail-modal" class="fixed inset-0 z-[100] flex items-center justify-center hidden">
    <button class="absolute inset-0 bg-on-background/40 backdrop-blur-sm w-full h-full" onclick="closeDetailModal()" aria-label="Close modal"></button>
    <div class="relative bg-surface-container-lowest w-full max-w-3xl max-h-[90vh] overflow-y-auto rounded-xl shadow-xl flex flex-col">
        <div class="px-xl py-md border-b border-outline-variant flex justify-between items-center bg-surface-container-low">
            <h2 class="text-h2 font-h2 text-on-surface">Instructor Details</h2>
            <button onclick="closeDetailModal()" class="p-xs hover:bg-surface-container-high rounded-full transition-colors text-secondary" aria-label="Close modal">
                <span class="material-symbols-outlined" aria-hidden="true">close</span>
            </button>
        </div>
        <div class="p-xl" id="detail-content">
            <!-- Content loaded dynamically -->
        </div>
    </div>
</div>

<script>
    const modal = document.getElementById('modal-container');
    const form = document.getElementById('instructor-form');
    const formAction = document.getElementById('form-action');
    const formId = document.getElementById('form-id');
    const modalTitle = document.getElementById('modal-title');
    const passwordFieldWrapper = document.getElementById('password-field-wrapper');
    const errorDiv = document.getElementById('error-message');

    document.getElementById('add-instructor-btn').addEventListener('click', () => {
        form.reset();
        formAction.value = 'add';
        formId.value = '';
        modalTitle.innerText = 'Add New Instructor';
        passwordFieldWrapper.classList.remove('hidden');
        document.getElementById('form-password').setAttribute('required', 'true');
        errorDiv.classList.add('hidden');
        modal.classList.remove('hidden');
    });

    function editInstructor(inst) {
        form.reset();
        formAction.value = 'edit';
        formId.value = inst.id;
        modalTitle.innerText = 'Edit Instructor: ' + inst.name;
        document.getElementById('form-name').value = inst.name;
        document.getElementById('form-email').value = inst.email;
        document.getElementById('form-specialization').value = inst.specialization || '';
        document.getElementById('form-rate').value = inst.hourly_rate || '0.00';
        document.getElementById('form-hire-date').value = inst.hire_date || '<?= date('Y-m-d') ?>';
        document.getElementById('form-bio').value = inst.bio || '';
        
        if (inst.status === 'active') {
            document.getElementById('status-active').checked = true;
        } else {
            document.getElementById('status-inactive').checked = true;
        }

        passwordFieldWrapper.classList.add('hidden');
        document.getElementById('form-password').removeAttribute('required');
        errorDiv.classList.add('hidden');
        modal.classList.remove('hidden');
    }

    function closeModal() {
        modal.classList.add('hidden');
    }

    function closeDetailModal() {
        document.getElementById('detail-modal').classList.add('hidden');
    }

    function viewInstructor(inst) {
        const content = document.getElementById('detail-content');
        content.innerHTML = `
            <div class="space-y-lg">
                <div class="flex items-start gap-lg">
                    <div class="w-20 h-20 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold text-2xl flex-shrink-0">
                        ${inst.name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase()}
                    </div>
                    <div class="flex-1">
                        <h3 class="font-h2 text-h2 text-on-surface">${escapeHtml(inst.name)}</h3>
                        <p class="text-body-lg text-secondary">${escapeHtml(inst.email)}</p>
                        <div class="flex items-center gap-sm mt-2">
                            ${inst.status === 'active' 
                                ? '<span class="px-sm py-1 bg-green-100 text-green-800 rounded-full text-xs font-semibold">Active</span>'
                                : '<span class="px-sm py-1 bg-gray-100 text-gray-800 rounded-full text-xs font-semibold">Inactive</span>'}
                            <span class="px-sm py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-semibold">${escapeHtml(inst.specialization || 'General')}</span>
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-md">
                    <div class="bg-surface-container-low p-md rounded-xl border border-outline-variant">
                        <p class="text-label-md font-label-md text-secondary mb-xs">Hourly Rate</p>
                        <p class="text-h3 font-h3 text-on-surface">$${parseFloat(inst.hourly_rate || 0).toFixed(2)}</p>
                    </div>
                    <div class="bg-surface-container-low p-md rounded-xl border border-outline-variant">
                        <p class="text-label-md font-label-md text-secondary mb-xs">Hire Date</p>
                        <p class="text-h3 font-h3 text-on-surface">${inst.hire_date ? new Date(inst.hire_date).toLocaleDateString() : 'Not specified'}</p>
                    </div>
                </div>
                
                <div class="bg-surface-container-low p-md rounded-xl border border-outline-variant">
                    <p class="text-label-md font-label-md text-secondary mb-xs">Bio</p>
                    <p class="text-body-md text-on-surface">${escapeHtml(inst.bio || 'No bio provided.')}</p>
                </div>
                
                <div class="bg-surface-container-low p-md rounded-xl border border-outline-variant">
                    <p class="text-label-md font-label-md text-secondary mb-xs">Account Information</p>
                    <div class="grid grid-cols-2 gap-md mt-sm">
                        <div>
                            <p class="text-label-sm text-secondary">User ID</p>
                            <p class="text-body-md text-on-surface font-medium">#INS-${inst.id}</p>
                        </div>
                        <div>
                            <p class="text-label-sm text-secondary">Member Since</p>
                            <p class="text-body-md text-on-surface font-medium">${new Date(inst.created_at).toLocaleDateString()}</p>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.getElementById('detail-modal').classList.remove('hidden');
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function submitForm() {
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const data = {
            action: formAction.value,
            id: formId.value,
            name: document.getElementById('form-name').value,
            email: document.getElementById('form-email').value,
            specialization: document.getElementById('form-specialization').value,
            hourly_rate: document.getElementById('form-rate').value,
            hire_date: document.getElementById('form-hire-date').value,
            bio: document.getElementById('form-bio').value,
            status: document.querySelector('input[name="status"]:checked').value
        };

        if (formAction.value === 'add') {
            data.password = document.getElementById('form-password').value;
        }

        fetch(BASE_URL + '/admin/instructors.php', {
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

    function deleteInstructor(id) {
        if (confirm('Are you sure you want to delete this instructor? This action cannot be undone.')) {
            fetch(BASE_URL + '/admin/instructors.php', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?= csrf_token() ?>' },
                body: JSON.stringify({ id: id })
            })
            .then(res => res.json())
            .then(resData => {
                if (resData.success) {
                    location.reload();
                } else {
                    alert(resData.error || 'Failed to delete.');
                }
            })
            .catch(err => alert('Network error: ' + err.message));
        }
    }

    // Client-side search filtering
    document.getElementById('topbar-search').addEventListener('input', (e) => {
        const query = e.target.value.toLowerCase();
        document.querySelectorAll('.instructor-row').forEach(row => {
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

<?php
// 03_Instrument_Categories/index.php
// Dynamic Instrument Categories portal for Admins

require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/design-system.php';
require_once __DIR__ . '/../config/csrf.php';
requireRole('admin'); // Protect via role guard

$pdo = require_once __DIR__ . '/../config/db.php';

// Fetch all instruments and course count
try {
    $stmt = $pdo->query("
        SELECT i.id, i.name, i.description, i.created_at,
               (SELECT COUNT(*) FROM courses c WHERE c.instrument_id = i.id) as course_count
        FROM instruments i
        ORDER BY i.name ASC
    ");
    $instruments = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('DB error: ' . $e->getMessage()); die('A database error occurred. Please try again later.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php lms_head('Instrument Categories', 'admin'); ?>
</head>
<body class="text-on-surface">
<?php lms_sidebar('admin', '/03_Instrument_Categories/index.php'); ?>
<?php lms_topbar('admin', 'Instrument Categories'); ?>

<!-- Main Content Area -->
<main id="lms-main-content" class="lms-main">

    <!-- Canvas -->
    <div class="page-content">
        <!-- Header Section -->
        <div class="flex justify-between items-end mb-lg">
            <div>
                <h2 class="text-h1 font-h1 text-on-surface mb-xs">Instrument Categories</h2>
                <p class="text-body-lg font-body-lg text-secondary">Manage available instruments for the school</p>
            </div>
            <button id="add-instrument-btn" class="flex items-center gap-xs px-md py-base bg-primary text-on-primary rounded-lg font-label-md transition-all hover:bg-primary-container shadow-sm">
                <span class="material-symbols-outlined" style="font-variation-settings: 'wght' 600;" aria-hidden="true">add</span>
                Add Instrument
            </button>
        </div>

        <!-- Table Container -->
        <div class="bg-surface-container-lowest rounded-xl border border-outline-variant shadow-[0_2px_4px_rgba(0,0,0,0.04)] overflow-hidden">
            <!-- Table Header Actions -->
            <div class="px-md py-md flex justify-between items-center bg-surface-container-lowest border-b border-outline-variant">
                <h3 class="font-h3 text-h3 text-on-surface">Category List</h3>
            </div>
            <!-- Main Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-surface-container-low">
                        <tr>
                            <th class="px-md py-sm font-label-sm text-label-sm text-secondary uppercase tracking-wider">ID</th>
                            <th class="px-md py-sm font-label-sm text-label-sm text-secondary uppercase tracking-wider">Instrument Name</th>
                            <th class="px-md py-sm font-label-sm text-label-sm text-secondary uppercase tracking-wider">Description</th>
                            <th class="px-md py-sm font-label-sm text-label-sm text-secondary uppercase tracking-wider text-center">Number of Courses</th>
                            <th class="px-md py-sm font-label-sm text-label-sm text-secondary uppercase tracking-wider text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant" id="instrument-list">
                        <?php if (empty($instruments)): ?>
                            <tr>
                                <td colspan="5" class="px-md py-md text-center text-secondary">No instrument categories found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($instruments as $inst): ?>
                                <tr class="hover:bg-surface-container transition-colors group instrument-row">
                                    <td class="px-md py-md text-body-md font-medium text-secondary">#CAT-<?= $inst['id'] ?></td>
                                    <td class="px-md py-md">
                                        <div class="flex items-center gap-sm">
                                            <div class="w-8 h-8 rounded bg-primary-container/10 flex items-center justify-center text-primary font-bold">
                                                <?= htmlspecialchars(substr($inst['name'], 0, 1)) ?>
                                            </div>
                                            <span class="text-body-md font-semibold text-on-surface"><?= htmlspecialchars($inst['name']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-md py-md text-body-md text-secondary max-w-xs truncate"><?= htmlspecialchars($inst['description'] ?: 'No description provided.') ?></td>
                                    <td class="px-md py-md text-body-md text-on-surface text-center"><?= intval($inst['course_count']) ?></td>
                                    <td class="px-md py-md text-right space-x-base">
                                        <button onclick="editInstrument(<?= htmlspecialchars(json_encode($inst)) ?>)" class="p-2 text-secondary hover:text-primary hover:bg-surface-container-high rounded-lg"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">edit</span></button>
                                        <button onclick="deleteInstrument(<?= $inst['id'] ?>)" class="p-2 text-secondary hover:text-error hover:bg-error-container/30 rounded-lg"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">delete</span></button>
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
    <div class="relative bg-surface-container-lowest w-full max-w-xl max-h-[90vh] overflow-y-auto rounded-xl shadow-xl flex flex-col">
        <!-- Modal Header -->
        <div class="px-xl py-md border-b border-outline-variant flex justify-between items-center bg-surface-container-low">
            <h2 id="modal-title" class="text-h2 font-h2 text-on-surface">Add Instrument Category</h2>
            <button onclick="closeModal()" class="p-xs hover:bg-surface-container-high rounded-full transition-colors text-secondary" aria-label="Close modal">
                <span class="material-symbols-outlined" aria-hidden="true">close</span>
            </button>
        </div>
        <!-- Modal Body / Form -->
        <form id="instrument-form" class="p-xl space-y-md">
            <input type="hidden" id="form-action" name="action" value="add"/>
            <input type="hidden" id="form-id" name="id" value=""/>

            <div id="error-message" class="hidden bg-error-container/20 border border-error/30 text-error text-sm p-4 rounded-lg" aria-live="polite"></div>

            <div class="flex flex-col gap-xs">
                <label for="form-name" class="text-label-md font-label-md text-on-surface-variant">Instrument Category Name</label>
                <input id="form-name" name="name" required class="px-md py-sm rounded-lg border border-outline-variant focus:border-primary focus:ring-1 focus:ring-primary outline-none text-body-md bg-surface-bright" placeholder="e.g. Classical Piano, Jazz Flute" type="text" autocomplete="off"/>
            </div>
            <div class="flex flex-col gap-xs">
                <label for="form-description" class="text-label-md font-label-md text-on-surface-variant">Description</label>
                <textarea id="form-description" name="description" class="px-md py-sm rounded-lg border border-outline-variant focus:border-primary focus:ring-1 focus:ring-primary outline-none text-body-md bg-surface-bright resize-none" placeholder="Provide category overview…" rows="3"></textarea>
            </div>
        </form>
        <!-- Modal Footer -->
        <div class="px-xl py-md border-t border-outline-variant flex justify-end gap-sm bg-surface-container-low">
            <button onclick="closeModal()" class="px-md py-sm border border-outline text-secondary rounded-lg font-label-md text-label-md hover:bg-surface-container-high transition-all">Cancel</button>
            <button onclick="submitForm()" class="px-md py-sm bg-primary text-on-primary rounded-lg font-label-md text-label-md hover:bg-primary-container transition-all shadow-sm">Save Category</button>
        </div>
    </div>
</div>

<script>
    const modal = document.getElementById('modal-container');
    const form = document.getElementById('instrument-form');
    const formAction = document.getElementById('form-action');
    const formId = document.getElementById('form-id');
    const modalTitle = document.getElementById('modal-title');
    const errorDiv = document.getElementById('error-message');

    document.getElementById('add-instrument-btn').addEventListener('click', () => {
        form.reset();
        formAction.value = 'add';
        formId.value = '';
        modalTitle.innerText = 'Add Instrument Category';
        errorDiv.classList.add('hidden');
        modal.classList.remove('hidden');
    });

    function editInstrument(inst) {
        form.reset();
        formAction.value = 'edit';
        formId.value = inst.id;
        modalTitle.innerText = 'Edit Instrument Category';
        document.getElementById('form-name').value = inst.name;
        document.getElementById('form-description').value = inst.description || '';
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
            name: document.getElementById('form-name').value,
            description: document.getElementById('form-description').value
        };

        fetch('/admin/instruments.php', {
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

    function deleteInstrument(id) {
        if (confirm('Are you sure you want to delete this category? All related courses will lose their category tag.')) {
            fetch('/admin/instruments.php', {
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
    document.getElementById('search-input').addEventListener('input', (e) => {
        const query = e.target.value.toLowerCase();
        document.querySelectorAll('.instrument-row').forEach(row => {
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

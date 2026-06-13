<?php
// 11_Enrollment_Requests/index.php
// Dynamic Enrollment Requests review portal for Admins

require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/design-system.php';
require_once __DIR__ . '/../config/csrf.php';
requireRole('admin'); // Protect via role guard

$pdo = require_once __DIR__ . '/../config/db.php';

// Fetch all enrollment requests
try {
    $stmt = $pdo->query("
        SELECT e.id, e.status, e.rejection_reason, e.enrolled_at, e.reviewed_at,
               u_student.id as student_id, u_student.name as student_name, u_student.email as student_email,
               c.id as course_id, c.title as course_title, inst.name as instrument_name,
               u_reviewer.name as reviewer_name
        FROM enrollments e
        JOIN users u_student ON e.student_id = u_student.id
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN instruments inst ON c.instrument_id = inst.id
        LEFT JOIN users u_reviewer ON e.reviewed_by = u_reviewer.id
        ORDER BY e.enrolled_at DESC
    ");
    $enrollments = $stmt->fetchAll();

    // Stats calculations
    $pendingCount = 0;
    $approvedCount = 0;
    $rejectedCount = 0;
    foreach ($enrollments as $e) {
        if ($e['status'] === 'pending') $pendingCount++;
        elseif ($e['status'] === 'approved') $approvedCount++;
        elseif ($e['status'] === 'rejected') $rejectedCount++;
    }

} catch (Exception $e) {
    error_log('DB error: ' . $e->getMessage()); die('A database error occurred. Please try again later.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php lms_head('Enrollment Requests', 'admin'); ?>
</head>
<body class="bg-background text-on-background">
<?php lms_sidebar('admin', '/11_Enrollment_Requests/index.php'); ?>

<?php lms_topbar('admin', 'Enrollment Requests'); ?>

<!-- Main Content -->
<main id="lms-main-content" class="lms-main">
    <div class="p-lg max-w-container-max mx-auto">
        <!-- Page Header -->
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-md mb-lg">
            <div>
                <h2 class="text-h1 font-h1 text-on-surface">Enrollment Requests</h2>
                <p class="text-body-lg text-secondary mt-1">Review and manage student enrollment applications for the upcoming semester.</p>
            </div>
        </div>

        <!-- Summary Bento Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-md mb-lg">
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-md flex items-center gap-md">
                <div class="w-12 h-12 bg-amber-50 rounded-full flex items-center justify-center text-amber-600">
                    <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;" aria-hidden="true">pending_actions</span>
                </div>
                <div>
                    <p class="text-label-sm text-secondary uppercase">Waiting Review</p>
                    <p class="text-h2 font-h2 text-on-surface"><?= $pendingCount ?></p>
                </div>
            </div>
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-md flex items-center gap-md">
                <div class="w-12 h-12 bg-green-50 rounded-full flex items-center justify-center text-green-600">
                    <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;" aria-hidden="true">task_alt</span>
                </div>
                <div>
                    <p class="text-label-sm text-secondary uppercase">Approved</p>
                    <p class="text-h2 font-h2 text-on-surface"><?= $approvedCount ?></p>
                </div>
            </div>
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-md flex items-center gap-md">
                <div class="w-12 h-12 bg-red-50 rounded-full flex items-center justify-center text-red-600">
                    <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;" aria-hidden="true">error</span>
                </div>
                <div>
                    <p class="text-label-sm text-secondary uppercase">Rejected</p>
                    <p class="text-h2 font-h2 text-on-surface"><?= $rejectedCount ?></p>
                </div>
            </div>
        </div>

        <!-- Data Table Card -->
        <div class="bg-white border border-outline-variant rounded-xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface-container-low border-b border-outline-variant">
                            <th class="py-4 px-md font-label-sm text-label-sm text-secondary uppercase tracking-wider">Request ID</th>
                            <th class="py-4 px-md font-label-sm text-label-sm text-secondary uppercase tracking-wider">Student Name</th>
                            <th class="py-4 px-md font-label-sm text-label-sm text-secondary uppercase tracking-wider">Requested Course</th>
                            <th class="py-4 px-md font-label-sm text-label-sm text-secondary uppercase tracking-wider">Instrument</th>
                            <th class="py-4 px-md font-label-sm text-label-sm text-secondary uppercase tracking-wider">Request Date</th>
                            <th class="py-4 px-md font-label-sm text-label-sm text-secondary uppercase tracking-wider">Status</th>
                            <th class="py-4 px-md font-label-sm text-label-sm text-secondary uppercase tracking-wider text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant" id="requests-list">
                        <?php if (empty($enrollments)): ?>
                            <tr>
                                <td colspan="7" class="px-md py-md text-center text-secondary">No enrollment requests found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($enrollments as $e): ?>
                                <tr class="hover:bg-surface-container-low/50 transition-colors request-row">
                                    <td class="py-4 px-md font-body-md text-body-md text-on-surface font-medium">#REQ-<?= $e['id'] ?></td>
                                    <td class="py-4 px-md">
                                        <div class="flex items-center gap-sm">
                                            <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold text-xs">
                                                <?= htmlspecialchars(substr($e['student_name'], 0, 2)) ?>
                                            </div>
                                            <span class="font-body-md text-body-md text-on-surface"><?= htmlspecialchars($e['student_name']) ?></span>
                                        </div>
                                    </td>
                                    <td class="py-4 px-md font-body-md text-body-md text-secondary"><?= htmlspecialchars($e['course_title']) ?></td>
                                    <td class="py-4 px-md">
                                        <span class="px-2.5 py-0.5 rounded-full bg-secondary-container/30 text-on-secondary-container text-[11px] font-semibold border border-secondary-container"><?= htmlspecialchars($e['instrument_name'] ?: 'General') ?></span>
                                    </td>
                                    <td class="py-4 px-md font-body-md text-body-md text-secondary"><?= date('M j, Y', strtotime($e['enrolled_at'])) ?></td>
                                    <td class="py-4 px-md">
                                        <?php if ($e['status'] === 'approved'): ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-green-50 text-green-700 text-[11px] font-bold border border-green-200 uppercase">
                                                <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Approved
                                            </span>
                                        <?php elseif ($e['status'] === 'rejected'): ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-red-50 text-red-700 text-[11px] font-bold border border-red-200 uppercase">
                                                <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> Rejected
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-amber-50 text-amber-700 text-[11px] font-bold border border-amber-200 uppercase">
                                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Pending
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-md text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <?php if ($e['status'] === 'pending'): ?>
                                                <button onclick="reviewEnrollment(<?= $e['id'] ?>, 'approved')" class="p-1.5 text-outline hover:text-green-600 hover:bg-green-50 rounded-lg transition-all" title="Approve"><span class="material-symbols-outlined text-[20px]">check_circle</span></button>
                                                <button onclick="rejectEnrollmentModal(<?= $e['id'] ?>)" class="p-1.5 text-outline hover:text-error hover:bg-error-container/20 rounded-lg transition-all" title="Reject"><span class="material-symbols-outlined text-[20px]">cancel</span></button>
                                            <?php else: ?>
                                                <span class="text-xs text-secondary italic">Reviewed</span>
                                            <?php endif; ?>
                                        </div>
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

<!-- Modal Overlay for Rejection Reason -->
<div id="reject-modal" class="fixed inset-0 z-[100] flex items-center justify-center hidden">
    <!-- Backdrop -->
    <button class="absolute inset-0 bg-on-background/40 backdrop-blur-sm w-full h-full" onclick="closeRejectModal()" aria-label="Close modal"></button>
    <!-- Modal Container -->
    <div class="relative bg-surface-container-lowest w-full max-w-md max-h-[90vh] overflow-y-auto rounded-xl shadow-xl flex flex-col">
        <!-- Modal Header -->
        <div class="px-xl py-md border-b border-outline-variant flex justify-between items-center bg-surface-container-low">
            <h2 class="text-h2 font-h2 text-on-surface">Reject Enrollment Request</h2>
            <button onclick="closeRejectModal()" class="p-xs hover:bg-surface-container-high rounded-full transition-colors text-secondary" aria-label="Close modal">
                <span class="material-symbols-outlined" aria-hidden="true">close</span>
            </button>
        </div>
        <!-- Modal Body / Form -->
        <div class="p-xl space-y-md">
            <input type="hidden" id="reject-enrollment-id" value=""/>
            <div class="flex flex-col gap-xs">
                <label for="reject-reason" class="text-label-md font-label-md text-on-surface-variant">Rejection Reason</label>
                <textarea id="reject-reason" name="rejection_reason" required class="px-md py-sm rounded-lg border border-outline-variant focus:border-primary focus:ring-1 focus:ring-primary outline-none text-body-md bg-surface-bright resize-none" placeholder="Provide a reason for rejection…" rows="3"></textarea>
            </div>
        </div>
        <!-- Modal Footer -->
        <div class="px-xl py-md border-t border-outline-variant flex justify-end gap-sm bg-surface-container-low">
            <button onclick="closeRejectModal()" class="px-md py-sm border border-outline text-secondary rounded-lg font-label-md text-label-md hover:bg-surface-container-high transition-all">Cancel</button>
            <button onclick="submitRejection()" class="px-md py-sm bg-error text-white rounded-lg font-label-md text-label-md hover:bg-red-700 transition-all shadow-sm">Confirm Reject</button>
        </div>
    </div>
</div>

<script>
    function reviewEnrollment(enrollmentId, status, rejectionReason = '') {
        const data = {
            enrollment_id: enrollmentId,
            status: status,
            rejection_reason: rejectionReason
        };

        fetch(BASE_URL + '/admin/enrollments.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?= csrf_token() ?>' },
            body: JSON.stringify(data)
        })
        .then(res => res.json())
        .then(resData => {
            if (resData.success) {
                location.reload();
            } else {
                alert(resData.error || 'Failed to review request.');
            }
        })
        .catch(err => alert('Network error: ' + err.message));
    }

    function rejectEnrollmentModal(enrollmentId) {
        document.getElementById('reject-enrollment-id').value = enrollmentId;
        document.getElementById('reject-reason').value = '';
        document.getElementById('reject-modal').classList.remove('hidden');
    }

    function closeRejectModal() {
        document.getElementById('reject-modal').classList.add('hidden');
    }

    function submitRejection() {
        const enrollmentId = document.getElementById('reject-enrollment-id').value;
        const reason = document.getElementById('reject-reason').value.trim();

        if (reason === '') {
            alert('Rejection reason is required.');
            return;
        }

        reviewEnrollment(enrollmentId, 'rejected', reason);
    }
</script>
</body>
</html>

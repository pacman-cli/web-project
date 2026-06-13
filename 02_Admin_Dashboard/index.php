<?php
// 02_Admin_Dashboard/index.php — Lyra Academy Admin Dashboard
require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/design-system.php';
requireRole('admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php lms_head('Admin Dashboard', 'admin'); ?>
</head>
<body class="bg-background text-on-surface">

<?php lms_sidebar('admin', '/02_Admin_Dashboard/index.php'); ?>

<?php lms_topbar('admin', 'Admin Dashboard', 'Search students, courses, schedules…'); ?>

<!-- Main Content -->
<main id="lms-main-content" class="lms-main">
    <div class="page-content">

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="text-h1 text-on-surface mb-xs">Academy Dashboard</h1>
                <p class="text-body-lg text-outline">Welcome back, <?= htmlspecialchars($_SESSION['name']) ?>. Here's what's happening at Lyra today.</p>
            </div>
            <div class="flex gap-sm">
                <button class="btn btn-ghost" id="btn-export-report">
                    <span class="material-symbols-outlined" aria-hidden="true">download</span>
                    Export Report
                </button>
            </div>
        </div>

        <!-- Metric Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-md mb-lg">
            <div class="stat-card">
                <div class="stat-card-icon" style="background:rgba(0,61,155,.1)">
                    <span class="material-symbols-outlined icon-fill" style="color:#003d9b" aria-hidden="true">group</span>
                </div>
                <p class="stat-card-value" id="total-students-val" aria-live="polite">—</p>
                <p class="stat-card-label">Total Students</p>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon" style="background:rgba(26,107,60,.1)">
                    <span class="material-symbols-outlined icon-fill" style="color:#1a6b3c" aria-hidden="true">school</span>
                </div>
                <p class="stat-card-value" id="total-instructors-val" aria-live="polite">—</p>
                <p class="stat-card-label">Instructors</p>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon" style="background:rgba(59,67,88,.1)">
                    <span class="material-symbols-outlined icon-fill" style="color:#3b4358" aria-hidden="true">auto_stories</span>
                </div>
                <p class="stat-card-value" id="active-courses-val" aria-live="polite">—</p>
                <p class="stat-card-label">Active Courses</p>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon" style="background:rgba(186,26,26,.1)">
                    <span class="material-symbols-outlined icon-fill" style="color:#ba1a1a" aria-hidden="true">pending_actions</span>
                </div>
                <p class="stat-card-value" id="pending-enrollments-val" aria-live="polite">—</p>
                <p class="stat-card-label">Pending Enrollments</p>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-lg">

            <!-- Recent Enrollments Table -->
            <div class="lg:col-span-2">
                <section class="lms-card overflow-hidden">
                    <div class="flex items-center justify-between px-md py-md border-b border-outline-variant">
                        <h2 class="section-title mb-0">Recent Enrollment Requests</h2>
                        <a href="<?= BASE_URL ?>/11_Enrollment_Requests/index.php" class="text-label-md font-semibold hover:underline" style="color:#003d9b">View All →</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="lms-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Course</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody id="recent-enrollments-tbody" aria-live="polite">
                                <tr>
                                    <td colspan="4" class="text-center text-outline py-lg">
                                        <span class="material-symbols-outlined text-2xl mb-1 block" aria-hidden="true">hourglass_empty</span>
                                        Loading enrollment data…
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <!-- System Health sidebar -->
            <div class="space-y-md">
                <!-- System Health -->
                <section class="lms-card">
                    <div class="px-md py-md border-b border-outline-variant">
                        <h2 class="section-title mb-0">System Health</h2>
                    </div>
                    <div class="px-md py-md space-y-md">
                        <div class="flex justify-between items-center text-body-md">
                            <span class="text-outline">Database</span>
                            <span class="badge badge-success"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Online</span>
                        </div>
                        <div class="flex justify-between items-center text-body-md">
                            <span class="text-outline">RBAC Status</span>
                            <span class="badge badge-success">Enforced</span>
                        </div>
                        <div class="flex justify-between items-center text-body-md">
                            <span class="text-outline">Upload Engine</span>
                            <span class="badge badge-info">Secure (20 MB)</span>
                        </div>
                        <div class="flex justify-between items-center text-body-md">
                            <span class="text-outline">Chat System</span>
                            <span class="badge badge-success">Polling Active</span>
                        </div>
                    </div>
                </section>

                <!-- Quick Actions -->
                <section class="lms-card">
                    <div class="px-md py-md border-b border-outline-variant">
                        <h2 class="section-title mb-0">Quick Actions</h2>
                    </div>
                    <div class="px-md py-md space-y-sm">
                        <a href="<?= BASE_URL ?>/01_Instructor_Management/index.php" class="btn btn-secondary w-full justify-start gap-sm">
                            <span class="material-symbols-outlined" aria-hidden="true">person_add</span> Add Instructor
                        </a>
                        <a href="<?= BASE_URL ?>/05_Course_Management/index.php" class="btn btn-secondary w-full justify-start gap-sm">
                            <span class="material-symbols-outlined" aria-hidden="true">add_circle</span> Create Course
                        </a>
                        <a href="<?= BASE_URL ?>/11_Enrollment_Requests/index.php" class="btn btn-secondary w-full justify-start gap-sm">
                            <span class="material-symbols-outlined" aria-hidden="true">checklist</span> Review Enrollments
                        </a>
                        <a href="<?= BASE_URL ?>/15_Reports_Analytics/index.php" class="btn btn-ghost w-full justify-start gap-sm">
                            <span class="material-symbols-outlined" aria-hidden="true">analytics</span> View Reports
                        </a>
                    </div>
                </section>
            </div>
        </div>
    </div>
</main>

<script>
function escapeHTML(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
document.addEventListener("DOMContentLoaded", () => {
    // Fetch metrics
    fetch(BASE_URL + "/admin/reports.php")
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            const m = data.reports.general_metrics;
            document.getElementById("total-students-val").textContent     = m.total_students;
            document.getElementById("total-instructors-val").textContent   = m.total_instructors;
            document.getElementById("active-courses-val").textContent      = m.total_courses;
            document.getElementById("pending-enrollments-val").textContent = m.pending_enrollments;
        })
        .catch(err => console.error("Metrics error:", err));

    // Fetch recent enrollments
    fetch(BASE_URL + "/admin/enrollments.php")
        .then(r => r.json())
        .then(data => {
            const tbody = document.getElementById("recent-enrollments-tbody");
            if (!data.success || data.data.length === 0) {
                tbody.innerHTML = `<tr><td colspan="4" class="text-center text-outline py-lg">No enrollment requests yet.</td></tr>`;
                return;
            }
            const badgeMap = { pending: 'badge-warning', approved: 'badge-success', rejected: 'badge-danger' };
            tbody.innerHTML = data.data.slice(0, 5).map(req => {
                const badge = badgeMap[req.status] || 'badge-neutral';
                const date  = new Date(req.enrolled_at).toLocaleDateString(undefined, { month:'short', day:'numeric', year:'numeric' });
                return `<tr>
                    <td class="font-semibold text-on-surface">${escapeHTML(req.student_name)}</td>
                    <td class="text-secondary">${escapeHTML(req.course_title)}</td>
                    <td><span class="badge ${badge}">${escapeHTML(req.status)}</span></td>
                    <td class="text-outline">${escapeHTML(date)}</td>
                </tr>`;
            }).join('');
        })
        .catch(err => console.error("Enrollments error:", err));
});
</script>
</body>
</html>

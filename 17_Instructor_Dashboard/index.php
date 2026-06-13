<?php
// 17_Instructor_Dashboard/index.php — Lyra Academy Instructor Dashboard
require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/design-system.php';
requireRole('instructor');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php lms_head('Instructor Dashboard', 'instructor'); ?>
</head>
<body class="bg-background text-on-surface">

<?php lms_sidebar('instructor', '/17_Instructor_Dashboard/index.php'); ?>

<?php lms_topbar('instructor', 'Instructor Dashboard', 'Search students, courses, materials…'); ?>

<!-- Main Content -->
<main id="lms-main-content" class="lms-main">
    <div class="page-content">

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="text-h1 text-on-surface mb-xs">Instructor Dashboard</h1>
                <p class="text-body-lg text-outline">Welcome back, <?= htmlspecialchars($_SESSION['name']) ?>. Here's an overview of your classes and students.</p>
            </div>
        </div>

        <!-- Metric Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-md mb-lg">
            <div class="stat-card">
                <div class="stat-card-icon" style="background:rgba(26,107,60,.1)">
                    <span class="material-symbols-outlined icon-fill" style="color:#1a6b3c" aria-hidden="true">library_music</span>
                </div>
                <p class="stat-card-value" id="assigned-courses-val" aria-live="polite">—</p>
                <p class="stat-card-label">Assigned Courses</p>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon" style="background:rgba(0,61,155,.1)">
                    <span class="material-symbols-outlined icon-fill" style="color:#003d9b" aria-hidden="true">group</span>
                </div>
                <p class="stat-card-value" id="active-students-val" aria-live="polite">—</p>
                <p class="stat-card-label">Active Students</p>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon" style="background:rgba(59,67,88,.1)">
                    <span class="material-symbols-outlined icon-fill" style="color:#3b4358" aria-hidden="true">calendar_month</span>
                </div>
                <p class="stat-card-value" id="active-schedules-val" aria-live="polite">—</p>
                <p class="stat-card-label">Active Schedules</p>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon" style="background:rgba(26,107,60,.08)">
                    <span class="material-symbols-outlined icon-fill" style="color:#1a6b3c" aria-hidden="true">verified</span>
                </div>
                <p class="stat-card-value" style="color:#1a6b3c">Active</p>
                <p class="stat-card-label">Session Status</p>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-lg">

            <!-- Assigned Courses -->
            <section class="lg:col-span-2">
                <div class="flex items-center justify-between mb-md">
                    <h2 class="section-title mb-0">Your Assigned Courses</h2>
                    <a href="/19_My_Courses/index.php" class="text-label-md font-semibold hover:underline" style="color:#1a6b3c">View All →</a>
                </div>
                <div id="instructor-courses-container" class="grid grid-cols-1 md:grid-cols-2 gap-md" aria-live="polite">
                    <div class="col-span-2 text-center py-lg text-outline">
                        <span class="material-symbols-outlined text-3xl mb-2 block" aria-hidden="true">hourglass_empty</span>
                        Loading courses…
                    </div>
                </div>
            </section>

            <!-- Right column -->
            <div class="space-y-md">
                <!-- Quick Actions -->
                <section class="lms-card">
                    <div class="px-md py-md border-b border-outline-variant">
                        <h2 class="section-title mb-0">Quick Actions</h2>
                    </div>
                    <div class="px-md py-md space-y-sm">
                        <a href="/16_Lesson_Materials/index.php" class="btn btn-secondary w-full justify-start gap-sm" style="background:rgba(26,107,60,.1);color:#1a6b3c">
                            <span class="material-symbols-outlined" aria-hidden="true">upload_file</span> Upload Material
                        </a>
                        <a href="/18_Class_Schedules/index.php" class="btn btn-secondary w-full justify-start gap-sm" style="background:rgba(26,107,60,.1);color:#1a6b3c">
                            <span class="material-symbols-outlined" aria-hidden="true">add_circle</span> Add Schedule
                        </a>
                        <a href="/22_Attendance/index.php" class="btn btn-secondary w-full justify-start gap-sm" style="background:rgba(26,107,60,.1);color:#1a6b3c">
                            <span class="material-symbols-outlined" aria-hidden="true">how_to_reg</span> Mark Attendance
                        </a>
                        <a href="/23_Assignments/index.php" class="btn btn-ghost w-full justify-start gap-sm">
                            <span class="material-symbols-outlined" aria-hidden="true">assignment_add</span> Create Assignment
                        </a>
                        <a href="/25_Recording_Reviews/index.php" class="btn btn-ghost w-full justify-start gap-sm">
                            <span class="material-symbols-outlined" aria-hidden="true">rate_review</span> Review Submissions
                        </a>
                    </div>
                </section>

                <!-- Session Info -->
                <section class="lms-card">
                    <div class="px-md py-md border-b border-outline-variant">
                        <h2 class="section-title mb-0">Session Info</h2>
                    </div>
                    <div class="px-md py-md space-y-md text-body-md">
                        <div class="flex justify-between">
                            <span class="text-outline">Role</span>
                            <span class="badge badge-success">Instructor</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-outline">Access Policy</span>
                            <span class="badge badge-info">Verified</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-outline">Upload Engine</span>
                            <span class="badge badge-neutral">Secure</span>
                        </div>
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
    // Fetch assigned courses
    fetch("/instructor/courses.php?action=list_courses")
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            const container = document.getElementById("instructor-courses-container");
            document.getElementById("assigned-courses-val").textContent = data.courses.length;

            if (data.courses.length === 0) {
                container.innerHTML = `<div class="col-span-2 text-center py-lg text-outline">No courses currently assigned to you.</div>`;
                return;
            }

            container.innerHTML = '';
            let totalStudents = 0;

            data.courses.forEach(course => {
                const card = document.createElement("div");
                card.className = "lms-card overflow-hidden";
                card.innerHTML = `
                    <div class="h-28 flex items-center justify-center text-white relative" style="background:#1a6b3c">
                        <span class="font-bold text-base px-4 text-center">${escapeHTML(course.title)}</span>
                        <span class="absolute top-2 right-2 badge badge-neutral text-xs">${escapeHTML(course.difficulty || '')}</span>
                    </div>
                    <div class="px-md py-md">
                        <p class="text-body-md text-outline line-clamp-2 mb-sm">${escapeHTML(course.description || 'No description available.')}</p>
                        <div class="flex items-center gap-xs text-outline">
                            <span class="material-symbols-outlined" style="font-size:16px" aria-hidden="true">group</span>
                            <span class="text-label-md" id="studs-course-${course.id}" aria-live="polite">Loading…</span>
                        </div>
                    </div>
                `;
                container.appendChild(card);

                fetch(`/instructor/courses.php?action=list_students&course_id=${course.id}`)
                    .then(r => r.json())
                    .then(sd => {
                        if (sd.success) {
                            const count = sd.students.length;
                            const el = document.getElementById(`studs-course-${course.id}`);
                            if (el) el.textContent = `${count} Student${count !== 1 ? 's' : ''}`;
                            totalStudents += count;
                            document.getElementById("active-students-val").textContent = totalStudents;
                        }
                    });
            });
        })
        .catch(err => console.error("Courses error:", err));

    // Fetch schedules count
    fetch("/instructor/schedules.php")
        .then(r => r.json())
        .then(data => {
            if (data.success) document.getElementById("active-schedules-val").textContent = data.data.length;
        });
});
</script>
</body>
</html>

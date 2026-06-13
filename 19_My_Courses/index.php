<?php
// 19_My_Courses/index.php
// Instructor portal to view assigned courses

require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/design-system.php';
requireRole('instructor');

$pdo = require_once __DIR__ . '/../config/db.php';
$instructorId = $_SESSION['user_id'];
$instructorName = $_SESSION['name'];

try {
    // Fetch assigned courses
    $stmt = $pdo->prepare("
        SELECT c.id, c.title, c.description, c.difficulty,
               (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status = 'approved') as student_count,
               i.name as instrument_name
        FROM courses c
        JOIN instructor_assignments ia ON c.id = ia.course_id
        LEFT JOIN instruments i ON c.instrument_id = i.id
        WHERE ia.instructor_id = :instructor_id
        ORDER BY c.title ASC
    ");
    $stmt->execute(['instructor_id' => $instructorId]);
    $courses = $stmt->fetchAll();

} catch (Exception $e) {
    error_log('DB error: ' . $e->getMessage()); die('A database error occurred. Please try again later.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php lms_head('My Courses', 'instructor'); ?>
</head>
<body class="bg-background text-on-surface">

<?php lms_sidebar('instructor', '/19_My_Courses/index.php'); ?>

<?php lms_topbar('instructor', 'My Courses'); ?>

<!-- Main Content Area -->
<main id="lms-main-content" class="lms-main">
    <div class="max-w-container-max mx-auto p-lg">
        <!-- Page Header -->
        <div class="flex justify-between items-end mb-lg">
            <div>
                <h2 class="font-h1 text-h1 text-on-surface mb-xs">My Courses</h2>
                <p class="font-body-lg text-body-lg text-on-surface-variant">Manage assigned teaching courses and track student progress.</p>
            </div>
        </div>

        <!-- Course Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-md">
            <?php if (empty($courses)): ?>
                <div class="col-span-full bg-surface-container-lowest border border-outline-variant rounded-lg p-lg text-center">
                    <p class="text-on-surface-variant text-body-lg">You are not assigned to teach any courses yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($courses as $c): ?>
                    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl card-shadow overflow-hidden flex flex-col group">
                        <div class="h-40 relative bg-primary/10 flex items-center justify-center">
                            <span class="material-symbols-outlined text-[64px] text-primary/30" aria-hidden="true">piano</span>
                            <div class="absolute top-sm right-sm bg-white/95 px-3 py-1 rounded-full flex items-center shadow-sm text-xs font-semibold text-primary">
                                <?= htmlspecialchars($c['instrument_name'] ?: 'General') ?>
                            </div>
                        </div>
                        <div class="p-md flex-1 flex flex-col">
                            <div class="flex justify-between items-start mb-base">
                                <h3 class="font-h3 text-h3 text-on-surface font-semibold"><?= htmlspecialchars($c['title']) ?></h3>
                                <span class="px-2 py-0.5 bg-tertiary-fixed text-on-tertiary-fixed-variant rounded font-label-sm text-label-sm uppercase">
                                    <?= htmlspecialchars($c['difficulty']) ?>
                                </span>
                            </div>
                            <div class="grid grid-cols-2 gap-sm mb-md mt-sm">
                                <div class="flex items-center text-on-surface-variant">
                                    <span class="material-symbols-outlined text-[18px] mr-2" aria-hidden="true">group</span>
                                    <span class="font-label-md text-label-md"><?= intval($c['student_count']) ?> Students</span>
                                </div>
                            </div>
                            <a href="/27_Course_Students/index.php?course_id=<?= $c['id'] ?>" class="mt-auto w-full py-2.5 bg-primary text-on-primary rounded-lg font-label-md text-label-md hover:bg-primary-container transition-all flex justify-center items-center group/btn">
                                Open Course Students
                                <span class="material-symbols-outlined ml-2 text-[18px] group-hover/btn:translate-x-1 transition-transform" aria-hidden="true">arrow_forward</span>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

</body>
</html>

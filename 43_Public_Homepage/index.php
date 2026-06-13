<?php
// 43_Public_Homepage/index.php
// Dynamic public homepage

session_start();

$pdo = require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/design-system.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userName = $isLoggedIn ? $_SESSION['name'] : '';
$userRole = $isLoggedIn ? $_SESSION['role'] : 'guest';

try {
    // Fetch top 3 featured published courses
    $stmt = $pdo->query("
        SELECT c.id as course_id, c.title, c.description, c.difficulty, c.price, i.name as instrument_name, u.name as instructor_name
        FROM courses c
        LEFT JOIN instruments i ON c.instrument_id = i.id
        LEFT JOIN instructor_assignments ia ON c.id = ia.course_id
        LEFT JOIN users u ON ia.instructor_id = u.id
        WHERE c.status = 'published'
        ORDER BY c.id DESC
        LIMIT 3
    ");
    $featuredCourses = $stmt->fetchAll();
} catch (Exception $e) {
    $featuredCourses = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php lms_head('Welcome to Lyra Academy', 'guest'); ?>
</head>
<body class="bg-background text-on-surface">
<?php lms_public_navbar('/43_Public_Homepage/index.php'); ?>

<main id="lms-main-content" class="lms-main lms-page--full">
    <!-- Hero Section -->
    <section class="relative min-h-[800px] flex items-center overflow-hidden hero-gradient">
        <div class="absolute inset-0 music-pattern opacity-50"></div>
        <div class="container mx-auto px-md max-w-container-max relative z-10 grid grid-cols-1 md:grid-cols-2 gap-lg items-center">
            <div class="space-y-md">
                <div class="inline-flex items-center gap-xs px-sm py-1 rounded-full bg-secondary-container text-on-secondary-container">
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">new_releases</span>
                    <span class="font-label-sm text-label-sm uppercase tracking-wider">Semester Enrollment Open</span>
                </div>
                <h1 class="font-h1 text-[48px] md:text-[64px] leading-tight text-on-surface max-w-[600px]">
                    Master the Art of <span class="text-primary">Music</span> with World-Class Instructors
                </h1>
                <p class="font-body-lg text-body-lg text-secondary max-w-[500px]">
                    Experience institutional reliability blended with artistic precision. From foundational theory to advanced performance masterclasses.
                </p>
                <div class="flex flex-wrap gap-md pt-base">
                    <a class="px-xl py-md rounded-lg font-h3 text-h3 bg-primary text-on-primary shadow-lg hover:shadow-xl transition-all active:scale-95 text-center" href="/42_Public_Course_Catalog/index.php">Browse Courses</a>
                    <?php if (!$isLoggedIn): ?>
                        <a class="px-xl py-md rounded-lg font-h3 text-h3 border border-primary text-primary hover:bg-primary-fixed transition-colors active:scale-95 text-center" href="/auth/register.php">Join Now</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="hidden md:block relative">
                <div class="relative z-10 rounded-xl overflow-hidden shadow-2xl border border-outline-variant">
                    <img alt="Grand Piano in Academy Studio" class="w-full h-auto object-cover aspect-[4/3]" width="1200" height="600" src="https://lh3.googleusercontent.com/aida-public/AB6AXuC7u5BCpUdUpSNdAfa8551yzUWQdG1jIqCFHMhh1l9PZOzy1a7tV1P_9uxYBjmh2CNAYwmgJaKTrRWgIDIkOEq7Uwihhy9N9Hc_EeL2Jqpsb2hi87HhZS4NQc3SJoF1wDRNeVjDVaG3Q9yBos3NH9xo29PIzUlbPpodeOBnjUByWGty0bvoHet_1yMOMnNmbuvmHWsC-UgFkWDWvH2PiBerqgE_a3Tk8ObFpjFwh5wumMO-LvEnJ_Nab3Sj-sMBM_EFX0urPEdFU9g"/>
                </div>
            </div>
        </div>
    </section>

    <!-- Key Features Section -->
    <section class="py-xl bg-surface">
        <div class="container mx-auto px-md max-w-container-max">
            <div class="text-center mb-lg space-y-xs">
                <h2 class="font-h2 text-h2 text-on-surface">Precision in Education</h2>
                <p class="font-body-md text-body-md text-secondary">The tools you need to reach professional excellence</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-md">
                <div class="p-lg bg-surface-container-lowest rounded-xl border border-outline-variant hover:shadow-md transition-shadow group">
                    <div class="w-lg h-lg rounded-lg bg-primary-fixed flex items-center justify-center mb-md group-hover:bg-primary transition-colors">
                        <span aria-hidden="true" class="material-symbols-outlined text-primary group-hover:text-on-primary">school</span>
                    </div>
                    <h3 class="font-h3 text-h3 text-on-surface mb-xs">Expert Instructors</h3>
                    <p class="font-body-md text-body-md text-secondary">Learn from concert soloists and acclaimed theory scholars from top global conservatories.</p>
                </div>
                <div class="p-lg bg-surface-container-lowest rounded-xl border border-outline-variant hover:shadow-md transition-shadow group">
                    <div class="w-lg h-lg rounded-lg bg-primary-fixed flex items-center justify-center mb-md group-hover:bg-primary transition-colors">
                        <span aria-hidden="true" class="material-symbols-outlined text-primary group-hover:text-on-primary">account_tree</span>
                    </div>
                    <h3 class="font-h3 text-h3 text-on-surface mb-xs">Structured Learning</h3>
                    <p class="font-body-md text-body-md text-secondary">A rigorous curriculum designed to take you from foundational basics to virtuoso status.</p>
                </div>
                <div class="p-lg bg-surface-container-lowest rounded-xl border border-outline-variant hover:shadow-md transition-shadow group">
                    <div class="w-lg h-lg rounded-lg bg-primary-fixed flex items-center justify-center mb-md group-hover:bg-primary transition-colors">
                        <span aria-hidden="true" class="material-symbols-outlined text-primary group-hover:text-on-primary">monitoring</span>
                    </div>
                    <h3 class="font-h3 text-h3 text-on-surface mb-xs">Progress Tracking</h3>
                    <p class="font-body-md text-body-md text-secondary">Detailed analytics on your practice hours, technique mastery, and theoretical understanding.</p>
                </div>
                <div class="p-lg bg-surface-container-lowest rounded-xl border border-outline-variant hover:shadow-md transition-shadow group">
                    <div class="w-lg h-lg rounded-lg bg-primary-fixed flex items-center justify-center mb-md group-hover:bg-primary transition-colors">
                        <span aria-hidden="true" class="material-symbols-outlined text-primary group-hover:text-on-primary">verified</span>
                    </div>
                    <h3 class="font-h3 text-h3 text-on-surface mb-xs">Professional Certificates</h3>
                    <p class="font-body-md text-body-md text-secondary">Earn accredited certification upon completion to bolster your professional portfolio.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Courses Section -->
    <section class="py-xl bg-surface-container-low">
        <div class="container mx-auto px-md max-w-container-max">
            <div class="flex flex-col md:flex-row md:items-end justify-between mb-lg gap-md">
                <div class="space-y-xs">
                    <h2 class="font-h2 text-h2 text-on-surface">Featured Masterclasses</h2>
                    <p class="font-body-md text-body-md text-secondary">Our most prestigious courses currently open for enrollment.</p>
                </div>
                <a href="/42_Public_Course_Catalog/index.php" class="flex items-center gap-xs font-label-md text-label-md text-primary hover:underline">
                    View All Courses <span aria-hidden="true" class="material-symbols-outlined text-sm">arrow_forward</span>
                </a>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-md">
                <?php if (empty($featuredCourses)): ?>
                    <p class="col-span-full text-center text-on-surface-variant">No featured courses available.</p>
                <?php else: ?>
                    <?php foreach ($featuredCourses as $c): ?>
                        <div class="bg-surface-container-lowest rounded-xl overflow-hidden border border-outline-variant hover:shadow-lg transition-all flex flex-col h-full">
                            <div class="relative aspect-[16/9]">
                                <img alt="<?= htmlspecialchars($c['title']) ?>" class="w-full h-full object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuB7nNZJflLrdQZ7eFWZQJSGiuex4Vhyu4rBOnx1lv2-fMCHZPsFv162XrI7RgkyqUztLCiS6ntDZh1KkRj_DEMrqhQN6i9HURwT6OyDApK9dpPxuXOf3JAA-BDNCv0ZQAeilqM-WQmTAFg1dWYWedFUAVUMb4twEfhDp_N4FF64XdplGtVQWVgwSEf1awaK9Y151MgMrinH37sgILwNnGEP484Pv7A9D44FXVdLwcWjfbbw4S-oEB5JZKFx-LKAdXrFQqgsUQrdgf4"/>
                                <div class="absolute top-sm right-sm bg-primary text-on-primary px-sm py-xs rounded font-label-sm text-label-sm uppercase font-bold"><?= htmlspecialchars($c['instrument_name'] ?? 'General') ?></div>
                            </div>
                            <div class="p-md flex flex-col flex-grow space-y-sm">
                                <div class="flex justify-between items-start">
                                    <h4 class="font-h3 text-h3 text-on-surface"><?= htmlspecialchars($c['title']) ?></h4>
                                    <span class="font-bold text-primary">$<?= number_format($c['price'], 2) ?></span>
                                </div>
                                <p class="font-body-md text-body-md text-secondary flex-grow line-clamp-2"><?= htmlspecialchars($c['description'] ?: 'No description provided.') ?></p>
                                <div class="pt-sm border-t border-outline-variant flex items-center justify-between">
                                    <div class="flex items-center gap-xs">
                                        <span aria-hidden="true" class="material-symbols-outlined text-primary text-[18px]">person</span>
                                        <span class="font-label-md text-label-md text-on-surface-variant"><?= htmlspecialchars($c['instructor_name'] ?? 'TBD') ?></span>
                                    </div>
                                    <a href="/45_Public_Course_Detail/index.php?course_id=<?= $c['course_id'] ?>" class="text-primary hover:underline font-bold text-xs">Details</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<footer class="w-full py-lg px-md bg-surface-container border-t border-outline-variant">
    <div class="max-w-container-max mx-auto text-center">
        <p class="font-body-md text-body-md text-secondary">
            © 2026 Lyra Academy. Artistic precision in every note.
        </p>
    </div>
</footer>
</body>
</html>

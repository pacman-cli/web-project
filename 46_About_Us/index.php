<?php
session_start();
$pdo = require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/design-system.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userName = $isLoggedIn ? $_SESSION['name'] : '';
$userRole = $isLoggedIn ? $_SESSION['role'] : 'guest';

// Fetch instructor count for stats
$instructorCount = 0;
$studentCount = 0;
$courseCount = 0;
try {
    $instructorCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'instructor'")->fetchColumn();
    $studentCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
    $courseCount = $pdo->query("SELECT COUNT(*) FROM courses WHERE status = 'published'")->fetchColumn();
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php lms_head('About Us', 'guest'); ?>
</head>
<body class="bg-background text-on-surface">

<?php lms_public_navbar('/46_About_Us/index.php'); ?>

<main id="lms-main-content" class="max-w-4xl mx-auto px-lg py-xl">
    <section class="text-center mb-xl">
        <h1 class="font-h1 text-h1 text-on-surface mb-md">About Lyra Academy</h1>
        <p class="font-body-lg text-body-lg text-on-surface-variant max-w-2xl mx-auto leading-relaxed">
            Founded in 2020, Lyra Academy is a modern music school dedicated to making quality music education accessible to everyone. 
            We combine traditional teaching methods with modern technology to create a unique learning experience.
        </p>
    </section>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-lg mb-xl">
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-lg text-center">
            <span aria-hidden="true" class="material-symbols-outlined text-4xl text-primary mb-sm">school</span>
            <p class="font-h2 text-h2 text-on-surface"><?= $instructorCount ?></p>
            <p class="text-on-surface-variant font-body-md">Expert Instructors</p>
        </div>
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-lg text-center">
            <span aria-hidden="true" class="material-symbols-outlined text-4xl text-primary mb-sm">groups</span>
            <p class="font-h2 text-h2 text-on-surface"><?= $studentCount ?></p>
            <p class="text-on-surface-variant font-body-md">Active Students</p>
        </div>
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-lg text-center">
            <span aria-hidden="true" class="material-symbols-outlined text-4xl text-primary mb-sm">library_music</span>
            <p class="font-h2 text-h2 text-on-surface"><?= $courseCount ?></p>
            <p class="text-on-surface-variant font-body-md">Courses Offered</p>
        </div>
    </div>

    <!-- Mission -->
    <section class="mb-xl">
        <h2 class="font-h2 text-h2 text-on-surface mb-md">Our Mission</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-lg">
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-lg">
                <span aria-hidden="true" class="material-symbols-outlined text-3xl text-primary mb-sm">music_note</span>
                <h3 class="font-h3 text-h3 text-on-surface mb-xs">Quality Education</h3>
                <p class="text-on-surface-variant">Structured curriculum designed by professional musicians to ensure comprehensive musical development.</p>
            </div>
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-lg">
                <span aria-hidden="true" class="material-symbols-outlined text-3xl text-primary mb-sm">groups</span>
                <h3 class="font-h3 text-h3 text-on-surface mb-xs">One-on-One Attention</h3>
                <p class="text-on-surface-variant">Small class sizes and personal mentoring ensure every student gets the attention they deserve.</p>
            </div>
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-lg">
                <span aria-hidden="true" class="material-symbols-outlined text-3xl text-primary mb-sm">schedule</span>
                <h3 class="font-h3 text-h3 text-on-surface mb-xs">Flexible Learning</h3>
                <p class="text-on-surface-variant">Online and in-person options with scheduling that fits your busy lifestyle.</p>
            </div>
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-lg">
                <span aria-hidden="true" class="material-symbols-outlined text-3xl text-primary mb-sm">workspace_premium</span>
                <h3 class="font-h3 text-h3 text-on-surface mb-xs">Recognized Certification</h3>
                <p class="text-on-surface-variant">Earn recognized certificates upon course completion to showcase your achievements.</p>
            </div>
        </div>
    </section>

    <section class="text-center">
        <h2 class="font-h2 text-h2 text-on-surface mb-md">Ready to Start Your Musical Journey?</h2>
        <a href="<?= BASE_URL ?>/auth/register.php" class="inline-block bg-primary text-on-primary px-lg py-sm rounded-lg font-semibold hover:opacity-90 transition-opacity">Join Lyra Academy Today</a>
    </section>
</main>

</body>
</html>

<?php
session_start();
$pdo = require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/design-system.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userName = $isLoggedIn ? $_SESSION['name'] : '';
$userRole = $isLoggedIn ? $_SESSION['role'] : 'guest';

$instruments = [];
try {
    $stmt = $pdo->query("
        SELECT i.*, 
               (SELECT COUNT(*) FROM courses WHERE instrument_id = i.id AND status = 'published') as course_count
        FROM instruments i
        ORDER BY i.name ASC
    ");
    $instruments = $stmt->fetchAll();
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php lms_head('Instruments', 'guest'); ?>
</head>
<body class="bg-background text-on-surface">

<?php lms_public_navbar('/44_Instrument_Categories/index.php'); ?>

<main id="lms-main-content" class="max-w-5xl mx-auto px-lg py-xl">
    <section class="text-center mb-xl">
        <h1 class="font-h1 text-h1 text-on-surface mb-md">Our Instruments</h1>
        <p class="font-body-lg text-body-lg text-on-surface-variant max-w-2xl mx-auto">
            Explore the wide range of instruments we offer. From classical to contemporary, find your perfect match.
        </p>
    </section>

    <?php if (empty($instruments)): ?>
        <div class="text-center text-on-surface-variant py-xl">
            <span aria-hidden="true" class="material-symbols-outlined text-5xl mb-sm">piano</span>
            <p>No instruments available yet. Check back soon!</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-lg">
            <?php foreach ($instruments as $inst): ?>
                <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-lg hover:shadow-md transition-shadow">
                    <div class="w-14 h-14 rounded-xl bg-primary/10 flex items-center justify-center mb-md">
                        <span aria-hidden="true" class="material-symbols-outlined text-3xl text-primary">music_note</span>
                    </div>
                    <h3 class="font-h3 text-h3 text-on-surface mb-xs"><?= htmlspecialchars($inst['name']) ?></h3>
                    <p class="text-body-md text-on-surface-variant mb-md"><?= htmlspecialchars($inst['description'] ?? 'Learn to play this beautiful instrument.') ?></p>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-outline"><?= intval($inst['course_count']) ?> courses available</span>
                        <a href="/42_Public_Course_Catalog/index.php?instrument_id=<?= $inst['id'] ?>" class="text-primary font-semibold text-sm hover:underline">Browse Courses →</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
</body>
</html>

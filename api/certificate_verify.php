<?php
// api/certificate_verify.php
// Public certificate verification page — no auth required

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/design-system.php';

$hash = trim($_GET['hash'] ?? '');
$valid = false;
$cert = null;
$error = '';

if (empty($hash)) {
    $error = 'No certificate hash provided.';
} elseif (strlen($hash) !== 64 || !ctype_xdigit($hash)) {
    $error = 'Invalid certificate hash format.';
} else {
    try {
        $stmt = $pdo->prepare("
            SELECT c.id, c.issued_at, c.file_path,
                   u.name AS student_name,
                   co.title AS course_title,
                   co.difficulty AS course_difficulty
            FROM certificates c
            JOIN users u ON c.student_id = u.id
            JOIN courses co ON c.course_id = co.id
            WHERE c.certificate_hash = :hash
        ");
        $stmt->execute(['hash' => $hash]);
        $cert = $stmt->fetch();

        if ($cert) {
            $valid = true;
            // Get instructor name
            $instStmt = $pdo->prepare("
                SELECT u.name FROM users u
                JOIN instructor_assignments ia ON u.id = ia.instructor_id
                JOIN courses co ON ia.course_id = co.id
                JOIN certificates c ON c.course_id = co.id
                WHERE c.certificate_hash = :hash LIMIT 1
            ");
            $instStmt->execute(['hash' => $hash]);
            $cert['instructor_name'] = $instStmt->fetchColumn() ?: 'Instructor';
        } else {
            $error = 'Certificate not found. This certificate hash does not match any issued certificate.';
        }
    } catch (Exception $e) {
        error_log('Certificate verify error: ' . $e->getMessage());
        $error = 'A system error occurred. Please try again later.';
    }
}

$shortId = $valid ? strtoupper(substr($hash, 0, 16)) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php lms_head('Certificate Verification', 'guest'); ?>
</head>
<body class="min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-lg">
        <!-- Logo / Brand -->
        <div class="text-center mb-8">
            <a href="<?= BASE_URL ?>/43_Public_Homepage/index.php" class="inline-flex items-center gap-sm">
                <div class="w-10 h-10 rounded-lg flex items-center justify-center text-white" style="background:#5b4800">
                    <span class="material-symbols-outlined icon-fill text-xl">public</span>
                </div>
                <span class="text-h2 font-bold text-on-surface">Lyra Academy</span>
            </a>
            <p class="text-body-md text-on-surface-variant mt-2">Certificate Verification</p>
        </div>

        <?php if ($valid): ?>
            <!-- Valid Certificate -->
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant shadow-card-lg overflow-hidden">
                <!-- Header -->
                <div class="bg-green-50 border-b border-green-200 p-md flex items-center gap-sm">
                    <span class="material-symbols-outlined text-green-600 text-[28px]" aria-hidden="true">verified</span>
                    <div>
                        <h1 class="text-h3 font-bold text-green-900">Certificate Verified</h1>
                        <p class="text-label-md text-green-700">This certificate is authentic and valid.</p>
                    </div>
                </div>

                <!-- Details -->
                <div class="p-lg space-y-md">
                    <div class="grid grid-cols-2 gap-md">
                        <div>
                            <p class="text-label-sm text-on-surface-variant uppercase tracking-wider mb-xs">Student</p>
                            <p class="text-body-lg font-semibold text-on-surface"><?= htmlspecialchars($cert['student_name']) ?></p>
                        </div>
                        <div>
                            <p class="text-label-sm text-on-surface-variant uppercase tracking-wider mb-xs">Course</p>
                            <p class="text-body-lg font-semibold text-on-surface"><?= htmlspecialchars($cert['course_title']) ?></p>
                        </div>
                        <div>
                            <p class="text-label-sm text-on-surface-variant uppercase tracking-wider mb-xs">Instructor</p>
                            <p class="text-body-md text-on-surface"><?= htmlspecialchars($cert['instructor_name']) ?></p>
                        </div>
                        <div>
                            <p class="text-label-sm text-on-surface-variant uppercase tracking-wider mb-xs">Difficulty</p>
                            <p class="text-body-md text-on-surface"><?= htmlspecialchars(ucfirst($cert['course_difficulty'])) ?></p>
                        </div>
                        <div>
                            <p class="text-label-sm text-on-surface-variant uppercase tracking-wider mb-xs">Issue Date</p>
                            <p class="text-body-md text-on-surface"><?= date('F d, Y', strtotime($cert['issued_at'])) ?></p>
                        </div>
                        <div>
                            <p class="text-label-sm text-on-surface-variant uppercase tracking-wider mb-xs">Certificate ID</p>
                            <p class="text-body-md text-on-surface font-mono"><?= htmlspecialchars($shortId) ?></p>
                        </div>
                    </div>

                    <div class="pt-md border-t border-outline-variant">
                        <p class="text-label-sm text-on-surface-variant mb-xs">Full Certificate Hash</p>
                        <p class="text-body-sm text-on-surface-variant font-mono break-all bg-surface-container-low p-sm rounded"><?= htmlspecialchars($hash) ?></p>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- Invalid / Not Found -->
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant shadow-card-lg overflow-hidden">
                <div class="bg-red-50 border-b border-red-200 p-md flex items-center gap-sm">
                    <span class="material-symbols-outlined text-red-600 text-[28px]" aria-hidden="true">error</span>
                    <div>
                        <h1 class="text-h3 font-bold text-red-900">Certificate Not Found</h1>
                        <p class="text-label-md text-red-700">We could not verify this certificate.</p>
                    </div>
                </div>
                <div class="p-lg">
                    <p class="text-body-md text-on-surface-variant"><?= htmlspecialchars($error) ?></p>
                    <div class="mt-md">
                        <a href="<?= BASE_URL ?>/43_Public_Homepage/index.php" class="text-primary font-semibold hover:underline text-body-md">Return to Homepage</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <p class="text-center text-label-sm text-on-surface-variant mt-md">
            <a href="<?= BASE_URL ?>/43_Public_Homepage/index.php" class="hover:text-primary transition-colors">Lyra Academy Music School</a>
        </p>
    </div>

</body>
</html>

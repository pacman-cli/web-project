<?php
// 34_Student_Certificates/index.php
// Student certificate view & claim triggers

require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/design-system.php';
require_once __DIR__ . '/../config/cert_helper.php';
require_once __DIR__ . '/../config/csrf.php';
requireRole('student');

$pdo = require_once __DIR__ . '/../config/db.php';
$studentId = $_SESSION['user_id'];
$studentName = $_SESSION['name'];

try {
    // 1. Fetch student's enrolled courses to check for certificates
    $coursesStmt = $pdo->prepare("
        SELECT c.id as course_id, c.title, c.description, c.difficulty,
               cert.certificate_hash, cert.file_path, cert.issued_at
        FROM courses c
        JOIN enrollments e ON c.id = e.course_id
        LEFT JOIN certificates cert ON c.id = cert.course_id AND cert.student_id = :sid
        WHERE e.student_id = :eid AND e.status = 'approved'
        ORDER BY c.title ASC
    ");
    $coursesStmt->execute(['sid' => $studentId, 'eid' => $studentId]);
    $courses = $coursesStmt->fetchAll();

    $claimedCertificates = [];
    $eligibleToClaim = [];
    $inProgressCourses = [];

    foreach ($courses as $c) {
        $courseId = $c['course_id'];
        
        if ($c['certificate_hash'] !== null) {
            // Already claimed
            $claimedCertificates[] = $c;
        } else {
            // Check eligibility using shared function
            $elig = cert_get_eligibility($pdo, $studentId, $courseId);
            $c['progress_percent'] = $elig['progress'];
            $c['attendance_rate'] = $elig['attendance'];

            if ($elig['eligible']) {
                $eligibleToClaim[] = $c;
            } else {
                $inProgressCourses[] = $c;
            }
        }
    }

    // Fetch student profile additional info
    $profileStmt = $pdo->prepare("SELECT experience_level FROM students WHERE user_id = :student_id");
    $profileStmt->execute(['student_id' => $studentId]);
    $studentProfile = $profileStmt->fetch();
    $experienceLevel = $studentProfile ? $studentProfile['experience_level'] : 'beginner';

} catch (Exception $e) {
    error_log('DB error: ' . $e->getMessage()); die('A database error occurred. Please try again later.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php lms_head('My Certificates', 'student'); ?>
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>">
</head>
<body class="bg-background text-on-surface">

<?php lms_sidebar('student', '/34_Student_Certificates/index.php'); ?>

<?php lms_topbar('student', 'My Certificates'); ?>

<!-- Main Content Area -->
<main id="lms-main-content" class="lms-main">
    <div class="max-w-container-max mx-auto space-y-lg">
        <!-- Page Header -->
        <div class="mb-lg">
            <h1 class="text-h1 font-h1 text-on-background">My Certificates</h1>
            <p class="text-body-lg font-body-lg text-on-surface-variant mt-xs">Track your academic achievements and download your verified musical credentials.</p>
        </div>

        <!-- Claim Eligibility Section -->
        <?php if (!empty($eligibleToClaim)): ?>
            <section class="bg-amber-50 border border-amber-200 rounded-xl p-lg shadow-sm">
                <h2 class="text-h3 font-bold text-amber-950 flex items-center gap-2 mb-sm">
                    <span class="material-symbols-outlined text-amber-700" aria-hidden="true">stars</span>
                    Congratulations! You have courses eligible for certificate issuance:
                </h2>
                <div class="space-y-md">
                    <?php foreach ($eligibleToClaim as $c): ?>
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between p-md bg-white rounded-lg border border-amber-200">
                            <div>
                                <h3 class="font-bold text-on-surface"><?= htmlspecialchars($c['title']) ?></h3>
                                <p class="text-xs text-on-surface-variant">Assignments completed: <?= $c['progress_percent'] ?>% • Attendance rate: <?= $c['attendance_rate'] ?>%</p>
                            </div>
                            <button onclick="claimCertificate(<?= $c['course_id'] ?>)" class="mt-md sm:mt-0 px-lg py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg font-bold text-body-md shadow-sm transition-all active:scale-[0.98]">
                                Claim Certificate
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- Featured Certificate Section -->
        <?php if (!empty($claimedCertificates)): ?>
            <?php $recent = $claimedCertificates[0]; ?>
            <section class="mb-xl">
                <h2 class="text-label-sm font-label-sm text-primary uppercase tracking-wider mb-sm">Most Recent Achievement</h2>
                <div class="bg-surface-container-lowest rounded-xl border border-outline-variant shadow-sm overflow-hidden flex flex-col md:flex-row">
                    <div class="md:w-1/3 relative h-48 md:h-auto overflow-hidden">
                        <img alt="Certificate Preview" class="w-full h-full object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuBD5eF7Uar_zcLnwXGjapdQrWEI5nASOdfJZij8ITvpPZGZ7-EsiBJL4rZ2hlXhTwo1AsAR7pBsogn5iJK_rZLaKYMt6riu68vcYc2dfd3NGmaxiiWGcPrGuj_euBs3OW6GB0_FRw0te2fUJ1u5XFILJM6hQj9pZj4XrN2BNYl0ANQ3FlmocbCrIDrTFEGNpVYCh-3zylsGyriJ9qTkS6Cl60gOamxiyxw3gNLnjjrLCx_qGk68_qNsQWjZLaF5IWyMDqUfjdBBJ-w"/>
                        <div class="absolute inset-0 bg-primary/10 flex items-center justify-center">
                            <div class="bg-white/90 backdrop-blur-sm p-sm rounded-full shadow-lg">
                                <span class="material-symbols-outlined text-primary text-[32px]" aria-hidden="true">verified</span>
                            </div>
                        </div>
                    </div>
                    <div class="md:w-2/3 p-lg flex flex-col justify-between">
                        <div>
                            <div class="flex items-start justify-between">
                                <div>
                                    <h3 class="text-h2 font-h2 text-on-background"><?= htmlspecialchars($recent['title']) ?></h3>
                                    <p class="text-body-md font-body-md text-on-surface-variant">Difficulty: <?= htmlspecialchars(ucfirst($recent['difficulty'])) ?></p>
                                </div>
                                <span class="bg-secondary-container text-on-secondary-container px-base py-xs rounded-full text-label-sm font-label-sm flex items-center gap-xs">
                                    <span class="material-symbols-outlined text-[14px]" style="font-variation-settings: 'FILL' 1;" aria-hidden="true">verified</span> Verified
                                </span>
                            </div>
                            <p class="mt-md text-body-md font-body-md text-on-surface-variant leading-relaxed">
                                Congratulations! This certificate verifies Grade/Level graduation in <?= htmlspecialchars($recent['title']) ?>. It represents outstanding command over lesson topics and rigorous coursework at Lyra Academy.
                            </p>
                        </div>
                        <div class="mt-lg flex flex-wrap gap-sm">
                            <a href="<?= htmlspecialchars($recent['file_path']) ?>" download class="bg-primary text-on-primary px-md py-base rounded-lg font-label-md text-label-md flex items-center gap-xs hover:bg-primary-container transition-colors shadow-sm">
                                <span class="material-symbols-outlined" aria-hidden="true">download</span> Download PDF
                            </a>
                            <?php if (!empty($recent['certificate_hash'])): ?>
                                <a href="/api/certificate_verify.php?hash=<?= htmlspecialchars($recent['certificate_hash']) ?>" target="_blank" class="bg-surface-container-high text-on-surface px-md py-base rounded-lg font-label-md text-label-md flex items-center gap-xs hover:bg-surface-container-highest transition-colors">
                                    <span class="material-symbols-outlined" aria-hidden="true">verified</span> Verify
                                </a>
                            <?php endif; ?>
                            <span class="text-outline text-xs self-center">Issued on: <?= date('M d, Y', strtotime($recent['issued_at'])) ?></span>
                        </div>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <!-- Certificates Grid -->
        <section>
            <div class="flex items-center justify-between mb-md">
                <h2 class="text-h3 font-h3 text-on-background">All Verified Certificates</h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-md">
                <?php if (empty($claimedCertificates)): ?>
                    <div class="col-span-full bg-surface-container-lowest border border-outline-variant rounded-xl p-lg text-center text-on-surface-variant">
                        No claimed certificates yet. Meet course requirements to unlock graduation awards!
                    </div>
                <?php else: ?>
                    <?php foreach ($claimedCertificates as $cert): ?>
                        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl shadow-sm p-lg flex flex-col hover:shadow-md transition-shadow">
                            <div class="flex justify-between items-start mb-sm">
                                <span class="bg-secondary-container text-on-secondary-container px-xs py-[2px] rounded text-label-sm font-label-sm uppercase tracking-wider"><?= htmlspecialchars($cert['difficulty']) ?></span>
                                <span class="material-symbols-outlined text-primary" style="font-variation-settings: 'FILL' 1;" aria-hidden="true">verified</span>
                            </div>
                            <h4 class="text-h3 font-h3 text-on-background mb-xs"><?= htmlspecialchars($cert['title']) ?></h4>
                            <div class="flex items-center gap-xs text-label-md font-label-md text-on-surface-variant mb-lg mt-md">
                                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">calendar_today</span>
                                Completed on <?= date('M d, Y', strtotime($cert['issued_at'])) ?>
                            </div>
                            <div class="mt-auto flex gap-sm">
                                <a href="<?= htmlspecialchars($cert['file_path']) ?>" download class="flex-1 bg-primary text-on-primary py-base rounded-lg font-label-md text-label-md hover:bg-primary-container transition-colors text-center font-semibold">Download</a>
                            </div>
                            <?php if (!empty($cert['certificate_hash'])): ?>
                                <div class="mt-sm pt-sm border-t border-outline-variant">
                                    <p class="text-label-sm text-on-surface-variant mb-xs">Verification</p>
                                    <div class="flex items-center gap-sm">
                                        <div class="qr-code" data-hash="<?= htmlspecialchars($cert['certificate_hash']) ?>" data-host="<?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'localhost') ?>" data-scheme="<?= (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http' ?>"></div>
                                        <div class="min-w-0">
                                            <p class="text-label-sm text-on-surface-variant truncate" title="<?= htmlspecialchars(strtoupper(substr($cert['certificate_hash'], 0, 16))) ?>"><?= htmlspecialchars(strtoupper(substr($cert['certificate_hash'], 0, 16))) ?></p>
                                            <a href="/api/certificate_verify.php?hash=<?= htmlspecialchars($cert['certificate_hash']) ?>" target="_blank" class="text-primary text-label-sm hover:underline">Verify online</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- Courses In Progress Criteria Section -->
        <section class="mt-xl">
            <h2 class="text-h3 font-h3 text-on-background mb-md">In Progress Graduation Status</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-md">
                <?php if (empty($inProgressCourses)): ?>
                    <div class="col-span-full bg-surface-container-lowest border border-outline-variant rounded-xl p-lg text-center text-on-surface-variant">
                        No additional courses currently in progress.
                    </div>
                <?php else: ?>
                    <?php foreach ($inProgressCourses as $c): ?>
                        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-md shadow-sm space-y-md">
                            <h3 class="font-bold text-on-surface"><?= htmlspecialchars($c['title']) ?></h3>
                            <div class="space-y-sm">
                                <div>
                                    <div class="flex justify-between text-xs mb-xs">
                                        <span class="text-on-surface-variant">Assignment Completion</span>
                                        <span class="font-semibold text-primary"><?= $c['progress_percent'] ?>% / 90% Required</span>
                                    </div>
                                    <div class="h-2 w-full bg-surface-container rounded-full overflow-hidden" role="progressbar" aria-valuenow="<?= min(100, $c['progress_percent']) ?>" aria-valuemin="0" aria-valuemax="100">
                                         <div class="h-full bg-primary rounded-full" style="width: <?= min(100, $c['progress_percent']) ?>%"></div>
                                     </div>
                                 </div>
                                 <div>
                                     <div class="flex justify-between text-xs mb-xs">
                                         <span class="text-on-surface-variant">Attendance Rate</span>
                                         <span class="font-semibold text-primary"><?= $c['attendance_rate'] ?>% / 80% Required</span>
                                     </div>
                                     <div class="h-2 w-full bg-surface-container rounded-full overflow-hidden" role="progressbar" aria-valuenow="<?= min(100, $c['attendance_rate']) ?>" aria-valuemin="0" aria-valuemax="100">
                                         <div class="h-full bg-primary rounded-full" style="width: <?= min(100, $c['attendance_rate']) ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
    // Render QR codes for certificate verification
    document.querySelectorAll('.qr-code').forEach(el => {
        const hash = el.dataset.hash;
        const host = el.dataset.host;
        const scheme = el.dataset.scheme;
        const url = scheme + '://' + host + '/api/certificate_verify.php?hash=' + hash;
        new QRCode(el, {
            text: url,
            width: 48,
            height: 48,
            colorDark: '#191c1e',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.M
        });
    });

    function claimCertificate(courseId) {
        if (!confirm('Are you sure you want to claim your certificate for this course?')) return;

        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        fetch('/student/certificates.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({ course_id: courseId })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(data.message || 'Certificate issued successfully!');
                window.location.reload();
            } else {
                alert(data.error || 'Claim failed.');
            }
        })
        .catch(err => {
            console.error(err);
            alert('Failed to contact server.');
        });
    }
</script>
</body>
</html>

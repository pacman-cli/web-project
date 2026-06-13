<?php
// 28_Bulk_Certificates/index.php
// Instructor portal to view and generate student certificates

require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/design-system.php';
require_once __DIR__ . '/../config/csrf.php';
requireRole('instructor');

$pdo = require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pdf_cert.php';
require_once __DIR__ . '/../config/cert_helper.php';
$instructorId = $_SESSION['user_id'];
$instructorName = $_SESSION['name'];

$message = '';
$error = '';

// Handle certificate issuance POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        $error = 'Session expired. Please refresh and try again.';
    } elseif (isset($_POST['action'])) {
        if ($_POST['action'] === 'generate' && isset($_POST['student_id'], $_POST['course_id'])) {
            $studentId = intval($_POST['student_id']);
            $courseId = intval($_POST['course_id']);
            
            try {
                // Verify instructor is assigned to this course
                $authStmt = $pdo->prepare("
                    SELECT 1 FROM instructor_assignments 
                    WHERE instructor_id = :instructor_id AND course_id = :course_id
                ");
                $authStmt->execute(['instructor_id' => $instructorId, 'course_id' => $courseId]);
                if (!$authStmt->fetch()) {
                    throw new Exception("Access denied: You do not teach this course.");
                }
                
                // Verify student approved enrollment
                $enrollStmt = $pdo->prepare("
                    SELECT 1 FROM enrollments 
                    WHERE student_id = :student_id AND course_id = :course_id AND status = 'approved'
                ");
                $enrollStmt->execute(['student_id' => $studentId, 'course_id' => $courseId]);
                if (!$enrollStmt->fetch()) {
                    throw new Exception("Student is not approved in this course.");
                }
                
                // Check if already exists
                $checkStmt = $pdo->prepare("SELECT 1 FROM certificates WHERE student_id = :student_id AND course_id = :course_id");
                $checkStmt->execute(['student_id' => $studentId, 'course_id' => $courseId]);
                if ($checkStmt->fetch()) {
                    throw new Exception("Certificate already issued.");
                }
                
                // Generate Certificate
                $certHash = cert_generate_hash($studentId, $courseId);
                $filePath = "/uploads/certificates/cert_" . $certHash . ".pdf";
                
                $certDir = __DIR__ . '/../uploads/certificates/';
                if (!file_exists($certDir)) {
                    mkdir($certDir, 0755, true);
                }
                // Get student and course names
                $nameStmt = $pdo->prepare("SELECT name FROM users WHERE id = :sid");
                $nameStmt->execute(['sid' => $studentId]);
                $sName = $nameStmt->fetchColumn() ?: 'Student';

                $cnameStmt = $pdo->prepare("SELECT title FROM courses WHERE id = :cid");
                $cnameStmt->execute(['cid' => $courseId]);
                $cName = $cnameStmt->fetchColumn() ?: 'Course';

                $pdfContent = generate_certificate_pdf($sName, $cName, $instructorName, date('F d, Y'), $certHash);
                file_put_contents($certDir . "cert_" . $certHash . ".pdf", $pdfContent);
                
                $insertStmt = $pdo->prepare("
                    INSERT INTO certificates (student_id, course_id, certificate_hash, file_path) 
                    VALUES (:student_id, :course_id, :certificate_hash, :file_path)
                ");
                $insertStmt->execute([
                    'student_id' => $studentId,
                    'course_id' => $courseId,
                    'certificate_hash' => $certHash,
                    'file_path' => $filePath
                ]);
                
                $message = "Certificate issued successfully!";
            } catch (Exception $e) {
                $error = 'An error occurred while generating the certificate. Please try again.';
error_log('Certificate generation error: ' . $e->getMessage());
            }
        } elseif ($_POST['action'] === 'bulk_generate' && isset($_POST['selected_items'])) {
            $items = $_POST['selected_items']; // Array of "student_id:course_id"
            $issuedCount = 0;
            $failCount = 0;
            
            foreach ($items as $item) {
                $parts = explode(':', $item);
                if (count($parts) !== 2) continue;
                $sId = intval($parts[0]);
                $cId = intval($parts[1]);
                
                try {
                    // Check authorization
                    $authStmt = $pdo->prepare("
                        SELECT 1 FROM instructor_assignments
                        WHERE instructor_id = :instructor_id AND course_id = :course_id
                    ");
                    $authStmt->execute(['instructor_id' => $instructorId, 'course_id' => $cId]);
                    if (!$authStmt->fetch()) continue;

                    // Verify student is approved-enrolled
                    $enrollStmt = $pdo->prepare("
                        SELECT 1 FROM enrollments
                        WHERE student_id = :student_id AND course_id = :course_id AND status = 'approved'
                    ");
                    $enrollStmt->execute(['student_id' => $sId, 'course_id' => $cId]);
                    if (!$enrollStmt->fetch()) continue;

                    // Check if already exists
                    $checkStmt = $pdo->prepare("SELECT 1 FROM certificates WHERE student_id = :student_id AND course_id = :course_id");
                    $checkStmt->execute(['student_id' => $sId, 'course_id' => $cId]);
                    if ($checkStmt->fetch()) continue;
                    
                    // Generate Certificate
                    $certHash = cert_generate_hash($sId, $cId);
                    $filePath = "/uploads/certificates/cert_" . $certHash . ".pdf";
                    
                    $certDir = __DIR__ . '/../uploads/certificates/';
                    if (!file_exists($certDir)) {
                        mkdir($certDir, 0755, true);
                    }
                    // Get student and course names
                    $nameStmt = $pdo->prepare("SELECT name FROM users WHERE id = :sid");
                    $nameStmt->execute(['sid' => $sId]);
                    $sName = $nameStmt->fetchColumn() ?: 'Student';

                    $cnameStmt = $pdo->prepare("SELECT title FROM courses WHERE id = :cid");
                    $cnameStmt->execute(['cid' => $cId]);
                    $cName = $cnameStmt->fetchColumn() ?: 'Course';

                    $pdfContent = generate_certificate_pdf($sName, $cName, $instructorName, date('F d, Y'), $certHash);
                    file_put_contents($certDir . "cert_" . $certHash . ".pdf", $pdfContent);
                    
                    $insertStmt = $pdo->prepare("
                        INSERT INTO certificates (student_id, course_id, certificate_hash, file_path) 
                        VALUES (:student_id, :course_id, :certificate_hash, :file_path)
                    ");
                    $insertStmt->execute([
                        'student_id' => $sId,
                        'course_id' => $cId,
                        'certificate_hash' => $certHash,
                        'file_path' => $filePath
                    ]);
                    $issuedCount++;
                } catch (Exception $e) {
                    $failCount++;
                }
            }
            $message = "Bulk generation finished. Issued: $issuedCount. Failed/Skipped: $failCount.";
        }
    }
}

// Fetch instructor's courses for filter
$coursesFilterStmt = $pdo->prepare("
    SELECT c.id, c.title 
    FROM courses c
    JOIN instructor_assignments ia ON c.id = ia.course_id
    WHERE ia.instructor_id = :instructor_id
    ORDER BY c.title ASC
");
$coursesFilterStmt->execute(['instructor_id' => $instructorId]);
$instructorCourses = $coursesFilterStmt->fetchAll();

// Fetch enrollments
$enrollmentsStmt = $pdo->prepare("
    SELECT 
        e.id as enrollment_id, 
        u.id as student_id,
        u.name as student_name, 
        u.email as student_email,
        c.id as course_id,
        c.title as course_title,
        c.difficulty,
        cert.certificate_hash,
        cert.file_path,
        cert.issued_at
    FROM enrollments e
    JOIN students s ON e.student_id = s.user_id
    JOIN users u ON s.user_id = u.id
    JOIN courses c ON e.course_id = c.id
    JOIN instructor_assignments ia ON c.id = ia.course_id
    LEFT JOIN certificates cert ON e.course_id = cert.course_id AND e.student_id = cert.student_id
    WHERE ia.instructor_id = :instructor_id AND e.status = 'approved'
    ORDER BY u.name ASC
");
$enrollmentsStmt->execute(['instructor_id' => $instructorId]);
$enrollments = $enrollmentsStmt->fetchAll();

$processedEnrollments = [];
$totalIssued = 0;
$pendingIssuance = 0;

foreach ($enrollments as $e) {
    $cId = $e['course_id'];
    $sId = $e['student_id'];
    
    // Use shared eligibility calculation
    $elig = cert_get_eligibility($pdo, $sId, $cId);
    $e['progress_percent'] = $elig['progress'];
    $e['attendance_rate'] = $elig['attendance'];
    $e['eligible'] = $elig['eligible'];

    if ($e['certificate_hash'] !== null) {
        $totalIssued++;
    } elseif ($elig['eligible']) {
        $pendingIssuance++;
    }
    
    $processedEnrollments[] = $e;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php lms_head('Bulk Certificates', 'instructor'); ?>
</head>
<body class="bg-background text-on-surface">

<?php lms_sidebar('instructor', '/28_Bulk_Certificates/index.php'); ?>
<?php lms_topbar('instructor', 'Bulk Certificates'); ?>

<!-- Main Content Area -->
<main id="lms-main-content" class="lms-main">

    <!-- Canvas -->
    <div class="page-content space-y-md">
        <!-- Page Header -->
        <div class="flex justify-between items-end">
            <div>
                <h2 class="font-h1 text-h1 text-on-surface">Certificates</h2>
                <p class="font-body-lg text-body-lg text-on-surface-variant">Generate and manage course completion certificates</p>
            </div>
            <button onclick="submitBulkForm()" class="bg-primary text-on-primary flex items-center gap-xs px-md py-sm rounded-lg font-semibold hover:opacity-90 transition-opacity">
                <span class="material-symbols-outlined text-[20px]" aria-hidden="true">workspace_premium</span>
                <span>Bulk Issue Selected</span>
            </button>
        </div>

        <?php if ($message): ?>
            <div class="p-md bg-green-50 border border-green-200 text-green-800 rounded-lg"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="p-md bg-red-50 border border-red-200 text-red-800 rounded-lg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Table Card -->
        <form id="bulk-form" method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="bulk_generate"/>
            <div class="bg-surface-container-lowest rounded-xl border border-outline-variant shadow-[0_2px_4px_rgba(0,0,0,0.04)] overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-surface border-b border-outline-variant">
                        <tr>
                            <th class="px-md py-sm w-12">
                                <input class="rounded border-outline-variant text-primary focus:ring-primary" type="checkbox" id="select-all" aria-label="Select all students" onclick="toggleSelectAll(this)"/>
                            </th>
                            <th class="px-md py-sm font-label-sm text-label-sm text-secondary uppercase tracking-wider">Student Name</th>
                            <th class="px-md py-sm font-label-sm text-label-sm text-secondary uppercase tracking-wider">Course</th>
                            <th class="px-md py-sm font-label-sm text-label-sm text-secondary uppercase tracking-wider">Progress</th>
                            <th class="px-md py-sm font-label-sm text-label-sm text-secondary uppercase tracking-wider text-center">Attendance</th>
                            <th class="px-md py-sm font-label-sm text-label-sm text-secondary uppercase tracking-wider">Cert. Status</th>
                            <th class="px-md py-sm font-label-sm text-label-sm text-secondary uppercase tracking-wider text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant">
                        <?php if (empty($processedEnrollments)): ?>
                            <tr>
                                <td colspan="7" class="px-md py-md text-center text-on-surface-variant">No enrolled students found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($processedEnrollments as $e): ?>
                                <tr class="hover:bg-surface-container-low transition-colors group">
                                    <td class="px-md py-md w-12">
                                        <?php if ($e['certificate_hash'] === null && $e['eligible']): ?>
                                            <input class="rounded border-outline-variant text-primary focus:ring-primary student-checkbox" type="checkbox" name="selected_items[]" value="<?= $e['student_id'] ?>:<?= $e['course_id'] ?>" aria-label="Select <?= htmlspecialchars($e['student_name']) ?> for <?= htmlspecialchars($e['course_title']) ?>" onclick="updateBulkBar()"/>
                                        <?php else: ?>
                                            <input class="rounded border-outline-variant text-primary focus:ring-primary" type="checkbox" disabled aria-label="Certificate already issued for <?= htmlspecialchars($e['student_name']) ?>"/>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-md py-md">
                                        <div class="flex items-center gap-sm">
                                            <div class="w-10 h-10 rounded-full bg-primary-fixed-dim flex items-center justify-center font-semibold text-primary">
                                                <?= htmlspecialchars(substr($e['student_name'], 0, 2)) ?>
                                            </div>
                                            <div>
                                                <p class="font-body-md text-body-md font-semibold text-on-surface"><?= htmlspecialchars($e['student_name']) ?></p>
                                                <p class="text-[12px] text-on-surface-variant"><?= htmlspecialchars($e['student_email']) ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-md py-md font-body-md text-body-md text-on-surface-variant"><?= htmlspecialchars($e['course_title']) ?></td>
                                    <td class="px-md py-md">
                                        <div class="flex items-center gap-xs">
                                            <span class="font-bold"><?= $e['progress_percent'] ?>%</span>
                                            <span class="text-xs text-on-surface-variant">(90% Req)</span>
                                        </div>
                                    </td>
                                    <td class="px-md py-md text-center font-body-md text-body-md text-on-surface font-medium"><?= $e['attendance_rate'] ?>% <span class="text-xs text-on-surface-variant">(80% Req)</span></td>
                                    <td class="px-md py-md">
                                        <?php if ($e['certificate_hash'] !== null): ?>
                                            <div class="flex items-center gap-xs text-green-700">
                                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">verified</span>
                                                <span class="font-label-md text-label-md">Issued</span>
                                            </div>
                                        <?php elseif ($e['eligible']): ?>
                                            <span class="px-sm py-1 bg-amber-100 text-amber-800 rounded-full text-[10px] font-bold uppercase">Ready</span>
                                        <?php else: ?>
                                            <span class="px-sm py-1 bg-surface-container-high text-on-surface-variant rounded-full text-[10px] font-bold uppercase">Not Eligible</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-md py-md text-right">
                                        <div class="flex justify-end gap-sm">
                                                <button type="button" onclick="showPreview('<?= htmlspecialchars($e['student_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($e['course_title'], ENT_QUOTES) ?>', '<?= htmlspecialchars($e['certificate_hash'] !== null && $e['issued_at'] ? date('M d, Y', strtotime($e['issued_at'])) : date('M d, Y')) ?>')" class="p-xs text-outline hover:text-primary transition-colors" title="View Preview" aria-label="Preview certificate for <?= htmlspecialchars($e['student_name']) ?>">
                                                <span class="material-symbols-outlined" aria-hidden="true">visibility</span>
                                            </button>
                                            <?php if ($e['certificate_hash'] !== null): ?>
                                                <a href="<?= htmlspecialchars($e['file_path']) ?>" download class="p-xs text-outline hover:text-primary transition-colors" title="Download PDF">
                                                    <span class="material-symbols-outlined" aria-hidden="true">download</span>
                                                </a>
                                            <?php elseif ($e['eligible']): ?>
                                                <button type="button" onclick="issueSingle(<?= $e['student_id'] ?>, <?= $e['course_id'] ?>)" class="text-primary font-semibold font-label-md text-label-md hover:underline flex items-center gap-xs" title="Generate Certificate">
                                                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">auto_fix_high</span>
                                                    <span>Issue</span>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>

        <!-- Stats Bento Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-md">
            <div class="bg-primary-container/5 border border-primary/20 p-md rounded-xl">
                <div class="flex items-center justify-between mb-sm">
                    <span class="material-symbols-outlined text-primary text-[32px]" aria-hidden="true">workspace_premium</span>
                    <span class="text-primary font-bold text-h2"><?= $totalIssued ?></span>
                </div>
                <p class="font-label-md text-label-md text-primary/80 uppercase">Total Certificates Issued</p>
            </div>
            <div class="bg-surface-container-lowest border border-outline-variant p-md rounded-xl">
                <div class="flex items-center justify-between mb-sm">
                    <span class="material-symbols-outlined text-secondary text-[32px]" aria-hidden="true">pending_actions</span>
                    <span class="text-on-surface font-bold text-h2"><?= $pendingIssuance ?></span>
                </div>
                <p class="font-label-md text-label-md text-secondary uppercase">Ready for Issuance</p>
            </div>
            <div class="bg-surface-container-lowest border border-outline-variant p-md rounded-xl flex items-center gap-md">
                <div class="flex-1">
                    <p class="font-label-md text-label-md text-secondary uppercase mb-xs">Template Status</p>
                    <p class="font-body-md text-body-md text-on-surface">Lyra Academy Diploma 2026</p>
                </div>
                <div class="w-16 h-20 bg-surface border border-outline-variant rounded p-xs flex items-center justify-center overflow-hidden">
                    <div class="w-full h-full bg-white border border-dashed border-outline-variant rounded-sm flex items-center justify-center">
                        <span class="material-symbols-outlined text-outline text-[20px]" aria-hidden="true">description</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Hidden forms for single issuance -->
<form id="single-issue-form" method="POST" action="">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="generate"/>
    <input type="hidden" name="student_id" id="single-student-id"/>
    <input type="hidden" name="course_id" id="single-course-id"/>
</form>

<!-- MODAL OVERLAY: Certificate Preview -->
<div id="preview-modal" class="fixed inset-0 z-[100] flex items-center justify-center bg-inverse-surface/40 backdrop-blur-sm p-md hidden" role="dialog" aria-modal="true" aria-labelledby="preview-modal-title">
    <div class="bg-surface-container-lowest w-full max-w-[900px] rounded-xl shadow-2xl overflow-hidden flex flex-col max-h-[90%]">
        <!-- Modal Header -->
        <div class="px-lg py-md border-b border-outline-variant flex justify-between items-center bg-surface-container-low">
            <div>
                <h2 id="preview-modal-title" class="font-h2 text-h2 text-on-surface">Certificate Preview</h2>
                <p class="text-secondary font-body-md">Verify the student's achievement details.</p>
            </div>
            <button onclick="closeModal()" class="p-xs hover:bg-surface-container-high rounded-full transition-colors" aria-label="Close certificate preview">
                <span class="material-symbols-outlined text-secondary" aria-hidden="true">close</span>
            </button>
        </div>
        <!-- Modal Body: Formal Certificate Layout -->
        <div class="flex-1 overflow-y-auto p-lg bg-surface-container">
            <div class="certificate-watermark relative bg-white aspect-[1.414/1] w-full border-[12px] border-primary/5 shadow-lg mx-auto p-xl flex flex-col items-center justify-between text-center font-body-md text-on-surface-variant overflow-hidden">
                <!-- Decorative Corners -->
                <div class="absolute top-0 left-0 w-32 h-32 border-t-2 border-l-2 border-primary/20 m-md"></div>
                <div class="absolute top-0 right-0 w-32 h-32 border-t-2 border-r-2 border-primary/20 m-md"></div>
                <div class="absolute bottom-0 left-0 w-32 h-32 border-b-2 border-l-2 border-primary/20 m-md"></div>
                <div class="absolute bottom-0 right-0 w-32 h-32 border-b-2 border-r-2 border-primary/20 m-md"></div>
                <!-- School Header -->
                <div class="z-10 mt-md">
                    <span class="material-symbols-outlined text-primary text-4xl mb-base" aria-hidden="true">auto_awesome</span>
                    <h4 class="font-h3 text-h3 tracking-[0.2em] text-primary uppercase">Lyra Academy of Music</h4>
                    <div class="h-[1px] w-16 bg-primary/30 mx-auto mt-xs"></div>
                </div>
                <!-- Certificate Title -->
                <div class="z-10">
                    <p class="font-label-md text-label-md text-secondary tracking-widest uppercase mb-xs">This is to certify that</p>
                    <h2 class="text-[40px] leading-tight font-extrabold text-on-surface mb-md" id="modal-student-name">Student Name</h2>
                    <p class="font-body-lg text-body-lg text-secondary">Has successfully completed the comprehensive curriculum for</p>
                    <h3 class="font-h2 text-h2 text-primary mt-sm" id="modal-course-title">Course Title</h3>
                </div>
                <!-- Completion Statement -->
                <div class="z-10 max-w-lg">
                    <p class="font-body-md text-body-md leading-relaxed italic text-secondary">
                        "This certificate is awarded for the successful completion of the advanced curriculum and performance requirements, demonstrating exceptional technical proficiency and artistic maturity."
                    </p>
                </div>
                <!-- Signature & Date Footer -->
                <div class="z-10 w-full flex justify-between items-end px-xl pb-md">
                    <div class="text-left w-1/3">
                        <p class="font-label-sm text-label-sm text-secondary uppercase border-b border-outline-variant pb-xs mb-xs">Issue Date</p>
                        <p class="font-semibold text-on-surface" id="modal-issue-date">Date</p>
                    </div>
                    <div class="relative flex flex-col items-center">
                        <div class="absolute -top-16 signature-script text-primary opacity-80 pointer-events-none"><?= htmlspecialchars($instructorName) ?></div>
                        <div class="w-16 h-16 rounded-full border-2 border-primary/10 flex items-center justify-center mb-md">
                            <span class="material-symbols-outlined text-primary/40 text-3xl" aria-hidden="true">verified_user</span>
                        </div>
                    </div>
                    <div class="text-right w-1/3">
                        <p class="font-label-sm text-label-sm text-secondary uppercase border-b border-outline-variant pb-xs mb-xs">Senior Faculty</p>
                        <p class="font-semibold text-on-surface">Prof. <?= htmlspecialchars($instructorName) ?></p>
                    </div>
                </div>
            </div>
        </div>
        <!-- Modal Footer -->
        <div class="px-lg py-md border-t border-outline-variant bg-surface-container-lowest flex justify-end gap-md">
            <button onclick="closeModal()" class="px-lg py-sm font-label-md text-label-md text-secondary hover:text-on-surface transition-colors">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Bulk Action Floating Bar -->
<div id="bulk-action-bar" class="fixed bottom-md left-1/2 -translate-x-1/2 bg-inverse-surface text-inverse-on-surface px-lg py-md rounded-xl shadow-xl flex items-center gap-xl z-50 transform transition-transform duration-300 translate-y-[200px]">
    <div class="flex items-center gap-md">
        <span class="font-label-md text-label-md bg-primary-container text-on-primary-container px-sm py-xs rounded-full" id="bulk-count" aria-live="polite">0 Selected</span>
        <p class="font-body-md">Bulk actions available for selected students</p>
    </div>
    <div class="h-6 w-px bg-outline-variant/30"></div>
    <div class="flex items-center gap-md">
        <button onclick="submitBulkForm()" class="flex items-center gap-xs px-md py-sm rounded-lg font-semibold bg-primary text-on-primary hover:opacity-95 transition-opacity text-label-md">
            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">auto_fix_high</span>
            <span>Generate Selected</span>
        </button>
        <button onclick="cancelSelection()" class="p-xs hover:bg-white/10 rounded-full transition-colors" aria-label="Cancel selection">
            <span class="material-symbols-outlined" aria-hidden="true">close</span>
        </button>
    </div>
</div>

<script>
    function toggleSelectAll(master) {
        const checkboxes = document.querySelectorAll(".student-checkbox");
        checkboxes.forEach(cb => {
            if (!cb.disabled) {
                cb.checked = master.checked;
            }
        });
        updateBulkBar();
    }

    function updateBulkBar() {
        const selected = document.querySelectorAll(".student-checkbox:checked");
        const bar = document.getElementById("bulk-action-bar");
        const countSpan = document.getElementById("bulk-count");
        
        if (selected.length > 0) {
            countSpan.innerText = `${selected.length} Selected`;
            bar.classList.remove("translate-y-[200px]");
            bar.classList.add("translate-y-0");
        } else {
            bar.classList.remove("translate-y-0");
            bar.classList.add("translate-y-[200px]");
        }
    }

    function cancelSelection() {
        document.getElementById("select-all").checked = false;
        toggleSelectAll({checked: false});
    }

    function submitBulkForm() {
        const selected = document.querySelectorAll(".student-checkbox:checked");
        if (selected.length === 0) {
            alert("Please select at least one student to issue certificates.");
            return;
        }
        if (confirm(`Are you sure you want to generate certificates for the ${selected.length} selected student(s)?`)) {
            document.getElementById("bulk-form").submit();
        }
    }

    function issueSingle(studentId, courseId) {
        if (confirm("Are you sure you want to issue the certificate for this student?")) {
            document.getElementById("single-student-id").value = studentId;
            document.getElementById("single-course-id").value = courseId;
            document.getElementById("single-issue-form").submit();
        }
    }

    function showPreview(studentName, courseTitle, date) {
        document.getElementById("modal-student-name").innerText = studentName;
        document.getElementById("modal-course-title").innerText = courseTitle;
        document.getElementById("modal-issue-date").innerText = date;
        document.getElementById("preview-modal").classList.remove("hidden");
    }

    function closeModal() {
        document.getElementById("preview-modal").classList.add("hidden");
    }

    document.addEventListener("keydown", function(e) {
        if (e.key === "Escape" && !document.getElementById("preview-modal").classList.contains("hidden")) {
            closeModal();
        }
    });
</script>
</body>
</html>

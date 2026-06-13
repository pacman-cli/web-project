<?php
// 25_Recording_Reviews/index.php
// Instructor portal for reviewing and grading student assignments / recordings

require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/design-system.php';
require_once __DIR__ . '/../config/csrf.php';
requireRole('instructor');

$pdo = require_once __DIR__ . '/../config/db.php';
$instructorId = $_SESSION['user_id'];
$instructorName = $_SESSION['name'];

$assignmentId = intval($_GET['assignment_id'] ?? 0);
$selectedSubmissionId = intval($_GET['submission_id'] ?? 0);

try {
    // 1. Fetch instructor's assignments for dropdown
    $assignmentsStmt = $pdo->prepare("
        SELECT a.id, a.title, c.title as course_title, a.max_points
        FROM assignments a
        JOIN courses c ON a.course_id = c.id
        JOIN instructor_assignments ia ON c.id = ia.course_id
        WHERE ia.instructor_id = :instructor_id
        ORDER BY a.created_at DESC
    ");
    $assignmentsStmt->execute(['instructor_id' => $instructorId]);
    $assignments = $assignmentsStmt->fetchAll();

    // Pick first assignment if none selected
    if ($assignmentId <= 0 && !empty($assignments)) {
        $assignmentId = intval($assignments[0]['id']);
    }

    $selectedAssignment = null;
    foreach ($assignments as $asg) {
        if (intval($asg['id']) === $assignmentId) {
            $selectedAssignment = $asg;
            break;
        }
    }

    if ($assignmentId > 0 && !$selectedAssignment) {
        die("Access denied: Assignment not found or not assigned to you.");
    }

    // 2. Fetch submissions for the selected assignment
    $submissions = [];
    if ($assignmentId > 0) {
        $submissionsStmt = $pdo->prepare("
            SELECT s.*, u.name as student_name, u.email as student_email
            FROM submissions s
            JOIN users u ON s.student_id = u.id
            WHERE s.assignment_id = :assignment_id
            ORDER BY s.submitted_at DESC
        ");
        $submissionsStmt->execute(['assignment_id' => $assignmentId]);
        $submissions = $submissionsStmt->fetchAll();
    }

    // Pick first submission if none selected
    if ($selectedSubmissionId <= 0 && !empty($submissions)) {
        $selectedSubmissionId = intval($submissions[0]['id']);
    }

    $selectedSubmission = null;
    foreach ($submissions as $sub) {
        if (intval($sub['id']) === $selectedSubmissionId) {
            $selectedSubmission = $sub;
            break;
        }
    }

} catch (Exception $e) {
    error_log('DB error: ' . $e->getMessage()); die('A database error occurred. Please try again later.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php lms_head('Recording Reviews', 'instructor'); ?>
</head>
<body class="bg-background text-on-surface">

<?php lms_sidebar('instructor', '/25_Recording_Reviews/index.php'); ?>

<?php lms_topbar('instructor', 'Recording Reviews'); ?>

<!-- Main Content Area -->
<main id="lms-main-content" class="lms-main">
    <!-- Header -->
    <header class="mb-lg">
        <h2 class="font-h1 text-h1 text-on-surface mb-xs">Recording Reviews</h2>
        <p class="font-body-lg text-secondary">Evaluate student performance recordings and provide academic feedback.</p>
    </header>

    <!-- Filter Bar -->
    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-md mb-lg flex flex-wrap items-center gap-md">
        <div class="flex flex-col gap-1 flex-1 min-w-[200px]">
            <label for="assignment-select" class="font-label-sm text-on-surface-variant px-1 uppercase tracking-wider">Select Assignment</label>
            <select id="assignment-select" onchange="changeAssignment()" class="bg-surface-container-low border-outline-variant rounded-lg font-body-md py-2 px-3 focus:ring-primary focus:border-primary">
                <?php foreach ($assignments as $asg): ?>
                    <option value="<?= $asg['id'] ?>" <?= $asg['id'] == $assignmentId ? 'selected' : '' ?>>
                        <?= htmlspecialchars($asg['course_title']) ?>: <?= htmlspecialchars($asg['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Dual-panel Content -->
    <div class="grid grid-cols-10 gap-lg">
        <!-- Left Panel: Submissions list & Media Player -->
        <div class="col-span-10 lg:col-span-6 flex flex-col gap-md">
            <!-- Submissions list for assignment -->
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-md shadow-sm">
                <h3 class="font-h3 text-h3 mb-sm">Students Submissions</h3>
                <div class="space-y-sm">
                    <?php if (empty($submissions)): ?>
                        <p class="text-on-surface-variant text-body-md">No student submissions for this assignment yet.</p>
                    <?php else: ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-sm">
                            <?php foreach ($submissions as $sub): ?>
                                <a href="?assignment_id=<?= $assignmentId ?>&submission_id=<?= $sub['id'] ?>" class="p-sm rounded-lg border transition-all flex items-center justify-between <?= $sub['id'] == $selectedSubmissionId ? 'border-primary bg-primary/5' : 'border-outline-variant bg-surface-container-low hover:border-outline' ?>">
                                    <div>
                                        <p class="font-semibold text-body-md text-on-surface"><?= htmlspecialchars($sub['student_name']) ?></p>
                                        <p class="text-xs text-outline"><?= date('M d, Y H:i', strtotime($sub['submitted_at'])) ?></p>
                                    </div>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase <?= $sub['status'] === 'graded' ? 'bg-green-150 text-green-800' : 'bg-primary-container/20 text-primary' ?>">
                                        <?= htmlspecialchars($sub['status']) ?>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($selectedSubmission): ?>
                <!-- Media Player -->
                <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden shadow-sm">
                    <!-- Player Header -->
                    <div class="px-lg py-md border-b border-outline-variant">
                        <h3 class="font-h3 text-h3 text-on-surface"><?= htmlspecialchars($selectedSubmission['student_name']) ?></h3>
                        <p class="font-body-md text-secondary">Submission Details & File Playback</p>
                    </div>
                    <!-- File render -->
                    <div class="p-lg bg-surface-container-high flex flex-col items-center justify-center">
                        <?php 
                        $ext = strtolower(pathinfo($selectedSubmission['file_path'], PATHINFO_EXTENSION));
                        if (in_array($ext, ['mp4', 'mov', 'webm'])): 
                        ?>
                            <video controls class="w-full max-h-[360px] rounded-lg bg-black">
                                <source src="<?= htmlspecialchars($selectedSubmission['file_path']) ?>" type="video/mp4">
                                Your browser does not support the video tag.
                            </video>
                        <?php elseif (in_array($ext, ['mp3', 'wav', 'ogg'])): ?>
                            <div class="text-center w-full p-md bg-white rounded-lg shadow-sm">
                                <span class="material-symbols-outlined text-[64px] text-secondary/30 mb-sm" aria-hidden="true">audio_file</span>
                                <audio controls class="w-full">
                                    <source src="<?= htmlspecialchars($selectedSubmission['file_path']) ?>" type="audio/mpeg">
                                    Your browser does not support the audio element.
                                </audio>
                            </div>
                        <?php elseif ($ext === 'pdf'): ?>
                            <div class="w-full bg-white rounded-lg shadow-sm overflow-hidden">
                                <iframe src="<?= htmlspecialchars($selectedSubmission['file_path']) ?>" class="w-full h-[500px] border-0" title="PDF Preview"></iframe>
                            </div>
                            <a href="<?= htmlspecialchars($selectedSubmission['file_path']) ?>" download class="mt-3 px-md py-sm bg-primary text-on-primary rounded-lg font-semibold inline-flex items-center gap-2">
                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">download</span>
                                Download PDF
                            </a>
                        <?php else: ?>
                            <div class="text-center p-md bg-white rounded-lg shadow-sm w-full">
                                <span class="material-symbols-outlined text-[64px] text-secondary/30 mb-sm" aria-hidden="true">description</span>
                                <p class="text-body-md text-on-surface mb-md">Document File: <?= htmlspecialchars(basename($selectedSubmission['file_path'])) ?></p>
                                <a href="<?= htmlspecialchars($selectedSubmission['file_path']) ?>" download class="px-md py-sm bg-primary text-on-primary rounded-lg font-semibold inline-block">Download File</a>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($selectedSubmission['submission_text'])): ?>
                            <div class="w-full mt-md p-md bg-white rounded-lg border border-outline-variant text-body-md">
                                <h4 class="font-bold text-on-surface mb-1">Student Comments:</h4>
                                <p class="text-on-surface-variant italic">"<?= htmlspecialchars($selectedSubmission['submission_text']) ?>"</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right Panel: Grading & Feedback Form -->
        <div class="col-span-10 lg:col-span-4 flex flex-col gap-md">
            <?php if ($selectedSubmission): ?>
                <div class="bg-surface-container-lowest border border-outline-variant rounded-xl shadow-sm h-full flex flex-col">
                    <div class="px-lg py-md border-b border-outline-variant">
                        <h3 class="font-h3 text-h3 text-on-surface">Grading Form</h3>
                    </div>
                    <form id="grading-form" onsubmit="submitGrade(event)" class="p-lg space-y-md flex-1">
                        <input type="hidden" name="submission_id" value="<?= $selectedSubmission['id'] ?>"/>
                        
                        <div class="space-y-xs">
                            <label for="points_earned" class="font-label-md text-on-surface font-semibold block">Points Awarded</label>
                            <div class="flex items-center gap-sm">
                                <input required type="number" min="0" max="<?= intval($selectedAssignment['max_points']) ?>" name="points_earned" value="<?= isset($selectedSubmission['points_earned']) ? intval($selectedSubmission['points_earned']) : '' ?>" class="w-24 bg-surface-container-low border-outline-variant rounded-lg text-center font-bold text-primary py-2 text-lg focus:ring-primary focus:border-primary"/>
                                <span class="text-secondary font-body-lg">/ <?= intval($selectedAssignment['max_points']) ?> points</span>
                            </div>
                        </div>

                        <div class="space-y-xs">
                            <label for="feedback" class="font-label-sm text-on-surface-variant uppercase tracking-wider block">Feedback / Comments</label>
                            <textarea required name="feedback" class="w-full bg-surface-container-low border-outline-variant rounded-lg font-body-md p-3 focus:ring-primary focus:border-primary resize-none" placeholder="Provide constructive criticism, suggestions, or praise..." rows="8"><?= htmlspecialchars($selectedSubmission['feedback'] ?? '') ?></textarea>
                        </div>

                        <div id="grading-error" class="text-error text-body-md font-semibold hidden" aria-live="polite"></div>

                        <div class="pt-md border-t border-outline-variant flex gap-md">
                            <button type="submit" class="w-full bg-primary text-on-primary font-label-md py-3 rounded-lg hover:bg-primary-container transition-colors shadow-lg font-bold">
                                <?= $selectedSubmission['status'] === 'graded' ? 'Update Grade' : 'Submit Grade' ?>
                            </button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-lg text-center shadow-sm">
                    <p class="text-on-surface-variant text-body-md">No student selected for evaluation.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
    function changeAssignment() {
        const val = document.getElementById('assignment-select').value;
        if (val) {
            window.location.search = '?assignment_id=' + val;
        }
    }

    function submitGrade(event) {
        event.preventDefault();
        const form = document.getElementById('grading-form');
        const data = {
            submission_id: parseInt(form.submission_id.value),
            points_earned: parseInt(form.points_earned.value),
            feedback: form.feedback.value
        };

        const errorDiv = document.getElementById('grading-error');
        errorDiv.classList.add('hidden');

        fetch('/instructor/submissions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': '<?= csrf_token() ?>'
            },
            body: JSON.stringify(data)
        })
        .then(res => res.json())
        .then(resData => {
            if (resData.success) {
                alert('Submission graded successfully!');
                window.location.reload();
            } else {
                errorDiv.innerText = resData.error || 'Failed to submit grade.';
                errorDiv.classList.remove('hidden');
            }
        })
        .catch(err => {
            console.error(err);
            errorDiv.innerText = 'Network error or server failed.';
            errorDiv.classList.remove('hidden');
        });
    }
</script>
</body>
</html>

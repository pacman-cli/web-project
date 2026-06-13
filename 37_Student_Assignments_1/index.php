<?php
// 37_Student_Assignments_1/index.php
// Student portal for viewing assignments and submitting homework

require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/design-system.php';
require_once __DIR__ . '/../config/csrf.php';
requireRole('student');

$pdo = require_once __DIR__ . '/../config/db.php';
$studentId = $_SESSION['user_id'];
$studentName = $_SESSION['name'];

$courseId = intval($_GET['course_id'] ?? 0);
$selectedAssignmentId = intval($_GET['assignment_id'] ?? 0);

try {
    // 1. Fetch student's enrolled courses
    $coursesStmt = $pdo->prepare("
        SELECT c.id, c.title
        FROM courses c
        JOIN enrollments e ON c.id = e.course_id
        WHERE e.student_id = :student_id AND e.status = 'approved'
        ORDER BY c.title ASC
    ");
    $coursesStmt->execute(['student_id' => $studentId]);
    $courses = $coursesStmt->fetchAll();

    // Pick first course if none selected
    if ($courseId <= 0 && !empty($courses)) {
        $courseId = intval($courses[0]['id']);
    }

    $assignments = [];
    $selectedAssignment = null;

    if ($courseId > 0) {
        // 2. Fetch assignments with submission status
        $assignmentsStmt = $pdo->prepare("
            SELECT a.id as assignment_id, a.title, a.description, a.due_date, a.max_points,
                   s.id as submission_id, s.file_path, s.submission_text, s.points_earned, 
                   s.feedback, s.status as submission_status, s.submitted_at
            FROM assignments a
            LEFT JOIN submissions s ON a.id = s.assignment_id AND s.student_id = :student_id
            WHERE a.course_id = :course_id
            ORDER BY a.due_date ASC
        ");
        $assignmentsStmt->execute(['student_id' => $studentId, 'course_id' => $courseId]);
        $assignments = $assignmentsStmt->fetchAll();

        // Pick first assignment if none selected
        if ($selectedAssignmentId <= 0 && !empty($assignments)) {
            $selectedAssignmentId = intval($assignments[0]['assignment_id']);
        }

        foreach ($assignments as $asg) {
            if (intval($asg['assignment_id']) === $selectedAssignmentId) {
                $selectedAssignment = $asg;
                break;
            }
        }
    }

} catch (Exception $e) {
    error_log('DB error: ' . $e->getMessage()); die('A database error occurred. Please try again later.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php lms_head('My Assignments', 'student'); ?>
</head>
<body class="bg-background text-on-surface">

<?php lms_sidebar('student', '/37_Student_Assignments_1/index.php'); ?>

<?php lms_topbar('student', 'My Assignments'); ?>

<!-- Main Content Area -->
<main id="lms-main-content" class="lms-main">
    <div class="flex h-full">
        <!-- Left Column: Assignment List -->
        <div class="w-1/3 flex flex-col border-r border-outline-variant bg-white">
            <header class="p-lg border-b border-outline-variant">
                <h1 class="font-h1 text-h1 text-on-surface">Assignments</h1>
                <p class="font-body-md text-on-surface-variant">Stay on top of your practice and submissions</p>
            </header>
            <div class="flex-1 overflow-y-auto p-md space-y-md">
                <?php if (empty($assignments)): ?>
                    <p class="text-on-surface-variant text-body-md text-center py-8">No assignments for this course.</p>
                <?php else: ?>
                    <?php foreach ($assignments as $asg): ?>
                        <a href="?course_id=<?= $courseId ?>&assignment_id=<?= $asg['assignment_id'] ?>" class="block p-md rounded-xl border transition-all <?= $asg['assignment_id'] == $selectedAssignmentId ? 'border-primary bg-primary/5 ring-1 ring-primary/20' : 'border-outline-variant bg-white hover:bg-surface-container-low' ?>">
                            <div class="flex justify-between items-start mb-sm">
                                <?php if ($asg['submission_status'] === 'graded'): ?>
                                    <span class="bg-green-100 text-green-800 text-[10px] font-bold px-2 py-0.5 rounded-full uppercase">Graded</span>
                                <?php elseif ($asg['submission_status'] === 'submitted'): ?>
                                    <span class="bg-blue-100 text-blue-800 text-[10px] font-bold px-2 py-0.5 rounded-full uppercase">Submitted</span>
                                <?php else: ?>
                                    <span class="bg-amber-100 text-amber-800 text-[10px] font-bold px-2 py-0.5 rounded-full uppercase">Pending</span>
                                <?php endif; ?>
                                <span class="font-label-md text-outline">Due: <?= date('M d', strtotime($asg['due_date'])) ?></span>
                            </div>
                            <h3 class="font-semibold text-on-surface mb-xs"><?= htmlspecialchars($asg['title']) ?></h3>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Column: Detail & Submission / Feedback View -->
        <div class="flex-1 bg-surface-container-lowest overflow-y-auto p-lg">
            <?php if ($selectedAssignment): ?>
                <div class="max-w-3xl mx-auto space-y-lg">
                    <div>
                        <h2 class="font-h2 text-h2 text-on-surface mb-2"><?= htmlspecialchars($selectedAssignment['title']) ?></h2>
                        <div class="flex items-center gap-md py-4 border-y border-outline-variant my-md text-body-md">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-error" aria-hidden="true">alarm</span>
                                <span class="font-label-md text-error">Deadline: <?= date('M d, Y H:i', strtotime($selectedAssignment['due_date'])) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Instructions -->
                    <section class="bg-white p-lg rounded-xl border border-outline-variant shadow-sm space-y-sm">
                        <h3 class="font-bold text-on-surface flex items-center gap-2">
                            <span class="material-symbols-outlined" aria-hidden="true">description</span>
                            Instructions
                        </h3>
                        <p class="text-on-surface-variant leading-relaxed">
                            <?= nl2br(htmlspecialchars($selectedAssignment['description'] ?: 'No instructions provided.')) ?>
                        </p>
                    </section>

                    <!-- Feedback block (If graded) -->
                    <?php if ($selectedAssignment['submission_status'] === 'graded'): ?>
                        <section class="bg-green-50/50 p-lg rounded-xl border border-green-200 shadow-sm space-y-md">
                            <div class="flex justify-between items-center">
                                <h3 class="font-bold text-green-950 flex items-center gap-2">
                                    <span class="material-symbols-outlined text-green-700" aria-hidden="true">feedback</span>
                                    Instructor Feedback
                                </h3>
                                <span class="text-h2 font-h2 text-green-700 font-bold"><?= intval($selectedAssignment['points_earned']) ?> / <?= intval($selectedAssignment['max_points']) ?></span>
                            </div>
                            <p class="text-green-900 italic font-body-md">
                                "<?= nl2br(htmlspecialchars($selectedAssignment['feedback'])) ?>"
                            </p>
                        </section>
                    <?php endif; ?>

                    <!-- Submission Form (If NOT submitted/graded or if resubmitting) -->
                    <section class="bg-white p-lg rounded-xl border border-outline-variant shadow-sm space-y-md">
                        <h3 class="font-bold text-on-surface flex items-center gap-2">
                            <span class="material-symbols-outlined" aria-hidden="true">cloud_upload</span>
                            Submission status: <span class="capitalize text-primary"><?= htmlspecialchars($selectedAssignment['submission_status'] ?: 'Not Submitted') ?></span>
                        </h3>

                        <?php if ($selectedAssignment['submission_status'] === 'submitted'): ?>
                            <div class="p-md bg-surface-container-low rounded-lg text-body-md text-on-surface">
                                <p class="font-semibold">Your submission is currently pending review by your instructor.</p>
                                <p class="text-outline text-xs mt-1">Submitted file: <a href="<?= htmlspecialchars($selectedAssignment['file_path']) ?>" download class="text-primary hover:underline font-bold"><?= htmlspecialchars(basename($selectedAssignment['file_path'])) ?></a></p>
                            </div>
                        <?php endif; ?>

                        <?php if ($selectedAssignment['submission_status'] !== 'graded'): ?>
                            <form id="submission-form" onsubmit="submitHomework(event)" class="space-y-md pt-sm border-t border-outline-variant">
                                <input type="hidden" name="assignment_id" value="<?= $selectedAssignment['assignment_id'] ?>"/>
                                
                                <div class="space-y-xs">
                                    <label for="submission_text" class="block font-label-md text-on-surface font-semibold">Comments for Instructor</label>
                                    <textarea id="submission_text" name="submission_text" rows="3" placeholder="Tell the instructor about your practice, parts you struggled with, or dynamic adjustments..." class="w-full bg-surface-container-low border border-outline-variant rounded-lg p-sm font-body-md focus:border-primary outline-none focus:ring-0"></textarea>
                                </div>
                                <div class="space-y-xs">
                                    <label for="submission_file" class="block font-label-md text-on-surface font-semibold">Upload Homework File</label>
                                    <input required type="file" id="submission_file" name="submission_file" class="w-full p-2 border border-outline-variant rounded-lg bg-surface-container-low text-body-md"/>
                                    <p class="text-xs text-outline">Accepted types: PDF, MP3, WAV, MP4, MOV, DOC, DOCX (Max 20MB)</p>
                                </div>
                                
                                <div id="submission-error" class="text-error text-body-md font-semibold hidden" aria-live="polite"></div>
                                <button type="submit" class="w-full bg-primary text-on-primary py-3 rounded-lg font-bold hover:opacity-90 transition-opacity shadow-sm">
                                    <?= $selectedAssignment['submission_status'] === 'submitted' ? 'Resubmit Assignment' : 'Submit Assignment' ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </section>
                </div>
            <?php else: ?>
                <div class="h-full flex items-center justify-center text-center text-on-surface-variant font-body-lg">
                    Select an assignment to view details and submit.
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
    function changeCourse() {
        const val = document.getElementById('course-select').value;
        if (val) {
            window.location.search = '?course_id=' + val;
        }
    }

    function submitHomework(event) {
        event.preventDefault();
        const form = document.getElementById('submission-form');
        const formData = new FormData(form);
        const errorDiv = document.getElementById('submission-error');
        errorDiv.classList.add('hidden');

        fetch(BASE_URL + '/student/submissions.php', {
            method: 'POST',
            headers: {
                'X-CSRF-Token': '<?= csrf_token() ?>'
            },
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('Homework submitted successfully!');
                window.location.reload();
            } else {
                errorDiv.innerText = data.error || 'Submission failed.';
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

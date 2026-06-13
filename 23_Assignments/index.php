<?php
// 23_Assignments/index.php
// Instructor portal for assignments management

require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/design-system.php';
require_once __DIR__ . '/../config/csrf.php';
requireRole('instructor');

$pdo = require_once __DIR__ . '/../config/db.php';
$instructorId = $_SESSION['user_id'];
$instructorName = $_SESSION['name'];

try {
    // 1. Fetch instructor's courses
    $coursesStmt = $pdo->prepare("
        SELECT c.id, c.title
        FROM courses c
        JOIN instructor_assignments ia ON c.id = ia.course_id
        WHERE ia.instructor_id = :instructor_id
        ORDER BY c.title ASC
    ");
    $coursesStmt->execute(['instructor_id' => $instructorId]);
    $courses = $coursesStmt->fetchAll();

    // 2. Fetch assignments list
    $assignmentsStmt = $pdo->prepare("
        SELECT a.*, c.title as course_title,
               (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id) as submission_count,
               (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = a.course_id AND e.status = 'approved') as student_count
        FROM assignments a
        JOIN courses c ON a.course_id = c.id
        JOIN instructor_assignments ia ON c.id = ia.course_id
        WHERE ia.instructor_id = :instructor_id
        ORDER BY a.due_date ASC
    ");
    $assignmentsStmt->execute(['instructor_id' => $instructorId]);
    $assignments = $assignmentsStmt->fetchAll();

} catch (Exception $e) {
    error_log('DB error: ' . $e->getMessage()); die('A database error occurred. Please try again later.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php lms_head('Assignments', 'instructor'); ?>
</head>
<body class="bg-background text-on-surface">

<?php lms_sidebar('instructor', '/23_Assignments/index.php'); ?>

<?php lms_topbar('instructor', 'Assignments'); ?>

<!-- Main Content -->
<main id="lms-main-content" class="lms-main">
    <div class="max-w-container-max mx-auto space-y-lg">
        <!-- Header Section -->
        <div class="flex justify-between items-end">
            <div>
                <h2 class="font-h1 text-h1 text-on-background">Assignments</h2>
                <p class="text-secondary font-body-lg">Create tasks and review student submissions for your courses.</p>
            </div>
            <button onclick="openAssignmentModal()" class="flex items-center gap-2 bg-primary text-on-primary px-5 py-2.5 rounded-lg font-semibold hover:opacity-90 transition-opacity">
                <span class="material-symbols-outlined" aria-hidden="true">add</span>
                Create Assignment
            </button>
        </div>

        <!-- Assignments Table -->
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden custom-shadow">
            <table class="w-full text-left border-collapse">
                <thead class="bg-surface-container-low border-b border-outline-variant">
                    <tr>
                        <th class="px-md py-4 text-label-sm text-secondary uppercase tracking-wider">Assignment Title</th>
                        <th class="px-md py-4 text-label-sm text-secondary uppercase tracking-wider">Course</th>
                        <th class="px-md py-4 text-label-sm text-secondary tracking-wider">Due Date</th>
                        <th class="px-md py-4 text-label-sm text-secondary tracking-wider">Points</th>
                        <th class="px-md py-4 text-label-sm text-secondary tracking-wider">Submissions</th>
                        <th class="px-md py-4 text-label-sm text-secondary tracking-wider text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant">
                    <?php if (empty($assignments)): ?>
                        <tr>
                            <td colspan="6" class="px-md py-8 text-center text-on-surface-variant">No assignments created yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($assignments as $a): ?>
                            <tr class="hover:bg-surface-container-low transition-colors group">
                                <td class="px-md py-5 font-semibold text-on-surface"><?= htmlspecialchars($a['title']) ?></td>
                                <td class="px-md py-5 text-body-md text-secondary"><?= htmlspecialchars($a['course_title']) ?></td>
                                <td class="px-md py-5 text-body-md text-on-surface"><?= date('M d, Y H:i', strtotime($a['due_date'])) ?></td>
                                <td class="px-md py-5 text-body-md text-on-surface"><?= intval($a['max_points']) ?> pts</td>
                                <td class="px-md py-5">
                                    <div class="flex flex-col gap-1 w-32">
                                        <div class="flex justify-between text-label-md">
                                            <span class="font-semibold text-primary"><?= intval($a['submission_count']) ?>/<?= intval($a['student_count']) ?></span>
                                            <span class="text-secondary">
                                                <?= $a['student_count'] > 0 ? round(($a['submission_count'] / $a['student_count']) * 100) : 0 ?>%
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-md py-5 text-right">
                                    <a href="/25_Recording_Reviews/index.php?assignment_id=<?= $a['id'] ?>" class="inline-flex items-center px-3 py-1.5 bg-primary/10 text-primary hover:bg-primary hover:text-white rounded-lg font-label-md text-label-md transition-all">
                                        <span class="material-symbols-outlined text-[18px] mr-1" aria-hidden="true">grading</span>
                                        Review Submissions
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Create Assignment Modal -->
<div id="assignment-modal" class="fixed inset-0 bg-inverse-surface/40 backdrop-blur-sm z-[100] flex items-center justify-center p-md hidden">
    <div class="bg-surface-container-lowest w-full max-w-xl rounded-xl shadow-[0_8px_32px_rgba(0,0,0,0.12)] flex flex-col overflow-hidden border border-outline-variant">
        <!-- Header -->
        <div class="px-xl py-md border-b border-outline-variant flex justify-between items-center bg-surface-container-lowest">
            <h2 class="font-h2 text-h2 text-on-surface font-bold">Create New Assignment</h2>
            <button onclick="closeAssignmentModal()" class="text-outline hover:text-on-surface transition-colors" aria-label="Close assignment dialog">
                <span class="material-symbols-outlined" aria-hidden="true">close</span>
            </button>
        </div>
        <!-- Modal Content -->
        <form id="assignment-form" onsubmit="handleCreateAssignment(event)" class="p-lg space-y-md">
            <!-- Course Selection -->
            <div class="space-y-xs">
                <label for="course_id" class="font-label-md text-on-surface font-semibold">Course</label>
                <select required name="course_id" id="course_id" class="w-full bg-white border border-outline-variant rounded-lg px-base py-2 focus:ring-1 focus:ring-primary focus:border-primary">
                    <option value="">Select a course...</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- Title -->
            <div class="space-y-xs">
                <label for="title" class="font-label-md text-on-surface font-semibold">Assignment Title</label>
                <input required name="title" id="title" class="w-full bg-surface-container-lowest border border-outline-variant rounded-lg px-base py-2 focus:ring-1 focus:ring-primary focus:border-primary" placeholder="e.g., Major Scale Mastery Recording" type="text"/>
            </div>
            <!-- Description -->
            <div class="space-y-xs">
                <label for="description" class="font-label-md text-on-surface font-semibold">Description</label>
                <textarea name="description" id="description" class="w-full bg-white border border-outline-variant rounded-lg px-base py-2 focus:ring-1 focus:ring-primary focus:border-primary" placeholder="A brief summary of the assignment goals..." rows="3"></textarea>
            </div>
            <!-- Due Date & Points -->
            <div class="grid grid-cols-2 gap-md">
                <div class="space-y-xs">
                    <label for="due_date" class="font-label-md text-on-surface font-semibold">Due Date</label>
                    <input required name="due_date" id="due_date" class="w-full bg-white border border-outline-variant rounded-lg px-base py-2 focus:ring-1 focus:ring-primary focus:border-primary" type="datetime-local"/>
                </div>
                <div class="space-y-xs">
                    <label for="max_points" class="font-label-md text-on-surface font-semibold">Max Points</label>
                    <input required name="max_points" id="max_points" class="w-full bg-white border border-outline-variant rounded-lg px-base py-2 focus:ring-1 focus:ring-primary focus:border-primary" type="number" value="100"/>
                </div>
            </div>
            <!-- Attachment (Optional) -->
            <div class="space-y-xs">
                <label for="assignment_file" class="font-label-md text-on-surface font-semibold">Attachment (Optional)</label>
                <input type="file" name="assignment_file" id="assignment_file" accept=".pdf,.mp3,.wav,.mp4,.doc,.docx" class="w-full bg-white border border-outline-variant rounded-lg px-base py-2 focus:ring-1 focus:ring-primary focus:border-primary file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-primary/10 file:text-primary file:font-semibold file:cursor-pointer hover:file:bg-primary/20"/>
                <p class="text-body-sm text-secondary mt-1">PDF, MP3, WAV, MP4, DOC, DOCX (max 20MB)</p>
            </div>

            <div id="assignment-error" class="text-error text-body-md font-semibold hidden" aria-live="polite"></div>

            <!-- Footer Buttons -->
            <div class="pt-md border-t border-outline-variant flex justify-end gap-md">
                <button type="button" onclick="closeAssignmentModal()" class="px-md py-2 border border-outline-variant rounded-lg font-label-md text-on-surface hover:bg-surface-container-highest transition-colors">Cancel</button>
                <button type="submit" class="px-md py-2 rounded-lg bg-primary text-on-primary font-label-md shadow-sm hover:opacity-90 transition-opacity">Create Assignment</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openAssignmentModal() {
        document.getElementById('assignment-form').reset();
        document.getElementById('assignment-error').classList.add('hidden');
        document.getElementById('assignment-modal').classList.remove('hidden');
    }

    function closeAssignmentModal() {
        document.getElementById('assignment-modal').classList.add('hidden');
    }

    function handleCreateAssignment(event) {
        event.preventDefault();
        const form = document.getElementById('assignment-form');
        
        // Format datetime-local ('YYYY-MM-DDTHH:MM') to MySQL datetime ('YYYY-MM-DD HH:MM:00')
        let rawDate = form.due_date.value;
        let formattedDate = rawDate.replace('T', ' ') + ':00';

        const formData = new FormData();
        formData.append('course_id', parseInt(form.course_id.value));
        formData.append('title', form.title.value);
        formData.append('description', form.description.value);
        formData.append('due_date', formattedDate);
        formData.append('max_points', parseInt(form.max_points.value));
        formData.append('_csrf_token', '<?= csrf_token() ?>');
        
        if (form.assignment_file.files.length > 0) {
            formData.append('assignment_file', form.assignment_file.files[0]);
        }

        const errorDiv = document.getElementById('assignment-error');
        errorDiv.classList.add('hidden');

        fetch('/instructor/assignments.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(resData => {
            if (resData.success) {
                window.location.reload();
            } else {
                errorDiv.innerText = resData.error || 'Failed to create assignment.';
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

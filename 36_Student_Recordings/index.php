<?php
// 36_Student_Recordings/index.php
// Student portal for viewing and uploading audio/video recordings

require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/design-system.php';
require_once __DIR__ . '/../config/csrf.php';
requireRole('student');

$pdo = require_once __DIR__ . '/../config/db.php';
$studentId = $_SESSION['user_id'];
$studentName = $_SESSION['name'];

try {
    // 1. Fetch active courses and assignments for dropdown list
    $assignStmt = $pdo->prepare("
        SELECT a.id as assignment_id, a.course_id, a.title as assignment_title, c.title as course_title
        FROM assignments a
        JOIN courses c ON a.course_id = c.id
        JOIN enrollments e ON c.id = e.course_id
        WHERE e.student_id = :student_id AND e.status = 'approved'
        ORDER BY a.due_date ASC
    ");
    $assignStmt->execute(['student_id' => $studentId]);
    $assignments = $assignStmt->fetchAll();

    // 2. Fetch homework recordings (submissions)
    $subStmt = $pdo->prepare("
        SELECT s.id, s.file_path, s.submitted_at, s.points_earned, s.feedback, s.status, a.title as assignment_title, c.title as course_title
        FROM submissions s
        JOIN assignments a ON s.assignment_id = a.id
        JOIN courses c ON a.course_id = c.id
        WHERE s.student_id = :student_id
          AND (s.file_path LIKE '%.mp3' OR s.file_path LIKE '%.wav' OR s.file_path LIKE '%.mp4' OR s.file_path LIKE '%.mov')
        ORDER BY s.submitted_at DESC
    ");
    $subStmt->execute(['student_id' => $studentId]);
    $homeworkRecordings = $subStmt->fetchAll();

    // 3. Fetch personal practice recordings (general uploads)
    $pracStmt = $pdo->prepare("
        SELECT id, original_name, file_path, uploaded_at, file_type
        FROM user_uploads
        WHERE user_id = :student_id AND file_type IN ('audio', 'video')
        ORDER BY uploaded_at DESC
    ");
    $pracStmt->execute(['student_id' => $studentId]);
    $practiceRecordings = $pracStmt->fetchAll();

    // Fetch student experience level
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
<?php lms_head('My Recordings', 'student'); ?>
</head>
<body class="bg-surface text-on-surface">

<?php lms_sidebar('student', '/36_Student_Recordings/index.php'); ?>

<?php lms_topbar('student', 'My Recordings'); ?>

<!-- Main Content -->
<main id="lms-main-content" class="lms-main">
    <div class="max-w-container-max mx-auto space-y-lg">
        <!-- Header Section -->
        <section>
            <h2 class="font-h1 text-h1 text-on-surface">My Recordings</h2>
            <p class="font-body-lg text-on-surface-variant mt-1">Manage your performance uploads and review instructor feedback.</p>
        </section>

        <!-- Upload Section -->
        <section class="bg-surface-container-lowest rounded-xl border border-outline-variant card-shadow overflow-hidden">
            <div class="p-md border-b border-outline-variant flex items-center justify-between">
                <h3 class="font-h3 text-h3 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary" aria-hidden="true">cloud_upload</span>
                    Upload New Recording
                </h3>
            </div>
            <div class="p-lg">
                <form id="recording-form" onsubmit="handleRecordingUpload(event)" class="grid grid-cols-1 lg:grid-cols-3 gap-lg">
                    <div class="lg:col-span-2">
                        <div class="border-2 border-dashed border-outline-variant rounded-xl bg-surface-container-low hover:bg-surface-container transition-colors flex flex-col items-center justify-center p-lg cursor-pointer group relative">
                            <input required type="file" id="recording-file" name="upload_file" aria-label="Upload recording file" class="absolute inset-0 opacity-0 cursor-pointer"/>
                            <div class="flex gap-4 mb-4">
                                <span class="material-symbols-outlined text-4xl text-on-surface-variant group-hover:text-primary transition-colors" aria-hidden="true">videocam</span>
                                 <span class="material-symbols-outlined text-4xl text-on-surface-variant group-hover:text-primary transition-colors" aria-hidden="true">audio_file</span>
                            </div>
                            <p class="font-body-lg font-bold text-on-surface" id="upload-status-text">Click to choose a file</p>
                            <p class="font-label-md text-on-surface-variant mt-1 text-center">Supported formats: Video (mp4, mov) and Audio (wav, mp3). Max file size: 20MB.</p>
                        </div>
                    </div>
                    <div class="space-y-md">
                        <div>
                            <label for="link-destination" class="block font-label-md text-on-surface-variant mb-2 font-semibold">Link to Destination</label>
                            <select id="link-destination" onchange="toggleFormAction()" class="w-full bg-surface border border-outline-variant rounded-lg p-3 font-body-md focus:ring-primary focus:border-primary">
                                <option value="personal">Personal Practice (General Vault)</option>
                                <?php foreach ($assignments as $asg): ?>
                                    <option value="<?= $asg['assignment_id'] ?>">Assignment: <?= htmlspecialchars($asg['assignment_title']) ?> (<?= htmlspecialchars($asg['course_title']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="recording-title" class="block font-label-md text-on-surface-variant mb-2 font-semibold">Recording Title / Comments</label>
                            <input required id="recording-title" name="title" class="w-full bg-surface border border-outline-variant rounded-lg p-3 font-body-md focus:ring-primary focus:border-primary" placeholder="e.g. Chopin Nocturne - Take 3" type="text"/>
                        </div>
                        <div id="upload-error" class="text-error text-body-md font-semibold hidden" aria-live="polite"></div>
                        <button type="submit" class="w-full bg-primary text-white py-3 rounded-lg font-h3 hover:opacity-90 transition-opacity flex items-center justify-center gap-2">
                            Process Upload
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <!-- Recordings List -->
        <section class="space-y-md">
            <h3 class="font-h2 text-h2">Recent Uploads</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-md">
                <!-- Homework submissions -->
                <?php foreach ($homeworkRecordings as $rec): ?>
                    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl card-shadow group overflow-hidden">
                        <div class="aspect-video bg-surface-container flex items-center justify-center relative">
                            <span class="material-symbols-outlined text-6xl text-outline-variant" aria-hidden="true">audio_file</span>
                            <div class="absolute bottom-3 left-3 bg-primary text-white px-2 py-1 rounded-md font-label-sm uppercase tracking-wider text-xs">Homework</div>
                        </div>
                        <div class="p-md space-y-3">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h4 class="font-body-lg font-bold text-on-surface truncate max-w-[180px]"><?= htmlspecialchars(basename($rec['file_path'])) ?></h4>
                                    <p class="font-label-md text-on-surface-variant flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[16px]" aria-hidden="true">history</span> <?= date('M d, Y', strtotime($rec['submitted_at'])) ?>
                                    </p>
                                </div>
                                <span class="bg-primary-container text-primary font-label-sm px-2 py-1 rounded-full text-xs font-semibold uppercase"><?= htmlspecialchars($rec['status']) ?></span>
                            </div>
                            <div class="flex items-center gap-2 py-1 px-2 bg-surface-container rounded-lg">
                                <span class="material-symbols-outlined text-primary text-[18px]" aria-hidden="true">assignment</span>
                                <span class="font-label-md text-on-surface truncate"><?= htmlspecialchars($rec['assignment_title']) ?></span>
                            </div>
                            <?php if ($rec['status'] === 'graded'): ?>
                                <div class="flex items-center justify-between pt-2 border-t border-outline-variant">
                                    <div>
                                        <p class="font-label-sm text-on-surface-variant">Grade Evaluated</p>
                                        <p class="font-label-md font-bold text-primary">Score: <?= intval($rec['points_earned']) ?> pts</p>
                                    </div>
                                    <a href="<?= BASE_URL ?>/37_Student_Assignments_1/index.php?course_id=<?= intval($rec['course_id']) ?>&assignment_id=<?= intval($rec['assignment_id']) ?>" class="text-primary font-label-md hover:underline font-bold">Feedback</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Personal practice recordings -->
                <?php foreach ($practiceRecordings as $rec): ?>
                    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl card-shadow group overflow-hidden">
                        <div class="aspect-video bg-surface-container flex items-center justify-center relative">
                            <span class="material-symbols-outlined text-6xl text-outline-variant" aria-hidden="true"><?= $rec['file_type'] === 'video' ? 'video_library' : 'audio_file' ?></span>
                            <div class="absolute bottom-3 left-3 bg-secondary text-white px-2 py-1 rounded-md font-label-sm uppercase tracking-wider text-xs">Practice</div>
                        </div>
                        <div class="p-md space-y-3">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h4 class="font-body-lg font-bold text-on-surface truncate max-w-[180px]"><?= htmlspecialchars($rec['original_name']) ?></h4>
                                    <p class="font-label-md text-on-surface-variant flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[16px]" aria-hidden="true">history</span> <?= date('M d, Y', strtotime($rec['uploaded_at'])) ?>
                                    </p>
                                </div>
                                <span class="bg-secondary-container text-on-secondary-container font-label-sm px-2 py-1 rounded-full text-xs font-semibold uppercase">Vaulted</span>
                            </div>
                            <div class="flex items-center justify-between pt-2 border-t border-outline-variant">
                                <a href="<?= htmlspecialchars($rec['file_path']) ?>" download class="text-primary font-label-md hover:underline flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm" aria-hidden="true">download</span> Download File
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</main>

<script>
    const fileInput = document.getElementById('recording-file');
    const statusText = document.getElementById('upload-status-text');

    fileInput.addEventListener('change', () => {
        if (fileInput.files.length > 0) {
            statusText.innerText = `Selected file: ${fileInput.files[0].name}`;
        } else {
            statusText.innerText = "Click to choose a file";
        }
    });

    function handleRecordingUpload(event) {
        event.preventDefault();
        const dest = document.getElementById('link-destination').value;
        const errorDiv = document.getElementById('upload-error');
        errorDiv.classList.add('hidden');

        const formData = new FormData();
        const file = fileInput.files[0];
        if (!file) {
            alert('Please select a file to upload.');
            return;
        }

        if (dest === 'personal') {
            // Personal Practice -> call api/upload.php
            formData.append('file', file);
            fetch(BASE_URL + '/api/upload.php', {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': '<?= csrf_token() ?>'
                },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Practice recording uploaded to personal vault successfully!');
                    window.location.reload();
                } else {
                    errorDiv.innerText = data.error || 'Upload failed.';
                    errorDiv.classList.remove('hidden');
                }
            })
            .catch(err => {
                console.error(err);
                errorDiv.innerText = 'Network error during upload.';
                errorDiv.classList.remove('hidden');
            });
        } else {
            // Assignment Submission -> call student/submissions.php
            formData.append('assignment_id', dest);
            formData.append('submission_text', document.getElementById('recording-title').value);
            formData.append('submission_file', file);
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
                    alert('Assignment homework recording submitted successfully!');
                    window.location.reload();
                } else {
                    errorDiv.innerText = data.error || 'Upload failed.';
                    errorDiv.classList.remove('hidden');
                }
            })
            .catch(err => {
                console.error(err);
                errorDiv.innerText = 'Network error during upload.';
                errorDiv.classList.remove('hidden');
            });
        }
    }
</script>
</body>
</html>

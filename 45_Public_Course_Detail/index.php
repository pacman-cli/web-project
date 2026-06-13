<?php
// 45_Public_Course_Detail/index.php
// Dynamic public course detail page

session_start();

$pdo = require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/design-system.php';
require_once __DIR__ . '/../config/csrf.php';

$courseId = intval($_GET['course_id'] ?? 0);
if ($courseId <= 0) {
    header('Location: ' . BASE_URL . '/42_Public_Course_Catalog/index.php');
    exit;
}

$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $isLoggedIn ? $_SESSION['role'] : 'guest';
$studentId = $isLoggedIn ? $_SESSION['user_id'] : 0;

try {
    // 1. Fetch course details
    $courseStmt = $pdo->prepare("
        SELECT c.id as course_id, c.title, c.description, c.difficulty, c.price, c.status, i.name as instrument_name
        FROM courses c
        LEFT JOIN instruments i ON c.instrument_id = i.id
        WHERE c.id = :course_id
    ");
    $courseStmt->execute(['course_id' => $courseId]);
    $course = $courseStmt->fetch();

    if (!$course || ($course['status'] !== 'published' && $userRole !== 'admin')) {
        die("Course not found or not open for public viewing.");
    }

    // 2. Fetch syllabus/classes
    $classesStmt = $pdo->prepare("
        SELECT title, description 
        FROM course_classes 
        WHERE course_id = :course_id 
        ORDER BY order_index ASC
    ");
    $classesStmt->execute(['course_id' => $courseId]);
    $syllabus = $classesStmt->fetchAll();

    // 3. Fetch weekly schedule
    $schedStmt = $pdo->prepare("
        SELECT day_of_week, start_time, end_time, location_type, location_detail 
        FROM schedules 
        WHERE course_id = :course_id
    ");
    $schedStmt->execute(['course_id' => $courseId]);
    $schedules = $schedStmt->fetchAll();

    // 4. Fetch instructor
    $instStmt = $pdo->prepare("
        SELECT u.name, u.email, inst.bio, inst.specialization
        FROM users u
        JOIN instructors inst ON u.id = inst.user_id
        JOIN instructor_assignments ia ON u.id = ia.instructor_id
        WHERE ia.course_id = :course_id
        LIMIT 1
    ");
    $instStmt->execute(['course_id' => $courseId]);
    $instructor = $instStmt->fetch();

    // 5. Check student enrollment status for this course
    $enrollmentStatus = null;
    if ($isLoggedIn && $userRole === 'student') {
        $enrollStmt = $pdo->prepare("
            SELECT status FROM enrollments 
            WHERE student_id = :student_id AND course_id = :course_id
        ");
        $enrollStmt->execute(['student_id' => $studentId, 'course_id' => $courseId]);
        $enrollmentStatus = $enrollStmt->fetchColumn() ?: null;
    }

} catch (Exception $e) {
    error_log('DB error: ' . $e->getMessage()); die('A database error occurred. Please try again later.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php lms_head('Course Details', 'guest'); ?>
</head>
<body class="text-on-surface">
<?php lms_public_navbar('/45_Public_Course_Detail/index.php'); ?>

<main id="lms-main-content" class="lms-main lms-page--full">
    <!-- Hero Section: Course Header -->
    <section class="bg-white border-b border-outline-variant">
        <div class="max-w-container-max mx-auto px-lg py-xl">
            <div class="flex flex-col md:flex-row gap-xl items-center">
                <div class="flex-1 space-y-md">
                    <nav class="flex gap-xs text-secondary font-label-md text-label-md">
                        <a href="<?= BASE_URL ?>/42_Public_Course_Catalog/index.php" class="hover:underline">Catalog</a>
                        <span>/</span>
                        <span><?= htmlspecialchars($course['instrument_name'] ?? 'General') ?></span>
                    </nav>
                    <h1 class="font-h1 text-h1 text-on-surface"><?= htmlspecialchars($course['title']) ?></h1>
                    <p class="font-body-lg text-body-lg text-on-surface-variant max-w-2xl">
                        <?= htmlspecialchars($course['description'] ?: 'No description provided.') ?>
                    </p>
                    <div class="flex flex-wrap gap-md pt-md">
                        <div class="flex items-center gap-xs px-sm py-xs bg-surface-container-low rounded-full border border-outline-variant">
                            <span aria-hidden="true" class="material-symbols-outlined text-[18px] text-primary">piano</span>
                            <span class="font-label-md text-label-md"><?= htmlspecialchars($course['instrument_name'] ?? 'General') ?></span>
                        </div>
                        <div class="flex items-center gap-xs px-sm py-xs bg-surface-container-low rounded-full border border-outline-variant">
                            <span aria-hidden="true" class="material-symbols-outlined text-[18px] text-primary">equalizer</span>
                            <span class="font-label-md text-label-md uppercase"><?= htmlspecialchars($course['difficulty']) ?></span>
                        </div>
                    </div>
                </div>
                <div class="w-full md:w-[480px] h-[320px] rounded-xl overflow-hidden card-shadow border border-outline-variant relative">
                    <img alt="Course Image" class="w-full h-full object-cover" width="960" height="320" src="https://lh3.googleusercontent.com/aida-public/AB6AXuB8ju4AYHCmCUtUZeIs5kV4_7kp8c7viUm4aXHX3wJE55XXPUxgeRk1jDPSoLgkWMllfOutxGE2a-sdznCKndn_eDJZXNTS5HboieJA9RWdK00ZjTHzyGZWJX7X3WEBdQpGrtm67-BStpxlvlnP7FPfoAeEPAJOTu56mJWwAz7ETrvGWfUZdhCxIXIA61-1mQXOLo5mP9n9CvycBa49GRfitqc0nlaBch9WfqnCq3_SabTlAxlO4okrVI2JpMdvsBXGzjun6H7UPio"/>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content Area: Bento Grid Style -->
    <section class="max-w-container-max mx-auto px-lg py-xl">
        <div class="grid grid-cols-12 gap-md">
            <!-- Syllabus Column -->
            <div class="col-span-12 lg:col-span-8 space-y-md">
                <div class="bg-white p-lg rounded-xl border border-outline-variant card-shadow">
                    <h2 class="font-h2 text-h2 mb-lg">Syllabus Outline</h2>
                    <div class="space-y-md">
                        <?php if (empty($syllabus)): ?>
                            <p class="text-on-surface-variant font-body-md">Syllabus outline is currently in draft. Contact instructor for info.</p>
                        <?php else: ?>
                            <?php foreach ($syllabus as $index => $phase): ?>
                                <div class="flex gap-md group">
                                    <div class="flex flex-col items-center">
                                        <div class="w-10 h-10 rounded-full bg-primary-fixed flex items-center justify-center font-h3 text-primary"><?= ($index + 1) ?></div>
                                        <div class="w-[1px] h-full bg-outline-variant group-last:bg-transparent mt-xs"></div>
                                    </div>
                                    <div class="pb-lg">
                                        <h3 class="font-h3 text-h3"><?= htmlspecialchars($phase['title']) ?></h3>
                                        <p class="font-body-md text-body-md text-on-surface-variant mt-xs">
                                            <?= htmlspecialchars($phase['description']) ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Schedule Preview -->
                <div class="bg-white rounded-xl border border-outline-variant card-shadow overflow-hidden">
                    <div class="p-lg border-b border-outline-variant flex justify-between items-center bg-surface-container-low">
                        <h2 class="font-h2 text-h2">Weekly Schedule</h2>
                        <span class="text-secondary font-label-md text-label-md">Term: Active</span>
                    </div>
                    <div class="p-lg">
                        <div class="space-y-sm">
                            <?php if (empty($schedules)): ?>
                                <p class="text-on-surface-variant font-body-md">Schedule detail is currently TBD. Check back soon!</p>
                            <?php else: ?>
                                <?php foreach ($schedules as $sched): ?>
                                    <div class="flex items-center justify-between p-sm border-b border-outline-variant last:border-0">
                                        <div class="flex gap-md items-center">
                                            <div class="w-20 text-center">
                                                <p class="font-label-sm text-label-sm text-secondary uppercase"><?= substr($sched['day_of_week'], 0, 3) ?></p>
                                                <p class="font-h3 text-h3"><?= date('H:i', strtotime($sched['start_time'])) ?></p>
                                            </div>
                                            <div>
                                                <p class="font-h3 text-h3"><?= $sched['location_type'] === 'online' ? 'Online Video Class' : 'Physical Class Session' ?></p>
                                                <p class="font-label-md text-label-md text-on-surface-variant"><?= htmlspecialchars($sched['location_detail']) ?></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar Column -->
            <div class="col-span-12 lg:col-span-4 space-y-md">
                <!-- Instructor Card -->
                <?php if ($instructor): ?>
                    <div class="bg-white p-lg rounded-xl border border-outline-variant card-shadow">
                        <h3 class="font-label-sm text-label-sm text-secondary uppercase mb-md tracking-wider">Faculty</h3>
                        <div class="flex gap-md items-center mb-md">
                            <div class="w-16 h-16 rounded-full bg-primary/10 flex items-center justify-center font-bold text-primary flex-shrink-0">
                                <?= htmlspecialchars(substr($instructor['name'], 0, 2)) ?>
                            </div>
                            <div>
                                <p class="font-h3 text-h3 leading-tight"><?= htmlspecialchars($instructor['name']) ?></p>
                                <p class="font-label-md text-label-md text-on-surface-variant"><?= htmlspecialchars($instructor['specialization'] ?: 'Music Faculty') ?></p>
                            </div>
                        </div>
                        <p class="font-body-md text-body-md text-on-surface-variant leading-relaxed">
                            <?= htmlspecialchars($instructor['bio'] ?: 'No bio details provided yet.') ?>
                        </p>
                    </div>
                <?php endif; ?>

                <!-- Enroll CTA Card -->
                <div class="bg-primary text-on-primary p-lg rounded-xl border border-primary card-shadow">
                    <h3 class="font-h2 text-h2 mb-sm">Secure Your Seat</h3>
                    <p class="font-body-md text-body-md mb-lg opacity-90">Enrollment is limited. Apply today to secure instructor schedule slots.</p>
                    
                    <?php if (!$isLoggedIn): ?>
                        <a href="<?= BASE_URL ?>/auth/login.php" class="w-full bg-white text-primary text-center font-h3 py-sm rounded-lg hover:bg-surface-container-low active:scale-95 transition-all block font-semibold">Login to Enroll</a>
                    <?php elseif ($userRole === 'student'): ?>
                        <?php if ($enrollmentStatus === 'approved'): ?>
                            <span class="w-full bg-green-600 text-white text-center font-h3 py-sm rounded-lg block font-semibold uppercase">Already Enrolled</span>
                        <?php elseif ($enrollmentStatus === 'pending'): ?>
                            <span class="w-full bg-amber-600 text-white text-center font-h3 py-sm rounded-lg block font-semibold uppercase">Request Pending Review</span>
                        <?php else: ?>
                            <button onclick="requestEnrollment(<?= $courseId ?>)" class="w-full bg-white text-primary font-h3 py-sm rounded-lg hover:bg-surface-container-low active:scale-95 transition-all font-semibold">Submit Enrollment Request</button>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-xs text-on-primary/80 italic block text-center">Student enrollment only</span>
                    <?php endif; ?>
                </div>

                <!-- Price and Credits -->
                <div class="grid grid-cols-1 gap-md">
                    <div class="bg-white p-md rounded-xl border border-outline-variant card-shadow text-center">
                        <p class="font-label-sm text-label-sm text-secondary uppercase">Tuition Fee</p>
                        <p class="font-h3 text-h3 text-primary">$<?= number_format($course['price'], 2) ?></p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<footer class="w-full py-lg mt-xl bg-surface-container-highest border-t border-outline-variant">
    <div class="flex flex-col md:flex-row justify-between items-center px-lg max-w-container-max mx-auto gap-md">
        <div>
            <span class="font-h3 text-h3 text-primary font-bold">Lyra Academy</span>
            <p class="font-label-sm text-label-sm text-on-surface-variant mt-xs">© 2026 Lyra Academy of Music. All rights reserved.</p>
        </div>
    </div>
</footer>

<script>
    function requestEnrollment(courseId) {
        document.getElementById('enroll-course-id').value = courseId;
        document.getElementById('enroll-resume').value = '';
        document.getElementById('enroll-file-label').textContent = 'Choose PDF (required)';
        document.getElementById('enroll-modal').classList.remove('hidden');
    }

    function closeEnrollModal() {
        document.getElementById('enroll-modal').classList.add('hidden');
    }

    function submitEnrollment() {
        const fileInput = document.getElementById('enroll-resume');
        const courseId = document.getElementById('enroll-course-id').value;

        if (!fileInput.files.length) {
            alert('Please upload your resume (PDF) to proceed.');
            return;
        }

        const file = fileInput.files[0];
        if (file.type !== 'application/pdf') {
            alert('Resume must be a PDF file.');
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            alert('Resume file size must be under 5MB.');
            return;
        }

        const formData = new FormData();
        formData.append('course_id', courseId);
        formData.append('resume', file);

        const submitBtn = document.getElementById('enroll-submit-btn');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Submitting…';

        fetch(BASE_URL + '/student/enroll.php', {
            method: 'POST',
            headers: { 'X-CSRF-Token': '<?= csrf_token() ?>' },
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(data.message || 'Request sent successfully!');
                closeEnrollModal();
                window.location.reload();
            } else {
                alert(data.error || 'Failed to request enrollment.');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Submit Request';
            }
        })
        .catch(err => {
            console.error(err);
            alert('Failed to connect to server.');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Submit Request';
        });
    }
</script>

<!-- Enrollment Modal -->
<div id="enroll-modal" class="fixed inset-0 z-[100] flex items-center justify-center hidden">
    <button class="absolute inset-0 bg-on-background/40 backdrop-blur-sm w-full h-full" onclick="closeEnrollModal()" aria-label="Close modal"></button>
    <div class="relative bg-surface-container-lowest w-full max-w-md max-h-[90vh] overflow-y-auto rounded-xl shadow-xl flex flex-col">
        <div class="px-xl py-md border-b border-outline-variant flex justify-between items-center bg-surface-container-low">
            <h2 class="text-h2 font-h2 text-on-surface">Enrollment Request</h2>
            <button onclick="closeEnrollModal()" class="p-xs hover:bg-surface-container-high rounded-full transition-colors text-secondary" aria-label="Close modal">
                <span class="material-symbols-outlined" aria-hidden="true">close</span>
            </button>
        </div>
        <div class="p-xl space-y-md">
            <input type="hidden" id="enroll-course-id" value=""/>
            <p class="text-body-md text-secondary">Upload your resume (PDF) to request enrollment. The administrator will review your application.</p>
            <div>
                <label class="text-label-md font-label-md text-on-surface-variant block mb-xs">Resume (PDF, max 5MB)</label>
                <label for="enroll-resume" class="flex items-center gap-sm px-md py-sm border-2 border-dashed border-outline-variant rounded-lg cursor-pointer hover:border-primary hover:bg-primary-fixed/20 transition-all">
                    <span class="material-symbols-outlined text-secondary" aria-hidden="true">upload_file</span>
                    <span id="enroll-file-label" class="text-body-sm text-secondary">Choose PDF (required)</span>
                    <input type="file" id="enroll-resume" accept=".pdf,application/pdf" class="hidden"/>
                </label>
            </div>
        </div>
        <div class="px-xl py-md border-t border-outline-variant flex justify-end gap-sm bg-surface-container-low">
            <button onclick="closeEnrollModal()" class="px-md py-sm border border-outline text-secondary rounded-lg font-label-md text-label-md hover:bg-surface-container-high transition-all">Cancel</button>
            <button onclick="submitEnrollment()" id="enroll-submit-btn" class="px-md py-sm bg-primary text-on-primary rounded-lg font-label-md text-label-md hover:bg-primary-container transition-all shadow-sm">Submit Request</button>
        </div>
    </div>
</div>
</body>
</html>

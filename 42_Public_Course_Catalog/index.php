<?php
// 42_Public_Course_Catalog/index.php
// Dynamic public course catalog

session_start();

$pdo = require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/design-system.php';
require_once __DIR__ . '/../config/csrf.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $isLoggedIn ? $_SESSION['role'] : 'guest';
$studentId = $isLoggedIn ? $_SESSION['user_id'] : 0;

try {
    // 1. Fetch published courses
    $coursesStmt = $pdo->prepare("
        SELECT c.id as course_id, c.title, c.description, c.difficulty, c.price, i.name as instrument_name
        FROM courses c
        LEFT JOIN instruments i ON c.instrument_id = i.id
        WHERE c.status = 'published'
        ORDER BY c.title ASC
    ");
    $coursesStmt->execute();
    $publishedCourses = $coursesStmt->fetchAll();

    $coursesList = [];
    foreach ($publishedCourses as $c) {
        $courseId = $c['course_id'];

        // Get instructor details
        $instStmt = $pdo->prepare("
            SELECT u.name 
            FROM users u
            JOIN instructor_assignments ia ON u.id = ia.instructor_id
            WHERE ia.course_id = :course_id
            LIMIT 1
        ");
        $instStmt->execute(['course_id' => $courseId]);
        $instructorName = $instStmt->fetchColumn() ?: 'TBD';

        // Check student enrollment status for this course
        $enrollmentStatus = null;
        if ($isLoggedIn && $userRole === 'student') {
            $enrollStmt = $pdo->prepare("
                SELECT status FROM enrollments 
                WHERE student_id = :student_id AND course_id = :course_id
            ");
            $enrollStmt->execute(['student_id' => $studentId, 'course_id' => $courseId]);
            $enrollmentStatus = $enrollStmt->fetchColumn() ?: null;
        }

        $c['instructor_name'] = $instructorName;
        $c['enrollment_status'] = $enrollmentStatus;
        $coursesList[] = $c;
    }

    // 2. Fetch all instrument categories for filter bar
    $instsStmt = $pdo->query("SELECT id, name FROM instruments ORDER BY name ASC");
    $instruments = $instsStmt->fetchAll();

} catch (Exception $e) {
    error_log('DB error: ' . $e->getMessage()); die('A database error occurred. Please try again later.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php lms_head('Course Catalog', 'guest'); ?>
</head>
<body class="bg-background text-on-background font-body-md selection:bg-primary-container selection:text-on-primary-container">
<?php lms_public_navbar('/42_Public_Course_Catalog/index.php'); ?>

<main id="lms-main-content" class="lms-main lms-page--full">
    <!-- Hero Section -->
    <section class="py-xl bg-surface-container-lowest">
        <div class="max-w-container-max mx-auto px-md text-center">
            <h1 class="font-h1 text-h1 text-on-surface mb-base">Our Courses</h1>
            <p class="font-body-lg text-body-lg text-on-surface-variant max-w-2xl mx-auto">
                Master the art of performance with our world-class faculty. From foundational theory to advanced masterclasses, find the curriculum that matches your artistic journey.
            </p>
        </div>
    </section>

    <!-- Filter Section -->
    <section class="sticky top-[64px] bg-surface/80 backdrop-blur-md z-40 border-b border-outline-variant/30 py-md">
        <div class="max-w-container-max mx-auto px-md">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-md">
                <div class="relative flex-1 max-w-md">
                    <span aria-hidden="true" class="material-symbols-outlined absolute left-sm top-1/2 -translate-y-1/2 text-outline">search</span>
                    <label for="course-search" class="sr-only">Search Courses</label>
                    <input id="course-search" oninput="filterCourses()" class="w-full pl-[44px] pr-md py-base bg-surface-container-lowest border border-surface-variant rounded-lg focus:outline-none focus:border-primary font-body-md text-body-md" placeholder="Search courses, instructors, or instruments..." type="text"/>
                </div>
                <div class="flex items-center gap-base overflow-x-auto pb-xs md:pb-0 no-scrollbar">
                    <button onclick="filterInstrument('all')" class="whitespace-nowrap px-md py-xs rounded-full bg-primary text-on-primary font-label-md text-label-md instrument-filter-btn">All</button>
                    <?php foreach ($instruments as $inst): ?>
                        <button onclick="filterInstrument('<?= htmlspecialchars(strtolower($inst['name'])) ?>')" class="whitespace-nowrap px-md py-xs rounded-full bg-surface-container-high text-on-surface-variant hover:bg-secondary-container hover:text-on-secondary-container transition-colors font-label-md text-label-md instrument-filter-btn"><?= htmlspecialchars($inst['name']) ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Course Grid -->
    <section class="py-xl">
        <div class="max-w-container-max mx-auto px-md">
            <div id="course-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-lg">
                <?php if (empty($coursesList)): ?>
                    <p class="col-span-full text-center text-on-surface-variant py-8">No published courses available.</p>
                <?php else: ?>
                    <?php foreach ($coursesList as $c): ?>
                        <div class="course-card bg-surface-container-lowest rounded-xl border border-surface-variant shadow-sm hover:shadow-md transition-all flex flex-col overflow-hidden" data-instrument="<?= htmlspecialchars(strtolower($c['instrument_name'] ?? '')) ?>" data-title="<?= htmlspecialchars(strtolower($c['title'])) ?>" data-instructor="<?= htmlspecialchars(strtolower($c['instructor_name'])) ?>">
                            <div class="aspect-video w-full overflow-hidden">
                                <img alt="<?= htmlspecialchars($c['title']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" src="https://lh3.googleusercontent.com/aida-public/AB6AXuAA8Pa64yeqtBzASCPIox0-qmFGg5-kJqBkcmdu1MGCwR8tfKyXUi7twfD9d6cHheryDcL-XW7Epctb4LZjqmV-aC5e2mY1Qw_A9k2ZnCCt-PoCtFQrEJWZPsIrGJgNSNbZaCQXYgb6zDc1FHAOG0OmfoSqymnA7USrNoKDUBRxsu8wrnv7y7BYeRFmSXkeePyV860YiMjPEngk6LidHKhtbq2fNw8d2PZN1zFX_ZtZmdjXbih9w4JBR8tQKXqfbY90HuE8f-6zlus"/>
                            </div>
                            <div class="p-md flex flex-col flex-1">
                                <div class="flex items-center gap-base mb-sm">
                                    <span class="px-base py-[2px] bg-secondary-container text-on-secondary-container rounded-full font-label-sm text-label-sm uppercase font-bold"><?= htmlspecialchars($c['instrument_name'] ?? 'General') ?></span>
                                    <span class="px-base py-[2px] bg-tertiary-fixed text-on-tertiary-fixed rounded-full font-label-sm text-label-sm uppercase font-bold"><?= htmlspecialchars($c['difficulty']) ?></span>
                                </div>
                                <h3 class="font-h3 text-h3 text-on-surface mb-xs"><?= htmlspecialchars($c['title']) ?></h3>
                                <p class="text-xs text-secondary-container font-semibold mb-sm">Instructor: <?= htmlspecialchars($c['instructor_name']) ?></p>
                                <p class="font-body-md text-body-md text-on-surface-variant mb-lg line-clamp-2">
                                    <?= htmlspecialchars($c['description'] ?: 'No description provided.') ?>
                                </p>
                                <div class="mt-auto flex items-center justify-between border-t border-outline-variant pt-md">
                                    <span class="font-bold text-lg text-primary">$<?= number_format($c['price'], 2) ?></span>
                                    
                                    <?php if (!$isLoggedIn): ?>
                                        <a href="<?= BASE_URL ?>/auth/login.php" class="px-md py-2 bg-primary text-on-primary font-bold text-xs rounded-lg active:scale-95 transition-all">Enroll Now</a>
                                    <?php elseif ($userRole === 'student'): ?>
                                        <?php if ($c['enrollment_status'] === 'approved'): ?>
                                            <span class="px-3 py-1 bg-green-100 text-green-700 font-bold text-xs rounded-full uppercase">Enrolled</span>
                                        <?php elseif ($c['enrollment_status'] === 'pending'): ?>
                                            <span class="px-3 py-1 bg-amber-100 text-amber-700 font-bold text-xs rounded-full uppercase">Requested</span>
                                        <?php elseif ($c['enrollment_status'] === 'rejected'): ?>
                                            <button onclick="requestEnrollment(<?= $c['course_id'] ?>)" class="px-md py-2 bg-red-600 hover:bg-red-700 text-white font-bold text-xs rounded-lg active:scale-95 transition-all">Re-Request</button>
                                        <?php else: ?>
                                            <button onclick="requestEnrollment(<?= $c['course_id'] ?>)" class="px-md py-2 bg-primary text-on-primary font-bold text-xs rounded-lg active:scale-95 transition-all">Enroll Now</button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-xs text-on-surface-variant italic">Students only</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<script>
    let activeInstrument = 'all';

    function filterCourses() {
        const query = document.getElementById('course-search').value.toLowerCase();
        const cards = document.querySelectorAll('.course-card');
        
        cards.forEach(card => {
            const inst = card.getAttribute('data-instrument');
            const title = card.getAttribute('data-title');
            const instructor = card.getAttribute('data-instructor');
            
            const matchInstrument = (activeInstrument === 'all' || inst === activeInstrument);
            const matchQuery = (title.includes(query) || instructor.includes(query) || inst.includes(query));
            
            if (matchInstrument && matchQuery) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    }

    function filterInstrument(instName) {
        activeInstrument = instName;
        
        // Highlight active filter button
        const buttons = document.querySelectorAll('.instrument-filter-btn');
        buttons.forEach(btn => {
            if (btn.innerText.toLowerCase() === instName || (instName === 'all' && btn.innerText === 'All')) {
                btn.classList.remove('bg-surface-container-high', 'text-on-surface-variant');
                btn.classList.add('bg-primary', 'text-on-primary');
            } else {
                btn.classList.remove('bg-primary', 'text-on-primary');
                btn.classList.add('bg-surface-container-high', 'text-on-surface-variant');
            }
        });

        filterCourses();
    }

    function requestEnrollment(courseId) {
        if (!confirm('Would you like to request enrollment in this course? Your request will be sent to the administrator.')) return;

        fetch(BASE_URL + '/student/enroll.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': '<?= csrf_token() ?>'
            },
            body: JSON.stringify({ course_id: courseId })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(data.message || 'Request sent successfully!');
                window.location.reload();
            } else {
                alert(data.error || 'Failed to request enrollment.');
            }
        })
        .catch(err => {
            console.error(err);
            alert('Failed to connect to server.');
        });
    }
</script>
</body>
</html>

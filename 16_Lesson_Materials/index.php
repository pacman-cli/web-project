<?php
// 16_Lesson_Materials/index.php
// Dual role portal for lesson materials management (Instructor uploads, Student downloads)

require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/design-system.php';
require_once __DIR__ . '/../config/csrf.php';
requireAuth(); // Require logged in user

$pdo = require_once __DIR__ . '/../config/db.php';
$userId = $_SESSION['user_id'];
$userName = $_SESSION['name'];
$role = $_SESSION['role'];

if ($role !== 'instructor' && $role !== 'student') {
    http_response_code(403);
    die("Access denied.");
}

$selectedCourseId = intval($_GET['course_id'] ?? 0);

try {
    if ($role === 'instructor') {
        // 1. Fetch instructor's assigned courses with material counts
        $coursesStmt = $pdo->prepare("
            SELECT c.id, c.title, c.description, c.difficulty,
                   (SELECT COUNT(*) FROM materials m WHERE m.course_id = c.id) as material_count,
                   (SELECT COUNT(*) FROM materials m WHERE m.course_id = c.id AND m.file_type = 'pdf') as pdf_count,
                   (SELECT COUNT(*) FROM materials m WHERE m.course_id = c.id AND m.file_type = 'video') as video_count,
                   (SELECT COUNT(*) FROM materials m WHERE m.course_id = c.id AND m.file_type = 'audio') as audio_count,
                   (SELECT COUNT(*) FROM materials m WHERE m.course_id = c.id AND m.file_type = 'document') as doc_count
            FROM courses c
            JOIN instructor_assignments ia ON c.id = ia.course_id
            WHERE ia.instructor_id = :instructor_id
            ORDER BY c.title ASC
        ");
        $coursesStmt->execute(['instructor_id' => $userId]);
        $courses = $coursesStmt->fetchAll();

        // 2. Fetch recently uploaded materials
        $materialsStmt = $pdo->prepare("
            SELECT m.*, c.title as course_title
            FROM materials m
            JOIN courses c ON m.course_id = c.id
            JOIN instructor_assignments ia ON c.id = ia.course_id
            WHERE ia.instructor_id = :instructor_id
            ORDER BY m.uploaded_at DESC
            LIMIT 25
        ");
        $materialsStmt->execute(['instructor_id' => $userId]);
        $recentMaterials = $materialsStmt->fetchAll();
    } else {
        // Student role
        // 1. Fetch student's enrolled courses (approved) with material counts
        $coursesStmt = $pdo->prepare("
            SELECT c.id, c.title, c.description, c.difficulty,
                   (SELECT COUNT(*) FROM materials m WHERE m.course_id = c.id) as material_count,
                   (SELECT COUNT(*) FROM materials m WHERE m.course_id = c.id AND m.file_type = 'pdf') as pdf_count,
                   (SELECT COUNT(*) FROM materials m WHERE m.course_id = c.id AND m.file_type = 'video') as video_count,
                   (SELECT COUNT(*) FROM materials m WHERE m.course_id = c.id AND m.file_type = 'audio') as audio_count,
                   (SELECT COUNT(*) FROM materials m WHERE m.course_id = c.id AND m.file_type = 'document') as doc_count
            FROM courses c
            JOIN enrollments e ON c.id = e.course_id
            WHERE e.student_id = :student_id AND e.status = 'approved'
            ORDER BY c.title ASC
        ");
        $coursesStmt->execute(['student_id' => $userId]);
        $courses = $coursesStmt->fetchAll();

        // 2. Fetch materials for enrolled courses
        $materialsStmt = $pdo->prepare("
            SELECT m.*, c.title as course_title
            FROM materials m
            JOIN courses c ON m.course_id = c.id
            JOIN enrollments e ON c.id = e.course_id
            WHERE e.student_id = :student_id AND e.status = 'approved'
            ORDER BY m.uploaded_at DESC
            LIMIT 50
        ");
        $materialsStmt->execute(['student_id' => $userId]);
        $recentMaterials = $materialsStmt->fetchAll();
    }
} catch (Exception $e) {
    error_log('DB error: ' . $e->getMessage()); die('A database error occurred. Please try again later.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php lms_head('Lesson Materials', $role); ?>
</head>
<body class="bg-background text-on-background">

<?php lms_sidebar($role, '/16_Lesson_Materials/index.php'); ?>

<?php lms_topbar($role, 'Lesson Materials'); ?>

<!-- Main Content Canvas -->
<main id="lms-main-content" class="lms-main">
    <div class="max-w-container-max mx-auto p-lg">
        <!-- Page Header -->
        <div class="flex flex-col md:flex-row md:items-end justify-between mb-lg gap-md">
            <div>
                <h2 class="font-h1 text-h1 text-on-surface mb-xs">Lesson Materials</h2>
                <p class="font-body-lg text-body-lg text-on-surface-variant"><?= $role === 'instructor' ? 'Organize lesson content and learning resources' : 'Browse syllabus materials and sheet music uploaded by your instructors' ?></p>
            </div>
            <?php if ($role === 'instructor'): ?>
                <div class="flex flex-wrap gap-sm">
                    <button onclick="openUploadModal()" class="bg-primary text-on-primary px-md py-2 rounded-lg font-label-md text-label-md flex items-center space-x-2 shadow-sm hover:opacity-90">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">upload</span>
                        <span>Upload Material</span>
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Bento Grid Content (Courses Assigned/Enrolled) -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-md mb-lg">
            <?php if (empty($courses)): ?>
                <div class="col-span-full bg-surface-container-lowest border border-outline-variant rounded-lg p-lg text-center">
                    <p class="text-on-surface-variant text-body-lg"><?= $role === 'instructor' ? 'You are not assigned to any courses yet.' : 'You are not enrolled in any active courses yet.' ?></p>
                </div>
            <?php else: ?>
                <?php foreach ($courses as $c): ?>
                    <button type="button" aria-label="Filter materials for <?= htmlspecialchars($c['title']) ?>" class="course-card text-left bg-surface-container-lowest border border-outline-variant rounded-lg p-md card-shadow flex flex-col hover:border-primary/40 transition-all group" onclick="filterByCourse(<?= $c['id'] ?>)">
                        <div class="flex justify-between items-start mb-sm">
                            <span class="bg-secondary-container/30 text-on-secondary-container px-2 py-1 rounded text-label-sm font-label-sm uppercase tracking-wider">
                                <?= htmlspecialchars($c['difficulty']) ?>
                            </span>
                            <span class="text-on-surface-variant font-label-sm text-label-sm">Active Course</span>
                        </div>
                        <h3 class="font-h3 text-h3 text-on-surface mb-sm group-hover:text-primary transition-colors">
                            <?= htmlspecialchars($c['title']) ?>
                        </h3>
                        <p class="font-body-md text-body-md text-on-surface-variant flex-1 mb-md">
                            <?= htmlspecialchars($c['description'] ?: 'No description provided.') ?>
                        </p>
                        <div class="flex items-center space-x-2 mb-lg">
                            <span class="material-symbols-outlined text-primary text-[20px]" aria-hidden="true">description</span>
                            <span class="font-label-md text-label-md text-secondary">
                                <?= intval($c['material_count']) ?> Materials: 
                                <?= intval($c['pdf_count']) ?> PDFs, 
                                <?= intval($c['video_count']) ?> Videos, 
                                <?= intval($c['audio_count']) ?> Audio
                            </span>
                        </div>
                        <div class="pt-md border-t border-outline-variant flex items-center justify-between">
                            <div class="flex space-x-md">
                                <button class="font-label-md text-label-md text-primary font-semibold hover:underline">Filter Files</button>
                                <?php if ($role === 'instructor'): ?>
                                    <button onclick="event.stopPropagation(); openUploadModal(<?= $c['id'] ?>)" class="font-label-md text-label-md text-on-surface-variant hover:text-on-surface">Upload File</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </button>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Recently Uploaded Section -->
        <section class="mt-xl">
            <div class="flex items-center justify-between mb-md">
                <h3 class="font-h2 text-h2 text-on-surface"><?= $role === 'instructor' ? 'Recently Uploaded Materials' : 'Available Course Materials' ?></h3>
                <button onclick="filterByCourse('')" class="text-primary font-label-md text-label-md hover:underline">Show All</button>
            </div>
            <div class="bg-surface-container-lowest border border-outline-variant rounded-lg overflow-hidden card-shadow">
                <table class="w-full text-left font-body-md text-body-md">
                    <thead class="bg-surface-container-low text-on-surface-variant font-label-sm text-label-sm">
                        <tr>
                            <th class="px-md py-3 font-semibold uppercase tracking-wider">File Name</th>
                            <th class="px-md py-3 font-semibold uppercase tracking-wider">Course</th>
                            <th class="px-md py-3 font-semibold uppercase tracking-wider">Type</th>
                            <th class="px-md py-3 font-semibold uppercase tracking-wider">Date Added</th>
                            <th class="px-md py-3 text-right font-semibold uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="materials-tbody" class="divide-y divide-outline-variant">
                        <?php if (empty($recentMaterials)): ?>
                            <tr>
                                <td colspan="5" class="px-md py-8 text-center text-on-surface-variant">No materials found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentMaterials as $m): ?>
                                <tr class="hover:bg-surface-container-low/50 transition-colors material-row" data-course-id="<?= $m['course_id'] ?>" data-title="<?= htmlspecialchars(strtolower($m['title'])) ?>">
                                    <td class="px-md py-4">
                                        <div class="flex items-center">
                                            <?php if ($m['file_type'] === 'pdf'): ?>
                                                <span class="material-symbols-outlined text-error mr-3" aria-hidden="true">picture_as_pdf</span>
                                            <?php elseif ($m['file_type'] === 'video'): ?>
                                                <span class="material-symbols-outlined text-primary mr-3" aria-hidden="true">video_library</span>
                                            <?php elseif ($m['file_type'] === 'audio'): ?>
                                                <span class="material-symbols-outlined text-secondary mr-3" aria-hidden="true">audio_file</span>
                                            <?php else: ?>
                                                <span class="material-symbols-outlined text-outline mr-3" aria-hidden="true">description</span>
                                            <?php endif; ?>
                                            <span class="text-on-surface font-medium"><?= htmlspecialchars($m['title']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-md py-4 text-on-surface-variant"><?= htmlspecialchars($m['course_title']) ?></td>
                                    <td class="px-md py-4 text-on-surface-variant uppercase text-xs font-semibold"><?= htmlspecialchars($m['file_type']) ?></td>
                                    <td class="px-md py-4 text-on-surface-variant"><?= date('M d, Y', strtotime($m['uploaded_at'])) ?></td>
                                    <td class="px-md py-4 text-right">
                                        <?php if (in_array($m['file_type'], ['pdf', 'video', 'audio'])): ?>
                                            <button onclick="previewMaterial(<?= $m['id'] ?>, '<?= htmlspecialchars($m['title']) ?>', '<?= $m['file_type'] ?>')" class="text-on-surface-variant hover:text-primary transition-colors inline-block mr-3" aria-label="Preview <?= htmlspecialchars($m['title']) ?>">
                                                <span class="material-symbols-outlined text-[20px]" aria-hidden="true">visibility</span>
                                            </button>
                                        <?php endif; ?>
                                        <a href="/api/download_material.php?id=<?= $m['id'] ?>" class="text-on-surface-variant hover:text-primary transition-colors inline-block mr-3" aria-label="Download <?= htmlspecialchars($m['title']) ?>">
                                            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">download</span>
                                        </a>
                                        <?php if ($role === 'instructor'): ?>
                                            <button onclick="deleteMaterial(<?= $m['id'] ?>)" class="text-on-surface-variant hover:text-error transition-colors" aria-label="Delete material: <?= htmlspecialchars($m['title']) ?>">
                                                <span class="material-symbols-outlined text-[20px]" aria-hidden="true">delete</span>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</main>

<?php if ($role === 'instructor'): ?>
<!-- Upload Material Modal (Instructor Only) -->
<div id="upload-modal" class="fixed inset-0 z-[100] flex items-center justify-center bg-inverse-surface/40 backdrop-blur-sm hidden">
    <div class="bg-surface-container-lowest w-full max-w-[500px] rounded-lg shadow-[0_8px_32px_rgba(0,0,0,0.12)] border border-outline-variant overflow-hidden">
        <!-- Modal Header -->
        <div class="px-lg py-md border-b border-outline-variant flex justify-between items-center">
            <div>
                <h2 class="font-h2 text-h2 text-on-surface">Upload Material</h2>
                <p class="font-body-md text-body-md text-on-surface-variant">Add resources to your lesson library</p>
            </div>
            <button onclick="closeUploadModal()" class="text-outline hover:text-on-surface p-1 rounded-full hover:bg-surface-container-low transition-colors" aria-label="Close upload dialog">
                <span class="material-symbols-outlined" aria-hidden="true">close</span>
            </button>
        </div>
        <!-- Modal Content (Form) -->
        <form id="upload-form" onsubmit="handleUpload(event)" class="p-lg space-y-md">
            <div>
                <label for="upload-title" class="block font-label-md text-label-md text-on-surface mb-2">Material Title</label>
                <input required id="upload-title" name="title" class="w-full h-11 border border-outline-variant focus:border-primary focus:ring-1 focus:ring-primary rounded-lg px-md font-body-md bg-surface-container-lowest outline-none transition-all" placeholder="e.g., Chopin Nocturne Sheet Music" type="text"/>
            </div>
            <div>
                <label for="upload-course-id" class="block font-label-md text-label-md text-on-surface mb-2">Select Course</label>
                <div class="relative">
                    <select required id="upload-course-id" name="course_id" class="w-full h-11 border border-outline-variant focus:border-primary focus:ring-1 focus:ring-primary rounded-lg px-md font-body-md bg-surface-container-lowest appearance-none cursor-pointer">
                        <option value="">Select a course...</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="material-symbols-outlined absolute right-3 top-2.5 text-outline pointer-events-none" aria-hidden="true">expand_more</span>
                </div>
            </div>
            <div>
                <label for="upload-file" class="block font-label-md text-label-md text-on-surface mb-2">File Upload</label>
                <input required type="file" id="upload-file" name="material_file" class="w-full p-2 border border-outline-variant rounded-lg bg-surface-container-low text-body-md"/>
                <p class="text-[11px] text-on-surface-variant mt-1">Allowed: PDF, MP3, WAV, MP4, MOV, DOC, DOCX (Max 20MB)</p>
            </div>
            <div id="upload-error" class="text-error text-body-md font-semibold hidden" aria-live="polite"></div>
            <!-- Modal Footer -->
            <div class="pt-md flex justify-end items-center gap-md border-t border-outline-variant">
                <button type="button" onclick="closeUploadModal()" class="px-md py-sm rounded-lg border border-outline-variant font-label-md text-on-surface-variant hover:bg-surface-container-high transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-md py-sm rounded-lg bg-primary text-on-primary font-label-md flex items-center gap-2 hover:opacity-90 shadow-sm transition-all">
                    Upload Material
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Material Preview Modal -->
<div id="preview-modal" class="fixed inset-0 z-[100] flex items-center justify-center bg-inverse-surface/40 backdrop-blur-sm hidden">
    <div class="bg-surface-container-lowest w-full max-w-4xl rounded-lg shadow-[0_8px_32px_rgba(0,0,0,0.12)] border border-outline-variant overflow-hidden max-h-[90vh] flex flex-col">
        <div class="px-lg py-md border-b border-outline-variant flex justify-between items-center shrink-0">
            <h2 class="font-h2 text-h2 text-on-surface" id="preview-title">Preview</h2>
            <button onclick="closePreviewModal()" class="text-outline hover:text-on-surface p-1 rounded-full hover:bg-surface-container-low transition-colors" aria-label="Close preview">
                <span class="material-symbols-outlined" aria-hidden="true">close</span>
            </button>
        </div>
        <div class="flex-1 overflow-auto p-lg" id="preview-content">
            <!-- Content loaded dynamically -->
        </div>
        <div class="px-lg py-md border-t border-outline-variant shrink-0">
            <a id="preview-download" href="#" download class="bg-primary text-on-primary px-md py-2 rounded-lg font-label-md text-label-md inline-flex items-center gap-2 hover:opacity-90">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">download</span>
                Download File
            </a>
        </div>
    </div>
</div>

<script>
    <?php if ($role === 'instructor'): ?>
    function openUploadModal(courseId = null) {
        document.getElementById('upload-form').reset();
        document.getElementById('upload-error').classList.add('hidden');
        if (courseId) {
            document.getElementById('upload-course-id').value = courseId;
        }
        document.getElementById('upload-modal').classList.remove('hidden');
    }

    function closeUploadModal() {
        document.getElementById('upload-modal').classList.add('hidden');
    }

    function handleUpload(event) {
        event.preventDefault();
        const form = document.getElementById('upload-form');
        const formData = new FormData(form);
        const errorDiv = document.getElementById('upload-error');
        errorDiv.classList.add('hidden');

        fetch('/instructor/materials.php', {
            method: 'POST',
            headers: {
                'X-CSRF-Token': '<?= csrf_token() ?>'
            },
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                errorDiv.innerText = data.error || 'Upload failed.';
                errorDiv.classList.remove('hidden');
            }
        })
        .catch(err => {
            console.error(err);
            errorDiv.innerText = 'Network error or server failed.';
            errorDiv.classList.remove('hidden');
        });
    }

    function deleteMaterial(id) {
        if (!confirm('Are you sure you want to delete this material? This will permanently delete the file.')) return;

        fetch('/instructor/materials.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': '<?= csrf_token() ?>'
            },
            body: JSON.stringify({ id: id })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert(data.error || 'Delete failed.');
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error deleting material.');
        });
    }
    <?php endif; ?>

    function filterByCourse(courseId) {
        const rows = document.querySelectorAll('.material-row');
        rows.forEach(row => {
            if (!courseId || row.getAttribute('data-course-id') == courseId) {
                row.classList.remove('hidden');
            } else {
                row.classList.add('hidden');
            }
        });
    }

    function searchFiles() {
        const query = document.getElementById('topbar-search').value.toLowerCase();
        const rows = document.querySelectorAll('.material-row');
        rows.forEach(row => {
            const title = row.getAttribute('data-title');
            if (title.includes(query)) {
                row.classList.remove('hidden');
            } else {
                row.classList.add('hidden');
            }
        });
    }

    // Proactively filter by URL course_id if passed
    window.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        const cid = urlParams.get('course_id');
        if (cid) {
            filterByCourse(cid);
        }
    });

    function previewMaterial(id, title, fileType) {
        document.getElementById('preview-title').textContent = title;
        const content = document.getElementById('preview-content');
        const downloadBtn = document.getElementById('preview-download');
        downloadBtn.href = `/api/download_material.php?id=${id}`;
        
        if (fileType === 'pdf') {
            content.innerHTML = `<iframe src="/api/download_material.php?id=${id}" class="w-full h-full min-h-[600px] border-0 rounded-lg" title="PDF Preview"></iframe>`;
        } else if (fileType === 'video') {
            content.innerHTML = `<video controls class="w-full max-h-[500px] rounded-lg"><source src="/api/download_material.php?id=${id}" type="video/mp4">Your browser does not support video playback.</video>`;
        } else if (fileType === 'audio') {
            content.innerHTML = `<div class="flex flex-col items-center justify-center py-xl"><span class="material-symbols-outlined text-6xl text-primary mb-md" aria-hidden="true">audio_file</span><audio controls class="w-full max-w-md"><source src="/api/download_material.php?id=${id}" type="audio/mpeg">Your browser does not support audio playback.</audio></div>`;
        } else {
            content.innerHTML = `<div class="text-center py-xl"><span class="material-symbols-outlined text-6xl text-outline mb-md" aria-hidden="true">description</span><p class="text-on-surface-variant">Preview not available for this file type. Please download to view.</p></div>`;
        }
        
        document.getElementById('preview-modal').classList.remove('hidden');
    }

    function closePreviewModal() {
        document.getElementById('preview-modal').classList.add('hidden');
        document.getElementById('preview-content').innerHTML = '';
    }
</script>
</body>
</html>

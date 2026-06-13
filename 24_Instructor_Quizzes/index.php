<?php
require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/design-system.php';
requireRole('instructor');

$pdo = require_once __DIR__ . '/../config/db.php';
$instructorId = $_SESSION['user_id'];

// Fetch instructor's courses
$coursesStmt = $pdo->prepare("
    SELECT c.id, c.title FROM courses c
    JOIN instructor_assignments ia ON c.id = ia.course_id
    WHERE ia.instructor_id = :iid ORDER BY c.title ASC
");
$coursesStmt->execute(['iid' => $instructorId]);
$courses = $coursesStmt->fetchAll();

// Fetch course classes for optional linking
$classesStmt = $pdo->prepare("
    SELECT cc.id, cc.title, cc.course_id FROM course_classes cc
    JOIN instructor_assignments ia ON cc.course_id = ia.course_id
    WHERE ia.instructor_id = :iid ORDER BY cc.course_id, cc.order_index ASC
");
$classesStmt->execute(['iid' => $instructorId]);
$classes = $classesStmt->fetchAll();
$classesByCourse = [];
foreach ($classes as $c) {
    $classesByCourse[$c['course_id']][] = $c;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php lms_head('Quizzes', 'instructor'); ?>
</head>
<body class="bg-background text-on-surface">
<?php lms_sidebar('instructor', '/24_Instructor_Quizzes/index.php'); ?>
<?php lms_topbar('instructor', 'Quizzes'); ?>
<main id="lms-main-content" class="lms-main">
<div class="page-content space-y-md">

    <div class="flex justify-between items-end">
        <div>
            <h2 class="font-h1 text-h1 text-on-surface">Quizzes</h2>
            <p class="font-body-lg text-body-lg text-on-surface-variant">Create and manage course quizzes</p>
        </div>
        <button onclick="openEditor()" class="bg-primary text-on-primary flex items-center gap-xs px-md py-sm rounded-lg font-semibold hover:opacity-90 transition-opacity">
            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">add</span>
            <span>New Quiz</span>
        </button>
    </div>

    <?php if (empty($courses)): ?>
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-xl text-center">
            <span class="material-symbols-outlined text-4xl text-outline mb-sm" aria-hidden="true">quiz</span>
            <p class="text-on-surface-variant">You are not assigned to any courses yet.</p>
        </div>
    <?php else: ?>
        <div id="quiz-list" class="space-y-md" aria-live="polite"></div>
    <?php endif; ?>
</div>
</main>

<!-- Quiz Editor Modal -->
<div id="editor-modal" class="fixed inset-0 z-[100] flex items-start justify-center bg-inverse-surface/40 backdrop-blur-sm p-md hidden pt-[5vh]">
    <div class="bg-surface-container-lowest w-full max-w-4xl rounded-xl shadow-2xl flex flex-col max-h-[90vh]">
        <div class="px-lg py-md border-b border-outline-variant flex justify-between items-center bg-surface-container-low shrink-0">
            <div>
                <h2 class="font-h2 text-h2 text-on-surface" id="editor-title">New Quiz</h2>
                <p class="text-secondary font-body-md">Configure quiz details, questions, and answers</p>
            </div>
            <button onclick="closeEditor()" class="p-xs hover:bg-surface-container-high rounded-full transition-colors" aria-label="Close quiz editor">
                <span class="material-symbols-outlined text-secondary" aria-hidden="true">close</span>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto p-lg">
            <input type="hidden" id="edit-id" value="0"/>

            <div class="grid grid-cols-2 gap-md mb-lg">
                <div>
                    <label for="edit-course" class="block text-sm font-medium text-on-surface mb-xs">Course *</label>
                    <select id="edit-course" class="w-full bg-surface border border-outline-variant rounded-lg px-3 py-2 text-on-surface focus:ring-2 focus:ring-primary/50">
                        <option value="">Select course</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="edit-class" class="block text-sm font-medium text-on-surface mb-xs">Class Module (optional)</label>
                    <select id="edit-class" class="w-full bg-surface border border-outline-variant rounded-lg px-3 py-2 text-on-surface focus:ring-2 focus:ring-primary/50">
                        <option value="">None</option>
                    </select>
                </div>
            </div>

            <div class="mb-lg">
                <label for="edit-title" class="block text-sm font-medium text-on-surface mb-xs">Title *</label>
                <input id="edit-title" type="text" autocomplete="off" class="w-full bg-surface border border-outline-variant rounded-lg px-3 py-2 text-on-surface focus:ring-2 focus:ring-primary/50" placeholder="Quiz title"/>
            </div>

            <div class="mb-lg">
                <label for="edit-desc" class="block text-sm font-medium text-on-surface mb-xs">Description</label>
                <textarea id="edit-desc" rows="2" class="w-full bg-surface border border-outline-variant rounded-lg px-3 py-2 text-on-surface focus:ring-2 focus:ring-primary/50" placeholder="Quiz description"></textarea>
            </div>

            <div class="grid grid-cols-3 gap-md mb-lg">
                <div>
                    <label for="edit-tlim" class="block text-sm font-medium text-on-surface mb-xs">Time Limit (minutes)</label>
                    <input id="edit-tlim" type="number" min="0" class="w-full bg-surface border border-outline-variant rounded-lg px-3 py-2 text-on-surface focus:ring-2 focus:ring-primary/50" placeholder="No limit"/>
                </div>
                <div>
                    <label for="edit-pscore" class="block text-sm font-medium text-on-surface mb-xs">Passing Score (%)</label>
                    <input id="edit-pscore" type="number" min="0" max="100" value="70" class="w-full bg-surface border border-outline-variant rounded-lg px-3 py-2 text-on-surface focus:ring-2 focus:ring-primary/50"/>
                </div>
                <div>
                    <label for="edit-maxatt" class="block text-sm font-medium text-on-surface mb-xs">Max Attempts</label>
                    <input id="edit-maxatt" type="number" min="0" value="1" class="w-full bg-surface border border-outline-variant rounded-lg px-3 py-2 text-on-surface focus:ring-2 focus:ring-primary/50" placeholder="0 = unlimited"/>
                </div>
            </div>

            <!-- Questions -->
            <div class="mb-lg">
                <div class="flex justify-between items-center mb-sm">
                    <h3 class="font-h3 text-h3 text-on-surface">Questions</h3>
                    <button onclick="addQuestion()" class="text-primary font-semibold flex items-center gap-xs text-sm hover:underline">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add_circle</span>
                        Add Question
                    </button>
                </div>
                <div id="questions-container" class="space-y-sm"></div>
                <p id="no-questions" class="text-center text-on-surface-variant py-lg border-2 border-dashed border-outline-variant rounded-xl">Click "Add Question" to start building your quiz.</p>
            </div>
        </div>
        <div class="px-lg py-md border-t border-outline-variant bg-surface-container-lowest flex justify-between items-center shrink-0">
            <div>
                <button onclick="publishQuiz()" id="btn-publish" class="px-md py-sm bg-green-600 text-white rounded-lg font-semibold hover:bg-green-700 transition-colors hidden">Publish</button>
            </div>
            <div class="flex gap-md">
                <button onclick="closeEditor()" class="px-lg py-sm font-label-md text-label-md text-secondary hover:text-on-surface transition-colors">Cancel</button>
                <button onclick="saveQuiz()" class="px-lg py-sm bg-primary text-on-primary rounded-lg font-semibold hover:opacity-90 transition-colors">Save Draft</button>
            </div>
        </div>
    </div>
</div>

<!-- Attempts Modal -->
<div id="attempts-modal" class="fixed inset-0 z-[100] flex items-start justify-center bg-inverse-surface/40 backdrop-blur-sm p-md hidden pt-[10vh]">
    <div class="bg-surface-container-lowest w-full max-w-3xl rounded-xl shadow-2xl flex flex-col max-h-[80vh]">
        <div class="px-lg py-md border-b border-outline-variant flex justify-between items-center shrink-0">
            <h2 class="font-h2 text-h2 text-on-surface" id="attempts-title">Student Attempts</h2>
            <button onclick="document.getElementById('attempts-modal').classList.add('hidden')" class="p-xs hover:bg-surface-container-high rounded-full" aria-label="Close attempts modal"><span class="material-symbols-outlined" aria-hidden="true">close</span></button>
        </div>
        <div class="flex-1 overflow-y-auto p-lg" id="attempts-body"></div>
    </div>
</div>

<!-- Grade Modal -->
<div id="grade-modal" class="fixed inset-0 z-[110] flex items-start justify-center bg-inverse-surface/40 backdrop-blur-sm p-md hidden pt-[10vh]">
    <div class="bg-surface-container-lowest w-full max-w-2xl rounded-xl shadow-2xl flex flex-col max-h-[80vh]">
        <div class="px-lg py-md border-b border-outline-variant flex justify-between items-center shrink-0">
            <h2 class="font-h2 text-h2 text-on-surface">Grade Answer</h2>
            <button onclick="document.getElementById('grade-modal').classList.add('hidden')" class="p-xs hover:bg-surface-container-high rounded-full" aria-label="Close grade modal"><span class="material-symbols-outlined" aria-hidden="true">close</span></button>
        </div>
        <div class="flex-1 overflow-y-auto p-lg" id="grade-body"></div>
    </div>
</div>

<script>
let courses = <?= json_encode($courses) ?>;
let classesByCourse = <?= json_encode($classesByCourse) ?>;
let questionCounter = 0;
let optionCounter = 0;

// ── Data ────────────────────────────────────────────────────────────────────
function getQuizzes(courseId = 0) {
    return fetch(BASE_URL + `/instructor/quizzes.php${courseId ? '?course_id='+courseId : ''}`)
        .then(r => r.json()).then(d => d.data || []);
}

function saveQuizData(data) {
    return fetch(BASE_URL + '/instructor/quizzes.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-Token': '<?= csrf_token() ?>'},
        body: JSON.stringify(data)
    }).then(r => r.json());
}

function deleteQuizData(id) {
    return fetch(BASE_URL + '/instructor/quizzes.php', {
        method: 'DELETE',
        headers: {'Content-Type': 'application/json', 'X-CSRF-Token': '<?= csrf_token() ?>'},
        body: JSON.stringify({id})
    }).then(r => r.json());
}

function getAttempts(quizId) {
    return fetch(BASE_URL + `/instructor/quiz_attempts.php?quiz_id=${quizId}`)
        .then(r => r.json()).then(d => d.data || []);
}

function gradeAnswer(answerId, pointsEarned, attemptId) {
    return fetch(BASE_URL + '/instructor/quiz_attempts.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-Token': '<?= csrf_token() ?>'},
        body: JSON.stringify({answer_id: answerId, points_earned: pointsEarned, attempt_id: attemptId})
    }).then(r => r.json());
}

// ── Render Quiz List ─────────────────────────────────────────────────────────
function renderQuizList() {
    const container = document.getElementById('quiz-list');
    container.innerHTML = '<div class="text-center py-lg text-on-surface-variant">Loading…</div>';
    getQuizzes().then(quizzes => {
        if (!quizzes.length) {
            container.innerHTML = '<div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-xl text-center"><span class="material-symbols-outlined text-4xl text-outline mb-sm" aria-hidden="true">quiz</span><p class="text-on-surface-variant">No quizzes yet. Click "New Quiz" to create one.</p></div>';
            return;
        }
        let html = '';
        const grouped = {};
        quizzes.forEach(q => {
            const cid = q.course_id;
            if (!grouped[cid]) {
                const course = courses.find(c => c.id == cid);
                grouped[cid] = {title: course ? course.title : 'Unknown Course', quizzes: []};
            }
            grouped[cid].quizzes.push(q);
        });
        for (const cid in grouped) {
            const g = grouped[cid];
            html += `<div class="mb-lg">`;
            html += `<h3 class="font-h3 text-h3 text-on-surface mb-md">${escHtml(g.title)}</h3>`;
            html += `<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-md">`;
            g.quizzes.forEach(q => {
                const isPub = q.status === 'published';
                html += `<div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-md hover:shadow-md transition-shadow">
                    <div class="flex justify-between items-start mb-sm">
                        <div class="flex-1">
                            <h4 class="font-semibold text-on-surface text-lg">${escHtml(q.title)}</h4>
                            ${q.description ? '<p class="text-sm text-on-surface-variant mt-1 line-clamp-2">'+escHtml(q.description.substring(0,100))+'</p>' : ''}
                        </div>
                        <div class="flex gap-xs ml-sm">
                            <button onclick="viewAttempts(${q.id},'${escHtml(q.title)}')" class="p-xs text-outline hover:text-primary transition-colors" title="Attempts" aria-label="View attempts for ${escHtml(q.title)}">
                                <span class="material-symbols-outlined" aria-hidden="true">assignment_turned_in</span>
                            </button>
                            <button onclick="openEditor(${q.id})" class="p-xs text-outline hover:text-primary transition-colors" title="Edit" aria-label="Edit ${escHtml(q.title)}">
                                <span class="material-symbols-outlined" aria-hidden="true">edit</span>
                            </button>
                            <button onclick="deleteQuiz(${q.id})" class="p-xs text-outline hover:text-red-500 transition-colors" title="Delete" aria-label="Delete ${escHtml(q.title)}">
                                <span class="material-symbols-outlined" aria-hidden="true">delete</span>
                            </button>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-sm mt-auto">
                        <span class="px-sm py-1 bg-surface-container-high rounded-full text-xs font-medium text-on-surface">
                            <span class="material-symbols-outlined text-[14px] align-middle" aria-hidden="true">help_outline</span>
                            ${q.question_count||0} questions
                        </span>
                        ${isPub ? '<span class="px-sm py-1 bg-green-100 text-green-800 rounded-full text-[10px] font-bold uppercase">Published</span>'
                               : '<span class="px-sm py-1 bg-amber-100 text-amber-800 rounded-full text-[10px] font-bold uppercase">Draft</span>'}
                        ${q.attempt_count ? `<span class="px-sm py-1 bg-blue-100 text-blue-800 rounded-full text-[10px] font-bold">${q.attempt_count} attempts</span>` : ''}
                        ${q.time_limit_minutes ? `<span class="px-sm py-1 bg-purple-100 text-purple-800 rounded-full text-[10px] font-bold">${q.time_limit_minutes} min</span>` : ''}
                    </div>
                </div>`;
            });
            html += `</div></div>`;
        }
        container.innerHTML = html;
    }).catch(() => {
        container.innerHTML = '<div class="bg-red-50 border border-red-200 text-red-800 p-md rounded-xl">Failed to load quizzes.</div>';
    });
}

// ── Quiz Editor ──────────────────────────────────────────────────────────────
function openEditor(quizId) {
    document.getElementById('edit-id').value = quizId || 0;
    document.getElementById('editor-title').textContent = quizId ? 'Edit Quiz' : 'New Quiz';
    document.getElementById('btn-publish').style.display = quizId ? 'inline-block' : 'none';
    document.getElementById('questions-container').innerHTML = '';
    document.getElementById('no-questions').style.display = 'block';
    questionCounter = 0;
    optionCounter = 0;

    if (quizId) {
        getQuizzes().then(quizzes => {
            const q = quizzes.find(x => x.id == quizId);
            if (!q) return;
            document.getElementById('edit-course').value = q.course_id;
            updateClasses(q.course_id);
            document.getElementById('edit-class').value = q.class_id || '';
            document.getElementById('edit-title').value = q.title;
            document.getElementById('edit-desc').value = q.description || '';
            document.getElementById('edit-tlim').value = q.time_limit_minutes || '';
            document.getElementById('edit-pscore').value = q.passing_score || 70;
            document.getElementById('edit-maxatt').value = q.max_attempts || 1;
            loadQuizQuestions(quizId);
        });
    } else {
        document.getElementById('edit-course').value = '';
        updateClasses(0);
        document.getElementById('edit-class').value = '';
        document.getElementById('edit-title').value = '';
        document.getElementById('edit-desc').value = '';
        document.getElementById('edit-tlim').value = '';
        document.getElementById('edit-pscore').value = 70;
        document.getElementById('edit-maxatt').value = 1;
    }
    document.getElementById('editor-modal').classList.remove('hidden');
}

function closeEditor() {
    document.getElementById('editor-modal').classList.add('hidden');
}

function loadQuizQuestions(quizId) {
    // Load existing questions from the saved quiz
    getQuizzes().then(quizzes => {
        const q = quizzes.find(x => x.id == quizId);
        if (!q) return;
        // We need full question data — fetch from a detail endpoint
        fetch(BASE_URL + `/instructor/quizzes.php?course_id=${q.course_id}`)
            .then(r => r.json()).then(d => {
                const fullQuiz = (d.data || []).find(x => x.id == quizId);
                if (fullQuiz) fetchQuestionsDetail(quizId);
                else fetchQuestionsDetail(quizId);
            });
    });
}

function fetchQuestionsDetail(quizId) {
    // Fetch quiz with questions and options from the API
    fetch(BASE_URL + `/instructor/quizzes.php?quiz_id=${quizId}`)
        .then(r => r.json())
        .then(res => {
            if (res.success && res.data && res.data.questions) {
                const questions = res.data.questions;
                questions.forEach(q => {
                    addQuestion({
                        id: q.id,
                        question_text: q.question_text,
                        question_type: q.question_type,
                        points: q.points,
                        options: q.options || []
                    });
                });
            }
        })
        .catch(err => console.error('Failed to load quiz questions:', err));
}

// ── Questions Builder ────────────────────────────────────────────────────────
function addQuestion(data) {
    const container = document.getElementById('questions-container');
    document.getElementById('no-questions').style.display = 'none';
    const idx = questionCounter++;
    const id = data && data.id ? data.id : 0;
    const qText = data ? (data.question_text || '') : '';
    const qType = data ? (data.question_type || 'multiple_choice') : 'multiple_choice';
    const qPoints = data ? (data.points || 10) : 10;

    container.insertAdjacentHTML('beforeend', `
        <div class="border border-outline-variant rounded-xl p-md bg-surface-container-low" id="qblock-${idx}">
            <input type="hidden" name="qid" value="${id}"/>
            <div class="flex justify-between items-center mb-sm">
                <span class="font-semibold text-on-surface">Question ${idx + 1}</span>
                <button onclick="removeQuestion(${idx})" class="text-red-500 hover:text-red-700 p-xs" title="Remove" aria-label="Remove question ${idx + 1}">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">close</span>
                </button>
            </div>
            <div class="grid grid-cols-[1fr_auto_auto] gap-sm mb-sm">
                <input type="text" class="qtext w-full bg-surface border border-outline-variant rounded-lg px-3 py-2 text-on-surface" value="${escHtml(qText)}" placeholder="Question text"/>
                <select class="qtype bg-surface border border-outline-variant rounded-lg px-3 py-2 text-on-surface">
                    <option value="multiple_choice" ${qType==='multiple_choice'?'selected':''}>Multiple Choice</option>
                    <option value="true_false" ${qType==='true_false'?'selected':''}>True/False</option>
                    <option value="short_answer" ${qType==='short_answer'?'selected':''}>Short Answer</option>
                    <option value="essay" ${qType==='essay'?'selected':''}>Essay</option>
                </select>
                <input type="number" class="qpts w-20 bg-surface border border-outline-variant rounded-lg px-3 py-2 text-on-surface" value="${qPoints}" min="0" title="Points"/>
            </div>
            <div class="options-container space-y-xs ml-sm">
                ${data && data.options ? data.options.map((o, oi) => renderOption(idx, o, oi)).join('') : ''}
            </div>
            <button onclick="addOption(${idx})" class="text-primary text-sm mt-xs flex items-center gap-xs hover:underline">
                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">add</span> Add Option
            </button>
        </div>
    `);
}

function renderOption(qIdx, data, oIdx) {
    const id = data && data.id ? data.id : 0;
    const text = data ? (data.option_text || '') : '';
    const correct = data ? (data.is_correct ? 1 : 0) : 0;
    return `
        <div class="flex items-center gap-sm option-row" id="opt-${qIdx}-${oIdx}">
            <input type="hidden" name="oid" value="${id}"/>
            <input type="radio" name="correct-${qIdx}" class="text-primary" ${correct ? 'checked' : ''} title="Mark as correct"/>
            <input type="text" class="otext flex-1 bg-surface border border-outline-variant rounded px-2 py-1 text-sm text-on-surface" value="${escHtml(text)}" placeholder="Option text"/>
            <button onclick="this.closest('.option-row').remove()" class="text-red-400 hover:text-red-600" aria-label="Remove option">
                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">remove_circle</span>
            </button>
        </div>
    `;
}

function addOption(qIdx) {
    const container = document.querySelector(`#qblock-${qIdx} .options-container`);
    const oi = container.children.length;
    container.insertAdjacentHTML('beforeend', renderOption(qIdx, null, oi));
}

function removeQuestion(idx) {
    const el = document.getElementById(`qblock-${idx}`);
    if (el) el.remove();
    // Renumber
    document.querySelectorAll('#questions-container > div').forEach((div, i) => {
        div.querySelector('.font-semibold').textContent = `Question ${i+1}`;
    });
    if (!document.querySelectorAll('#questions-container > div').length) {
        document.getElementById('no-questions').style.display = 'block';
    }
}

function updateClasses(courseId) {
    const sel = document.getElementById('edit-class');
    sel.innerHTML = '<option value="">None</option>';
    if (classesByCourse[courseId]) {
        classesByCourse[courseId].forEach(c => {
            sel.insertAdjacentHTML('beforeend', `<option value="${c.id}">${escHtml(c.title)}</option>`);
        });
    }
}

document.getElementById('edit-course').addEventListener('change', function() {
    updateClasses(parseInt(this.value));
});

// ── Save ─────────────────────────────────────────────────────────────────────
function collectQuizData() {
    const id = parseInt(document.getElementById('edit-id').value) || 0;
    const questions = [];
    document.querySelectorAll('#questions-container > div').forEach(block => {
        const qtext = block.querySelector('.qtext').value.trim();
        if (!qtext) return;
        const qid = parseInt(block.querySelector('input[name="qid"]').value) || 0;
        const qtype = block.querySelector('.qtype').value;
        const qpts = parseInt(block.querySelector('.qpts').value) || 10;
        const options = [];
        block.querySelectorAll('.option-row').forEach(row => {
            const otext = row.querySelector('.otext').value.trim();
            if (!otext) return;
            const oid = parseInt(row.querySelector('input[name="oid"]').value) || 0;
            const ocorrect = row.querySelector('input[type="radio"]').checked ? 1 : 0;
            options.push({id: oid, option_text: otext, is_correct: ocorrect});
        });
        // For true_false, auto-generate options if missing
        if (qtype === 'true_false' && options.length === 0) {
            options.push({id: 0, option_text: 'True', is_correct: 1});
            options.push({id: 0, option_text: 'False', is_correct: 0});
        }
        questions.push({id: qid, question_text: qtext, question_type: qtype, points: qpts, options});
    });

    return {
        action: 'save',
        id,
        course_id: parseInt(document.getElementById('edit-course').value) || 0,
        class_id: parseInt(document.getElementById('edit-class').value) || 0,
        title: document.getElementById('edit-title').value.trim(),
        description: document.getElementById('edit-desc').value.trim(),
        time_limit_minutes: parseInt(document.getElementById('edit-tlim').value) || null,
        passing_score: parseInt(document.getElementById('edit-pscore').value) || 70,
        max_attempts: parseInt(document.getElementById('edit-maxatt').value) || 1,
        status: 'draft',
        questions
    };
}

function saveQuiz() {
    const data = collectQuizData();
    if (!data.title) { alert('Title is required.'); return; }
    if (!data.course_id) { alert('Course is required.'); return; }

    const btn = document.querySelector('#editor-modal .bg-primary');
    const orig = btn.textContent;
    btn.textContent = 'Saving…';
    btn.disabled = true;

    saveQuizData(data).then(res => {
        if (res.success) {
            document.getElementById('edit-id').value = res.id;
            document.getElementById('btn-publish').style.display = 'inline-block';
            closeEditor();
            renderQuizList();
        } else {
            alert(res.error || 'Save failed.');
        }
    }).catch(() => alert('Network error.')).finally(() => {
        btn.textContent = orig;
        btn.disabled = false;
    });
}

function publishQuiz() {
    const id = parseInt(document.getElementById('edit-id').value);
    if (!id) { alert('Save the quiz first.'); return; }
    if (!confirm('Publish this quiz? Students will be able to see and take it.')) return;

    fetch(BASE_URL + '/instructor/quizzes.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-Token': '<?= csrf_token() ?>'},
        body: JSON.stringify({action: 'publish', id})
    }).then(r => r.json()).then(res => {
        if (res.success) { closeEditor(); renderQuizList(); }
        else alert(res.error || 'Publish failed.');
    });
}

function deleteQuiz(id) {
    if (!confirm('Delete this quiz and all its questions?')) return;
    deleteQuizData(id).then(res => {
        if (res.success) renderQuizList();
        else alert(res.error || 'Delete failed.');
    });
}

// ── Attempts ─────────────────────────────────────────────────────────────────
function viewAttempts(quizId, title) {
    document.getElementById('attempts-title').textContent = `Attempts: ${title}`;
    const body = document.getElementById('attempts-body');
    body.innerHTML = '<div class="text-center py-lg text-on-surface-variant">Loading…</div>';
    document.getElementById('attempts-modal').classList.remove('hidden');

    getAttempts(quizId).then(attempts => {
        if (!attempts.length) {
            body.innerHTML = '<div class="text-center py-lg text-on-surface-variant">No attempts yet.</div>';
            return;
        }
        let html = '<table class="w-full text-left border-collapse"><thead class="bg-surface border-b border-outline-variant"><tr>';
        html += '<th class="px-md py-sm font-label-sm text-label-sm text-secondary uppercase">Student</th>';
        html += '<th class="px-md py-sm font-label-sm text-label-sm text-secondary uppercase">Score</th>';
        html += '<th class="px-md py-sm font-label-sm text-label-sm text-secondary uppercase">Passed</th>';
        html += '<th class="px-md py-sm font-label-sm text-label-sm text-secondary uppercase">Completed</th>';
        html += '<th class="px-md py-sm font-label-sm text-label-sm text-secondary uppercase">Actions</th></tr></thead><tbody class="divide-y divide-outline-variant">';

        attempts.forEach(a => {
            const pct = a.total_points > 0 ? Math.round(a.score / a.total_points * 100) : 0;
            html += `<tr class="hover:bg-surface-container-low transition-colors">
                <td class="px-md py-md font-medium">${escHtml(a.student_name)}</td>
                <td class="px-md py-md">${a.score}/${a.total_points} (${pct}%)</td>
                <td class="px-md py-md">${a.passed ? '<span class="text-green-600 font-semibold">Yes</span>' : '<span class="text-red-600 font-semibold">No</span>'}</td>
                <td class="px-md py-md text-sm text-on-surface-variant">${a.completed_at ? new Date(a.completed_at).toLocaleString() : '-'}</td>
                <td class="px-md py-md"><button onclick="gradeAttempt(${a.id},'${escHtml(a.student_name)}')" class="text-primary font-semibold text-sm hover:underline">Grade</button></td>
            </tr>`;
        });
        html += '</tbody></table>';
        body.innerHTML = html;
    });
}

function gradeAttempt(attemptId, studentName) {
    document.getElementById('grade-body').innerHTML = '<div class="text-center py-lg text-on-surface-variant">Loading…</div>';
    document.getElementById('grade-modal').classList.remove('hidden');

    fetch(BASE_URL + `/student/quiz_attempt.php?quiz_id=0`, {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-Token': '<?= csrf_token() ?>'},
        body: JSON.stringify({action: 'results', attempt_id: attemptId})
    }).then(r => r.json()).then(res => {
        if (!res.success) { document.getElementById('grade-body').innerHTML = '<div class="text-red-600">Failed to load.</div>'; return; }
        const d = res.data;
        let html = `<div class="mb-md"><strong>Student:</strong> ${escHtml(studentName)} | <strong>Score:</strong> ${d.attempt.score}/${d.attempt.total_points}</div>`;
        d.answers.forEach(a => {
            const isEssay = a.question_type === 'essay' || a.question_type === 'short_answer';
            html += `<div class="border border-outline-variant rounded-lg p-md mb-sm">
                <div class="font-medium mb-xs">${escHtml(a.question_text)} <span class="text-sm text-on-surface-variant">(${a.max_points} pts)</span></div>`;
            if (isEssay) {
                html += `<div class="text-sm mb-xs bg-surface p-sm rounded"><strong>Answer:</strong> ${escHtml(a.answer_text || 'No answer')}</div>`;
                html += `<div class="flex items-center gap-sm">
                    <label class="text-sm">Points: <input type="number" class="grade-pts border border-outline-variant rounded px-2 py-1 w-20 text-on-surface bg-surface" value="${a.points_earned !== null ? a.points_earned : ''}" min="0" max="${a.max_points}" data-answer="${a.id}" data-attempt="${attemptId}"/></label>
                    <button onclick="saveGrade(this)" class="text-sm text-primary font-semibold hover:underline">Save</button>
                </div>`;
            } else {
                const selectedText = a.selected_option_text || 'Not answered';
                html += `<div class="text-sm"><span class="text-on-surface-variant">Selected:</span> ${escHtml(selectedText)} ${a.is_correct ? '<span class="text-green-600 font-semibold">✓</span>' : '<span class="text-red-600">✗</span>'}</div>`;
                if (a.points_earned !== null) {
                    html += `<div class="text-sm text-on-surface-variant">Points: ${a.points_earned}/${a.max_points}</div>`;
                }
            }
            html += '</div>';
        });
        document.getElementById('grade-body').innerHTML = html;
    });
}

function saveGrade(btn) {
    const input = btn.previousElementSibling;
    const answerId = input.dataset.answer;
    const attemptId = input.dataset.attempt;
    const points = parseInt(input.value);

    if (points === undefined || points === '') { alert('Enter points.'); return; }

    gradeAnswer(answerId, points, attemptId).then(res => {
        if (res.success) {
            btn.textContent = 'Saved ✓';
            setTimeout(() => btn.textContent = 'Save', 2000);
        } else {
            alert(res.error || 'Grade failed.');
        }
    });
}

// ── Helpers ──────────────────────────────────────────────────────────────────
function escHtml(s) {
    if (!s) return '';
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

// ── Init ─────────────────────────────────────────────────────────────────────
renderQuizList();
</script>
</body>
</html>

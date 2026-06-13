<?php
// 38_Student_Quizzes/index.php
// Student quiz taking and results portal

require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/design-system.php';
requireRole('student');

$pdo = require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/csrf.php';
$studentId = $_SESSION['user_id'];

try {
    $coursesStmt = $pdo->prepare("
        SELECT c.id, c.title
        FROM courses c
        JOIN enrollments e ON c.id = e.course_id
        WHERE e.student_id = :sid AND e.status = 'approved'
        ORDER BY c.title ASC
    ");
    $coursesStmt->execute(['sid' => $studentId]);
    $courses = $coursesStmt->fetchAll();

    $courseId = intval($_GET['course_id'] ?? 0);
    if ($courseId <= 0 && !empty($courses)) {
        $courseId = intval($courses[0]['id']);
    }
} catch (Exception $e) {
    error_log('DB error: ' . $e->getMessage());
    die('A database error occurred. Please try again later.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php lms_head('My Quizzes', 'student'); ?>
<style>
.quiz-option { cursor: pointer; transition: all .15s ease; }
.quiz-option:hover { border-color: var(--color-primary); background: color-mix(in srgb, var(--color-primary) 5%, transparent); }
.quiz-option.selected { border-color: var(--color-primary); background: color-mix(in srgb, var(--color-primary) 10%, transparent); box-shadow: 0 0 0 2px var(--color-primary); }
.quiz-option.correct { border-color: #16a34a; background: #f0fdf4; }
.quiz-option.incorrect { border-color: #dc2626; background: #fef2f2; }
</style>
</head>
<body class="bg-background text-on-surface">

<?php lms_sidebar('student', '/38_Student_Quizzes/index.php'); ?>
<?php lms_topbar('student', 'My Quizzes'); ?>

<main id="lms-main-content" class="lms-main">
    <div class="flex h-full">
        <!-- Left Column: Quiz List -->
        <div class="w-1/3 flex flex-col border-r border-outline-variant bg-white">
            <header class="p-lg border-b border-outline-variant space-y-sm">
                <h1 class="font-h1 text-h1 text-on-surface">Quizzes</h1>
                <label for="course-select" class="sr-only">Select Course</label>
                <select id="course-select" onchange="changeCourse()" class="w-full bg-surface border border-outline-variant rounded-lg px-3 py-2 text-sm text-on-surface focus:ring-2 focus:ring-primary/50">
                    <option value="">Select a course</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $c['id'] == $courseId ? 'selected' : '' ?>><?= htmlspecialchars($c['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </header>
                <div id="quiz-list" class="flex-1 overflow-y-auto p-md space-y-md" aria-live="polite">
                <div class="text-center text-on-surface-variant text-body-md py-8">Select a course to view quizzes.</div>
            </div>
        </div>

        <!-- Right Column: Quiz Detail / Taking / Results -->
            <div id="quiz-detail" class="flex-1 bg-surface-container-lowest overflow-y-auto p-lg" aria-live="polite">
            <div class="h-full flex items-center justify-center text-center text-on-surface-variant font-body-lg">
                Select a quiz to begin.
            </div>
        </div>
    </div>
</main>

<!-- Load attempt template (hidden) -->
<template id="question-tmpl">
    <div class="question-block space-y-md" data-qid="">
        <div class="flex justify-between items-start">
            <div class="flex-1">
                <div class="flex items-center gap-2 mb-xs">
                    <span class="text-xs font-bold text-outline uppercase tracking-wide question-type-badge"></span>
                    <span class="text-xs text-outline question-points-badge"></span>
                </div>
                <p class="font-body-lg text-on-surface font-semibold question-text"></p>
            </div>
            <span class="question-number text-sm font-bold text-outline ml-4"></span>
        </div>
        <div class="options-container space-y-sm"></div>
        <div class="text-area-container hidden">
            <textarea class="answer-textarea w-full bg-surface border border-outline-variant rounded-lg px-3 py-2 text-body-md text-on-surface focus:ring-2 focus:ring-primary/50" rows="4" placeholder="Type your answer..."></textarea>
        </div>
    </div>
</template>

<template id="result-question-tmpl">
    <div class="result-question-block border border-outline-variant rounded-xl p-md" data-qid="">
        <div class="flex justify-between items-start mb-sm">
            <div class="flex-1">
                <div class="flex items-center gap-2 mb-xs">
                    <span class="text-xs font-bold text-outline uppercase tracking-wide result-qtype"></span>
                    <span class="text-xs text-outline">/ <span class="result-qpoints"></span> pts</span>
                </div>
                <p class="font-semibold text-on-surface result-qtext"></p>
            </div>
            <div class="result-badge text-sm font-bold"></div>
        </div>
        <div class="result-answer text-body-md"></div>
        <div class="result-correct text-body-md text-green-700 mt-xs hidden"></div>
    </div>
</template>

<script>
const API = '/student/quiz_attempt.php';
let courseId = <?= json_encode($courseId) ?>;
let quizzes = [];
let currentQuiz = null;
let currentAttemptId = 0;
let questions = [];
let options = {};
let currentQuestionIndex = 0;
let timeLimit = null;
let timerInterval = null;
let startedAt = null;
let isSubmitting = false;

function changeCourse() {
    const val = document.getElementById('course-select').value;
    if (val) {
        window.location.search = '?course_id=' + val;
    }
}

function loadQuizzes() {
    const list = document.getElementById('quiz-list');
    if (!courseId) {
        list.innerHTML = '<div class="text-center text-on-surface-variant text-body-md py-8">Select a course to view quizzes.</div>';
        return;
    }
    list.innerHTML = '<div class="text-center text-on-surface-variant text-body-md py-8">Loading quizzes...</div>';

    fetch(API + '?course_id=' + courseId)
        .then(r => r.json())
        .then(d => {
            if (!d.success) { list.innerHTML = '<div class="text-center text-error py-8">Failed to load quizzes.</div>'; return; }
            quizzes = d.data || [];
            if (quizzes.length === 0) {
                list.innerHTML = '<div class="text-center text-on-surface-variant text-body-md py-8">No quizzes available for this course.</div>';
                return;
            }
            list.innerHTML = quizzes.map(q => {
                const attemptCount = parseInt(q.attempt_count) || 0;
                const maxAttempts = parseInt(q.max_attempts) || 0;
                const remaining = maxAttempts > 0 ? maxAttempts - attemptCount : 'Unlimited';
                return `<button type="button" class="quiz-card p-md rounded-xl border border-outline-variant bg-white hover:border-primary/30 hover:shadow-sm transition-all cursor-pointer text-left w-full" data-qid="${q.id}" onclick="selectQuiz(${q.id})">
                    <div class="flex justify-between items-start mb-sm">
                        <span class="bg-primary/10 text-primary text-[10px] font-bold px-2 py-0.5 rounded-full uppercase">${attemptCount > 0 ? 'Attempted' : 'New'}</span>
                        <span class="text-xs text-outline">${q.question_count || 0} questions</span>
                    </div>
                    <h3 class="font-semibold text-on-surface mb-xs">${esc(quizTitle(q))}</h3>
                    <div class="flex justify-between text-xs text-outline">
                        <span>Pass: ${q.passing_score || 0}%</span>
                        <span>Attempts: ${attemptCount} / ${remaining}</span>
                    </div>
                </button>`;
            }).join('');
        })
        .catch(() => {
            list.innerHTML = '<div class="text-center text-error py-8">Network error loading quizzes.</div>';
        });
}

function quizTitle(q) {
    if (q.title) return q.title;
    if (q.course_title) return 'Quiz for ' + q.course_title;
    return 'Untitled Quiz';
}

function esc(s) {
    const d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
}

function selectQuiz(quizId) {
    currentQuiz = quizzes.find(q => parseInt(q.id) === quizId);
    if (!currentQuiz) return;

    const detail = document.getElementById('quiz-detail');
    const attemptCount = parseInt(currentQuiz.attempt_count) || 0;

    detail.innerHTML = `<div class="max-w-2xl mx-auto space-y-lg pt-lg">
        <div class="bg-white rounded-xl border border-outline-variant shadow-sm p-lg text-center space-y-md">
            <span class="material-symbols-outlined text-5xl text-primary">quiz</span>
            <h2 class="font-h2 text-h2 text-on-surface">${esc(quizTitle(currentQuiz))}</h2>
            <p class="text-on-surface-variant text-body-md">${currentQuiz.description ? esc(currentQuiz.description) : 'Test your knowledge.'}</p>
            <div class="flex justify-center gap-lg text-sm text-on-surface-variant">
                <span>${currentQuiz.question_count || 0} Questions</span>
                <span>Pass: ${currentQuiz.passing_score || 0}%</span>
                ${currentQuiz.time_limit_minutes ? '<span>' + currentQuiz.time_limit_minutes + ' min</span>' : ''}
                <span>Attempts: ${attemptCount} / ${(currentQuiz.max_attempts || 0) > 0 ? currentQuiz.max_attempts : '∞'}</span>
            </div>
            <button onclick="startQuiz(${currentQuiz.id})" class="bg-primary text-on-primary px-lg py-sm rounded-lg font-semibold hover:opacity-90 transition-opacity">
                ${attemptCount > 0 ? 'Retake Quiz' : 'Start Quiz'}
            </button>
            ${attemptCount > 0 ? `<button onclick="viewResults(${currentQuiz.id})" class="ml-sm bg-surface-container-high text-on-surface px-lg py-sm rounded-lg font-semibold hover:opacity-90 transition-opacity">View Past Results</button>` : ''}
        </div>
    </div>`;
}

function startQuiz(quizId) {
    const detail = document.getElementById('quiz-detail');
    detail.innerHTML = '<div class="h-full flex items-center justify-center"><p class="text-on-surface-variant">Starting quiz...</p></div>';

    fetch(API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'start', quiz_id: quizId, csrf_token: '<?= csrf_token() ?>' })
    })
    .then(r => r.json())
    .then(d => {
        if (!d.success) { showQuizError(d.error || 'Failed to start quiz.'); return; }
        currentAttemptId = parseInt(d.data.attempt_id);
        loadQuestions();
    })
    .catch(() => showQuizError('Network error.'));
}

function loadQuestions() {
    fetch(API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'questions', attempt_id: currentAttemptId, quiz_id: currentQuiz.id, csrf_token: '<?= csrf_token() ?>' })
    })
    .then(r => r.json())
    .then(d => {
        if (!d.success) { showQuizError(d.error || 'Failed to load questions.'); return; }
        questions = d.data.questions || [];
        options = d.data.options || {};
        timeLimit = d.data.time_limit_minutes;
        startedAt = d.data.started_at;
        currentQuestionIndex = 0;
        renderQuestions();
    })
    .catch(() => showQuizError('Network error.'));
}

function renderQuestions() {
    const detail = document.getElementById('quiz-detail');
    const qs = questions.filter(q => !parseInt(q.is_submitted));

    if (qs.length === 0) {
        detail.innerHTML = `<div class="max-w-2xl mx-auto space-y-lg pt-lg">
            <div class="bg-white rounded-xl border border-outline-variant shadow-sm p-lg text-center space-y-md">
                <span class="material-symbols-outlined text-5xl text-primary">check_circle</span>
                <h2 class="font-h2 text-h2 text-on-surface">Quiz Already Submitted</h2>
                <p class="text-on-surface-variant">This attempt has already been submitted.</p>
                <button onclick="viewResults(${currentQuiz.id})" class="bg-primary text-on-primary px-lg py-sm rounded-lg font-semibold">View Results</button>
            </div>
        </div>`;
        return;
    }

    const total = qs.length;
    const q = qs[currentQuestionIndex];
    const qOptions = options[q.id] || [];

    const tmpl = document.getElementById('question-tmpl').content.cloneNode(true);
    const block = tmpl.querySelector('.question-block');
    block.dataset.qid = q.id;

    block.querySelector('.question-number').textContent = (currentQuestionIndex + 1) + ' / ' + total;
    block.querySelector('.question-text').textContent = q.question_text;
    block.querySelector('.question-type-badge').textContent = q.question_type.replace(/_/g, ' ');
    block.querySelector('.question-points-badge').textContent = (q.points || 0) + ' pts';

    const optContainer = block.querySelector('.options-container');
    const textContainer = block.querySelector('.text-area-container');

    if (q.question_type === 'multiple_choice' || q.question_type === 'true_false') {
        optContainer.innerHTML = (qOptions || []).map(o =>
            `<button type="button" class="quiz-option border border-outline-variant rounded-lg px-3 py-2 text-body-md text-on-surface text-left w-full" data-oid="${o.id}" onclick="selectOption(this, ${q.id}, ${o.id})">${esc(o.option_text)}</button>`
        ).join('');
        textContainer.classList.add('hidden');
    } else {
        optContainer.innerHTML = '';
        textContainer.classList.remove('hidden');
        const ta = textContainer.querySelector('textarea');
        if (ta) {
            ta.oninput = function() { saveAnswer(q.id, null, this.value); };
        }
        textContainer.classList.remove('hidden');
    }

    const opts = qOptions || [];
    if (q.answer_id) {
        if (q.question_type === 'multiple_choice' || q.question_type === 'true_false') {
            const prevOpt = optContainer.querySelector(`[data-oid="${q.selected_option_id}"]`);
            if (prevOpt) selectOption(prevOpt, q.id, q.selected_option_id);
        } else {
            const ta = textContainer.querySelector('textarea');
            if (ta && q.answer_text) ta.value = q.answer_text;
        }
    }

    detail.innerHTML = `<div class="max-w-2xl mx-auto space-y-lg pt-lg">
        <div class="flex justify-between items-center">
            <button onclick="quitQuiz()" class="text-outline hover:text-on-surface text-sm flex items-center gap-1">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span> Quit
            </button>
            ${timeLimit ? `<div id="timer" class="text-sm font-bold text-error flex items-center gap-1">
                <span class="material-symbols-outlined text-[18px]">timer</span> <span id="timer-display"></span>
            </div>` : ''}
        </div>
        <div class="bg-white rounded-xl border border-outline-variant shadow-sm p-lg">
            ${block.outerHTML}
        </div>
        <div class="flex justify-between items-center">
            <button onclick="prevQuestion()" class="px-md py-sm rounded-lg border border-outline-variant text-on-surface font-semibold hover:bg-surface-container-low transition-colors ${currentQuestionIndex === 0 ? 'opacity-30 pointer-events-none' : ''}">
                <span class="material-symbols-outlined text-[18px] align-middle">chevron_left</span> Previous
            </button>
            <div class="flex items-center gap-2">
                <span class="text-xs text-outline">${currentQuestionIndex + 1} of ${total}</span>
                <div class="flex gap-1">
                    ${qs.map((_, i) =>
                        `<button type="button" class="w-2 h-2 rounded-full ${i === currentQuestionIndex ? 'bg-primary' : 'bg-outline-variant'} cursor-pointer" onclick="goToQuestion(${i})" aria-label="Go to question ${i + 1}"></button>`
                    ).join('')}
                </div>
            </div>
            ${currentQuestionIndex < total - 1
                ? `<button onclick="nextQuestion()" class="px-md py-sm rounded-lg bg-primary text-on-primary font-semibold hover:opacity-90 transition-opacity">Next <span class="material-symbols-outlined text-[18px] align-middle">chevron_right</span></button>`
                : `<button onclick="confirmSubmit()" class="px-md py-sm rounded-lg bg-green-600 text-white font-semibold hover:opacity-90 transition-opacity flex items-center gap-1"><span class="material-symbols-outlined text-[18px]">check</span> Submit Quiz</button>`
            }
        </div>
    </div>`;

    // Start timer
    if (timeLimit && startedAt) {
        startTimer();
    }
}

function selectOption(el, questionId, optionId) {
    const container = el.closest('.options-container');
    container.querySelectorAll('.quiz-option').forEach(o => o.classList.remove('selected'));
    el.classList.add('selected');
    saveAnswer(questionId, optionId, '');
}

function saveAnswer(questionId, optionId, text) {
    fetch(API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'answer', attempt_id: currentAttemptId,
            quiz_id: currentQuiz.id,
            question_id: questionId,
            selected_option_id: optionId,
            answer_text: text,
            csrf_token: '<?= csrf_token() ?>'
        })
    }).catch(() => {});
}

function nextQuestion() {
    if (currentQuestionIndex < questions.filter(q => !parseInt(q.is_submitted)).length - 1) {
        currentQuestionIndex++;
        renderQuestions();
    }
}

function prevQuestion() {
    if (currentQuestionIndex > 0) {
        currentQuestionIndex--;
        renderQuestions();
    }
}

function goToQuestion(i) {
    currentQuestionIndex = i;
    renderQuestions();
}

function confirmSubmit() {
    if (isSubmitting) return;
    if (!confirm('Are you sure you want to submit the quiz? This action cannot be undone.')) return;
    isSubmitting = true;

    fetch(API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'submit', attempt_id: currentAttemptId, quiz_id: currentQuiz.id, csrf_token: '<?= csrf_token() ?>' })
    })
    .then(r => r.json())
    .then(d => {
        isSubmitting = false;
        if (d.success) {
            if (timerInterval) clearInterval(timerInterval);
            viewResults(currentQuiz.id, currentAttemptId);
        } else {
            showQuizError(d.error || 'Failed to submit.');
        }
    })
    .catch(() => { isSubmitting = false; showQuizError('Network error.'); });
}

function startTimer() {
    if (timerInterval) clearInterval(timerInterval);
    const start = new Date(startedAt).getTime();
    const limitMs = timeLimit * 60 * 1000;
    const end = start + limitMs;

    function tick() {
        const now = Date.now();
        const remaining = Math.max(0, end - now);
        const mins = Math.floor(remaining / 60000);
        const secs = Math.floor((remaining % 60000) / 1000);
        const display = document.getElementById('timer-display');
        if (display) display.textContent = mins + ':' + (secs < 10 ? '0' : '') + secs;
        if (remaining <= 0) {
            clearInterval(timerInterval);
            confirmSubmit();
        }
    }
    tick();
    timerInterval = setInterval(tick, 1000);
}

function quitQuiz() {
    if (timerInterval) clearInterval(timerInterval);
    document.getElementById('quiz-detail').innerHTML =
        '<div class="h-full flex items-center justify-center text-center text-on-surface-variant font-body-lg">Quiz paused. Select it again to resume.</div>';
}

function viewResults(quizId, attemptId) {
    const aid = attemptId || 0;
    const detail = document.getElementById('quiz-detail');
    detail.innerHTML = '<div class="h-full flex items-center justify-center"><p class="text-on-surface-variant">Loading results...</p></div>';

    const fetchResults = (id) => {
        fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'results', attempt_id: id, quiz_id: quizId, csrf_token: '<?= csrf_token() ?>' })
        })
        .then(r => r.json())
        .then(d => {
            if (!d.success) { detail.innerHTML = '<div class="text-center text-error py-8">Failed to load results.</div>'; return; }
            renderResults(d.data);
        })
        .catch(() => { detail.innerHTML = '<div class="text-center text-error py-8">Network error.</div>'; });
    };

    if (aid > 0) {
        fetchResults(aid);
    } else {
        fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'my_attempts', quiz_id: quizId, csrf_token: '<?= csrf_token() ?>' })
        })
        .then(r => r.json())
        .then(d => {
            if (!d.success || !d.data || d.data.length === 0) {
                detail.innerHTML = '<div class="text-center text-on-surface-variant py-8">No results available.</div>';
                return;
            }
            const attempts = d.data;
            if (attempts.length === 1) {
                fetchResults(attempts[0].id);
            } else {
                detail.innerHTML = `<div class="max-w-2xl mx-auto space-y-lg pt-lg">
                    <h2 class="font-h2 text-h2 text-on-surface">Past Attempts</h2>
                    ${attempts.map(a => {
                        const pct = a.total_points > 0 ? Math.round((a.score / a.total_points) * 100) : 0;
                        return `<div class="bg-white rounded-xl border border-outline-variant shadow-sm p-md flex justify-between items-center cursor-pointer hover:border-primary/30" onclick="viewResults(${quizId}, ${a.id})">
                            <div>
                                <p class="font-semibold text-on-surface">Attempt on ${new Date(a.completed_at).toLocaleDateString()}</p>
                                <p class="text-sm text-on-surface-variant">${a.score} / ${a.total_points} points (${pct}%)</p>
                            </div>
                            <span class="text-sm font-bold ${parseInt(a.passed) ? 'text-green-600' : 'text-red-500'}">${parseInt(a.passed) ? 'Passed' : 'Failed'}</span>
                        </div>`;
                    }).join('')}
                </div>`;
            }
        })
        .catch(() => { detail.innerHTML = '<div class="text-center text-error py-8">Network error.</div>'; });
    }
}

function renderResults(data) {
    const attempt = data.attempt;
    const answers = data.answers || [];
    const correctOptions = data.correct_options || {};
    const att = parseInt(attempt.score) || 0;
    const total = parseInt(attempt.total_points) || 1;
    const pct = Math.round((att / total) * 100);
    const passed = parseInt(attempt.passed);

    let correctCount = 0;
    answers.forEach(a => {
        if (parseInt(a.is_correct)) correctCount++;
        else if (a.question_type === 'essay' || a.question_type === 'short_answer') {
            if (a.points_earned !== null && parseInt(a.points_earned) > 0 && parseInt(a.points_earned) >= parseInt(a.max_points) / 2) correctCount++;
        }
    });

    const detail = document.getElementById('quiz-detail');
    let html = `<div class="max-w-2xl mx-auto space-y-lg pt-lg">
        <div class="bg-white rounded-xl border ${passed ? 'border-green-200' : 'border-red-200'} shadow-sm p-lg text-center space-y-md">
            <span class="material-symbols-outlined text-5xl ${passed ? 'text-green-600' : 'text-red-500'}">${passed ? 'check_circle' : 'cancel'}</span>
            <h2 class="font-h2 text-h2 text-on-surface">${passed ? 'Passed!' : 'Not Passed'}</h2>
            <div class="flex justify-center items-baseline gap-2">
                <span class="text-4xl font-bold ${passed ? 'text-green-600' : 'text-red-500'}">${pct}%</span>
                <span class="text-on-surface-variant">(${att} / ${total} points)</span>
            </div>
            <div class="flex justify-center gap-lg text-sm text-on-surface-variant">
                <span>${correctCount} correct</span>
                <span>${answers.length} questions</span>
                <span>Pass: ${attempt.passing_score || currentQuiz?.passing_score || 0}%</span>
            </div>
            <div class="flex justify-center gap-sm">
                <button onclick="selectQuiz(${currentQuiz?.id || 0})" class="bg-primary text-on-primary px-lg py-sm rounded-lg font-semibold hover:opacity-90">Back to Quiz</button>
                ${currentQuiz && parseInt(currentQuiz.attempt_count) < parseInt(currentQuiz.max_attempts || 999) ? `<button onclick="startQuiz(${currentQuiz.id})" class="bg-surface-container-high text-on-surface px-lg py-sm rounded-lg font-semibold hover:opacity-90">Retake</button>` : ''}
            </div>
        </div>`;

    // Question-by-question results
    answers.forEach(a => {
        const maxPts = parseInt(a.max_points) || 0;
        const earned = a.points_earned !== null ? parseInt(a.points_earned) : 0;
        const isCorrect = parseInt(a.is_correct) || (a.question_type !== 'multiple_choice' && a.question_type !== 'true_false' && earned >= maxPts / 2);
        const co = correctOptions[a.id] || [];

        html += `<div class="bg-white rounded-xl border border-outline-variant shadow-sm p-md space-y-sm">
            <div class="flex justify-between items-start">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-xs">
                        <span class="text-xs font-bold text-outline uppercase">${a.question_type.replace(/_/g, ' ')}</span>
                        <span class="text-xs text-outline">/ ${maxPts} pts</span>
                    </div>
                    <p class="font-semibold text-on-surface">${esc(a.question_text)}</p>
                </div>
                <div class="text-sm font-bold ${earned >= maxPts ? 'text-green-600' : earned > 0 ? 'text-amber-600' : 'text-red-500'} ml-4">+${earned}</div>
            </div>
            <div class="text-body-md text-on-surface-variant">
                ${a.question_type === 'multiple_choice' || a.question_type === 'true_false'
                    ? `<p><span class="font-medium">Your answer:</span> ${a.selected_option_text ? esc(a.selected_option_text) : '<span class="text-error">No answer</span>'}</p>`
                    : `<p><span class="font-medium">Your answer:</span> ${a.answer_text ? esc(a.answer_text) : '<span class="text-error">No answer</span>'}</p>`
                }
            </div>`;

        if (a.question_type === 'multiple_choice' || a.question_type === 'true_false') {
            if (!parseInt(a.is_correct) && co.length > 0) {
                html += `<div class="text-body-md text-green-700"><span class="font-medium">Correct answer:</span> ${co.map(c => esc(c.option_text)).join(', ')}</div>`;
            }
        }

        if (a.points_earned === null && (a.question_type === 'essay' || a.question_type === 'short_answer')) {
            html += `<div class="text-body-md text-amber-600 italic">Awaiting instructor grading.</div>`;
        }

        html += `</div>`;
    });

    html += `</div>`;
    detail.innerHTML = html;
}

function showQuizError(msg) {
    document.getElementById('quiz-detail').innerHTML =
        `<div class="max-w-md mx-auto pt-lg text-center space-y-md">
            <span class="material-symbols-outlined text-4xl text-error">error</span>
            <p class="text-error font-semibold">${esc(msg)}</p>
            <button onclick="selectQuiz(${currentQuiz?.id || 0})" class="bg-primary text-on-primary px-lg py-sm rounded-lg font-semibold">Go Back</button>
        </div>`;
}

// Load quizzes on page load
document.addEventListener('DOMContentLoaded', loadQuizzes);
</script>
</body>
</html>

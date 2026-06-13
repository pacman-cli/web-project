<?php
// 30_Instructor_Messages/index.php
// Instructor message portal — fully dynamic SPA (no page reloads, realtime polling)

require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/design-system.php';
require_once __DIR__ . '/../config/csrf.php';
requireRole('instructor');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php lms_head('Messages', 'instructor'); ?>
<style>
    .conversation-item { transition: background-color .15s, border-color .15s; }
    .conversation-item.active { background-color: rgba(var(--tw-primary-rgb, 26 107 60) / 0.08); border-left: 4px solid var(--tw-primary, #1a6b3c); }
    .conversation-item:not(.active) { border-left: 4px solid transparent; }
    .msg-bubble { max-width: 80%; word-break: break-word; }
    @keyframes fadeIn { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:translateY(0); } }
    .msg-animate { animation: fadeIn .2s ease-out; }
</style>
</head>
<body class="bg-surface font-body-md text-on-surface">

<?php lms_sidebar('instructor', '/30_Instructor_Messages/index.php'); ?>
<?php lms_topbar('instructor', 'Messages'); ?>

<main id="lms-main-content" class="lms-main">
    <div class="flex-grow flex overflow-hidden h-[calc(100vh-120px)]">
        <!-- Conversation Sidebar -->
        <section class="w-[380px] border-r border-outline-variant bg-surface-container-lowest flex flex-col flex-shrink-0">
            <div class="p-md border-b border-outline-variant">
                <h2 class="font-h2 text-h2 text-on-surface mb-xs">Conversations</h2>
                <p class="text-xs text-on-surface-variant">Chat with students and colleagues</p>
            </div>
            <div id="conversations-list" class="flex-1 overflow-y-auto">
                <div class="p-lg text-center text-on-surface-variant text-sm">Loading conversations...</div>
            </div>
        </section>

        <!-- Chat Window -->
        <section class="flex-grow flex flex-col bg-surface-bright min-w-0">
            <!-- Chat Header -->
            <div id="chat-header" class="h-[72px] px-md border-b border-outline-variant flex items-center justify-between bg-surface-container-lowest flex-shrink-0">
                <div id="chat-header-content" class="flex items-center gap-sm">
                    <p class="text-on-surface-variant text-sm">Select a conversation to start chatting.</p>
                </div>
            </div>

            <!-- Messages Area -->
            <div id="chat-messages" class="flex-1 overflow-y-auto p-lg flex flex-col gap-md" aria-live="polite"></div>

            <!-- Input Area -->
            <div id="chat-input-area" class="p-md bg-surface-container-lowest border-t border-outline-variant flex-shrink-0 hidden">
                <form id="chat-form" onsubmit="sendMessage(event)" class="max-w-[1000px] mx-auto flex items-center gap-sm bg-surface p-xs border border-outline-variant rounded-xl shadow-sm">
                    <label for="chat-input" class="sr-only">Message</label>
                    <input required autocomplete="off" id="chat-input" class="flex-1 bg-transparent border-none focus:ring-0 text-body-md py-sm" placeholder="Type a message..." type="text"/>
                    <button type="submit" class="w-[44px] h-[44px] bg-primary text-on-primary rounded-lg flex items-center justify-center hover:brightness-110 active:scale-95 transition-all shadow-md" aria-label="Send message">
                        <span class="material-symbols-outlined" aria-hidden="true">send</span>
                    </button>
                </form>
            </div>
        </section>
    </div>
</main>

<script>
const CSRF_TOKEN = '<?= csrf_token() ?>';
const CURRENT_USER_ID = <?= $_SESSION['user_id'] ?>;

let conversations = [];
let activeCourseId = 0;
let activePartnerId = 0;
let lastMessageId = 0;
let messagePoll = null;
let conversationPoll = null;

// ── Init ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    loadConversations();
    startConversationPoll();
});

// ── Conversations ────────────────────────────────────────────────────────────
function loadConversations() {
    fetch(BASE_URL + '/api/chat/conversations.php')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                conversations = data.data;
                renderConversations();
            }
        })
        .catch(err => console.error('Failed to load conversations:', err));
}

function renderConversations() {
    const container = document.getElementById('conversations-list');

    if (conversations.length === 0) {
        container.innerHTML = '<p class="p-lg text-center text-on-surface-variant">No conversations available yet.</p>';
        return;
    }

    let html = '';
    let lastCourse = '';

    conversations.forEach(conv => {
        // Course group header
        if (conv.course_title !== lastCourse) {
            lastCourse = conv.course_title;
            html += `<div class="px-md py-xs bg-surface-container-low text-[11px] font-bold text-secondary uppercase tracking-wider sticky top-0 z-10">${escapeHtml(conv.course_title)}</div>`;
        }

        const isActive = conv.course_id === activeCourseId && conv.partner_id === activePartnerId;
        const avatarBg = conv.partner_role === 'instructor' ? 'bg-tertiary/10 text-tertiary' : 'bg-primary/10 text-primary';
        const roleBadge = conv.partner_role === 'instructor'
            ? '<span class="text-[9px] px-1 py-[1px] rounded bg-tertiary/10 text-tertiary font-bold uppercase">Instr</span>'
            : '<span class="text-[9px] px-1 py-[1px] rounded bg-primary/10 text-primary font-bold uppercase">Student</span>';

        html += `
            <a href="javascript:void(0)" onclick="selectConversation(${conv.course_id}, ${conv.partner_id})"
               class="conversation-item block p-md border-b border-outline-variant hover:bg-surface-container-low cursor-pointer ${isActive ? 'active' : ''}"
               data-course="${conv.course_id}" data-partner="${conv.partner_id}">
                <div class="flex gap-sm">
                    <div class="w-10 h-10 rounded-full ${avatarBg} flex items-center justify-center font-bold flex-shrink-0">
                        ${escapeHtml(conv.partner_name.substring(0, 2))}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex justify-between items-baseline mb-xs">
                            <div class="flex items-center gap-xs min-w-0">
                                <h4 class="font-label-md text-on-surface font-semibold truncate">${escapeHtml(conv.partner_name)}</h4>
                                ${roleBadge}
                            </div>
                            <span class="text-[10px] text-secondary flex-shrink-0 ml-xs">${conv.last_message_time}</span>
                        </div>
                        <p class="text-xs text-on-surface-variant truncate mt-0.5">${escapeHtml(conv.last_message)}</p>
                        ${conv.unread_count > 0 ? `<div class="mt-xs flex justify-end"><span class="bg-primary text-on-primary text-[10px] font-bold px-1.5 py-0.5 rounded-full">${conv.unread_count}</span></div>` : ''}
                    </div>
                </div>
            </a>`;
    });

    container.innerHTML = html;
}

function selectConversation(courseId, partnerId) {
    activeCourseId = courseId;
    activePartnerId = partnerId;
    lastMessageId = 0;

    // Update sidebar active state
    document.querySelectorAll('.conversation-item').forEach(el => {
        el.classList.toggle('active', parseInt(el.dataset.course) === courseId && parseInt(el.dataset.partner) === partnerId);
    });

    // Find conversation data
    const conv = conversations.find(c => c.course_id === courseId && c.partner_id === partnerId);
    if (!conv) return;

    // Update header
    const avatarBg = conv.partner_role === 'instructor' ? 'bg-tertiary/10 text-tertiary' : 'bg-primary/10 text-primary';
    document.getElementById('chat-header-content').innerHTML = `
        <div class="w-10 h-10 rounded-full ${avatarBg} flex items-center justify-center font-bold">${escapeHtml(conv.partner_name.substring(0, 2))}</div>
        <div>
            <h3 class="font-label-md text-on-surface font-bold">${escapeHtml(conv.partner_name)}</h3>
            <p class="text-xs text-secondary">${escapeHtml(conv.course_title)} · ${conv.partner_role === 'instructor' ? 'Instructor' : 'Student'}</p>
        </div>`;

    // Show input, clear messages
    document.getElementById('chat-input-area').classList.remove('hidden');
    document.getElementById('chat-messages').innerHTML = '<div class="py-8 text-center text-secondary text-sm">Loading messages...</div>';
    document.getElementById('chat-input').focus();

    // Clear unread badge in sidebar
    if (conv.unread_count > 0) {
        conv.unread_count = 0;
        renderConversations();
        // Re-highlight active
        document.querySelectorAll('.conversation-item').forEach(el => {
            el.classList.toggle('active', parseInt(el.dataset.course) === courseId && parseInt(el.dataset.partner) === partnerId);
        });
    }

    // Start message polling
    loadMessages();
    startMessagePoll();
}

// ── Messages ─────────────────────────────────────────────────────────────────
function loadMessages() {
    if (!activeCourseId || !activePartnerId) return;

    fetch(BASE_URL + `/api/chat/fetch_messages.php?course_id=${activeCourseId}&partner_id=${activePartnerId}&last_message_id=${lastMessageId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.data.length > 0) {
                const chatArea = document.getElementById('chat-messages');
                const wasAtBottom = chatArea.scrollHeight - chatArea.scrollTop - chatArea.clientHeight < 80;

                data.data.forEach(msg => {
                    const isSelf = parseInt(msg.sender_id) === CURRENT_USER_ID;
                    const msgDiv = document.createElement('div');
                    msgDiv.className = isSelf ? 'flex justify-end msg-animate' : 'flex gap-sm max-w-[80%] msg-animate';

                    if (isSelf) {
                        msgDiv.innerHTML = `<div class="msg-bubble bg-primary p-sm rounded-xl rounded-br-none text-on-primary shadow-md">
                            <p class="text-body-md">${escapeHTML(msg.message_text)}</p>
                            <span class="text-[10px] text-on-primary/70 mt-xs block text-right">${formatTime(msg.created_at)}</span>
                        </div>`;
                    } else {
                        msgDiv.innerHTML = `<div class="msg-bubble bg-surface-container-high p-sm rounded-xl rounded-bl-none text-on-surface-variant shadow-sm">
                            <p class="text-body-md">${escapeHTML(msg.message_text)}</p>
                            <span class="text-[10px] text-secondary mt-xs block">${formatTime(msg.created_at)}</span>
                        </div>`;
                    }
                    chatArea.appendChild(msgDiv);
                    lastMessageId = Math.max(lastMessageId, parseInt(msg.id));
                });

                if (wasAtBottom) chatArea.scrollTop = chatArea.scrollHeight;
            }
        })
        .catch(err => console.error('Message poll error:', err));
}

function sendMessage(event) {
    event.preventDefault();
    const input = document.getElementById('chat-input');
    const text = input.value.trim();
    if (!text || !activeCourseId || !activePartnerId) return;

    input.value = '';

    fetch(BASE_URL + '/api/chat/send_message.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
        body: JSON.stringify({ course_id: activeCourseId, receiver_id: activePartnerId, message_text: text })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            loadMessages();
            // Update conversation's last message in sidebar
            const conv = conversations.find(c => c.course_id === activeCourseId && c.partner_id === activePartnerId);
            if (conv) {
                conv.last_message = text;
                conv.last_message_time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                conv.last_message_time_raw = new Date().toISOString().slice(0, 19).replace('T', ' ');
                renderConversations();
                document.querySelectorAll('.conversation-item').forEach(el => {
                    el.classList.toggle('active', parseInt(el.dataset.course) === activeCourseId && parseInt(el.dataset.partner) === activePartnerId);
                });
            }
        } else {
            console.error('Send failed:', data.error);
            input.value = text;
        }
    })
    .catch(err => {
        console.error('Send error:', err);
        input.value = text;
    });
}

// ── Polling ──────────────────────────────────────────────────────────────────
function startMessagePoll() {
    if (messagePoll) clearInterval(messagePoll);
    messagePoll = setInterval(() => {
        if (!document.hidden) loadMessages();
    }, 3000);
}

function startConversationPoll() {
    if (conversationPoll) clearInterval(conversationPoll);
    conversationPoll = setInterval(() => {
        if (!document.hidden) loadConversations();
    }, 10000);
}

// Re-highlight active after conversation list re-renders from polling
const origRenderConversations = renderConversations;
renderConversations = function() {
    origRenderConversations();
    if (activeCourseId && activePartnerId) {
        document.querySelectorAll('.conversation-item').forEach(el => {
            el.classList.toggle('active', parseInt(el.dataset.course) === activeCourseId && parseInt(el.dataset.partner) === activePartnerId);
        });
    }
};

// ── Helpers ──────────────────────────────────────────────────────────────────
function escapeHTML(str) {
    if (!str) return '';
    return str.replace(/[&<>"']/g, tag => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[tag] || tag);
}
function escapeHtml(str) { return escapeHTML(str); }

function formatTime(dateTimeStr) {
    if (!dateTimeStr) return '';
    const date = new Date(dateTimeStr.replace(/-/g, '/'));
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}
</script>
</body>
</html>

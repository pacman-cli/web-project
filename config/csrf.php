<?php
// config/csrf.php
// CSRF token generation and validation for forms and AJAX requests

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generate or retrieve an existing CSRF token for the current session.
 * Tokens are HMAC-signed with session_id to prevent token reuse across sessions.
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_token_expires'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_expires'] = time() + 7200; // 2-hour expiry
    }
    if ($_SESSION['csrf_token_expires'] < time()) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_expires'] = time() + 7200;
    }
    $hmac = hash_hmac('sha256', $_SESSION['csrf_token'], session_id());
    return base64_encode($_SESSION['csrf_token'] . '.' . $hmac);
}

/**
 * Validate a CSRF token. Accepts token from POST field, JSON body, or X-CSRF-Token header.
 */
function csrf_validate($token = null) {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token)) {
            $input = json_decode(file_get_contents('php://input'), true);
            $token = $input['csrf_token'] ?? '';
        }
        if (empty($token)) {
            $headers = function_exists('getallheaders') ? getallheaders() : [];
            $token = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? '';
        }
    }
    if (empty($token)) {
        return false;
    }
    if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_token_expires'])) {
        return false;
    }
    if ($_SESSION['csrf_token_expires'] < time()) {
        return false;
    }
    $decoded = base64_decode($token, true);
    if ($decoded === false || strpos($decoded, '.') === false) {
        return false;
    }
    list($stored, $hmac) = explode('.', $decoded, 2);
    $expected = hash_hmac('sha256', $_SESSION['csrf_token'], session_id());
    if (!hash_equals($expected, $hmac)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $stored);
}

/**
 * Require a valid CSRF token; sends JSON error or dies with HTML error.
 */
function require_csrf() {
    if (!csrf_validate()) {
        $isJson = (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
               || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
        if ($isJson) {
            header('Content-Type: application/json');
            http_response_code(419);
            echo json_encode(['error' => 'CSRF token validation failed. Please refresh and try again.']);
            exit;
        }
        http_response_code(419);
        die('<div style="font-family:sans-serif;text-align:center;margin-top:100px"><h2>Session Expired</h2><p>CSRF token validation failed. Please <a href="javascript:history.back()">go back</a> and try again.</p></div>');
    }
}

/**
 * Return an HTML hidden input with the CSRF token.
 */
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '"/>';
}

/**
 * Check login rate limit. Allows 5 attempts per 15-minute window.
 * Returns true if allowed, false if blocked.
 */
function login_rate_check() {
    $maxAttempts = 5;
    $windowMinutes = 15;
    $now = time();

    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_attempt_window'] = $now;
    }

    if ($_SESSION['login_attempt_window'] < ($now - $windowMinutes * 60)) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_attempt_window'] = $now;
    }

    return $_SESSION['login_attempts'] < $maxAttempts;
}

/**
 * Increment the login failure counter.
 */
function login_rate_failed() {
    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
    if (!isset($_SESSION['login_attempt_window'])) {
        $_SESSION['login_attempt_window'] = time();
    }
}

/**
 * Reset the login rate counter on successful login.
 */
function login_rate_reset() {
    unset($_SESSION['login_attempts']);
    unset($_SESSION['login_attempt_window']);
}

/**
 * Return remaining login attempts before lockout.
 */
function login_rate_remaining() {
    $maxAttempts = 5;
    $used = $_SESSION['login_attempts'] ?? 0;
    return max(0, $maxAttempts - $used);
}

/**
 * Check registration rate limit. Allows 3 registrations per IP per hour.
 * Uses session storage as a simple throttle.
 */
function register_rate_check() {
    $maxAttempts = 3;
    $windowSeconds = 3600;
    $now = time();

    if (!isset($_SESSION['register_attempts'])) {
        $_SESSION['register_attempts'] = 0;
        $_SESSION['register_attempt_window'] = $now;
    }

    if ($_SESSION['register_attempt_window'] < ($now - $windowSeconds)) {
        $_SESSION['register_attempts'] = 0;
        $_SESSION['register_attempt_window'] = $now;
    }

    return $_SESSION['register_attempts'] < $maxAttempts;
}

/**
 * Increment the registration failure counter.
 */
function register_rate_failed() {
    $_SESSION['register_attempts'] = ($_SESSION['register_attempts'] ?? 0) + 1;
    if (!isset($_SESSION['register_attempt_window'])) {
        $_SESSION['register_attempt_window'] = time();
    }
}

/**
 * Reset the registration rate counter on success.
 */
function register_rate_reset() {
    unset($_SESSION['register_attempts']);
    unset($_SESSION['register_attempt_window']);
}

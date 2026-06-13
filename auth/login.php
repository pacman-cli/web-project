<?php
// auth/login.php
// Login page with dynamic credentials validation and redirection

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

require_once __DIR__ . '/../config/design-system.php';

$error = '';

require_once __DIR__ . '/../config/csrf.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        $error = 'Session expired. Please try again.';
    } else {
        if (!login_rate_check()) {
            $retryAfter = 15 * 60 - (time() - ($_SESSION['login_attempt_window'] ?? time()));
            $retryAfter = ceil(max(0, $retryAfter) / 60);
            $error = "Too many login attempts. Try again in {$retryAfter} minute(s).";
        } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!empty($email) && !empty($password)) {
            try {
                $pdo = require_once __DIR__ . '/../config/db.php';

                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
                $stmt->execute(['email' => $email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    if ($user['status'] === 'active') {
                        login_rate_reset();
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['name']    = $user['name'];
                        $_SESSION['role']    = $user['role'];

                        if ($user['role'] === 'admin') {
                            header('Location: /02_Admin_Dashboard/index.php');
                        } elseif ($user['role'] === 'instructor') {
                            header('Location: /17_Instructor_Dashboard/index.php');
                        } else {
                            header('Location: /40_Student_Dashboard/index.php');
                        }
                        exit;
                    } else {
                        login_rate_failed();
                        $error = 'Your account has been deactivated.';
                    }
                } else {
                    login_rate_failed();
                    $error = 'Invalid email or password.';
                }
            } catch (Exception $e) {
                error_log('Login DB error: ' . $e->getMessage());
                $error = 'A system error occurred. Please try again later.';
            }
        } else {
            $error = 'Please fill in all fields.';
        }
    }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php lms_head('Login', 'guest'); ?>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white/5 backdrop-blur-xl border border-white/10 p-8 rounded-2xl shadow-2xl relative overflow-hidden">
        <!-- Background decorative blur elements -->
        <div class="absolute -top-10 -left-10 w-40 h-40 bg-purple-500/20 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-10 -right-10 w-40 h-40 bg-blue-500/20 rounded-full blur-3xl"></div>

        <div class="text-center mb-8 relative z-10">
            <h1 class="text-3xl font-bold text-white tracking-tight">Lyra Academy</h1>
            <p class="text-gray-400 text-sm mt-2">Music School Learning Management System</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="mb-6 bg-red-500/10 border border-red-500/30 text-red-200 text-sm p-4 rounded-xl flex items-center gap-2">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-400 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="space-y-6 relative z-10">
            <?= csrf_field() ?>
            <div>
                <label for="email" class="block text-sm font-medium text-gray-300 mb-2">Email Address</label>
                <input type="email" id="email" name="email" autocomplete="email" required
                    class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition-colors duration-200"
                    placeholder="you@lyra.edu">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-300 mb-2">Password</label>
                <input type="password" id="password" name="password" autocomplete="current-password" required
                    class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition-colors duration-200"
                    placeholder="••••••••">
            </div>

            <button type="submit"
                class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-semibold py-3 px-4 rounded-xl transition-colors duration-300 shadow-lg shadow-blue-500/20 active:scale-[0.98]">
                Sign In
            </button>
        </form>

        <div class="mt-8 text-center relative z-10">
            <p class="text-sm text-gray-400">
                New student? 
                <a href="/auth/register.php" class="text-blue-400 hover:text-blue-300 font-medium transition-colors">Apply now</a>
            </p>
        </div>
    </div>
</body>
</html>

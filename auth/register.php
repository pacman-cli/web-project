<?php
// auth/register.php
// Student registration page with transaction handling

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

require_once __DIR__ . '/../config/design-system.php';
require_once __DIR__ . '/../config/csrf.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        $error = 'Session expired. Please try again.';
    } elseif (!register_rate_check()) {
        $error = 'Too many registration attempts. Please try again later.';
    } else {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $dob = trim($_POST['dob'] ?? '');
    $level = trim($_POST['level'] ?? 'beginner');
    if (!in_array($level, ['beginner', 'intermediate', 'advanced'], true)) {
        $level = 'beginner';
    }

    if (!empty($name) && !empty($email) && !empty($password)) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            if (strlen($password) >= 6) {
                try {
                    $pdo = require_once __DIR__ . '/../config/db.php';
                    
                    // Check if email already exists
                    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
                    $checkStmt->execute(['email' => $email]);
                    if ($checkStmt->fetch()) {
                        $error = 'Email is already registered.';
                    } else {
                        // Start transaction to insert into both users and students
                        $pdo->beginTransaction();
                        
                        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                        
                        // 1. Insert into users
                        $userStmt = $pdo->prepare("
                            INSERT INTO users (name, email, password_hash, role) 
                            VALUES (:name, :email, :password_hash, 'student')
                        ");
                        $userStmt->execute([
                            'name' => $name,
                            'email' => $email,
                            'password_hash' => $passwordHash
                        ]);
                        $userId = $pdo->lastInsertId();
                        
                        // 2. Insert into students
                        $studentStmt = $pdo->prepare("
                            INSERT INTO students (user_id, date_of_birth, experience_level, enrollment_date) 
                            VALUES (:user_id, :dob, :level, :enroll_date)
                        ");
                        $studentStmt->execute([
                            'user_id' => $userId,
                            'dob' => !empty($dob) ? $dob : null,
                            'level' => $level,
                            'enroll_date' => date('Y-m-d')
                        ]);
                        
                        $pdo->commit();
                        register_rate_reset();
                        $success = 'Registration successful! You can now log in.';
                    }
                } catch (Exception $e) {
                    if (isset($pdo) && $pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('Registration DB error: ' . $e->getMessage());
                    $error = 'A system error occurred. Please try again later.';
                }
            } else {
                $error = 'Password must be at least 6 characters.';
            }
        } else {
            $error = 'Invalid email address format.';
        }
    } else {
        $error = 'Please fill in all required fields.';
    }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php lms_head('Register', 'guest'); ?>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white/5 backdrop-blur-xl border border-white/10 p-8 rounded-2xl shadow-2xl relative overflow-hidden my-8">
        <!-- Background decorative blur elements -->
        <div class="absolute -top-10 -left-10 w-40 h-40 bg-purple-500/20 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-10 -right-10 w-40 h-40 bg-blue-500/20 rounded-full blur-3xl"></div>

        <div class="text-center mb-8 relative z-10">
            <h1 class="text-3xl font-bold text-white tracking-tight">Student Signup</h1>
            <p class="text-gray-400 text-sm mt-2">Join the Lyra Academy Music LMS</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="mb-6 bg-red-500/10 border border-red-500/30 text-red-200 text-sm p-4 rounded-xl flex items-center gap-2">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-400 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="mb-6 bg-green-500/10 border border-green-500/30 text-green-200 text-sm p-4 rounded-xl flex items-center gap-2">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-400 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="space-y-5 relative z-10">
            <?= csrf_field() ?>
            <div>
                <label for="name" class="block text-sm font-medium text-gray-300 mb-2">Full Name *</label>
                <input type="text" id="name" name="name" autocomplete="name" required
                    class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition-colors duration-200"
                    placeholder="John Doe">
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-300 mb-2">Email Address *</label>
                <input type="email" id="email" name="email" autocomplete="email" required
                    class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition-colors duration-200"
                    placeholder="john@example.com">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-300 mb-2">Password *</label>
                <input type="password" id="password" name="password" autocomplete="new-password" required
                    class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition-colors duration-200"
                    placeholder="Min 6 characters">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="dob" class="block text-sm font-medium text-gray-300 mb-2">Date of Birth</label>
                    <input type="date" id="dob" name="dob" autocomplete="bday"
                        class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition-colors duration-200">
                </div>

                <div>
                    <label for="level" class="block text-sm font-medium text-gray-300 mb-2">Experience Level</label>
                    <select id="level" name="level"
                        class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition-colors duration-200">
                        <option value="beginner" class="bg-slate-800 text-white">Beginner</option>
                        <option value="intermediate" class="bg-slate-800 text-white">Intermediate</option>
                        <option value="advanced" class="bg-slate-800 text-white">Advanced</option>
                    </select>
                </div>
            </div>

            <button type="submit"
                class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-semibold py-3 px-4 rounded-xl transition-colors duration-300 shadow-lg shadow-blue-500/20 active:scale-[0.98] mt-2">
                Register Account
            </button>
        </form>

        <div class="mt-8 text-center relative z-10">
            <p class="text-sm text-gray-400">
                Already registered? 
                <a href="/auth/login.php" class="text-blue-400 hover:text-blue-300 font-medium transition-colors">Sign in</a>
            </p>
        </div>
    </div>
</body>
</html>

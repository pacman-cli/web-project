<?php
// database/reseed_admin.php
// Run once to re-hash the seeded admin password using PHP's current password_hash().
// Usage: php database/reseed_admin.php
//
// Requires a working PDO connection via config/db.php (auto-loads local override).

$pdo = require_once __DIR__ . '/../config/db.php';

$email    = 'admin@lyra.edu';
$password = 'admin123';

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE email = :email");
$stmt->execute(['hash' => $hash, 'email' => $email]);

if ($stmt->rowCount() > 0) {
    echo "Admin password reset OK ({$email}).\n";
} else {
    echo "No admin user found with email {$email}. Register one via auth/register.php first.\n";
}

<?php
// config/db.php
// Database connection configuration using PDO

$host = '127.0.0.1';
$db   = 'music_elms';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

// Check if a local override file exists
$local_config = __DIR__ . '/db.local.php';
if (file_exists($local_config)) {
    $override = include($local_config);
    if (is_array($override)) {
        $host = $override['host'] ?? $host;
        $db = $override['db'] ?? $db;
        $user = $override['user'] ?? $user;
        $pass = $override['pass'] ?? $pass;
    }
}

// Certificate salt is now in config/cert_helper.php

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     return new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     error_log('DB connection error: ' . $e->getMessage());
     // Check if we are in an API / JSON request context
     if ((isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) || 
         (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
         header('Content-Type: application/json');
         http_response_code(500);
         echo json_encode(['error' => 'Database connection failed. Please try again later.']);
         exit;
     }
     die("A system error occurred. Please try again later.");
}

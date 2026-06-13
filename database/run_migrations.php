<?php
// database/run_migrations.php
// Run pending SQL migrations using the application's PDO connection.
// Usage: php database/run_migrations.php

$pdo = require_once __DIR__ . '/../config/db.php';

$migrations = [
    'migration_assignment_attachments.sql' => __DIR__ . '/migration_assignment_attachments.sql',
    'migration_student_stats.sql' => __DIR__ . '/migration_student_stats.sql'
];

foreach ($migrations as $name => $path) {
    if (!file_exists($path)) {
        echo "Migration file not found: $name\n";
        continue;
    }

    echo "Running migration: $name...\n";
    $sql = file_get_contents($path);

    // Remove comments
    $sql = preg_replace('/--.*$/m', '', $sql);
    
    // Split into individual queries (by semicolon)
    $queries = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($queries as $query) {
        if (empty($query)) continue;
        try {
            $pdo->exec($query);
            echo "Success executing: " . substr(clean_query($query), 0, 50) . "...\n";
        } catch (PDOException $e) {
            // Ignore duplicate column errors (1060) or index duplicate errors
            if ($e->getCode() == '42S21' || strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "Skipped: Columns already exist.\n";
            } else {
                echo "Error executing query: " . $e->getMessage() . "\n";
            }
        }
    }
}

function clean_query($q) {
    return preg_replace('/\s+/', ' ', $q);
}

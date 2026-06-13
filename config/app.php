<?php
// config/app.php
// Auto-detects the base URL path so the app works in any subdirectory.
// Define BASE_URL before including this file to override detection.

if (!defined('BASE_URL')) {
    $docRoot   = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '/');

    // Walk up from the current script directory until we find config/app.php
    // That directory is the project root.
    $candidate = $scriptDir;
    $detected  = '';
    while ($candidate !== '' && $candidate !== '/') {
        if (file_exists($docRoot . $candidate . '/config/app.php')) {
            $detected = $candidate;
            break;
        }
        $candidate = dirname($candidate);
    }

    define('BASE_URL', $detected);
}

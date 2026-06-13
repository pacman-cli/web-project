<?php
// api/upload.php
// Secure general-purpose file uploader for LMS (Supports audio, video, and documents)

header('Content-Type: application/json');

require_once __DIR__ . '/middleware.php';
requireAuth();
require_once __DIR__ . '/../config/csrf.php';
require_csrf();

$pdo = require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONError('Request method not supported. Only POST is allowed.', 405);
}

// Access variables
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];

// Validate file payload presence
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    sendJSONError('File payload missing or upload encountered an error.');
}

$file = $_FILES['file'];
$fileName = basename($file['name']);
$fileSize = $file['size'];
$tmpPath  = $file['tmp_name'];

// 1. Check size constraints (20MB limit)
$maxSize = 20 * 1024 * 1024;
if ($fileSize > $maxSize) {
    sendJSONError('File size exceeds maximum allowed limit (20MB).');
}

// 2. Validate extensions & determine types
$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

// MIME-to-extension mapping for validation
$validatedMimes = [
    'mp3' => ['audio/mpeg', 'audio/mp3'],
    'wav' => ['audio/wav', 'audio/x-wav', 'audio/wave'],
    'mp4' => ['video/mp4', 'audio/mp4'],
    'pdf' => ['application/pdf']
];

$allowedExts = array_keys($validatedMimes);

if (!in_array($ext, $allowedExts, true)) {
    sendJSONError('Unsupported file extension. Allowed: ' . strtoupper(implode(', ', $allowedExts)) . '.');
}

$fileType = match ($ext) {
    'mp3', 'wav' => 'audio',
    'mp4' => 'video',
    'pdf' => 'document',
    default => 'document'
};

// 3. MIME type validation against extension
$actualMime = mime_content_type($tmpPath);
$expectedMimes = $validatedMimes[$ext];
if (!$actualMime || !in_array($actualMime, $expectedMimes, true)) {
    sendJSONError('File content type does not match its extension. Upload rejected.');
}

// 4. Establish structure: /uploads/{user_id}/{type}/
$relativeDir = "/uploads/{$userId}/{$fileType}/";
$uploadDir = __DIR__ . '/..' . $relativeDir;

if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// 5. Generate unique stored name
$storedName = uniqid('file_', true) . '.' . $ext;
$destPath   = $uploadDir . $storedName;
$dbFilePath = $relativeDir . $storedName;

if (move_uploaded_file($tmpPath, $destPath)) {
    try {
        // Track file in user_uploads table
        $stmt = $pdo->prepare("
            INSERT INTO user_uploads (user_id, original_name, stored_name, file_path, file_type, mime_type, file_size) 
            VALUES (:user_id, :original_name, :stored_name, :file_path, :file_type, :mime_type, :file_size)
        ");
        $stmt->execute([
            'user_id' => $userId,
            'original_name' => $fileName,
            'stored_name' => $storedName,
            'file_path' => $dbFilePath,
            'file_type' => $fileType,
            'mime_type' => $actualMime,
            'file_size' => $fileSize
        ]);
        $uploadId = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'File uploaded and tracked successfully.',
            'file' => [
                'id' => $uploadId,
                'original_name' => $fileName,
                'file_path' => $dbFilePath,
                'file_type' => $fileType,
                'mime_type' => $actualMime,
                'file_size' => $fileSize
            ]
        ]);

    } catch (Exception $e) {
        error_log('Upload DB error: ' . $e->getMessage());
        if (file_exists($destPath)) {
            unlink($destPath);
        }
        sendJSONError('Failed to track uploaded file in database.', 500);
    }
} else {
    sendJSONError('Failed to move uploaded file. Check directory write permissions.', 500);
}

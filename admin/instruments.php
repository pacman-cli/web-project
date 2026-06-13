<?php
// admin/instruments.php
// Admin operations for managing instrument categories

header('Content-Type: application/json');

require_once __DIR__ . '/../api/middleware.php';
requireRole('admin');

$pdo = require_once __DIR__ . '/../config/db.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        try {
            $stmt = $pdo->query("SELECT * FROM instruments ORDER BY name ASC");
            $instruments = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $instruments]);
        } catch (Exception $e) {
            error_log('Instruments query failed: ' . $e->getMessage());
            sendJSONError('Failed to fetch instruments.', 500);
        }
        break;

    case 'POST':
        require_once __DIR__ . '/../config/csrf.php';
        require_csrf();

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $action = $input['action'] ?? 'add';
        $name = trim($input['name'] ?? '');
        $description = trim($input['description'] ?? '');

        if (empty($name)) {
            sendJSONError('Instrument name is required.');
        }

        if ($action === 'add') {
            try {
                $checkStmt = $pdo->prepare("SELECT id FROM instruments WHERE name = :name");
                $checkStmt->execute(['name' => $name]);
                if ($checkStmt->fetch()) {
                    sendJSONError('Instrument category already exists.');
                }

                $stmt = $pdo->prepare("
                    INSERT INTO instruments (name, description) 
                    VALUES (:name, :description)
                ");
                $stmt->execute([
                    'name' => $name,
                    'description' => $description
                ]);
                $instId = $pdo->lastInsertId();
                echo json_encode(['success' => true, 'message' => 'Instrument category created successfully.', 'id' => $instId]);
            } catch (Exception $e) {
                error_log('Instrument creation failed: ' . $e->getMessage());
                sendJSONError('Failed to create instrument category.', 500);
            }

        } elseif ($action === 'edit') {
            $instId = intval($input['id'] ?? 0);
            if ($instId <= 0) {
                sendJSONError('Valid Instrument ID is required.');
            }

            try {
                $dupStmt = $pdo->prepare("SELECT id FROM instruments WHERE name = :name AND id <> :id");
                $dupStmt->execute(['name' => $name, 'id' => $instId]);
                if ($dupStmt->fetch()) {
                    sendJSONError('Instrument category name already in use.');
                }

                $stmt = $pdo->prepare("
                    UPDATE instruments
                    SET name = :name, description = :description
                    WHERE id = :id
                ");
                $stmt->execute([
                    'name' => $name,
                    'description' => $description,
                    'id' => $instId
                ]);
                echo json_encode(['success' => true, 'message' => 'Instrument category updated successfully.']);
            } catch (Exception $e) {
                error_log('Instrument update failed: ' . $e->getMessage());
                sendJSONError('Failed to update instrument category.', 500);
            }
        } else {
            sendJSONError('Invalid action.');
        }
        break;

    case 'DELETE':
        require_once __DIR__ . '/../config/csrf.php';
        require_csrf();

        $input = json_decode(file_get_contents('php://input'), true);
        $instId = intval($input['id'] ?? 0);

        if ($instId <= 0) {
            sendJSONError('Valid Instrument ID is required.');
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM instruments WHERE id = :id");
            $stmt->execute(['id' => $instId]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Instrument category deleted successfully.']);
            } else {
                sendJSONError('Instrument category not found.', 404);
            }
        } catch (Exception $e) {
            error_log('Instrument deletion failed: ' . $e->getMessage());
            sendJSONError('Failed to delete instrument category.', 500);
        }
        break;

    default:
        sendJSONError('Method not allowed.', 405);
        break;
}

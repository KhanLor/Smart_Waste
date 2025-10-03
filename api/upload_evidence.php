<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// require_login(); // Uncomment in production

$user_role = $_SESSION['role'] ?? '';
$collector_id = $_SESSION['user_id'] ?? null;

if ($user_role !== 'collector' || !$collector_id) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
if ($task_id <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid task_id']);
    exit;
}

try {
    // Verify this task belongs to the collector
    $stmt = $conn->prepare("SELECT id FROM collection_schedules WHERE id = ? AND assigned_collector = ?");
    $stmt->bind_param('ii', $task_id, $collector_id);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    if (!$exists) {
        http_response_code(404);
        echo json_encode(['error' => 'Task not found']);
        exit;
    }

    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'No file uploaded or upload error']);
        exit;
    }

    $file = $_FILES['photo'];
    $allowed_types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowed_types[$mime])) {
        http_response_code(415);
        echo json_encode(['error' => 'Unsupported file type']);
        exit;
    }

    // Max 10MB
    if ($file['size'] > 10 * 1024 * 1024) {
        http_response_code(413);
        echo json_encode(['error' => 'File too large']);
        exit;
    }

    $ext = $allowed_types[$mime];
    $safe_name = bin2hex(random_bytes(8)) . "_task{$task_id}.{$ext}";
    $upload_dir = dirname(__DIR__) . '/uploads/evidence';
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0755, true);
    }
    $dest_path = $upload_dir . '/' . $safe_name;
    if (!move_uploaded_file($file['tmp_name'], $dest_path)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save file']);
        exit;
    }

    $public_path = BASE_URL . 'uploads/evidence/' . $safe_name;

    // Optionally insert into a task evidence table if exists
    try {
        $stmt = $conn->prepare("INSERT INTO task_evidence (schedule_id, collector_id, file_path, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param('iis', $task_id, $collector_id, $public_path);
        $stmt->execute();
    } catch (Exception $ignore) {
        // Table may not exist; ignore silently
    }

    echo json_encode(['success' => true, 'file_url' => $public_path]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}
?>



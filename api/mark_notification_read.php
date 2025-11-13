<?php
require_once __DIR__ . '/../config/config.php';
require_login();

header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) { $data = $_POST; }

$notif_id = isset($data['notification_id']) ? (int)$data['notification_id'] : 0;
$user_id = $_SESSION['user_id'] ?? null;

if (!$notif_id || !$user_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

try {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ? AND is_read = 0");
    $stmt->bind_param('ii', $notif_id, $user_id);
    $stmt->execute();
    $marked = $stmt->affected_rows;
    $stmt->close();

    echo json_encode(['success' => true, 'marked' => max(0, $marked)]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

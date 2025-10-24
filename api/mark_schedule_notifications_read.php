<?php
require_once '../config/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$role = $_SESSION['role'] ?? '';
$user_id = (int)($_SESSION['user_id'] ?? 0);
if (!$user_id || !in_array($role, ['authority','admin'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) { $data = $_POST; }

$schedule_id = isset($data['schedule_id']) ? (int)$data['schedule_id'] : 0;
if ($schedule_id <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid schedule_id']);
    exit;
}

try {
    // Mark all collection notifications for this schedule as read for the current authority/admin
    // notifications.reference_id references collection_history.id
    $sql = "UPDATE notifications n
            JOIN collection_history ch ON ch.id = n.reference_id
            SET n.is_read = 1
            WHERE n.user_id = ? AND n.reference_type = 'collection' AND n.is_read = 0 AND ch.schedule_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $user_id, $schedule_id);
    $stmt->execute();
    $marked = $stmt->affected_rows;
    $stmt->close();

    echo json_encode(['success' => true, 'marked' => max(0, $marked)]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}

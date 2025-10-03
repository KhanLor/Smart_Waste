<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// require_login(); // enable in production

$role = $_SESSION['role'] ?? '';
$collector_id = $_SESSION['user_id'] ?? null;

if ($role !== 'collector' || !$collector_id) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$history_id = isset($_POST['history_id']) ? intval($_POST['history_id']) : 0;
if ($history_id <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid history_id']);
    exit;
}

try {
    // Verify ownership: the history item must belong to this collector
    $stmt = $conn->prepare("SELECT id FROM collection_history WHERE id = ? AND collector_id = ?");
    $stmt->bind_param('ii', $history_id, $collector_id);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    if (!$exists) {
        http_response_code(404);
        echo json_encode(['error' => 'Record not found']);
        exit;
    }

    // Perform delete
    $stmt = $conn->prepare("DELETE FROM collection_history WHERE id = ?");
    $stmt->bind_param('i', $history_id);
    $stmt->execute();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}

?>


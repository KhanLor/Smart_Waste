<?php
require_once __DIR__ . '/../config/config.php';
require_login();

header('Content-Type: application/json');

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$report_id = $input['report_id'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;

if (!$report_id || !$user_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

try {
    // Mark notifications as read for this report
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND reference_type = 'report' AND reference_id = ?");
    $stmt->bind_param("ii", $user_id, $report_id);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'marked' => $stmt->affected_rows]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

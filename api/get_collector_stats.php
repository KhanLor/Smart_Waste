<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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

try {
    // Today boundaries
    $today_start = date('Y-m-d 00:00:00');
    $today_end = date('Y-m-d 23:59:59');

    // Today's assigned schedules count (by day name)
    $today_day = strtolower(date('l'));
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM collection_schedules WHERE assigned_collector = ? AND status IN ('active','in_progress') AND collection_day = ?");
    $stmt->bind_param('is', $collector_id, $today_day);
    $stmt->execute();
    $today_assigned = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);

    // Completed today from collection_history
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM collection_history WHERE collector_id = ? AND status = 'completed' AND collection_date BETWEEN ? AND ?");
    $stmt->bind_param('iss', $collector_id, $today_start, $today_end);
    $stmt->execute();
    $today_completed = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);

    $today_remaining = max(0, $today_assigned - $today_completed);

    // 30-day totals
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM collection_history WHERE collector_id = ? AND collection_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $stmt->bind_param('i', $collector_id);
    $stmt->execute();
    $last30_total = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);

    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM collection_history WHERE collector_id = ? AND status = 'completed' AND collection_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $stmt->bind_param('i', $collector_id);
    $stmt->execute();
    $last30_completed = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);

    echo json_encode([
        'success' => true,
        'today' => [
            'assigned' => $today_assigned,
            'completed' => $today_completed,
            'remaining' => $today_remaining,
        ],
        'last_30_days' => [
            'total' => $last30_total,
            'completed' => $last30_completed,
        ],
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}
?>



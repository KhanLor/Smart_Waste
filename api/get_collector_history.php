<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// require_login();

$role = $_SESSION['role'] ?? '';
$collector_id = $_SESSION['user_id'] ?? null;
if ($role !== 'collector' || !$collector_id) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$to = $_GET['to'] ?? date('Y-m-d');
$status = $_GET['status'] ?? '';

try {
    $sql = "
        SELECT ch.*, cs.area, cs.street_name, cs.waste_type
        FROM collection_history ch
        JOIN collection_schedules cs ON cs.id = ch.schedule_id
        WHERE ch.collector_id = ? AND ch.collection_date BETWEEN ? AND ?
    ";
    $types = 'iss';
    $params = [$collector_id, $from, $to];
    if ($status !== '') {
        $sql .= " AND ch.status = ?";
        $types .= 's';
        $params[] = $status;
    }
    $sql .= " ORDER BY ch.collection_date DESC, ch.collection_time DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $items = array_map(function($r) {
        return [
            'id' => (int)$r['id'],
            'schedule_id' => isset($r['schedule_id']) ? (int)$r['schedule_id'] : (isset($r['schedule_id']) ? (int)$r['schedule_id'] : null),
            'date' => $r['collection_date'],
            'time' => $r['collection_time'],
            'status' => $r['status'],
            'area' => $r['area'],
            'street_name' => $r['street_name'],
            'waste_type' => $r['waste_type'],
            'notes' => $r['notes'],
        ];
    }, $rows);

    echo json_encode(['success' => true, 'count' => count($items), 'items' => $items]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}
?>



<?php
require_once '../config/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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
if (!in_array($role, ['authority','admin'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$schedule_id = isset($_GET['schedule_id']) ? (int)$_GET['schedule_id'] : 0;
if ($schedule_id <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid schedule_id']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT ch.id, ch.collector_id, ch.schedule_id, ch.status, ch.collection_date, ch.notes, ch.evidence_image,
                                    u.first_name, u.last_name,
                                    cs.area, cs.street_name, cs.waste_type
                             FROM collection_history ch
                             LEFT JOIN users u ON u.id = ch.collector_id
                             LEFT JOIN collection_schedules cs ON cs.id = ch.schedule_id
                             WHERE ch.schedule_id = ?
                             ORDER BY ch.collection_date DESC, ch.id DESC");
    $stmt->bind_param('i', $schedule_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $items = array_map(function($r) {
        return [
            'id' => (int)$r['id'],
            'schedule_id' => (int)$r['schedule_id'],
            'status' => $r['status'],
            'date' => $r['collection_date'],
            'notes' => $r['notes'],
            'evidence_image' => $r['evidence_image'],
            'collector_name' => trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')),
            'area' => $r['area'],
            'street_name' => $r['street_name'],
            'waste_type' => $r['waste_type'],
        ];
    }, $rows);

    echo json_encode(['success' => true, 'count' => count($items), 'items' => $items]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}

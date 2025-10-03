<?php
require_once '../config/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Only allow authority or admin
$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['authority','admin'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

try {
    $sql = "SELECT u.id AS collector_id, u.first_name, u.last_name, cl.latitude, cl.longitude, cl.heading, cl.updated_at
            FROM users u
            LEFT JOIN collector_locations cl ON cl.collector_id = u.id
            WHERE u.role = 'collector'";
    $res = $conn->query($sql);
    $items = [];
    while ($r = $res->fetch_assoc()) {
        $items[] = [
            'collector_id' => (int)$r['collector_id'],
            'name' => trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')),
            'latitude' => isset($r['latitude']) ? (float)$r['latitude'] : null,
            'longitude' => isset($r['longitude']) ? (float)$r['longitude'] : null,
            'heading' => isset($r['heading']) ? (float)$r['heading'] : null,
            'updated_at' => $r['updated_at'] ?? null
        ];
    }
    echo json_encode(['success' => true, 'collectors' => $items]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}

?>

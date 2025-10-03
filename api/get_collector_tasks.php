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

$day = strtolower(trim($_GET['day'] ?? 'today'));
$status = trim($_GET['status'] ?? ''); // optional filter: active|in_progress|completed|pending

try {
    // Resolve day filter
    $days = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
    if ($day === 'today') { $day = strtolower(date('l')); }
    if (!in_array($day, $days, true)) { $day = strtolower(date('l')); }

    $params = [$collector_id, $day];
    $types = 'is';

    $sql = "
        SELECT cs.*, u.first_name, u.last_name
        FROM collection_schedules cs
        LEFT JOIN users u ON cs.assigned_collector = u.id
        WHERE cs.assigned_collector = ? AND cs.collection_day = ?
    ";

    if ($status !== '') {
        $sql .= " AND cs.status = ?";
        $params[] = $status;
        $types .= 's';
    }

    $sql .= " ORDER BY cs.collection_time";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $tasks = array_map(function($r) {
        return [
            'id' => $r['id'],
            'area' => $r['area'],
            'street_name' => $r['street_name'],
            'latitude' => isset($r['latitude']) ? (float)$r['latitude'] : null,
            'longitude' => isset($r['longitude']) ? (float)$r['longitude'] : null,
            'collection_day' => $r['collection_day'],
            'collection_time' => format_ph_date($r['collection_time'], 'g:i A'),
            'waste_type' => $r['waste_type'],
            'status' => $r['status'],
            'assigned_collector' => $r['first_name'] ? $r['first_name'] . ' ' . $r['last_name'] : null,
            'created_at' => format_ph_date($r['created_at']),
        ];
    }, $rows);

    echo json_encode([
        'success' => true,
        'day' => $day,
        'count' => count($tasks),
        'tasks' => $tasks,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}
?>



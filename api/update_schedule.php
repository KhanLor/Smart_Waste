<?php
require_once '../config/config.php';
require_once '../lib/push_notifications.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Only admin or authority can update schedules
$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['admin', 'authority'], true) || empty($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON body']);
    exit;
}

$id = isset($data['id']) ? intval($data['id']) : 0;
if ($id <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid schedule id']);
    exit;
}

// Fetch existing schedule
$stmt = $conn->prepare("SELECT * FROM collection_schedules WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$existing) {
    http_response_code(404);
    echo json_encode(['error' => 'Schedule not found']);
    exit;
}

$area = isset($data['area']) ? trim($data['area']) : $existing['area'];
$street_name = isset($data['street_name']) ? trim($data['street_name']) : $existing['street_name'];
$collection_day = isset($data['collection_day']) ? trim(strtolower($data['collection_day'])) : $existing['collection_day'];
$collection_time = isset($data['collection_time']) ? trim($data['collection_time']) : $existing['collection_time'];
$frequency = isset($data['frequency']) ? trim(strtolower($data['frequency'])) : $existing['frequency'];
$waste_type = isset($data['waste_type']) ? trim(strtolower($data['waste_type'])) : $existing['waste_type'];
$assigned_collector = array_key_exists('assigned_collector', $data) ? ($data['assigned_collector'] === null ? null : intval($data['assigned_collector'])) : $existing['assigned_collector'];
$status = isset($data['status']) ? trim(strtolower($data['status'])) : $existing['status'];

// Update DB
try {
    $stmt = $conn->prepare("UPDATE collection_schedules SET area = ?, street_name = ?, collection_day = ?, collection_time = ?, frequency = ?, waste_type = ?, assigned_collector = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    // assigned_collector may be null; use bind_param with i for intval or null handled as null via SQL
    $stmt->bind_param('ssssssisi', $area, $street_name, $collection_day, $collection_time, $frequency, $waste_type, $assigned_collector, $status, $id);
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update schedule']);
        exit;
    }
    $stmt->close();

	// Realtime broadcast via Pusher so residents see updates immediately
	try {
		$options = ['cluster' => PUSHER_CLUSTER, 'useTLS' => defined('PUSHER_USE_TLS') ? PUSHER_USE_TLS : true];
		$pusher = new Pusher\Pusher(PUSHER_KEY, PUSHER_SECRET, PUSHER_APP_ID, $options);
		$pusher->trigger('schedule-global', 'schedule-changed', [
			'action' => 'updated',
			'id' => $id,
			'area' => $area,
			'street_name' => $street_name,
			'collection_day' => $collection_day,
			'collection_time' => $collection_time,
			'waste_type' => $waste_type,
			'status' => $status
		]);
	} catch (Exception $e) {
		// Fail silently; push is best-effort
	}

    // Persist in-app notifications per resident (fan-out) and queue notification jobs
    $notif_title = 'Collection Schedule Updated';
    $notif_message = sprintf('%s on %s at %s', $street_name, ucfirst($collection_day), $collection_time);

    // Find residents whose address matches the street or area
    $stmtU = $conn->prepare("SELECT id FROM users WHERE role = 'resident' AND (address LIKE ? OR address LIKE ?)");
    $likeStreet = '%' . $street_name . '%';
    $likeArea = '%' . $area . '%';
    $stmtU->bind_param('ss', $likeStreet, $likeArea);
    $stmtU->execute();
    $resU = $stmtU->get_result();
    if ($resU && $resU->num_rows > 0) {
        $stmtN = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, reference_type, reference_id, created_at) VALUES (?, ?, ?, 'info', 'schedule', ?, CURRENT_TIMESTAMP)");
        while ($u = $resU->fetch_assoc()) {
            $uid = (int)$u['id'];
            $stmtN->bind_param('issi', $uid, $notif_title, $notif_message, $id);
            $stmtN->execute();
        }
        $stmtN->close();
    }
    $stmtU->close();

    // Queue jobs
    $stmtJ = $conn->prepare("INSERT INTO notification_jobs (target_type, target_value, title, message, payload, created_at) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
    $payload = json_encode(['schedule_id' => $id]);

    $old_collector = $existing['assigned_collector'];
    $new_collector = $assigned_collector;

    if ($old_collector && $old_collector != $new_collector) {
        $t = 'user'; $v = (string)$old_collector;
        $stmtJ->bind_param('sssss', $t, $v, $notif_title, "You have been unassigned: {$notif_message}", $payload);
        $stmtJ->execute();
    }

    if ($new_collector && $new_collector != $old_collector) {
        $t = 'user'; $v = (string)$new_collector;
        $stmtJ->bind_param('sssss', $t, $v, $notif_title, "You have been assigned: {$notif_message}", $payload);
        $stmtJ->execute();
    }

    // Area-wide job (web push)
    $t = 'area'; $v = $area;
    $stmtJ->bind_param('sssss', $t, $v, $notif_title, "Collection updated for {$street_name} on " . ucfirst($collection_day) . " at {$collection_time}", $payload);
    $stmtJ->execute();
    $stmtJ->close();

    echo json_encode(['success' => true, 'id' => $id]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}

?>

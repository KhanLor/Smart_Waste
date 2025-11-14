<?php
require_once '../config/config.php';
require_once '../lib/push_notifications.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Only admin or authority can create schedules
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

$area = trim($data['area'] ?? '');
$street_name = trim($data['street_name'] ?? '');
$collection_day = trim(strtolower($data['collection_day'] ?? ''));
$collection_time = trim($data['collection_time'] ?? '');
$frequency = trim(strtolower($data['frequency'] ?? 'weekly'));
$waste_type = trim(strtolower($data['waste_type'] ?? 'general'));
$assigned_collector = isset($data['assigned_collector']) && $data['assigned_collector'] !== '' ? intval($data['assigned_collector']) : null;

$valid_days = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
$valid_freq = ['daily','weekly','biweekly','monthly'];
$valid_waste = ['general','recyclable','organic','hazardous'];

if (!$area || !$street_name || !in_array($collection_day, $valid_days, true) || !$collection_time) {
    http_response_code(422);
    echo json_encode(['error' => 'Missing or invalid fields']);
    exit;
}

if (!in_array($frequency, $valid_freq, true)) $frequency = 'weekly';
if (!in_array($waste_type, $valid_waste, true)) $waste_type = 'general';

try {
    $stmt = $conn->prepare("INSERT INTO collection_schedules (area, street_name, collection_day, collection_time, frequency, waste_type, assigned_collector, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
    $created_by = $_SESSION['user_id'];
    // assigned_collector may be null
    if ($assigned_collector) {
        $stmt->bind_param('ssssssii', $area, $street_name, $collection_day, $collection_time, $frequency, $waste_type, $assigned_collector, $created_by);
    } else {
        // bind as null (use NULL for assigned_collector)
        $null = null;
        $stmt = $conn->prepare("INSERT INTO collection_schedules (area, street_name, collection_day, collection_time, frequency, waste_type, assigned_collector, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NULL, ?, CURRENT_TIMESTAMP)");
        $stmt->bind_param('ssssssi', $area, $street_name, $collection_day, $collection_time, $frequency, $waste_type, $created_by);
    }

    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create schedule']);
        exit;
    }

    $schedule_id = $conn->insert_id;
    $stmt->close();

	// Realtime broadcast via Pusher so residents see updates immediately
	try {
		$options = ['cluster' => PUSHER_CLUSTER, 'useTLS' => defined('PUSHER_USE_TLS') ? PUSHER_USE_TLS : true];
		$pusher = new Pusher\Pusher(PUSHER_KEY, PUSHER_SECRET, PUSHER_APP_ID, $options);
		$pusher->trigger('schedule-global', 'schedule-changed', [
			'action' => 'created',
			'id' => $schedule_id,
			'area' => $area,
			'street_name' => $street_name,
			'collection_day' => $collection_day,
			'collection_time' => $collection_time,
			'waste_type' => $waste_type
		]);
	} catch (Exception $e) {
		// Fail silently; push is best-effort
	}

    // Persist in-app notifications for matched residents (fan-out per user for unread badges)
    $notif_title = 'New Collection Scheduled';
    // Format collection time to 12-hour format for user-facing messages (e.g. 1:00 PM)
    $display_time = $collection_time;
    $ts = strtotime("1970-01-01 $collection_time");
    if ($ts !== false) {
        $display_time = date('g:i A', $ts);
    }
    $notif_message = sprintf('%s on %s at %s', $street_name, ucfirst($collection_day), $display_time);

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
            $stmtN->bind_param('issi', $uid, $notif_title, $notif_message, $schedule_id);
            $stmtN->execute();
        }
        $stmtN->close();
    }
    $stmtU->close();

    // Queue notification job for assigned collector (if any)
    $stmtJ = $conn->prepare("INSERT INTO notification_jobs (target_type, target_value, title, message, payload, created_at) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
    $payload = json_encode(['schedule_id' => $schedule_id]);
    if ($assigned_collector) {
        $t = 'user'; $v = (string)$assigned_collector;
        $stmtJ->bind_param('sssss', $t, $v, $notif_title, "You have been assigned: {$notif_message}", $payload);
        $stmtJ->execute();
    }

    // Queue an area-wide job (web push) - use formatted display time here too
    $t = 'area'; $v = $area;
    $stmtJ->bind_param('sssss', $t, $v, $notif_title, "Collection scheduled for {$street_name} on " . ucfirst($collection_day) . " at {$display_time}", $payload);
    $stmtJ->execute();
    $stmtJ->close();

    echo json_encode(['success' => true, 'id' => $schedule_id]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}

?>

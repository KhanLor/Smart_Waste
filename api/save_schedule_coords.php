<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// require_login(); // Uncomment in production

$role = $_SESSION['role'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id || !in_array($role, ['collector','authority','admin'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$schedule_id = isset($_POST['schedule_id']) ? intval($_POST['schedule_id']) : 0;
$lat = isset($_POST['latitude']) ? (float)$_POST['latitude'] : null;
$lng = isset($_POST['longitude']) ? (float)$_POST['longitude'] : null;

if ($schedule_id <= 0 || $lat === null || $lng === null) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid parameters']);
    exit;
}

try {
    // Authorization: collectors may update only their own schedules, admins/authorities can update any
    if ($role === 'collector') {
        $stmt = $conn->prepare("SELECT id FROM collection_schedules WHERE id = ? AND assigned_collector = ?");
        $stmt->bind_param('ii', $schedule_id, $user_id);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            http_response_code(404);
            echo json_encode(['error' => 'Schedule not found']);
            exit;
        }
    }

    $stmt = $conn->prepare("UPDATE collection_schedules SET latitude = ?, longitude = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->bind_param('ddi', $lat, $lng, $schedule_id);
    $stmt->execute();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}
?>



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
$collector_id = $_SESSION['user_id'] ?? null;

if ($role !== 'collector' || !$collector_id) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

$lat = isset($data['latitude']) ? (float)$data['latitude'] : null;
$lng = isset($data['longitude']) ? (float)$data['longitude'] : null;
$heading = isset($data['heading']) ? (float)$data['heading'] : null;

if ($lat === null || $lng === null) {
    http_response_code(422);
    echo json_encode(['error' => 'Missing coords']);
    exit;
}

try {
    // Upsert into collector_locations table
    // If heading column exists, include it; otherwise upsert without heading
    $hasHeadingColumn = false;
    $r = $conn->query("SHOW COLUMNS FROM collector_locations LIKE 'heading'");
    if ($r && $r->num_rows > 0) { $hasHeadingColumn = true; }

    if ($hasHeadingColumn) {
        $stmt = $conn->prepare("REPLACE INTO collector_locations (collector_id, latitude, longitude, heading, updated_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)");
        $stmt->bind_param('iddd', $collector_id, $lat, $lng, $heading);
    } else {
        $stmt = $conn->prepare("REPLACE INTO collector_locations (collector_id, latitude, longitude, updated_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)");
        $stmt->bind_param('idd', $collector_id, $lat, $lng);
    }
    $stmt->execute();

    // Broadcast via Pusher if configured
    if (defined('PUSHER_KEY') && PUSHER_KEY && defined('PUSHER_SECRET') && PUSHER_SECRET && defined('PUSHER_APP_ID') && PUSHER_APP_ID) {
        // Use the Pusher PHP library if available
        if (class_exists('Pusher\Pusher')) {
            try {
                $options = ['cluster' => PUSHER_CLUSTER, 'useTLS' => PUSHER_USE_TLS];
                $pusher = new Pusher\Pusher(PUSHER_KEY, PUSHER_SECRET, PUSHER_APP_ID, $options);
                $payload = [
                    'collector_id' => $collector_id,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'updated_at' => date('c')
                ];
                if ($heading !== null) $payload['heading'] = $heading;
                $pusher->trigger('collectors-channel', 'collector-location', $payload);
            } catch (Exception $e) {
                // non-fatal
                error_log('Pusher send failed: ' . $e->getMessage());
            }
        }
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}

?>

<?php
require_once '../config/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// must be logged in as collector
if (($_SESSION['role'] ?? '') !== 'collector' || empty($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$collector_id = $_SESSION['user_id'];

// Accept either JSON body or form-encoded POST (for compatibility)
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    // fallback to $_POST
    $data = $_POST;
}

$task_id = isset($data['id']) ? intval($data['id']) : (isset($data['task_id']) ? intval($data['task_id']) : 0);
$new_status = isset($data['status']) ? trim($data['status']) : '';
$comment = isset($data['comment']) ? trim($data['comment']) : null;

$valid_statuses = ['pending','in_progress','completed','missed'];
if ($task_id <= 0 || !in_array($new_status, $valid_statuses, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid task_id or status']);
    exit;
}

// If status is missed, comment is required
if ($new_status === 'missed' && empty($comment)) {
    http_response_code(422);
    echo json_encode(['error' => 'Comment is required when marking collection as missed']);
    exit;
}

try {
    // Verify this task belongs to the collector and get current status
    $stmt = $conn->prepare("SELECT id, status FROM collection_schedules WHERE id = ? AND assigned_collector = ?");
    $stmt->bind_param('ii', $task_id, $collector_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Task not found']);
        exit;
    }
    
    $task = $res->fetch_assoc();
    $current_status = $task['status'];
    
    // Prevent updating if already completed or missed (final states)
    if (($current_status === 'completed' || $current_status === 'missed') && $current_status !== $new_status) {
        http_response_code(400);
        echo json_encode(['error' => 'Task is already in a final state (' . $current_status . ')', 'current_status' => $current_status]);
        exit;
    }
    
    // Prevent duplicate status updates
    if ($current_status === $new_status) {
        // Already in this status, return success but don't update
        echo json_encode(['success' => true, 'message' => 'Task already in this status', 'current_status' => $current_status]);
        exit;
    }

    // Update schedule status and updated_at
    $stmt = $conn->prepare("UPDATE collection_schedules SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->bind_param('si', $new_status, $task_id);
    $stmt->execute();

    // Record history entry when completed or missed
    if ($new_status === 'completed' || $new_status === 'missed') {
        // Handle image upload for missed status
        $evidence_path = null;
        if ($new_status === 'missed' && isset($_FILES['evidence_image']) && $_FILES['evidence_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../uploads/evidence/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file = $_FILES['evidence_image'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            // Validate file type
            if (!in_array($file['type'], $allowed_types)) {
                http_response_code(422);
                echo json_encode(['error' => 'Invalid file type. Only JPG, PNG, and GIF images are allowed.']);
                exit;
            }
            
            // Validate file size
            if ($file['size'] > $max_size) {
                http_response_code(422);
                echo json_encode(['error' => 'File size exceeds 5MB limit.']);
                exit;
            }
            
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'missed_' . $task_id . '_' . time() . '_' . uniqid() . '.' . $extension;
            $filepath = $upload_dir . $filename;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $evidence_path = 'uploads/evidence/' . $filename;
            }
        }
        
        $stmt = $conn->prepare("INSERT INTO collection_history (collector_id, schedule_id, status, collection_date, notes, evidence_image) VALUES (?, ?, ?, NOW(), ?, ?)");
        $stmt->bind_param('iisss', $collector_id, $task_id, $new_status, $comment, $evidence_path);
        $stmt->execute();
        $history_id = $conn->insert_id;

        // Create in-app notifications for residents in the affected area (reference_type 'collection')
        // First fetch schedule details to get area/street
        $stmtS = $conn->prepare("SELECT area, street_name FROM collection_schedules WHERE id = ?");
        if ($stmtS) {
            $stmtS->bind_param('i', $task_id);
            $stmtS->execute();
            $sched = $stmtS->get_result()->fetch_assoc();
            $stmtS->close();
        } else {
            $sched = null;
        }

        $notif_title = $new_status === 'completed' ? 'Collection Completed' : 'Collection Missed';
        $street = $sched['street_name'] ?? 'your area';
        $area = $sched['area'] ?? '';
        $notif_message = sprintf('%s at %s has been marked %s', $street, date('M j, Y'), $new_status);

        // Insert per-resident in-app notifications for users whose address matches the schedule street or area
        if (!empty($sched)) {
            $addr1 = '%' . ($street) . '%';
            $addr2 = '%' . ($area) . '%';
            $stmtU = $conn->prepare("SELECT id FROM users WHERE role = 'resident' AND (address LIKE ? OR address LIKE ?)");
            if ($stmtU) {
                $stmtU->bind_param('ss', $addr1, $addr2);
                $stmtU->execute();
                $resUsers = $stmtU->get_result();
                $stmtIns = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, reference_type, reference_id, created_at) VALUES (?, ?, ?, 'info', 'collection', ?, CURRENT_TIMESTAMP)");
                while ($u = $resUsers->fetch_assoc()) {
                    if ($stmtIns) {
                        $uid = (int)$u['id'];
                        $stmtIns->bind_param('issi', $uid, $notif_title, $notif_message, $history_id);
                        $stmtIns->execute();
                    }
                }
                if ($stmtIns) $stmtIns->close();
                $stmtU->close();
            }
        }

        // Queue a notification job for area so push subscriptions get notified (area target)
        $stmtJ = $conn->prepare("INSERT INTO notification_jobs (target_type, target_value, title, message, payload, created_at) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
        if ($stmtJ) {
            $target_type = 'area';
            $target_value = $area ?: $street;
            $payload = json_encode(['history_id' => $history_id, 'schedule_id' => $task_id, 'status' => $new_status]);
            $stmtJ->bind_param('sssss', $target_type, $target_value, $notif_title, $notif_message, $payload);
            $stmtJ->execute();
            $stmtJ->close();
        }
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}

?>



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

$valid_statuses = ['pending','in_progress','completed','missed','cancelled'];
if ($task_id <= 0 || !in_array($new_status, $valid_statuses, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid task_id or status']);
    exit;
}

// If status is missed or cancelled, comment is required
if (($new_status === 'missed' || $new_status === 'cancelled') && empty($comment)) {
    http_response_code(422);
    echo json_encode(['error' => 'Comment is required when marking collection as missed or cancelled']);
    exit;
}

try {
    // Verify this task (schedule) belongs to the collector
    $stmt = $conn->prepare("SELECT id FROM collection_schedules WHERE id = ? AND assigned_collector = ?");
    $stmt->bind_param('ii', $task_id, $collector_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Task not found']);
        exit;
    }

    // Determine today's run status from collection_history (server authoritative).
    // This ensures the API returns the actual run state for today instead of relying on the schedule's admin status field.
    $stmtH = $conn->prepare("SELECT status FROM collection_history WHERE schedule_id = ? AND collector_id = ? AND DATE(collection_date) = CURDATE() ORDER BY id DESC LIMIT 1");
    if ($stmtH) {
        $stmtH->bind_param('ii', $task_id, $collector_id);
        $stmtH->execute();
        $resH = $stmtH->get_result();
        $current_status = ($resH && $resH->num_rows > 0) ? $resH->fetch_assoc()['status'] : null;
        $stmtH->close();
    } else {
        $current_status = null;
    }

    // Prevent duplicate status updates for the same run state
    if ($current_status === $new_status) {
        echo json_encode(['success' => true, 'message' => 'Task already in this status', 'current_status' => $current_status]);
        exit;
    }

    // If the collector started the task (in_progress), notify residents and authority
    if ($new_status === 'in_progress') {
        // fetch schedule details (area/street)
        $stmtS = $conn->prepare("SELECT area, street_name FROM collection_schedules WHERE id = ?");
        if ($stmtS) {
            $stmtS->bind_param('i', $task_id);
            $stmtS->execute();
            $sched = $stmtS->get_result()->fetch_assoc();
            $stmtS->close();
        } else {
            $sched = null;
        }

        // fetch collector display name
        $collector_name = '';
        $stmtC = $conn->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
        if ($stmtC) {
            $stmtC->bind_param('i', $collector_id);
            $stmtC->execute();
            $r = $stmtC->get_result()->fetch_assoc();
            if ($r) {
                $collector_name = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
            }
            $stmtC->close();
        }

        $street = $sched['street_name'] ?? 'your area';
        $area = $sched['area'] ?? '';
        $notif_title = 'Collection Started';
        $notif_message = ($collector_name ? $collector_name . ' started collection at ' : 'Collection started at ') . $street;

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
                        // reference_id left as NULL (0) because no history row for start
                        $ref = 0;
                        $stmtIns->bind_param('issi', $uid, $notif_title, $notif_message, $ref);
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
            $payload = json_encode(['schedule_id' => $task_id, 'status' => $new_status]);
            $stmtJ->bind_param('sssss', $target_type, $target_value, $notif_title, $notif_message, $payload);
            $stmtJ->execute();
            $stmtJ->close();
        }

        // Also notify authority/admin users inside the app (reference_type 'collection')
        try {
            if ($stmtAu = $conn->prepare("SELECT id FROM users WHERE role IN ('authority','admin')")) {
                $stmtAu->execute();
                $resAu = $stmtAu->get_result();
                if ($resAu && $resAu->num_rows > 0) {
                    $stmtInsA = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, reference_type, reference_id, created_at) VALUES (?, ?, ?, 'info', 'collection', ?, CURRENT_TIMESTAMP)");
                    while ($au = $resAu->fetch_assoc()) {
                        $aid = (int)$au['id'];
                        $msg = $notif_message;
                        if ($stmtInsA) {
                            $ref = 0;
                            $stmtInsA->bind_param('issi', $aid, $notif_title, $msg, $ref);
                            $stmtInsA->execute();
                        }
                    }
                    if ($stmtInsA) { $stmtInsA->close(); }
                }
                $stmtAu->close();
            }
        } catch (Throwable $e) {
            // Swallow authority notification errors to not block collector flow
        }
    }

    // Record history entry when completed, missed or cancelled and keep schedule record unchanged
    if ($new_status === 'completed' || $new_status === 'missed' || $new_status === 'cancelled') {
        // Handle image upload for missed or cancelled statuses
        $evidence_path = null;
        if (($new_status === 'missed' || $new_status === 'cancelled') && isset($_FILES['evidence_image']) && $_FILES['evidence_image']['error'] === UPLOAD_ERR_OK) {
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
            
            // Generate unique filename (include status for clarity)
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $safe_status = preg_replace('/[^a-z0-9_\-]/i', '', $new_status);
            $filename = $safe_status . '_' . $task_id . '_' . time() . '_' . uniqid() . '.' . $extension;
            $filepath = $upload_dir . $filename;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $evidence_path = 'uploads/evidence/' . $filename;
            }
        }
        
    // Insert a history row for today's run (server authoritative)
    $stmt = $conn->prepare("INSERT INTO collection_history (collector_id, schedule_id, status, collection_date, notes, evidence_image) VALUES (?, ?, ?, CURDATE(), ?, ?)");
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

        if ($new_status === 'completed') {
            $notif_title = 'Collection Completed';
        } elseif ($new_status === 'missed') {
            $notif_title = 'Collection Missed';
        } else {
            $notif_title = 'Collection Cancelled';
        }
        $street = $sched['street_name'] ?? 'your area';
        $area = $sched['area'] ?? '';
        $notif_message = sprintf('%s at %s has been marked %s', $street, date('M j, Y'), $new_status);
        // If collector provided a comment (reason) include it in the in-app message so residents/authority see why
        if (!empty($comment)) {
            $notif_message .= ' — Reason: ' . $comment;
        }

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

    // Also notify authority/admin users inside the app (reference_type 'collection')
        try {
            if ($stmtAu = $conn->prepare("SELECT id FROM users WHERE role IN ('authority','admin')")) {
                $stmtAu->execute();
                $resAu = $stmtAu->get_result();
                if ($resAu && $resAu->num_rows > 0) {
                    $stmtInsA = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, reference_type, reference_id, created_at) VALUES (?, ?, ?, 'info', 'collection', ?, CURRENT_TIMESTAMP)");
                    while ($au = $resAu->fetch_assoc()) {
                        $aid = (int)$au['id'];
                        $msg = $notif_message; // reuse composed message with street/area + status
                        if ($stmtInsA) {
                            $stmtInsA->bind_param('issi', $aid, $notif_title, $msg, $history_id);
                            $stmtInsA->execute();
                        }
                    }
                    if ($stmtInsA) { $stmtInsA->close(); }
                }
                $stmtAu->close();
            }
        } catch (Throwable $e) {
            // Swallow authority notification errors to not block collector flow
        }
        // Keep the parent schedule status intact (it represents recurrence/assignment). Update only updated_at to indicate recent activity.
        try {
            $stmtUp = $conn->prepare("UPDATE collection_schedules SET updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            if ($stmtUp) {
                $stmtUp->bind_param('i', $task_id);
                $stmtUp->execute();
                $stmtUp->close();
            }
        } catch (Throwable $e) {
            // Non-fatal: do not block response if schedule timestamp update fails
        }
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}

?>



<?php
// Worker script to process notification_jobs queue
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/push_notifications.php';

// Simple CLI-only guard
if (php_sapi_name() !== 'cli') {
    echo "This script must be run from CLI\n";
    exit(1);
}

$push = new PushNotifications($conn);

// Fetch one queued job and mark as processing (transactional-ish)
$stmt = $conn->prepare("SELECT * FROM notification_jobs WHERE status = 'queued' ORDER BY created_at ASC LIMIT 1 FOR UPDATE");
$res = $conn->query("SELECT id FROM notification_jobs WHERE status = 'queued' ORDER BY created_at ASC LIMIT 1");
$job = $res->fetch_assoc();
if (!$job) {
    echo "No queued jobs\n";
    exit(0);
}

$job_id = $job['id'];

// Update to processing
$stmt = $conn->prepare("UPDATE notification_jobs SET status = 'processing', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND status = 'queued'");
$stmt->bind_param('i', $job_id);
$stmt->execute();
$stmt->close();

// Re-fetch job data
$stmt = $conn->prepare("SELECT * FROM notification_jobs WHERE id = ?");
$stmt->bind_param('i', $job_id);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$job) {
    echo "Job not found after locking\n";
    exit(1);
}

echo "Processing job {$job_id} (target: {$job['target_type']}={$job['target_value']})\n";

$payload = json_decode($job['payload'] ?? 'null', true) ?: [];
$title = $job['title'];
$message = $job['message'];

$successfulSends = 0;

if ($job['target_type'] === 'user') {
    $user_id = intval($job['target_value']);
    // find subscriptions for user
    $stmt = $conn->prepare("SELECT * FROM push_subscriptions WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $subs = $stmt->get_result();
    while ($sub = $subs->fetch_assoc()) {
        $ok = $push->sendToSubscription($sub, $title, $message, $payload);
        // log
        $stmtLog = $conn->prepare("INSERT INTO notification_send_logs (job_id, subscription_id, success, response_text, created_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)");
        $resp = $ok ? 'OK' : 'FAIL';
        $s = $ok ? 1 : 0;
        $stmtLog->bind_param('iiis', $job_id, $sub['id'], $s, $resp);
        $stmtLog->execute();
        $stmtLog->close();
        if ($ok) $successfulSends++;
    }
    $stmt->close();
} else {
    // area - find users whose address LIKE target_value
    $area_search = '%' . $job['target_value'] . '%';
    $stmt = $conn->prepare("SELECT ps.* FROM push_subscriptions ps JOIN users u ON ps.user_id = u.id WHERE u.role = 'resident' AND (u.address LIKE ? OR u.street_name LIKE ?)");
    // note: u.street_name may not exist; the query is permissive
    $stmt->bind_param('ss', $area_search, $area_search);
    $stmt->execute();
    $subs = $stmt->get_result();
    while ($sub = $subs->fetch_assoc()) {
        $ok = $push->sendToSubscription($sub, $title, $message, $payload);
        $stmtLog = $conn->prepare("INSERT INTO notification_send_logs (job_id, subscription_id, success, response_text, created_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)");
        $resp = $ok ? 'OK' : 'FAIL';
        $s = $ok ? 1 : 0;
        $stmtLog->bind_param('iiis', $job_id, $sub['id'], $s, $resp);
        $stmtLog->execute();
        $stmtLog->close();
        if ($ok) $successfulSends++;
    }
    $stmt->close();
}

// Update job status
if ($successfulSends > 0) {
    $stmt = $conn->prepare("UPDATE notification_jobs SET status = 'sent', attempts = attempts + 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->bind_param('i', $job_id);
    $stmt->execute();
    $stmt->close();
    echo "Job {$job_id} sent to {$successfulSends} subscriptions\n";
} else {
    // increment attempts and decide if failed
    $attempts = intval($job['attempts']) + 1;
    if ($attempts >= intval($job['max_attempts'])) {
        $status = 'failed';
    } else {
        $status = 'queued';
    }
    $stmt = $conn->prepare("UPDATE notification_jobs SET status = ?, attempts = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->bind_param('sii', $status, $attempts, $job_id);
    $stmt->execute();
    $stmt->close();
    echo "Job {$job_id} had no successful sends, attempts={$attempts}, status={$status}\n";
}

exit(0);

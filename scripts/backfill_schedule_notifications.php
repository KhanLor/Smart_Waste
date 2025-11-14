<?php
// CLI script: backfill per-resident notifications for existing schedules
require_once __DIR__ . '/../config/config.php';

if (php_sapi_name() !== 'cli') {
    echo "This script must be run from CLI\n";
    exit(1);
}

date_default_timezone_set(date_default_timezone_get());

$days = isset($argv[1]) ? max(1, intval($argv[1])) : 30; // default 30 days

// Fetch schedules created/updated within the window
$stmt = $conn->prepare("SELECT id, area, street_name, collection_day, collection_time, created_at, updated_at FROM collection_schedules WHERE (created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) OR updated_at >= DATE_SUB(NOW(), INTERVAL ? DAY))");
$stmt->bind_param('ii', $days, $days);
$stmt->execute();
$res = $stmt->get_result();
$stmt->close();

$inserted = 0;
$checked = 0;

while ($row = $res->fetch_assoc()) {
    $schedule_id = (int)$row['id'];
    $area = trim((string)$row['area']);
    $street = trim((string)$row['street_name']);
    $day = strtolower((string)$row['collection_day']);
    $time = (string)$row['collection_time'];
    // Format time to 12-hour for user-facing messages
    $display_time = $time;
    $tst = strtotime("1970-01-01 $time");
    if ($tst !== false) { $display_time = date('g:i A', $tst); }

    $title = $row['updated_at'] && $row['updated_at'] > $row['created_at'] ? 'Collection Schedule Updated' : 'New Collection Scheduled';
    $message = sprintf('%s on %s at %s', $street, ucfirst($day), $display_time);

    // Find residents whose address matches
    $stmtU = $conn->prepare("SELECT id FROM users WHERE role = 'resident' AND (address LIKE ? OR address LIKE ?)");
    $likeStreet = '%' . $street . '%';
    $likeArea = '%' . $area . '%';
    $stmtU->bind_param('ss', $likeStreet, $likeArea);
    $stmtU->execute();
    $resU = $stmtU->get_result();

    while ($u = $resU->fetch_assoc()) {
        $checked++;
        $uid = (int)$u['id'];
        // Skip if a notification already exists for this schedule and user
        $check = $conn->prepare("SELECT id FROM notifications WHERE user_id = ? AND reference_type = 'schedule' AND reference_id = ? LIMIT 1");
        $check->bind_param('ii', $uid, $schedule_id);
        $check->execute();
        $exists = $check->get_result()->fetch_assoc();
        $check->close();
        if ($exists) continue;

        $ins = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, reference_type, reference_id, created_at) VALUES (?, ?, ?, 'info', 'schedule', ?, CURRENT_TIMESTAMP)");
        $ins->bind_param('issi', $uid, $title, $message, $schedule_id);
        if ($ins->execute()) { $inserted++; }
        $ins->close();
    }
    $stmtU->close();
}

echo "Checked residents: {$checked}, inserted notifications: {$inserted}\n";
exit(0);

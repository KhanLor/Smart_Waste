<?php
// CLI script: enqueue per-area collection notifications ahead of time
require_once __DIR__ . '/../config/config.php';

if (php_sapi_name() !== 'cli') {
	echo "This script must be run from CLI\n";
	exit(1);
}

date_default_timezone_set(date_default_timezone_get());

$now = new DateTime('now');
$today = strtolower($now->format('l'));

// Window before the collection to notify (in minutes)
$leadMinutes = 60; // notify 60 minutes before

// Fetch today's schedules
$stmt = $conn->prepare("SELECT id, street_name, area, waste_type, collection_time FROM collection_schedules WHERE collection_day = ?");
$stmt->bind_param('s', $today);
$stmt->execute();
$res = $stmt->get_result();
$stmt->close();

$enqueued = 0;

while ($row = $res->fetch_assoc()) {
	$street = trim((string)$row['street_name']);
	$area = trim((string)$row['area']);
	$wasteType = trim((string)$row['waste_type']);
	$timeStr = $row['collection_time'];
	if (!$timeStr) continue;

	try {
		$collectionAt = new DateTime($timeStr);
	} catch (Exception $e) {
		continue;
	}

	$diffMinutes = (int)floor(($collectionAt->getTimestamp() - $now->getTimestamp()) / 60);

	// Only enqueue within [0, leadMinutes] before the collection time
	if ($diffMinutes < 0 || $diffMinutes > $leadMinutes) {
		continue;
	}

	$target = $street !== '' ? $street : $area;
	if ($target === '') continue;

	$title = 'Upcoming waste collection today';
	$message = sprintf('%s - %s at %s', $target, ucfirst($wasteType ?: 'general'), $collectionAt->format('g:i A'));
	$payload = json_encode([
		'schedule_id' => (int)$row['id'],
		'waste_type' => $wasteType,
		'collection_time' => $collectionAt->format(DateTime::ATOM),
	]);

	// De-duplicate: avoid inserting if a job for the same target+title exists for today
	$check = $conn->prepare("SELECT id FROM notification_jobs WHERE target_type = 'area' AND target_value = ? AND title = ? AND DATE(created_at) = CURDATE() LIMIT 1");
	$check->bind_param('ss', $target, $title);
	$check->execute();
	$existing = $check->get_result()->fetch_assoc();
	$check->close();

	if ($existing) {
		continue;
	}

	$ins = $conn->prepare("INSERT INTO notification_jobs (target_type, target_value, title, message, payload, max_attempts, status) VALUES ('area', ?, ?, ?, ?, 3, 'queued')");
	$ins->bind_param('ssss', $target, $title, $message, $payload);
	if ($ins->execute()) {
		$enqueued++;
	}
	$ins->close();
}

echo "Enqueued {$enqueued} notification job(s) for today's schedules within {$leadMinutes} minutes.\n";

exit(0);



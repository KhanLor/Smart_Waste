<?php
require_once __DIR__ . '/config/config.php';

if (!is_logged_in()) {
	http_response_code(403);
	exit;
}

header('Content-Type: application/json');

$conversationId = trim($_GET['conversation_id'] ?? '');
$limit = max(1, min(200, (int)($_GET['limit'] ?? 50)));

if ($conversationId === '') {
	echo json_encode([]);
	exit;
}

// helper to check for optional columns
function column_exists_local($conn, $db, $table, $column) {
	$stmt = $conn->prepare('SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
	$stmt->bind_param('sss', $db, $table, $column);
	$stmt->execute();
	$r = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	return (int)($r['cnt'] ?? 0) > 0;
}

$selectExtras = '';
if (column_exists_local($conn, $DB_NAME, 'messages', 'edited_at')) {
	$selectExtras .= ', edited_at';
}
if (column_exists_local($conn, $DB_NAME, 'messages', 'is_unsent')) {
	$selectExtras .= ', is_unsent';
}
if (column_exists_local($conn, $DB_NAME, 'messages', 'unsent_at')) {
	$selectExtras .= ', unsent_at';
}

$sql = 'SELECT id, conversation_id, sender_id, receiver_id, body, created_at' . $selectExtras . ' FROM messages WHERE conversation_id = ? ORDER BY created_at ASC, id ASC LIMIT ?';
$stmt = $conn->prepare($sql);
$stmt->bind_param('si', $conversationId, $limit);
$stmt->execute();
$result = $stmt->get_result();
$messages = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// normalize missing fields for client convenience
foreach ($messages as &$m) {
	if (!isset($m['edited_at'])) $m['edited_at'] = null;
	if (!isset($m['is_unsent'])) $m['is_unsent'] = 0;
	if (!isset($m['unsent_at'])) $m['unsent_at'] = null;
}

echo json_encode($messages);



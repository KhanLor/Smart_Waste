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

$stmt = $conn->prepare('SELECT id, conversation_id, sender_id, receiver_id, body, created_at FROM messages WHERE conversation_id = ? ORDER BY created_at ASC, id ASC LIMIT ?');
$stmt->bind_param('si', $conversationId, $limit);
$stmt->execute();
$result = $stmt->get_result();
$messages = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode($messages);



<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/vendor/autoload.php';

if (!is_logged_in()) {
	http_response_code(403);
	exit;
}

header('Content-Type: application/json');

$senderId   = (int)($_SESSION['user_id'] ?? 0);
$receiverId = (int)($_POST['receiver_id'] ?? 0);
$body       = trim($_POST['body'] ?? '');
$conversationId = trim($_POST['conversation_id'] ?? '');

if ($senderId <= 0 || $receiverId <= 0 || $body === '' || $conversationId === '') {
	echo json_encode(['ok' => false, 'error' => 'Invalid input']);
	exit;
}

// Enforce: resident can only chat with authority
$senderRole = strtolower($_SESSION['role'] ?? 'resident');
if ($senderRole === 'resident') {
	$chk = $conn->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
	$chk->bind_param('i', $receiverId);
	$chk->execute();
	$r = $chk->get_result()->fetch_assoc();
	$chk->close();
	if (!$r || strtolower($r['role'] ?? '') !== 'authority') {
		echo json_encode(['ok' => false, 'error' => 'Residents may only message authorities.']);
		exit;
	}
}

$stmt = $conn->prepare('INSERT INTO messages (conversation_id, sender_id, receiver_id, body) VALUES (?, ?, ?, ?)');
$stmt->bind_param('siis', $conversationId, $senderId, $receiverId, $body);
$stmt->execute();
$msgId = $stmt->insert_id;
$stmt->close();

$res = $conn->prepare('SELECT id, conversation_id, sender_id, receiver_id, body, created_at FROM messages WHERE id = ?');
$res->bind_param('i', $msgId);
$res->execute();
$message = $res->get_result()->fetch_assoc();
$res->close();

$pusher = new Pusher\Pusher(
	PUSHER_KEY,
	PUSHER_SECRET,
	PUSHER_APP_ID,
	['cluster' => PUSHER_CLUSTER, 'useTLS' => PUSHER_USE_TLS]
);
$channel = 'private-chat-' . $conversationId;
$pusher->trigger($channel, 'new-message', $message);

echo json_encode(['ok' => true, 'message' => $message]);



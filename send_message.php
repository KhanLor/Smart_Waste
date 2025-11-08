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
$action = trim($_POST['action'] ?? 'send');

if ($senderId <= 0 || $receiverId <= 0 || $conversationId === '') {
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

// Helper to check whether optional columns exist in the messages table
function column_exists($conn, $db, $table, $column) {
	$stmt = $conn->prepare('SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
	$stmt->bind_param('sss', $db, $table, $column);
	$stmt->execute();
	$r = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	return (int)($r['cnt'] ?? 0) > 0;
}

// Route actions: send (default), edit, unsend
if ($action === 'edit') {
	$messageId = (int)($_POST['message_id'] ?? 0);
	if ($messageId <= 0 || $body === '') {
		echo json_encode(['ok' => false, 'error' => 'Invalid input']);
		exit;
	}

	// Only allow sender to edit their own message and ensure it isn't already unsent
	$chk = $conn->prepare('SELECT sender_id, conversation_id, is_unsent FROM messages WHERE id = ? LIMIT 1');
	$chk->bind_param('i', $messageId);
	$chk->execute();
	$row = $chk->get_result()->fetch_assoc();
	$chk->close();

	if (!$row || (int)$row['sender_id'] !== $senderId) {
		echo json_encode(['ok' => false, 'error' => 'Permission denied']);
		exit;
	}

	if (isset($row['is_unsent']) && (int)$row['is_unsent'] === 1) {
		echo json_encode(['ok' => false, 'error' => 'Cannot edit an unsent message']);
		exit;
	}

	// Update body and edited_at if column exists
	if (column_exists($conn, $DB_NAME, 'messages', 'edited_at')) {
		$stmt = $conn->prepare('UPDATE messages SET body = ?, edited_at = CURRENT_TIMESTAMP WHERE id = ? AND sender_id = ?');
		$stmt->bind_param('sii', $body, $messageId, $senderId);
	} else {
		$stmt = $conn->prepare('UPDATE messages SET body = ? WHERE id = ? AND sender_id = ?');
		$stmt->bind_param('sii', $body, $messageId, $senderId);
	}
	$stmt->execute();
	$stmt->close();

	$res = $conn->prepare('SELECT id, conversation_id, sender_id, receiver_id, body, created_at' . (column_exists($conn, $DB_NAME, 'messages', 'edited_at') ? ', edited_at' : '') . ' FROM messages WHERE id = ?');
	$res->bind_param('i', $messageId);
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
	$pusher->trigger($channel, 'update-message', ['type' => 'edit', 'message' => $message]);

	echo json_encode(['ok' => true, 'message' => $message]);
	exit;
}

if ($action === 'unsend') {
	$messageId = (int)($_POST['message_id'] ?? 0);
	if ($messageId <= 0) {
		echo json_encode(['ok' => false, 'error' => 'Invalid input']);
		exit;
	}

	// Only allow sender to unsend their own message
	$chk = $conn->prepare('SELECT sender_id, conversation_id FROM messages WHERE id = ? LIMIT 1');
	$chk->bind_param('i', $messageId);
	$chk->execute();
	$row = $chk->get_result()->fetch_assoc();
	$chk->close();

	if (!$row || (int)$row['sender_id'] !== $senderId) {
		echo json_encode(['ok' => false, 'error' => 'Permission denied']);
		exit;
	}

	// Mark message as unsent (soft delete) if column exists, otherwise overwrite body
	if (column_exists($conn, $DB_NAME, 'messages', 'is_unsent')) {
		if (column_exists($conn, $DB_NAME, 'messages', 'unsent_at')) {
			$stmt = $conn->prepare('UPDATE messages SET is_unsent = 1, unsent_at = CURRENT_TIMESTAMP WHERE id = ? AND sender_id = ?');
			$stmt->bind_param('ii', $messageId, $senderId);
		} else {
			$stmt = $conn->prepare('UPDATE messages SET is_unsent = 1 WHERE id = ? AND sender_id = ?');
			$stmt->bind_param('ii', $messageId, $senderId);
		}
	} else {
		// fallback: replace body with placeholder
		$placeholder = 'Message unsent.';
		$stmt = $conn->prepare('UPDATE messages SET body = ? WHERE id = ? AND sender_id = ?');
		$stmt->bind_param('sii', $placeholder, $messageId, $senderId);
	}
	$stmt->execute();
	$stmt->close();

	$res = $conn->prepare('SELECT id, conversation_id, sender_id, receiver_id, body, created_at' . (column_exists($conn, $DB_NAME, 'messages', 'is_unsent') ? ', is_unsent, unsent_at' : '') . ' FROM messages WHERE id = ?');
	$res->bind_param('i', $messageId);
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
	$pusher->trigger($channel, 'update-message', ['type' => 'unsend', 'message' => $message]);

	echo json_encode(['ok' => true, 'message' => $message]);
	exit;
}

// Default: create new message
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



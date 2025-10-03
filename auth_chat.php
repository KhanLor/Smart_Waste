<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/vendor/autoload.php';

// Only allow logged-in users
if (!is_logged_in()) {
	http_response_code(403);
	exit;
}

// Validate inputs from Pusher auth request
$channelName = $_POST['channel_name'] ?? '';
$socketId    = $_POST['socket_id'] ?? '';

if ($channelName === '' || $socketId === '') {
	http_response_code(422);
	exit;
}

// Optional: authorize that the user is allowed to subscribe to this channel
// Example private channel format: private-chat-resident_12_authority_3
// You can parse and check participants if needed.

$pusher = new Pusher\Pusher(
	PUSHER_KEY,
	PUSHER_SECRET,
	PUSHER_APP_ID,
	['cluster' => PUSHER_CLUSTER, 'useTLS' => PUSHER_USE_TLS]
);

header('Content-Type: application/json');
// Optional authorization: ensure user belongs to requested conversation
// Expect channel like private-chat-u_12_u_34
if (strpos($channelName, 'private-chat-u_') === 0) {
	$parts = explode('private-chat-u_', $channelName);
	if (isset($parts[1])) {
		list($a, $b) = array_pad(explode('_u_', $parts[1], 2), 2, null);
		$me = (int)($_SESSION['user_id'] ?? 0);
		if ((int)$a !== $me && (int)$b !== $me) {
			http_response_code(403);
			exit;
		}
	}
}

echo $pusher->authorizeChannel($channelName, $socketId);



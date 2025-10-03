<?php
require_once __DIR__ . '/config/config.php';
require_login();

// Example: build a deterministic conversation id between logged-in user and a target user (e.g., authority)
$me = (int)($_SESSION['user_id'] ?? 0);
$targetId = (int)($_GET['to'] ?? 0);
if ($targetId <= 0) {
	// Redirect or show simple message
	header('Location: ' . BASE_URL . 'index.php');
	exit;
}

// Simple deterministic conversation id (sorted ids so both sides get same id)
$a = min($me, $targetId);
$b = max($me, $targetId);
$conversationId = 'u_' . $a . '_u_' . $b;
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Chat - <?php echo APP_NAME; ?></title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
	<style>
		body { background:#f8fafc; }
		.chat-box { height:60vh; overflow:auto; background:#fff; border:1px solid #e5e7eb; border-radius:12px; }
		.me   { text-align:right; }
		.msg  { padding:.5rem .75rem; border-radius:10px; display:inline-block; max-width:70%; }
		.msg-me { background:#dcfce7; }
		.msg-them { background:#f3f4f6; }
	</style>
</head>
<body>
	<nav class="navbar navbar-expand-lg fixed-top bg-white border-bottom">
		<div class="container">
			<a class="navbar-brand" href="<?php echo BASE_URL; ?>index.php"><i class="fas fa-recycle text-success"></i> <?php echo APP_NAME; ?></a>
		</div>
	</nav>

	<div class="container" style="padding-top:90px;">
		<div class="row justify-content-center">
			<div class="col-md-8">
				<div class="mb-3">
					<div id="messages" class="chat-box p-3"></div>
				</div>
				<form id="chatForm" class="input-group">
					<input type="hidden" id="conversation_id" value="<?php echo e($conversationId); ?>">
					<input type="hidden" id="receiver_id" value="<?php echo (int)$targetId; ?>">
					<input class="form-control" id="msg" placeholder="Type a message...">
					<button class="btn btn-success" type="submit">Send</button>
				</form>
			</div>
		</div>
	</div>

	<script src="https://js.pusher.com/8.2/pusher.min.js"></script>
	<script src="<?php echo BASE_URL; ?>assets/js/notifications.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
	<script>
		const convoId = document.getElementById('conversation_id').value;
		const myId = <?php echo (int)$me; ?>;
		const messagesDiv = document.getElementById('messages');

		// Load history
		(async function loadHistory(){
			const res = await fetch('<?php echo BASE_URL; ?>fetch_messages.php?conversation_id=' + encodeURIComponent(convoId));
			const list = await res.json();
			list.forEach(appendMessage);
			messagesDiv.scrollTop = messagesDiv.scrollHeight;
		})();

		// Realtime subscribe
		const pusher = new Pusher('<?php echo PUSHER_KEY; ?>', {
			cluster: '<?php echo PUSHER_CLUSTER; ?>',
			authEndpoint: '<?php echo BASE_URL; ?>auth_chat.php'
		});
		const channel = pusher.subscribe('private-chat-' + convoId);
		channel.bind('new-message', data => {
			appendMessage(data);
			// If message from other user, show browser notification
			if (data && parseInt(data.sender_id) !== myId) {
				Notifications.requestPermissionOnce().then((granted) => {
					if (granted) {
						Notifications.show({
							title: 'New message',
							body: data.body || 'You have a new message',
							icon: '<?php echo BASE_URL; ?>assets/collector.png',
							onclick: () => { window.focus(); }
						});
					}
				});
			}
		});

		function appendMessage(m) {
			const wrap = document.createElement('div');
			wrap.className = (m.sender_id == myId) ? 'me my-1' : 'my-1';
			const bubble = document.createElement('div');
			bubble.className = 'msg ' + ((m.sender_id == myId) ? 'msg-me' : 'msg-them');
			bubble.textContent = m.body;
			wrap.appendChild(bubble);
			messagesDiv.appendChild(wrap);
			messagesDiv.scrollTop = messagesDiv.scrollHeight;
		}

		document.getElementById('chatForm').addEventListener('submit', async (e) => {
			e.preventDefault();
			const body = document.getElementById('msg').value.trim();
			if (!body) return;
			const form = new FormData();
			form.append('conversation_id', convoId);
			form.append('receiver_id', document.getElementById('receiver_id').value);
			form.append('body', body);
			const res = await fetch('<?php echo BASE_URL; ?>send_message.php', { method:'POST', body: form, credentials:'same-origin' });
			const json = await res.json();
			if (json.ok) document.getElementById('msg').value = '';
		});
	</script>
</body>
</html>



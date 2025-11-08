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

		/* Action menu sits slightly outside the bubble and does not overlap text */
		.msg { position: relative; padding-right: 64px; }
		.msg .msg-actions { position: absolute; top: 6px; right: -18px; width:36px; height:36px; }
		.msg .msg-actions .btn {
			width:100%; height:100%; padding:0; display:flex; align-items:center; justify-content:center;
			background:#fff; border:1px solid rgba(0,0,0,0.08); border-radius:8px; box-shadow:0 6px 18px rgba(0,0,0,0.12);
		}
		.msg .msg-actions .btn .fas { margin:0; }
		.msg .msg-actions .dropdown-menu { z-index: 4000; }
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

		// Listen for edits / unsend events
		channel.bind('update-message', payload => {
			if (!payload || !payload.message) return;
			const m = payload.message;
			// Find existing DOM element by message id
			const existing = document.querySelector('[data-msg-id="' + m.id + '"]');
			if (existing) {
				if (payload.type === 'unsend' || (m.is_unsent && parseInt(m.is_unsent) === 1)) {
					existing.textContent = 'Message unsent.';
					existing.classList.add('text-muted');
				} else {
					// edit
					existing.textContent = m.body + (m.edited_at ? ' (edited)' : '');
				}
			} else {
				// If not found, append it (in case client missed it)
				appendMessage(m);
			}
			messagesDiv.scrollTop = messagesDiv.scrollHeight;
		});

		// Event delegation for edit / unsend clicks inside messagesDiv
		messagesDiv.addEventListener('click', async function(e) {
			const editLink = e.target.closest('.edit-action');
			const unsendLink = e.target.closest('.unsend-action');
			if (editLink) {
				e.preventDefault();
				const msgId = editLink.getAttribute('data-msg-id');
				const bubble = document.querySelector('[data-msg-id="' + msgId + '"]');
				const current = bubble ? bubble.textContent.replace(/\s*\(edited\)\s*$/, '').trim() : '';
				const newText = prompt('Edit message:', current);
				if (newText === null) return;
				const body = newText.trim();
				if (!body) return alert('Message cannot be empty');
				const form = new FormData();
				form.append('action', 'edit');
				form.append('message_id', msgId);
				form.append('conversation_id', convoId);
				form.append('receiver_id', document.getElementById('receiver_id').value);
				form.append('body', body);
				const res = await fetch('<?php echo BASE_URL; ?>send_message.php', { method:'POST', body: form, credentials:'same-origin' });
				const json = await res.json();
				if (!json.ok) return alert(json.error || 'Failed to edit');
				if (bubble) bubble.textContent = json.message.body + (json.message.edited_at ? ' (edited)' : '');
			} else if (unsendLink) {
				e.preventDefault();
				const msgId = unsendLink.getAttribute('data-msg-id');
				if (!confirm('Unsend this message?')) return;
				const form = new FormData();
				form.append('action', 'unsend');
				form.append('message_id', msgId);
				form.append('conversation_id', convoId);
				form.append('receiver_id', document.getElementById('receiver_id').value);
				const res = await fetch('<?php echo BASE_URL; ?>send_message.php', { method:'POST', body: form, credentials:'same-origin' });
				const json = await res.json();
				if (!json.ok) return alert(json.error || 'Failed to unsend');
				const bubble = document.querySelector('[data-msg-id="' + msgId + '"]');
				if (bubble) { bubble.textContent = 'Message unsent.'; bubble.classList.add('text-muted'); }
			}
		});

		function appendMessage(m) {
			const wrap = document.createElement('div');
			wrap.className = (m.sender_id == myId) ? 'me my-1' : 'my-1';
			const bubble = document.createElement('div');
			bubble.className = 'msg ' + ((m.sender_id == myId) ? 'msg-me' : 'msg-them');
			bubble.setAttribute('data-msg-id', m.id);

			// If message was unsent, show placeholder
			if (m.is_unsent && parseInt(m.is_unsent) === 1) {
				bubble.textContent = 'Message unsent.';
				bubble.classList.add('text-muted');
			} else {
				bubble.textContent = m.body + (m.edited_at ? ' (edited)' : '');
			}

			// If this is my message and it's not unsent, add small action menu (three-dot) for Edit/Unsend
			if (parseInt(m.sender_id) === parseInt(myId) && !(m.is_unsent && parseInt(m.is_unsent) === 1)) {
				const dd = document.createElement('div');
				dd.className = 'dropdown msg-actions';
				dd.innerHTML = `
					<button class="btn btn-sm btn-light dropdown-toggle" type="button" id="msgActions${m.id}" data-bs-toggle="dropdown" aria-expanded="false">
						<i class="fas fa-ellipsis-v"></i>
					</button>
					<ul class="dropdown-menu dropdown-menu-end" aria-labelledby="msgActions${m.id}">
						<li><a class="dropdown-item edit-action" href="#" data-msg-id="${m.id}">Edit</a></li>
						<li><a class="dropdown-item text-danger unsend-action" href="#" data-msg-id="${m.id}">Unsend</a></li>
					</ul>
				`;
				bubble.appendChild(dd);
			}

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



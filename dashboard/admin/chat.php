<?php
require_once '../../config/config.php';
require_login();

// Check if user is an admin
if (($_SESSION['role'] ?? '') !== 'admin') {
	header('Location: ' . BASE_URL . 'login.php');
	exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$success_message = '';
$error_message = '';

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
	if ($_POST['action'] === 'send_message') {
		$receiver_id = $_POST['receiver_id'] ?? null;
		$message = trim($_POST['message'] ?? '');
		if ($receiver_id && !empty($message)) {
			try {
				$stmt = $conn->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message, is_read) VALUES (?, ?, ?, 0)");
				$stmt->bind_param("iis", $user_id, $receiver_id, $message);
				if ($stmt->execute()) { $success_message = 'Message sent successfully.'; } else { throw new Exception('Failed to send message.'); }
			} catch (Exception $e) { $error_message = 'Error sending message: ' . $e->getMessage(); }
		} else {
			$error_message = 'Please select a recipient and enter a message.';
		}
	}
}

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Residents list
$stmt = $conn->prepare("SELECT id, first_name, last_name, role FROM users WHERE role = 'resident' ORDER BY first_name, last_name");
$stmt->execute();
$residents = $stmt->get_result();

// Selected resident chat
$selected_resident = $_GET['resident'] ?? null;
$chat_messages = null;
$selected_resident_data = null;
if ($selected_resident) {
	$stmt = $conn->prepare("SELECT id, first_name, last_name FROM users WHERE id = ? AND role = 'resident'");
	$stmt->bind_param("i", $selected_resident);
	$stmt->execute();
	$selected_resident_data = $stmt->get_result()->fetch_assoc();
	if ($selected_resident_data) {
		$stmt = $conn->prepare("SELECT cm.*, u.first_name, u.last_name, u.role FROM chat_messages cm JOIN users u ON cm.sender_id = u.id WHERE (cm.sender_id = ? AND cm.receiver_id = ?) OR (cm.sender_id = ? AND cm.receiver_id = ?) ORDER BY cm.created_at ASC");
		$stmt->bind_param("iiii", $user_id, $selected_resident, $selected_resident, $user_id);
		$stmt->execute();
		$chat_messages = $stmt->get_result();
		$stmt = $conn->prepare("UPDATE chat_messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
		$stmt->bind_param("ii", $selected_resident, $user_id);
		$stmt->execute();
	}
}

// Unread count
$stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM chat_messages WHERE receiver_id = ? AND is_read = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$unread_count = $stmt->get_result()->fetch_assoc()['unread_count'];

// Recent conversations
$stmt = $conn->prepare("SELECT u.id, u.first_name, u.last_name, cm.message as last_message, cm.created_at as last_message_time, COUNT(CASE WHEN cm.is_read = 0 AND cm.sender_id = u.id THEN 1 END) as unread_count FROM users u LEFT JOIN chat_messages cm ON (cm.sender_id = u.id AND cm.receiver_id = ?) OR (cm.sender_id = ? AND cm.receiver_id = u.id) WHERE u.role = 'resident' GROUP BY u.id HAVING last_message IS NOT NULL ORDER BY last_message_time DESC");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$recent_conversations = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Chat - <?php echo APP_NAME; ?></title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
	<link rel="stylesheet" href="../../assets/css/dashboard.css">
	<style>
		.sidebar { min-height: 100vh; background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); }
		.card { border: none; border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
		.nav-link { border-radius: 10px; margin: 2px 0; transition: all 0.3s; }
		.nav-link:hover, .nav-link.active { background-color: rgba(255,255,255,0.2); transform: translateX(5px); }
		.chat-container { height: 70vh; display: flex; flex-direction: column; }
		.chat-messages { flex: 1; overflow-y: auto; padding: 20px; background: #f8f9fa; }
		.message { margin-bottom: 15px; display: flex; align-items: flex-end; }
		.message.sent { justify-content: flex-end; }
		.message.received { justify-content: flex-start; }
		.message-content { max-width: 70%; padding: 10px 15px; border-radius: 18px; position: relative; }
		.message.sent .message-content { background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white; border-bottom-right-radius: 5px; }
		.message.received .message-content { background: white; color: #333; border: 1px solid #e9ecef; border-bottom-left-radius: 5px; }
		.message-time { font-size: 0.75rem; color: #6c757d; margin-top: 5px; }
		.message.sent .message-time { text-align: right; }
		.resident-item { padding: 15px; border-bottom: 1px solid #e9ecef; cursor: pointer; transition: background-color 0.2s; }
		.resident-item:hover { background-color: #f8f9fa; }
		.resident-item.active { background-color: #e3f2fd; border-left: 4px solid #007bff; }
		.unread-badge { background: #dc3545; color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: bold; }
		.chat-input { border-top: 1px solid #e9ecef; padding: 20px; background: white; }
		.no-chat-selected { display: flex; align-items: center; justify-content: center; height: 100%; color: #6c757d; }
	</style>
</head>
<body class="role-admin">
	<div class="container-fluid">
		<div class="row">
			<div class="col-md-3 col-lg-2 sidebar text-white p-0"><div class="p-3"><h4 class="mb-4"><i class="fas fa-user-shield me-2"></i><?php echo APP_NAME; ?></h4><hr class="bg-white"><nav class="nav flex-column"><a class="nav-link text-white" href="index.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a><a class="nav-link text-white" href="reports.php"><i class="fas fa-exclamation-triangle me-2"></i>Waste Reports</a><a class="nav-link text-white" href="schedules.php"><i class="fas fa-calendar me-2"></i>Collection Schedules</a><a class="nav-link text-white" href="collectors.php"><i class="fas fa-users me-2"></i>Collectors</a><a class="nav-link text-white" href="residents.php"><i class="fas fa-home me-2"></i>Residents</a><a class="nav-link text-white active" href="chat.php"><i class="fas fa-comments me-2"></i>Chat <?php if ($unread_count > 0): ?><span class="badge bg-warning text-dark ms-2"><?php echo $unread_count; ?></span><?php endif; ?></a><hr class="bg-white"><a class="nav-link text-white" href="../../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></nav></div></div>
			<div class="col-md-9 col-lg-10"><div class="p-4">
				<div class="d-flex justify-content-between align-items-center mb-4"><div><h2 class="mb-1">Chat with Residents</h2><p class="text-muted mb-0">Communicate directly with residents about their reports and concerns</p></div><div class="text-end"><?php if ($unread_count > 0): ?><div class="h4 text-danger mb-0"><?php echo $unread_count; ?></div><small class="text-muted">Unread Messages</small><?php else: ?><div class="h4 text-success mb-0">0</div><small class="text-muted">Unread Messages</small><?php endif; ?></div></div>
				<?php if ($success_message): ?><div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?php echo e($success_message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
				<?php if ($error_message): ?><div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-triangle me-2"></i><?php echo e($error_message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
				<div class="row">
					<div class="col-md-4"><div class="card"><div class="card-header bg-light"><h6 class="mb-0"><i class="fas fa-users me-2"></i>Residents</h6></div><div class="card-body p-0"><?php if ($residents->num_rows > 0): ?><?php while ($resident = $residents->fetch_assoc()): ?><div class="resident-item <?php echo ($selected_resident == $resident['id']) ? 'active' : ''; ?>" onclick="selectResident(<?php echo $resident['id']; ?>)"><div class="d-flex align-items-center"><div class="flex-grow-1"><h6 class="mb-1"><?php echo e($resident['first_name'] . ' ' . $resident['last_name']); ?></h6><small class="text-muted">Resident</small></div><i class="fas fa-chevron-right text-muted"></i></div></div><?php endwhile; ?><?php else: ?><p class="text-muted text-center p-3">No residents available.</p><?php endif; ?></div></div></div>
					<div class="col-md-8"><div class="card chat-container"><?php if ($selected_resident_data): ?><div class="card-header bg-light"><div class="d-flex align-items-center"><div class="flex-grow-1"><h6 class="mb-0"><i class="fas fa-user me-2"></i><?php echo e($selected_resident_data['first_name'] . ' ' . $selected_resident_data['last_name']); ?></h6><small class="text-muted">Resident</small></div><div class="dropdown"><button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button><ul class="dropdown-menu"><li><a class="dropdown-item" href="#" onclick="clearChat()"><i class="fas fa-trash me-2"></i>Clear Chat</a></li></ul></div></div></div><div class="chat-messages" id="chatMessages"><?php if ($chat_messages && $chat_messages->num_rows > 0): ?><?php while ($message = $chat_messages->fetch_assoc()): ?><div class="message <?php echo $message['sender_id'] == $user_id ? 'sent' : 'received'; ?>"><div class="message-content"><div><?php echo e($message['message']); ?></div><div class="message-time"><?php echo format_ph_date($message['created_at']); ?></div></div></div><?php endwhile; ?><?php else: ?><div class="text-center text-muted"><i class="fas fa-comments fa-3x mb-3"></i><p>No messages yet. Start a conversation!</p></div><?php endif; ?></div><div class="chat-input"><form method="POST" id="chatForm"><input type="hidden" name="action" value="send_message"><input type="hidden" name="receiver_id" value="<?php echo $selected_resident; ?>"><div class="input-group"><input type="text" class="form-control" name="message" placeholder="Type your message..." required><button class="btn btn-primary" type="submit"><i class="fas fa-paper-plane"></i></button></div></form></div><?php else: ?><div class="no-chat-selected"><div class="text-center"><i class="fas fa-comments fa-4x text-muted mb-4"></i><h4>Select a Resident</h4><p class="text-muted">Choose a resident from the list to start chatting</p></div></div><?php endif; ?></div></div>
				</div>
			</div></div>
		</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
	<script>
		function selectResident(residentId) { window.location.href = '?resident=' + residentId; }
		function clearChat() { if (confirm('Are you sure you want to clear this chat? This action cannot be undone.')) { alert('Chat clearing functionality would be implemented here.'); } }
		function scrollToBottom() { const chatMessages = document.getElementById('chatMessages'); if (chatMessages) { chatMessages.scrollTop = chatMessages.scrollHeight; } }
		document.addEventListener('DOMContentLoaded', function() { scrollToBottom(); });
		setInterval(function() { if (<?php echo $selected_resident ? 'true' : 'false'; ?>) { location.reload(); } }, 30000);
	</script>
</body>
</html>

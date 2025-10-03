<?php
require_once __DIR__ . '/../../config/config.php';
require_login();

// Check if user is a resident
if (($_SESSION['role'] ?? '') !== 'resident') {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$success_message = '';
$error_message = '';

// Handle message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'send_message') {
        $receiver_id = $_POST['receiver_id'] ?? null;
        $message = trim($_POST['message'] ?? '');
        
        if (empty($message) || !$receiver_id) {
            $error_message = 'Please enter a message and select a recipient.';
        } else {
            try {
                // Insert chat message
                $stmt = $conn->prepare("
                    INSERT INTO chat_messages (sender_id, receiver_id, message, message_type) 
                    VALUES (?, ?, ?, 'text')
                ");
                $stmt->bind_param("iis", $user_id, $receiver_id, $message);
                
                if ($stmt->execute()) {
                    $success_message = 'Message sent successfully!';
                    
                    // Create notification for receiver
                    $stmt = $conn->prepare("
                        INSERT INTO notifications (user_id, title, message, type, reference_type, reference_id) 
                        VALUES (?, ?, ?, 'info', 'chat', ?)
                    ");
                    $notification_title = 'New Message';
                    $notification_message = 'You have a new message from a resident.';
                    $message_id = $conn->insert_id;
                    $stmt->bind_param("issi", $receiver_id, $notification_title, $notification_message, $message_id);
                    $stmt->execute();
                } else {
                    throw new Exception('Failed to send message.');
                }
            } catch (Exception $e) {
                $error_message = 'Error sending message: ' . $e->getMessage();
            }
        }
    }
}

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get available authorities to chat with
$stmt = $conn->prepare("SELECT id, first_name, last_name, role FROM users WHERE role = 'authority' ORDER BY first_name, last_name");
$stmt->execute();
$authorities = $stmt->get_result();

// Get chat history with selected authority
$selected_authority = $_GET['authority'] ?? null;
$chat_messages = null;
$selected_authority_data = null;

if ($selected_authority) {
    // Get authority data
    $stmt = $conn->prepare("SELECT id, first_name, last_name FROM users WHERE id = ? AND role = 'authority'");
    $stmt->bind_param("i", $selected_authority);
    $stmt->execute();
    $selected_authority_data = $stmt->get_result()->fetch_assoc();
    
    if ($selected_authority_data) {
        // Get chat messages
        $stmt = $conn->prepare("
            SELECT cm.*, u.first_name, u.last_name, u.role 
            FROM chat_messages cm 
            JOIN users u ON cm.sender_id = u.id 
            WHERE (cm.sender_id = ? AND cm.receiver_id = ?) 
               OR (cm.sender_id = ? AND cm.receiver_id = ?)
            ORDER BY cm.created_at ASC
        ");
        $stmt->bind_param("iiii", $user_id, $selected_authority, $selected_authority, $user_id);
        $stmt->execute();
        $chat_messages = $stmt->get_result();
        
        // Mark messages as read
        $stmt = $conn->prepare("
            UPDATE chat_messages 
            SET is_read = 1 
            WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
        ");
        $stmt->bind_param("ii", $selected_authority, $user_id);
        $stmt->execute();
    }
}

// Get unread message count
$stmt = $conn->prepare("
    SELECT COUNT(*) as unread_count 
    FROM chat_messages 
    WHERE receiver_id = ? AND is_read = 0
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$unread_count = $stmt->get_result()->fetch_assoc()['unread_count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Support - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .nav-link {
            border-radius: 10px;
            margin: 2px 0;
            transition: all 0.3s;
        }
        .nav-link:hover, .nav-link.active {
            background-color: rgba(255, 255, 255, 0.2);
            transform: translateX(5px);
        }
        .chat-container {
            height: 500px;
            overflow-y: auto;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
        }
        .message {
            margin-bottom: 15px;
            max-width: 80%;
        }
        .message.sent {
            margin-left: auto;
        }
        .message.received {
            margin-right: auto;
        }
        .message-content {
            padding: 10px 15px;
            border-radius: 18px;
            word-wrap: break-word;
        }
        .message.sent .message-content {
            background: #007bff;
            color: white;
        }
        .message.received .message-content {
            background: white;
            border: 1px solid #dee2e6;
        }
        .message-time {
            font-size: 0.75rem;
            color: #6c757d;
            margin-top: 5px;
            text-align: center;
        }
        .authority-item {
            cursor: pointer;
            transition: all 0.3s;
            border-radius: 10px;
        }
        .authority-item:hover {
            background-color: #f8f9fa;
            transform: translateX(5px);
        }
        .authority-item.active {
            background-color: #e3f2fd;
            border-left: 4px solid #2196f3;
        }
        .unread-badge {
            background: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 10px;
            margin-left: auto;
        }
        .chat-input {
            border-radius: 20px;
            border: 2px solid #e9ecef;
            transition: border-color 0.3s;
        }
        .chat-input:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }
        .btn-send {
            border-radius: 50%;
            width: 40px;
            height: 40px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar text-white p-0">
                <div class="p-3">
                    <h4 class="mb-4"><i class="fas fa-recycle me-2"></i><?php echo APP_NAME; ?></h4>
                    <hr class="bg-white">
                    <nav class="nav flex-column">
                        <a class="nav-link text-white" href="index.php">
                            <i class="fas fa-home me-2"></i>Dashboard
                        </a>
                        <a class="nav-link text-white" href="reports.php">
                            <i class="fas fa-exclamation-circle me-2"></i>My Reports
                        </a>
                        <a class="nav-link text-white" href="submit_report.php">
                            <i class="fas fa-plus-circle me-2"></i>Submit Report
                        </a>
                        <a class="nav-link text-white" href="schedule.php">
                            <i class="fas fa-calendar me-2"></i>Collection Schedule
                        </a>
                        <a class="nav-link text-white" href="points.php">
                            <i class="fas fa-leaf me-2"></i>Eco Points
                        </a>
                        <a class="nav-link text-white" href="feedback.php">
                            <i class="fas fa-comment me-2"></i>Feedback
                        </a>
                        <a class="nav-link text-white active" href="chat.php">
                            <i class="fas fa-comments me-2"></i>Chat
                            <?php if ($unread_count > 0): ?>
                                <span class="notification-badge"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </a>
                        <a class="nav-link text-white" href="profile.php">
                            <i class="fas fa-user me-2"></i>Profile
                        </a>
                        <hr class="bg-white">
                        <a class="nav-link text-white" href="../../logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="p-4">
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="mb-1">Chat Support</h2>
                            <p class="text-muted mb-0">Get help from waste management authorities</p>
                        </div>
                        <div class="text-end">
                            <div class="h4 text-success mb-0"><?php echo $user['eco_points']; ?></div>
                            <small class="text-muted">Eco Points</small>
                        </div>
                    </div>

                    <!-- Messages -->
                    <?php if ($success_message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?php echo e($success_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($error_message): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo e($error_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <!-- Authorities List -->
                        <div class="col-md-4 mb-4">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="fas fa-users me-2"></i>Available Authorities</h6>
                                </div>
                                <div class="card-body p-0">
                                    <?php if ($authorities->num_rows > 0): ?>
                                        <?php while ($authority = $authorities->fetch_assoc()): ?>
                                            <div class="authority-item p-3 <?php echo ($selected_authority == $authority['id']) ? 'active' : ''; ?>" 
                                                 onclick="selectAuthority(<?php echo $authority['id']; ?>)">
                                                <div class="d-flex align-items-center">
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1"><?php echo e($authority['first_name'] . ' ' . $authority['last_name']); ?></h6>
                                                        <small class="text-muted"><?php echo ucfirst($authority['role']); ?></small>
                                                    </div>
                                                    <i class="fas fa-chevron-right text-muted"></i>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <p class="text-muted text-center p-3">No authorities available.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Chat Area -->
                        <div class="col-md-8">
                            <?php if ($selected_authority_data): ?>
                                <!-- Chat Header -->
                                <div class="card mb-3">
                                    <div class="card-header bg-primary text-white">
                                        <h6 class="mb-0">
                                            <i class="fas fa-comments me-2"></i>
                                            Chat with <?php echo e($selected_authority_data['first_name'] . ' ' . $selected_authority_data['last_name']); ?>
                                        </h6>
                                    </div>
                                </div>

                                <!-- Chat Messages -->
                                <div class="card mb-3">
                                    <div class="chat-container" id="chatContainer">
                                        <?php if ($chat_messages && $chat_messages->num_rows > 0): ?>
                                            <?php while ($msg = $chat_messages->fetch_assoc()): ?>
                                                <div class="message <?php echo ($msg['sender_id'] == $user_id) ? 'sent' : 'received'; ?>">
                                                    <div class="message-content">
                                                        <?php echo e($msg['message']); ?>
                                                    </div>
                                                    <div class="message-time">
                                                        <?php echo format_ph_date($msg['created_at'], 'g:i A'); ?>
                                                    </div>
                                                </div>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <div class="text-center text-muted mt-4">
                                                <i class="fas fa-comments fa-3x mb-3"></i>
                                                <p>No messages yet. Start the conversation!</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Message Input -->
                                <div class="card">
                                    <div class="card-body">
                                        <form method="POST" id="messageForm">
                                            <input type="hidden" name="action" value="send_message">
                                            <input type="hidden" name="receiver_id" value="<?php echo $selected_authority_data['id']; ?>">
                                            <div class="input-group">
                                                <input type="text" class="form-control chat-input" name="message" 
                                                       placeholder="Type your message..." required>
                                                <button type="submit" class="btn btn-primary btn-send">
                                                    <i class="fas fa-paper-plane"></i>
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- Welcome Message -->
                                <div class="card">
                                    <div class="card-body text-center py-5">
                                        <i class="fas fa-comments fa-4x text-muted mb-4"></i>
                                        <h4>Welcome to Chat Support</h4>
                                        <p class="text-muted">Select an authority from the list to start chatting and get help with your waste management concerns.</p>
                                        <div class="row mt-4">
                                            <div class="col-md-4">
                                                <div class="text-center">
                                                    <i class="fas fa-question-circle fa-2x text-primary mb-2"></i>
                                                    <h6>Ask Questions</h6>
                                                    <small class="text-muted">Get clarification on waste collection schedules</small>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="text-center">
                                                    <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                                                    <h6>Report Issues</h6>
                                                    <small class="text-muted">Discuss urgent waste management problems</small>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="text-center">
                                                    <i class="fas fa-lightbulb fa-2x text-success mb-2"></i>
                                                    <h6>Get Advice</h6>
                                                    <small class="text-muted">Receive guidance on proper waste disposal</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectAuthority(authorityId) {
            window.location.href = 'chat.php?authority=' + authorityId;
        }

        // Auto-scroll to bottom of chat
        const chatContainer = document.getElementById('chatContainer');
        if (chatContainer) {
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }

        // Auto-refresh chat every 10 seconds
        if (<?php echo $selected_authority ? 'true' : 'false'; ?>) {
            setInterval(function() {
                location.reload();
            }, 10000);
        }

        // Form submission handling
        document.getElementById('messageForm')?.addEventListener('submit', function(e) {
            const messageInput = this.querySelector('input[name="message"]');
            if (messageInput.value.trim() === '') {
                e.preventDefault();
                return false;
            }
        });
    </script>
</body>
</html>

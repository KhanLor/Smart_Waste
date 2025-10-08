<?php
require_once __DIR__ . '/../../config/config.php';
require_login();

// Ensure user is authority
if (($_SESSION['role'] ?? '') !== 'authority') {
	header('Location: ' . BASE_URL . 'login.php');
	exit;
}

$user_id = $_SESSION['user_id'] ?? null;

// Handle mark all as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
	$stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
	$stmt->bind_param("i", $user_id);
	$stmt->execute();
	header('Location: notifications.php');
	exit;
}

// Fetch notifications (most recent first)
$limit = 100;
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
$stmt->bind_param("ii", $user_id, $limit);
$stmt->execute();
$notifications = $stmt->get_result();

// Count unread
$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$unread_row = $stmt->get_result()->fetch_assoc();
$unread_count = (int)($unread_row['cnt'] ?? 0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Notifications - <?php echo APP_NAME; ?></title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
	<link rel="stylesheet" href="../../assets/css/dashboard.css">
	<style>
		.sidebar { min-height: 100vh; background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); }
		.nav-link { border-radius: 10px; margin: 2px 0; transition: all 0.3s; }
		.nav-link:hover, .nav-link.active { background-color: rgba(255,255,255,0.2); transform: translateX(5px); }
		.notification-badge { position: absolute; top: -5px; right: -5px; background: #dc3545; color: #fff; border-radius: 50%; padding: 2px 6px; font-size: 10px; }
	</style>
</head>
<body class="role-authority">
	<div class="container-fluid">
		<div class="row">
			<!-- Sidebar -->
			<div class="col-md-3 col-lg-2 sidebar text-white p-0">
				<div class="p-3">
					<h4 class="mb-4"><i class="fas fa-shield-alt me-2"></i><?php echo APP_NAME; ?></h4>
					<hr class="bg-white">
					<nav class="nav flex-column">
						<a class="nav-link text-white" href="index.php">
							<i class="fas fa-tachometer-alt me-2"></i>Dashboard
						</a>
						<a class="nav-link text-white" href="reports.php">
							<i class="fas fa-exclamation-triangle me-2"></i>Waste Reports
						</a>
						<a class="nav-link text-white" href="schedules.php">
							<i class="fas fa-calendar me-2"></i>Collection Schedules
						</a>
						<a class="nav-link text-white" href="collectors.php">
							<i class="fas fa-users me-2"></i>Collectors
						</a>
						<a class="nav-link text-white" href="tracking.php">
							<i class="fas fa-map-marker-alt me-2"></i>Tracking
						</a>
						<a class="nav-link text-white" href="residents.php">
							<i class="fas fa-home me-2"></i>Residents
						</a>
						<a class="nav-link text-white" href="analytics.php">
							<i class="fas fa-chart-line me-2"></i>Analytics
						</a>
						<a class="nav-link text-white position-relative active" href="notifications.php">
							<i class="fas fa-bell me-2"></i>Notifications
							<?php if ($unread_count > 0): ?>
								<span class="notification-badge"><?php echo $unread_count; ?></span>
							<?php endif; ?>
						</a>
						<a class="nav-link text-white" href="chat.php">
							<i class="fas fa-comments me-2"></i>Chat Support
						</a>
						<a class="nav-link text-white" href="settings.php">
							<i class="fas fa-cog me-2"></i>Settings
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
					<div class="d-flex justify-content-between align-items-center mb-4">
						<h2 class="mb-0"><i class="fas fa-bell me-2"></i>Notifications</h2>
						<form method="post" class="m-0">
							<button type="submit" name="mark_all_read" value="1" class="btn btn-sm btn-outline-secondary" <?php echo $unread_count === 0 ? 'disabled' : '';?>>
								<i class="fas fa-check-double me-1"></i>Mark all as read
							</button>
						</form>
					</div>

					<div class="card">
						<div class="card-body">
							<?php if ($notifications->num_rows > 0): ?>
								<?php while ($n = $notifications->fetch_assoc()): ?>
									<div class="alert alert-<?php echo $n['type'] === 'success' ? 'success' : ($n['type'] === 'warning' ? 'warning' : ($n['type'] === 'error' ? 'danger' : 'info')); ?> d-flex justify-content-between align-items-start">
										<div>
											<strong><?php echo e($n['title']); ?></strong>
											<div class="small text-muted"><?php echo format_ph_date($n['created_at']); ?></div>
											<div class="mt-1"><?php echo e($n['message']); ?></div>
										</div>
										<div>
											<?php if ((int)$n['is_read'] === 0): ?><span class="badge bg-danger">New</span><?php endif; ?>
										</div>
									</div>
								<?php endwhile; ?>
							<?php else: ?>
								<p class="text-muted mb-0 text-center">No notifications yet.</p>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>



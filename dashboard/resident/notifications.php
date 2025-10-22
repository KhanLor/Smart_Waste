<?php
require_once __DIR__ . '/../../config/config.php';
require_login();

// Ensure user is a resident
if (($_SESSION['role'] ?? '') !== 'resident') {
	header('Location: ' . BASE_URL . 'login.php');
	exit;
}

$user_id = $_SESSION['user_id'] ?? null;

// Optional filter by reference_type (e.g., 'schedule', 'collection')
$allowed_filters = ['schedule', 'collection'];
$filterParam = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['filter'] ?? null) : ($_GET['filter'] ?? null);
$filter = ($filterParam && in_array($filterParam, $allowed_filters, true)) ? $filterParam : null;

// Handle mark all as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
	if ($filter) {
		$stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0 AND reference_type = ?");
		$stmt->bind_param("is", $user_id, $filter);
	} else {
		$stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
		$stmt->bind_param("i", $user_id);
	}
	$stmt->execute();
	$redirect = 'notifications.php' . ($filter ? ('?filter=' . urlencode($filter)) : '');
	header('Location: ' . $redirect);
	exit;
}

// Handle delete all notifications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_all'])) {
	if ($filter) {
		$stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ? AND reference_type = ?");
		$stmt->bind_param("is", $user_id, $filter);
	} else {
		$stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
		$stmt->bind_param("i", $user_id);
	}
	$stmt->execute();
	$redirect = 'notifications.php' . ($filter ? ('?filter=' . urlencode($filter)) : '');
	header('Location: ' . $redirect);
	exit;
}

// Fetch notifications (most recent first)
$limit = 100;
if ($filter) {
	$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? AND reference_type = ? ORDER BY created_at DESC LIMIT ?");
	$stmt->bind_param("isi", $user_id, $filter, $limit);
} else {
	$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
	$stmt->bind_param("ii", $user_id, $limit);
}
$stmt->execute();
$notifications = $stmt->get_result();

// Count unread
if ($filter) {
	$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0 AND reference_type = ?");
	$stmt->bind_param("is", $user_id, $filter);
} else {
	$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0");
	$stmt->bind_param("i", $user_id);
}
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
		.sidebar { min-height: 100vh; background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
		.nav-link { border-radius: 10px; margin: 2px 0; transition: all 0.3s; }
		.nav-link:hover, .nav-link.active { background-color: rgba(255,255,255,0.2); transform: translateX(5px); }
		.notification-badge { position: absolute; top: -5px; right: -5px; background: #dc3545; color: #fff; border-radius: 50%; padding: 2px 6px; font-size: 10px; }

		/* Notifications page layout tweaks */
		.notif-filters .nav { gap: .25rem; }
		.notif-actions .btn { white-space: nowrap; }
		.notif-card.alert { border-radius: 14px; padding: 1rem 1rem; }
		.notif-card .badge { font-size: .75rem; }

		/* Mobile responsiveness */
		@media (max-width: 767.98px) {
			/* Stack header blocks and add breathing room */
			.notif-header { flex-direction: column !important; align-items: stretch !important; gap: .75rem; }
			.notif-actions { width: 100%; display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; }
			.notif-actions form { width: 100%; }
			.notif-actions .btn { width: 100%; }

			/* Make filter tabs horizontally scrollable with good touch behavior */
			.notif-filters .nav { flex-wrap: nowrap; overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: none; }
			.notif-filters .nav::-webkit-scrollbar { display: none; }
			.notif-filters .nav .nav-link { white-space: nowrap; padding: .4rem .75rem; }

			/* Compact cards */
			.notif-card.alert { padding: .875rem .9rem; }
			.notif-card strong { font-size: 1.02rem; }
			.notif-card .small { font-size: .86rem; }
		}
		@media (max-width: 420px) {
			.notif-actions { grid-template-columns: 1fr; }
		}
	</style>
</head>
<body class="role-resident">
	<div class="container-fluid">
		<div class="row">
			<!-- Sidebar -->
			<div class="col-md-3 col-lg-2 sidebar text-white p-0">
				<?php include __DIR__ . '/_sidebar.php'; ?>
			</div>

			<!-- Main Content -->
			<div class="col-md-9 col-lg-10">
				<div class="p-4">
					<div class="d-flex justify-content-between align-items-center mb-3 notif-header gap-3">
						<div>
							<h2 class="mb-1"><i class="fas fa-bell me-2"></i>Notifications<?php echo $filter ? ' - ' . ucfirst($filter) : ''; ?></h2>
							<div class="mt-2 notif-filters">
								<ul class="nav nav-pills small">
									<li class="nav-item"><a class="nav-link<?php echo $filter ? '' : ' active'; ?>" href="notifications.php">All</a></li>
									<li class="nav-item"><a class="nav-link<?php echo $filter === 'schedule' ? ' active' : ''; ?>" href="notifications.php?filter=schedule">Schedule</a></li>
									<li class="nav-item"><a class="nav-link<?php echo $filter === 'collection' ? ' active' : ''; ?>" href="notifications.php?filter=collection">Collections</a></li>
								</ul>
							</div>
						</div>
						<div class="d-flex gap-2 notif-actions">
							<form method="post" class="m-0">
								<?php if ($filter): ?><input type="hidden" name="filter" value="<?php echo e($filter); ?>"><?php endif; ?>
								<button type="submit" name="mark_all_read" value="1" class="btn btn-sm btn-outline-secondary" <?php echo $unread_count === 0 ? 'disabled' : '';?>>
									<i class="fas fa-check-double me-1"></i>Mark all as read
								</button>
							</form>
							<form method="post" class="m-0" onsubmit="return confirmDeleteAll()">
								<?php if ($filter): ?><input type="hidden" name="filter" value="<?php echo e($filter); ?>"><?php endif; ?>
								<button type="submit" name="delete_all" value="1" class="btn btn-sm btn-outline-danger" <?php echo $notifications->num_rows === 0 ? 'disabled' : '';?>>
									<i class="fas fa-trash me-1"></i>Delete all
								</button>
							</form>
						</div>
					</div>

					<div class="card">
						<div class="card-body">
							<?php if ($notifications->num_rows > 0): ?>
								<?php while ($n = $notifications->fetch_assoc()): ?>
									<div class="alert notif-card alert-<?php echo $n['type'] === 'success' ? 'success' : ($n['type'] === 'warning' ? 'warning' : ($n['type'] === 'error' ? 'danger' : 'info')); ?> d-flex justify-content-between align-items-start">
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
	<script>
		function confirmDeleteAll() {
			return confirm('Are you sure you want to delete all notifications? This action cannot be undone.');
		}
	</script>
</body>
</html>



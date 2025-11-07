<?php
require_once __DIR__ . '/../../config/config.php';
require_login();

// Ensure user is authority
if (($_SESSION['role'] ?? '') !== 'authority') {
	header('Location: ' . BASE_URL . 'login.php');
	exit;
}

$user_id = $_SESSION['user_id'] ?? null;

// Optional filter by reference_type (e.g., 'collection', 'chat')
$allowed_filters = ['collection', 'chat', 'report'];
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

// Handle delete all
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
	<link rel="stylesheet" href="../../assets/css/dashboard.css?v=20251024">
	<style>
		.sidebar { min-height: 100vh; background: linear-gradient(135deg, #8B7E74 0%, #6B635A 100%); }
		.nav-link { border-radius: 10px; margin: 2px 0; transition: all 0.3s; }
		.nav-link:hover, .nav-link.active { background-color: rgba(255,255,255,0.2); transform: translateX(5px); }
		.notification-badge { position: absolute; top: -5px; right: -5px; background: #dc3545; color: #fff; border-radius: 50%; padding: 2px 6px; font-size: 10px; }
	</style>
	<style>
		/* Notifications layout (match resident style for clarity and mobile) */
		.notif-filters .nav { gap: .25rem; }
		.notif-filters .nav { flex-wrap: nowrap; overflow-x: auto; -webkit-overflow-scrolling: touch; padding-bottom: 6px; }
		/* WebKit browsers - horizontal scrollbar */
		.notif-filters .nav::-webkit-scrollbar { height: 8px; }
		.notif-filters .nav::-webkit-scrollbar-track { background: transparent; }
		.notif-filters .nav::-webkit-scrollbar-thumb { background: rgba(15,23,42,0.12); border-radius: 6px; }
		/* Firefox */
		.notif-filters .nav { scrollbar-width: thin; scrollbar-color: rgba(15,23,42,0.12) transparent; }
		.notif-actions .btn { white-space: nowrap; }
		.notif-card.alert { border-radius: 14px; padding: 1rem 1rem; display: flex; gap: 12px; align-items: flex-start; }
		.notif-card .badge { font-size: .75rem; }
		.notif-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.04); flex-shrink:0; }
		.notif-body { flex: 1 1 auto; }
		/* allow text to wrap inside flex children (important for long messages) */
		.notif-body { min-width: 0; }
		.notif-title { font-size: 1rem; margin-bottom: 6px; font-weight: 600; color: #0b2740; }
		.notif-meta { font-size: .85rem; color: #6c757d; }
		/* small label next to title */
		.notif-label { font-size: .7rem; padding: .18rem .45rem; border-radius: 8px; vertical-align: middle; }
		a.notif-link { display: block; border-radius: 14px; }
		a.notif-link .notif-card { margin: 0; }
		.notif-item { margin-bottom: 0.85rem; }
		/* Subtle card shadow for separation */
		.card .notif-card { box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04); }
		/* Type specific icon backgrounds and colors */
		.notif-type-chat .notif-icon { background: rgba(14,165,233,0.10); color: #0369a1; }
		.notif-type-report .notif-icon { background: rgba(59,130,246,0.08); color: #1e40af; }
		.notif-type-feedback .notif-icon { background: rgba(16,185,129,0.08); color: #047857; }
		@media (max-width: 767.98px) {
			.notif-header { flex-direction: column !important; align-items: stretch !important; gap: .75rem; }
			.notif-actions { width: 100%; display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; }
			.notif-actions form { width: 100%; }
			.notif-actions .btn { width: 100%; }
			.notif-filters .nav { flex-wrap: nowrap; overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: none; }
			.notif-filters .nav::-webkit-scrollbar { display: none; }
			.notif-filters .nav .nav-link { white-space: nowrap; padding: .4rem .75rem; }
			.notif-card.alert { padding: .875rem .9rem; }
		}
	</style>
	<style>
		/* Preview clamp and expand for mobile */
		.notif-preview { color: #075985; display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 3; overflow: hidden; text-overflow: ellipsis; background: transparent; padding: 0; margin: 0; }
		.notif-expand { color: #0b5ed7; display: inline-block; margin-top: 6px; background: none; border: none; padding: 0; }
		.notif-preview.expanded { display: block; -webkit-line-clamp: unset; overflow: visible; }
		/* remove boxed look and hide expand on desktop */
		.notif-card .notif-preview { background: transparent !important; padding: 0 !important; border-radius: 0 !important; }
		@media (min-width: 768px) {
			.notif-expand { display: none !important; }
			/* show full preview on desktop (no clamp) and allow wrapping */
			.notif-preview { -webkit-line-clamp: unset; display: block; overflow: visible; white-space: normal; }
		}
		/* spacing for items */
		.notif-item { margin-bottom: .85rem; }
	</style>
</head>
<body class="role-authority">
	<div class="container-fluid">
		<div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar text-white p-0">
                <?php include __DIR__ . '/_sidebar.php'; ?>
            </div>

			<!-- Main Content -->
			<div class="col-md-9 col-lg-10">
				<div class="p-4">
					<div class="d-flex justify-content-between align-items-center mb-4 notif-header gap-3">
						<div>
							<h2 class="mb-0"><i class="fas fa-bell me-2"></i>Notifications<?php echo $filter ? ' - ' . ucfirst($filter) : ''; ?></h2>
							<div class="mt-2 notif-filters">
								<ul class="nav nav-pills small">
									<li class="nav-item"><a class="nav-link<?php echo $filter ? '' : ' active'; ?>" href="notifications.php">All</a></li>
									<li class="nav-item"><a class="nav-link<?php echo $filter === 'collection' ? ' active' : ''; ?>" href="notifications.php?filter=collection">Collections</a></li>
									<li class="nav-item"><a class="nav-link<?php echo $filter === 'chat' ? ' active' : ''; ?>" href="notifications.php?filter=chat">Chat</a></li>
									<li class="nav-item"><a class="nav-link<?php echo $filter === 'report' ? ' active' : ''; ?>" href="notifications.php?filter=report">Reports</a></li>
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
							<form method="post" class="m-0" onsubmit="return confirm('Are you sure you want to delete all notifications? This action cannot be undone.');">
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
									<?php
										$cardClass = 'alert-' . ($n['type'] === 'success' ? 'success' : ($n['type'] === 'warning' ? 'warning' : ($n['type'] === 'error' ? 'danger' : 'info')));
										$isChat = (isset($n['reference_type']) && $n['reference_type'] === 'chat' && !empty($n['reference_id']));
										$chatHref = '';
										if ($isChat) {
											// Resolve resident id from chat_messages reference_id
											try {
												$stmtC = $conn->prepare("SELECT sender_id, receiver_id FROM chat_messages WHERE id = ? LIMIT 1");
												$stmtC->bind_param('i', $n['reference_id']);
												$stmtC->execute();
												$cm = $stmtC->get_result()->fetch_assoc();
												if ($cm) {
													$other = (int)$cm['sender_id'] === (int)$user_id ? (int)$cm['receiver_id'] : (int)$cm['sender_id'];
													// Only link if the other user exists and is a resident
													if ($other) {
														// If current user is an authority (this page), link to the authority dashboard chat
														// which expects ?resident=USER_ID. Otherwise fall back to global chat.php ?to=USER_ID
														if (isset($_SESSION['role']) && $_SESSION['role'] === 'authority') {
															$chatHref = BASE_URL . 'dashboard/authority/chat.php?resident=' . urlencode($other);
														} else {
															$chatHref = BASE_URL . 'chat.php?to=' . urlencode($other);
														}
													}
												}
												$stmtC->close();
											} catch (Exception $e) { /* ignore */ }
										}
										// Report link
										$isReport = (isset($n['reference_type']) && $n['reference_type'] === 'report' && !empty($n['reference_id']));
										$reportHref = $isReport ? ('reports.php?report=' . urlencode($n['reference_id'])) : '';
									?>

									<?php
										// type-based wrapper and label for consistency with resident UI
										$typeClass = 'notif-type-' . ($n['reference_type'] ?? 'other');
										$typeLabel = $n['reference_type'] ? ucfirst($n['reference_type']) : '';
									?>
									<div class="<?php echo e($typeClass); ?> notif-item">
									<?php if ($isChat && $chatHref): ?>
										<a href="<?php echo e($chatHref); ?>" class="notif-link text-decoration-none text-dark" tabindex="0">
									<?php elseif ($isReport && $reportHref): ?>
										<a href="<?php echo e($reportHref); ?>" class="notif-link text-decoration-none text-dark" tabindex="0">
									<?php endif; ?>

									<div class="alert <?php echo $cardClass; ?> notif-card d-flex gap-3 align-items-start">
										<div class="notif-icon text-center">
											<?php if ($n['reference_type'] === 'chat'): ?>
												<i class="fas fa-comments fa-lg text-info"></i>
											<?php elseif ($n['reference_type'] === 'report'): ?>
												<i class="fas fa-file-alt fa-lg text-primary"></i>
											<?php else: ?>
												<i class="fas fa-bell fa-lg text-secondary"></i>
											<?php endif; ?>
										</div>
										<div class="notif-body">
											<div class="d-flex justify-content-between align-items-start">
												<div>
													<div class="notif-title">
														<?php echo e($n['title']); ?>
														<?php if (!empty($typeLabel)): ?>
															<span class="notif-label bg-white text-muted border ms-2"><?php echo e($typeLabel); ?></span>
														<?php endif; ?>
													</div>
													<div class="notif-meta"><?php echo format_ph_date($n['created_at']); ?></div>
												</div>
												<div>
													<?php if ((int)$n['is_read'] === 0): ?><span class="badge bg-danger">New</span><?php endif; ?>
												</div>
											</div>
											<div id="notif-preview-<?php echo e($n['id']); ?>" class="mt-2 notif-preview"><?php echo e($n['message']); ?></div>
											<button type="button" class="btn btn-link btn-sm p-0 notif-expand" data-target="notif-preview-<?php echo e($n['id']); ?>">Show more</button>
										</div>
									</div>

									<?php if (($isChat && $chatHref) || ($isReport && $reportHref)): ?>
										</a>
									<?php endif; ?>
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
		// Expand/collapse long previews (works on mobile)
		document.addEventListener('click', function(e) {
			if (e.target && e.target.classList && e.target.classList.contains('notif-expand')) {
				var targetId = e.target.getAttribute('data-target');
				var el = document.getElementById(targetId);
				if (!el) return;
				if (el.classList.contains('expanded')) {
					el.classList.remove('expanded');
					e.target.textContent = 'Show more';
				} else {
					el.classList.add('expanded');
					e.target.textContent = 'Show less';
				}
			}
		});
	</script>
</body>
</html>



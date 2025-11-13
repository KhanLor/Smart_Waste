<?php
require_once __DIR__ . '/../../config/config.php';
require_login();

// Ensure user is a resident
if (($_SESSION['role'] ?? '') !== 'resident') {
	header('Location: ' . BASE_URL . 'login.php');
	exit;
}

$user_id = $_SESSION['user_id'] ?? null;

$allowed_filters = ['schedule', 'collection', 'chat', 'report', 'feedback'];
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
		/* Allow filters to scroll horizontally on small screens and show a thin scrollbar for affordance */
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
		.notif-preview { color: #07304a; line-height: 1.6; }
		/* limit preview to 3 lines */
		.notif-preview { display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 3; overflow: hidden; text-overflow: ellipsis; }
		/* small label next to title */
		.notif-label { font-size: .7rem; padding: .18rem .45rem; border-radius: 8px; vertical-align: middle; }
		.notif-meta { font-size: .85rem; color: #6c757d; }

		/* Make the whole card clickable when wrapped in an anchor */
		a.notif-link { display: block; border-radius: 14px; }
		a.notif-link .notif-card { margin: 0; }
		/* spacing between items */
		.notif-item { margin-bottom: 0.85rem; }
		/* expand toggle */
		.notif-expand { color: #0b5ed7; display: inline-block; margin-top: 6px; }
		/* when expanded, show full text */
		.notif-preview.expanded { display: block; -webkit-line-clamp: unset; overflow: visible; }
		@media (min-width: 768px) {
			/* On desktop show full preview by default and allow wrapping */
				.notif-preview { -webkit-line-clamp: unset; display: block; overflow: visible; white-space: normal; }
			.notif-expand { display: none !important; }
		}

		/* remove any unexpected boxed background from the preview area */
		.notif-card .notif-preview { background: transparent !important; padding: 0 !important; border-radius: 0 !important; margin: 0; }
		/* show the expand toggle only on small screens to reduce clutter */
		@media (min-width: 768px) {
			.notif-expand { display: none !important; }
		}

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
				.notif-card .notif-title { font-size: 1.02rem; }
				.notif-card .notif-meta { font-size: .86rem; }

				/* On small screens stack icon above text for better wrapping */
				a.notif-link .notif-card { flex-direction: row; }
				@media (max-width: 420px) {
					a.notif-link .notif-card { flex-direction: column; align-items: stretch; }
					.notif-icon { width: 48px; height: 48px; margin-bottom: 8px; }
				}
		}

		/* Notifications list container spacing */
		.notifications-list { padding-top: 6px; }

		/* Subtle card shadow for separation */
		.card .notif-card { box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04); }
		/* Type specific icon backgrounds and colors */
		.notif-type-chat .notif-icon { background: rgba(14,165,233,0.10); color: #0369a1; }
		.notif-type-report .notif-icon { background: rgba(59,130,246,0.08); color: #1e40af; }
		.notif-type-feedback .notif-icon { background: rgba(16,185,129,0.08); color: #047857; }
		.notif-type-collection .notif-icon { background: rgba(99,102,241,0.06); color: #4f46e5; }
		.notif-type-schedule .notif-icon { background: rgba(250,204,21,0.08); color: #a16207; }
		.notif-type- .notif-icon, .notif-type-other .notif-icon { background: rgba(0,0,0,0.04); color: #374151; }
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
									<li class="nav-item"><a class="nav-link<?php echo $filter === 'chat' ? ' active' : ''; ?>" href="notifications.php?filter=chat">Chat</a></li>
									<li class="nav-item"><a class="nav-link<?php echo $filter === 'report' ? ' active' : ''; ?>" href="notifications.php?filter=report">Reports</a></li>
									<li class="nav-item"><a class="nav-link<?php echo $filter === 'feedback' ? ' active' : ''; ?>" href="notifications.php?filter=feedback">Feedback</a></li>
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
							<form method="post" class="m-0" id="deleteAllForm">
								<?php if ($filter): ?><input type="hidden" name="filter" value="<?php echo e($filter); ?>"><?php endif; ?>
								<button type="button" id="deleteAllBtn" class="btn btn-sm btn-outline-danger" <?php echo $notifications->num_rows === 0 ? 'disabled' : '';?>>
									<i class="fas fa-trash me-1"></i>Delete all
								</button>
							</form>
						</div>
					</div>

					<div class="card">
						<div class="card-body">
							<?php if ($notifications->num_rows > 0): ?>
								<div class="notifications-list">
								<?php while ($n = $notifications->fetch_assoc()): ?>
									<?php
										$cardClass = 'alert-' . ($n['type'] === 'success' ? 'success' : ($n['type'] === 'warning' ? 'warning' : ($n['type'] === 'error' ? 'danger' : 'info')));
										$isChat = (isset($n['reference_type']) && $n['reference_type'] === 'chat' && !empty($n['reference_id']));
										$chatHref = '';
										if ($isChat) {
											// reference_id stores the chat_messages.id (see send logic). Resolve the other participant
											try {
												$stmtC = $conn->prepare("SELECT sender_id, receiver_id FROM chat_messages WHERE id = ? LIMIT 1");
												$stmtC->bind_param('i', $n['reference_id']);
												$stmtC->execute();
												$cm = $stmtC->get_result()->fetch_assoc();
												if ($cm) {
													$other = (int)$cm['sender_id'] === (int)$user_id ? (int)$cm['receiver_id'] : (int)$cm['sender_id'];
													if ($other) {
														// If current user is a resident (this page), link to resident dashboard chat which expects ?authority=USER_ID
														if (isset($_SESSION['role']) && $_SESSION['role'] === 'resident') {
															$chatHref = BASE_URL . 'dashboard/resident/chat.php?authority=' . urlencode($other);
														} else {
															$chatHref = BASE_URL . 'chat.php?to=' . urlencode($other);
														}
													}
												}
												$stmtC->close();
											} catch (Exception $e) { /* ignore */ }
										}
										$isReport = (isset($n['reference_type']) && $n['reference_type'] === 'report' && !empty($n['reference_id']));
										$reportHref = $isReport ? (BASE_URL . 'dashboard/resident/reports.php?report=' . urlencode($n['reference_id'])) : '';
										// Collection notifications reference a collection_history.id -> link to resident collections.
										// For "Collection Started" notifications there may be no history row yet (reference_id = 0),
										// so link to the collections page (no anchor). If a history id exists, link to the specific history anchor.
										$isCollection = (isset($n['reference_type']) && $n['reference_type'] === 'collection');
										if ($isCollection) {
											if (!empty($n['reference_id']) && intval($n['reference_id']) > 0) {
												$collectionHref = BASE_URL . 'dashboard/resident/collections.php#history-' . urlencode($n['reference_id']);
											} else {
												$collectionHref = BASE_URL . 'dashboard/resident/collections.php';
											}
										} else {
											$collectionHref = '';
										}
										// Schedule notifications reference a collection_schedules.id -> link to resident schedule page with schedule param
										$isSchedule = (isset($n['reference_type']) && $n['reference_type'] === 'schedule' && !empty($n['reference_id']));
										$scheduleHref = $isSchedule ? (BASE_URL . 'dashboard/resident/schedule.php?schedule=' . urlencode($n['reference_id'])) : '';
										$isFeedback = (isset($n['reference_type']) && $n['reference_type'] === 'feedback' && !empty($n['reference_id']));
										$feedbackHref = $isFeedback ? (BASE_URL . 'dashboard/resident/feedback.php?feedback=' . urlencode($n['reference_id'])) : '';
									?>

									<?php
										// type-based wrapper and label
										$typeClass = 'notif-type-' . ($n['reference_type'] ?? 'other');
										$typeLabel = $n['reference_type'] ? ucfirst($n['reference_type']) : '';
									?>
									<div class="<?php echo e($typeClass); ?> notif-item">
									<?php if ($isChat): ?>
										<a href="<?php echo e($chatHref); ?>" data-notif-id="<?php echo e($n['id']); ?>" class="notif-link text-decoration-none text-dark" tabindex="0">
									<?php elseif ($isReport): ?>
										<a href="<?php echo e($reportHref); ?>" data-notif-id="<?php echo e($n['id']); ?>" class="notif-link text-decoration-none text-dark" tabindex="0">
									<?php elseif ($isFeedback): ?>
										<a href="<?php echo e($feedbackHref); ?>" data-notif-id="<?php echo e($n['id']); ?>" class="notif-link text-decoration-none text-dark" tabindex="0">
									<?php elseif ($isCollection && $collectionHref): ?>
										<a href="<?php echo e($collectionHref); ?>" data-notif-id="<?php echo e($n['id']); ?>" class="notif-link text-decoration-none text-dark" tabindex="0">
									<?php elseif ($isSchedule && $scheduleHref): ?>
										<a href="<?php echo e($scheduleHref); ?>" data-notif-id="<?php echo e($n['id']); ?>" class="notif-link text-decoration-none text-dark" tabindex="0">
									<?php endif; ?>

									<div class="alert notif-card <?php echo $cardClass; ?>">
										<div class="notif-icon text-center me-2">
											<?php if ($n['reference_type'] === 'chat'): ?>
												<i class="fas fa-comments fa-lg text-info"></i>
											<?php elseif ($n['reference_type'] === 'report'): ?>
												<i class="fas fa-file-alt fa-lg text-primary"></i>
											<?php elseif ($n['reference_type'] === 'feedback'): ?>
												<i class="fas fa-comment-dots fa-lg text-success"></i>
											<?php elseif ($n['reference_type'] === 'schedule'): ?>
												<i class="fas fa-calendar-alt fa-lg text-warning"></i>
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
												<div class="d-flex align-items-start">
													<div class="me-2">
														<?php if ((int)$n['is_read'] === 0): ?><span class="badge bg-danger">New</span><?php endif; ?>
													</div>
													<div>
														<!-- Delete single notification button -->
														<button type="button" class="btn btn-sm btn-outline-secondary delete-notif-btn" data-notif-id="<?php echo e($n['id']); ?>" title="Delete notification">
															<i class="fas fa-trash"></i>
														</button>
													</div>
												</div>
											</div>
											<div id="notif-preview-<?php echo e($n['id']); ?>" class="mt-2 notif-preview"><?php echo e($n['message']); ?></div>
											<button type="button" class="btn btn-link btn-sm p-0 notif-expand" data-target="notif-preview-<?php echo e($n['id']); ?>">Show more</button>
										</div>
									</div>

									<?php if ($isChat || $isReport || $isFeedback || $isCollection || $isSchedule): ?>
										</a>
									<?php endif; ?>
									</div>
								<?php endwhile; ?>
								</div>
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

	<!-- Confirmation modal for single delete -->
	<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="confirmDeleteModalLabel">Delete notification</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					Are you sure you want to delete this notification?
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="button" id="confirmDeleteOk" class="btn btn-danger">Delete</button>
				</div>
			</div>
		</div>
	</div>

	<script>
	// Delete single notification via API using a Bootstrap modal confirmation
	(function(){
		var deleteModalEl = document.getElementById('confirmDeleteModal');
		var deleteModal = deleteModalEl ? new bootstrap.Modal(deleteModalEl) : null;
		var pendingButton = null;
		var pendingNid = null;

		// Open modal when delete button clicked
		document.addEventListener('click', function(e){
			var delBtn = e.target.closest && e.target.closest('.delete-notif-btn');
			if (!delBtn) return;
			// Prevent navigation if inside link
			if (e.preventDefault) e.preventDefault();
			if (e.stopImmediatePropagation) e.stopImmediatePropagation();
			pendingButton = delBtn;
			pendingNid = delBtn.getAttribute('data-notif-id');
			if (!pendingNid) return;
			if (deleteModal) deleteModal.show();
		});

		// Handle confirm in modal
		var okBtn = document.getElementById('confirmDeleteOk');
		if (okBtn) okBtn.addEventListener('click', function(){
			if (!pendingButton || !pendingNid) {
				if (deleteModal) deleteModal.hide();
				return;
			}
			var btn = pendingButton;
			var nid = pendingNid;
			btn.disabled = true;
			if (deleteModal) deleteModal.hide();
			fetch('<?php echo BASE_URL; ?>api/delete_notification.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ notification_id: parseInt(nid, 10) })
			}).then(r => r.json()).then(data => {
				if (data && data.success && data.deleted > 0) {
					var item = btn.closest('.notif-item');
					if (item) item.remove();
				} else {
					btn.disabled = false;
					alert('Failed to delete notification');
				}
			}).catch(err => { console.error(err); btn.disabled = false; alert('Error deleting notification'); });

			// clear pending
			pendingButton = null;
			pendingNid = null;
		});
	})();
	</script>

	<!-- Confirmation modal for delete all -->
	<div class="modal fade" id="confirmDeleteAllModal" tabindex="-1" aria-labelledby="confirmDeleteAllModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="confirmDeleteAllModalLabel">Delete all notifications</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					Are you sure you want to delete all notifications? This action cannot be undone.
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="button" id="confirmDeleteAllOk" class="btn btn-danger">Delete</button>
				</div>
			</div>
		</div>
	</div>

	<script>
	(function(){
		var delAllBtn = document.getElementById('deleteAllBtn');
		var delAllForm = document.getElementById('deleteAllForm');
		var modalEl = document.getElementById('confirmDeleteAllModal');
		var modal = modalEl ? new bootstrap.Modal(modalEl) : null;
		var confirmBtn = document.getElementById('confirmDeleteAllOk');
		if (!delAllBtn || !delAllForm || !modal || !confirmBtn) return;

		delAllBtn.addEventListener('click', function(e){
			e.preventDefault();
			modal.show();
		});

		confirmBtn.addEventListener('click', function(){
			var inp = delAllForm.querySelector('input[name="delete_all"]');
			if (!inp) {
				inp = document.createElement('input');
				inp.type = 'hidden';
				inp.name = 'delete_all';
				inp.value = '1';
				delAllForm.appendChild(inp);
			} else {
				inp.value = '1';
			}
			modal.hide();
			delAllForm.submit();
		});
	})();
	</script>

	<script>
	// Intercept clicks on notification links and mark the notification read via API before navigating.
	(function(){
		async function markAndNavigate(e, anchor) {
			e.preventDefault();
			const nid = anchor.getAttribute('data-notif-id');
			const href = anchor.getAttribute('href');
			if (!nid) { window.location = href; return; }
			try {
				await fetch('<?php echo BASE_URL; ?>api/mark_notification_read.php', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ notification_id: parseInt(nid, 10) })
				});
			} catch (err) {
				// ignore errors, still navigate
				console.error('Failed to mark notification read', err);
			}
			// Navigate after attempting to mark as read
			window.location = href;
		}

		document.addEventListener('click', function(ev){
			const a = ev.target.closest && ev.target.closest('a.notif-link');
			if (!a) return;
			// only intercept internal links (same origin) to avoid cross-origin issues
			const href = a.getAttribute('href');
			if (!href) return;
			// If href starts with http and is different origin, don't intercept
			try {
				const u = new URL(href, window.location.href);
				if (u.origin !== window.location.origin) return;
			} catch (e) { /* ignore if invalid URL */ }
			markAndNavigate(ev, a);
		});
	})();
	</script>
</body>
</html>



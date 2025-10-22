<?php
// Reusable resident sidebar. Call include from resident pages.
if (!isset($_SESSION)) { session_start(); }

// Ensure role is resident; pages already gate this, but sidebar is role-specific.
$role = $_SESSION['role'] ?? '';

// Determine current filename (for active link highlighting)
$currentFile = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// A helper to mark active links based on current file name
function rs_active(string $file, string $current): string {
    return $current === $file ? ' active' : '';
}

// Compute unread notification counts for specific sidebar items (schedule and collections)
$schedule_unread = 0;
$collections_unread = 0;
$reports_unread = 0;
$schedule_preview = [];
$chat_unread = 0;
$chat_preview = [];
$user_id = $_SESSION['user_id'] ?? null;
if (!empty($user_id) && isset($conn)) {
	// unread schedule notifications
	$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0 AND reference_type = 'schedule'");
	if ($stmt) {
		$stmt->bind_param('i', $user_id);
		$stmt->execute();
		$row = $stmt->get_result()->fetch_assoc();
		$schedule_unread = (int)($row['cnt'] ?? 0);
		$stmt->close();
	}

	// latest schedule notifications for sidebar preview
	$stmt = $conn->prepare("SELECT id, title, message, is_read, created_at FROM notifications WHERE user_id = ? AND reference_type = 'schedule' ORDER BY is_read ASC, created_at DESC LIMIT 5");
	if ($stmt) {
		$stmt->bind_param('i', $user_id);
		$stmt->execute();
		$res = $stmt->get_result();
		while ($r = $res->fetch_assoc()) { $schedule_preview[] = $r; }
		$stmt->close();
	}

	// unread collection notifications
	$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0 AND reference_type = 'collection'");
	if ($stmt) {
		$stmt->bind_param('i', $user_id);
		$stmt->execute();
		$row = $stmt->get_result()->fetch_assoc();
		$collections_unread = (int)($row['cnt'] ?? 0);
		$stmt->close();
	}

	// unread report notifications
	$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0 AND reference_type = 'report'");
	if ($stmt) {
		$stmt->bind_param('i', $user_id);
		$stmt->execute();
		$row = $stmt->get_result()->fetch_assoc();
		$reports_unread = (int)($row['cnt'] ?? 0);
		$stmt->close();
	}
}
?>

<style>
    /* Ensure sidebar is clickable above overlapping content and consistent link backgrounds */
	/* Raise z-index so sidebar dropdowns appear above map/Leaflet controls */
	.sidebar { position: relative; z-index: 2001; }
    .sidebar .nav-link { background: transparent; }
    .sidebar .nav-link.active, .sidebar .nav-link:hover { background-color: rgba(255,255,255,0.2); }
    /* Mobile off-canvas sidebar */
    @media (max-width: 991.98px) {
	.sidebar { z-index: 2050; position: fixed; top: 0; bottom: 0; left: -280px; width: 260px; max-width: 80%; overflow-y: auto; transition: left 0.25s ease-in-out; }
        body.sidebar-open .sidebar { left: 0; }
        .sidebar-overlay { content: ""; position: fixed; inset: 0; background: rgba(0,0,0,0.45); opacity: 0; visibility: hidden; transition: opacity 0.2s ease-in-out, visibility 0.2s ease-in-out; z-index: 1040; }
        body.sidebar-open .sidebar-overlay { opacity: 1; visibility: visible; }
        /* Default style for toggle when placed inside the header */
        .sidebar-toggle-btn { border: none; background: #20c997; color: #fff; width: 28px; height: 28px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 2px 6px rgba(0,0,0,0.15); padding: 0; margin-right: 10px; }
        .sidebar-toggle-btn .bar { display: block; width: 16px; height: 2px; background: #fff; margin: 2px 0; border-radius: 1px; }
        /* Fallback style when header not found: fix to top-left but keep small and padded */
        .sidebar-toggle-btn.fixed { position: fixed; top: 10px; left: 10px; z-index: 1100; }
        /* Ensure page content below header has breathing room */
        .p-4 > .d-flex.justify-content-between.align-items-center { gap: 8px; }
    }
 </style>
<style>
	/* Notification preview styles */
	.notification-badge { display: inline-flex; align-items: center; justify-content: center; background: #dc3545; color: #fff; border-radius: 999px; padding: 0 7px; font-size: 12px; line-height: 1; height: 20px; min-width: 20px; margin-left:8px; }
	.sidebar .nav-item { position: relative; }
	.badge-container { position: relative; }
    #sidebarNotificationsPreview, #sidebarSchedulePreview, #sidebarChatPreview {
		display: none;
		position: absolute;
		left: 0; /* anchor inside sidebar to avoid overlapping main content */
		top: 48px; /* appear below the notifications link */
		width: 280px; /* slightly wider for clarity */
		max-height: 60vh;
		overflow-y: auto;
		/* ensure above leaflet map controls and most UI layers */
		z-index: 3000;
		box-shadow: 0 8px 24px rgba(0,0,0,0.12);
		border-radius: 8px;
	}
    #sidebarNotificationsPreview .card-body, #sidebarSchedulePreview .card-body, #sidebarChatPreview .card-body { padding: 0.5rem; }
	/* Header */
	.preview-header { display:flex; align-items:center; justify-content: space-between; padding: 0.25rem 0.25rem 0.5rem; border-bottom: 1px solid #e9ecef; margin-bottom: 0.5rem; }
	.preview-header .title { display:flex; align-items:center; gap: 8px; font-weight: 600; }
	.preview-header .title i { color:#198754; }
	/* Items */
	#sidebarNotificationsPreview .notif-item, #sidebarSchedulePreview .notif-item, #sidebarChatPreview .notif-item { padding: 0.6rem; border-radius: 8px; margin-bottom: 0.45rem; border: 1px solid #eef1f4; background: #fff; }
	#sidebarSchedulePreview .notif-item.unread { background: #fff9e6; border-color: #ffe8a1; }
	#sidebarNotificationsPreview .notif-item.unread { background: #eef7ff; border-color: #cfe2ff; }
	#sidebarChatPreview .notif-item.unread { background: #e6fff4; border-color: #b6f2d8; }
	#sidebarNotificationsPreview .notif-item.read, #sidebarSchedulePreview .notif-item.read, #sidebarChatPreview .notif-item.read { background: #f9fafb; }
	.notif-row { display:flex; align-items:flex-start; gap:10px; }
	.notif-icon { color:#0d6efd; margin-top:2px; }
	.notif-main { flex:1 1 auto; min-width:0; }
	.notif-title { font-size:0.95rem; font-weight:700; color:#212529; margin:0; }
	.notif-meta { font-size:0.78rem; color:#6c757d; margin-top:2px; }
	.notif-text { font-size:0.86rem; color:#343a40; margin-top:6px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
	.notif-dot { width:8px; height:8px; border-radius:999px; background:#dc3545; margin-left:6px; margin-top:4px; flex:0 0 8px; visibility:hidden; }
	.unread .notif-dot { visibility:visible; }
	/* Footer CTA */
	.preview-footer { padding-top: 0.25rem; border-top: 1px solid #e9ecef; margin-top: 0.5rem; display:flex; justify-content:center; }
	.preview-footer .btn { min-width: 90%; }
	/* ensure anchor is the stacking context for the badge and align items consistently */
	 /* Keep link contents aligned to the left; badges are absolutely positioned to the right
		 so links should use flex-start. nav-left reserves space for the icon+label and
		 truncates long labels while allowing the badge to remain visible. */
	 .nav-link { position: relative; display: flex; align-items: center; justify-content: flex-start; min-height: 44px; padding-top: 0.5rem; padding-bottom: 0.5rem; }
	 .nav-left { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; max-width: 180px; flex: 1 1 auto; min-width: 0; }
	.badge-container { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); display: inline-flex; align-items: center; gap: 8px; cursor: pointer; }
	.badge-container .fa-chevron-down { margin-left: 4px; }
	.notification-badge, .badge-container .fa-chevron-down { touch-action: manipulation; }
	/* Simple badge for nav items without dropdown */
	.nav-link > .badge-container:not(:has(.fa-chevron-down)) { pointer-events: none; }
	@media (max-width: 991.98px) {
		.badge-container { padding: 6px; border-radius: 6px; }
	}
    @media (max-width: 991.98px) {
		#sidebarNotificationsPreview, #sidebarSchedulePreview, #sidebarChatPreview { left: 0; top: 52px; width: 100%; position: static; box-shadow: none; max-height: 40vh; }
	}
</style>

<div class="p-3">
    <h4 class="mb-4"><i class="fas fa-recycle me-2"></i><?php echo APP_NAME; ?></h4>
    <hr class="bg-white">
    <nav class="nav flex-column">
        <a class="nav-link text-white<?php echo rs_active('index.php', $currentFile); ?>" href="index.php">
            <i class="fas fa-home me-2"></i>Dashboard
        </a>
        <a class="nav-link text-white<?php echo rs_active('reports.php', $currentFile); ?>" href="reports.php">
			<span class="nav-left"><i class="fas fa-exclamation-circle me-2"></i>My Reports</span>
			<?php if ($reports_unread > 0): ?>
				<span class="badge-container d-flex align-items-center gap-2">
					<span class="notification-badge"><?php echo $reports_unread > 99 ? '99+' : $reports_unread; ?></span>
				</span>
			<?php endif; ?>
        </a>
        <a class="nav-link text-white<?php echo rs_active('submit_report.php', $currentFile); ?>" href="submit_report.php">
            <i class="fas fa-plus-circle me-2"></i>Submit Report
        </a>
		<div class="nav-item position-relative">
			<a class="nav-link text-white<?php echo rs_active('schedule.php', $currentFile); ?>" href="schedule.php" id="sidebarScheduleToggle">
				<span class="nav-left"><i class="fas fa-calendar me-2"></i>Collection Schedule</span>
				<span class="badge-container d-flex align-items-center gap-2">
					<?php if ($schedule_unread > 0): ?>
						<span class="notification-badge"><?php echo $schedule_unread > 99 ? '99+' : $schedule_unread; ?></span>
					<?php endif; ?>
					<i class="fas fa-chevron-down small"></i>
				</span>
			</a>

			<!-- Schedule preview dropdown -->
			<div id="sidebarSchedulePreview" class="card bg-white text-dark">
				<div class="card-body p-2">
					<div class="preview-header">
						<div class="title"><i class="fas fa-calendar-check"></i><span>Schedule updates</span></div>
					</div>
					<?php if (count($schedule_preview) === 0): ?>
						<div class="text-center text-muted py-3">No schedule notifications</div>
					<?php else: ?>
						<?php foreach ($schedule_preview as $n): ?>
							<?php $readClass = $n['is_read'] ? 'read' : 'unread'; ?>
							<div class="notif-item <?php echo $readClass; ?>">
								<div class="notif-row">
									<i class="fas fa-calendar-day notif-icon"></i>
									<div class="notif-main">
										<p class="notif-title mb-0"><?php echo e($n['title']); ?></p>
										<div class="notif-meta"><?php echo format_ph_date($n['created_at']); ?></div>
										<div class="notif-text"><?php echo e($n['message']); ?></div>
									</div>
									<span class="notif-dot" aria-hidden="true"></span>
								</div>
							</div>
						<?php endforeach; ?>
						<div class="preview-footer">
							<a href="notifications.php?filter=schedule" class="btn btn-sm btn-outline-primary">View all schedule updates</a>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<a class="nav-link text-white<?php echo rs_active('collections.php', $currentFile); ?>" href="collections.php">
			<span class="nav-left"><i class="fas fa-history me-2"></i>Recent Collections</span>
			<span class="badge-container d-flex align-items-center gap-2">
				<?php if ($collections_unread > 0): ?>
					<span class="notification-badge"><?php echo $collections_unread > 99 ? '99+' : $collections_unread; ?></span>
				<?php endif; ?>
			</span>
		</a>
        <a class="nav-link text-white<?php echo rs_active('points.php', $currentFile); ?>" href="points.php">
            <i class="fas fa-leaf me-2"></i>Eco Points
        </a>
        <a class="nav-link text-white<?php echo rs_active('feedback.php', $currentFile); ?>" href="feedback.php">
            <i class="fas fa-comment me-2"></i>Feedback
        </a>
		<?php
		// Fetch unread count and latest notifications for preview
		$notif_unread = 0;
		$notif_preview = [];
		if (!empty($user_id) && isset($conn)) {
			$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0");
			if ($stmt) {
				$stmt->bind_param('i', $user_id);
				$stmt->execute();
				$row = $stmt->get_result()->fetch_assoc();
				$notif_unread = (int)($row['cnt'] ?? 0);
				$stmt->close();
			}

			$limit = 5;
			$stmt = $conn->prepare("SELECT id, title, message, type, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY is_read ASC, created_at DESC LIMIT ?");
			if ($stmt) {
				$stmt->bind_param('ii', $user_id, $limit);
				$stmt->execute();
				$res = $stmt->get_result();
				while ($r = $res->fetch_assoc()) { $notif_preview[] = $r; }
				$stmt->close();
			}
		}
		?>

		<div class="nav-item position-relative">
			<a class="nav-link text-white<?php echo rs_active('notifications.php', $currentFile); ?>" href="notifications.php" id="sidebarNotificationsToggle">
				<span class="nav-left"><i class="fas fa-bell me-2"></i>Notifications</span>
				<span class="badge-container d-flex align-items-center gap-2">
					<?php if ($notif_unread > 0): ?>
						<span class="notification-badge"><?php echo $notif_unread > 99 ? '99+' : $notif_unread; ?></span>
					<?php endif; ?>
					<i class="fas fa-chevron-down small"></i>
				</span>
			</a>

			<!-- Preview dropdown -->
			<div id="sidebarNotificationsPreview" class="card bg-white text-dark">
				<div class="card-body p-2">
					<h6 class="mb-2">Recent Notifications</h6>
					<?php if (count($notif_preview) === 0): ?>
						<div class="text-center text-muted py-3">No notifications</div>
					<?php else: ?>
						<?php foreach ($notif_preview as $n): ?>
							<?php $readClass = $n['is_read'] ? 'read' : 'unread'; ?>
							<div class="notif-item <?php echo $readClass; ?>">
								<div>
									<strong style="font-size:0.92rem"><?php echo e($n['title']); ?></strong>
									<div class="small text-muted"><?php echo format_ph_date($n['created_at']); ?></div>
									<div class="small text-truncate mt-1" style="max-width:220px"><?php echo e($n['message']); ?></div>
								</div>
							</div>
						<?php endforeach; ?>
						<div class="text-center mt-2">
							<a href="notifications.php" class="btn btn-sm btn-outline-primary">View all</a>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
		// Compute unread chat messages and preview
		if (!empty($user_id) && isset($conn)) {
			// unread chat count
			$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM chat_messages WHERE receiver_id = ? AND is_read = 0");
			if ($stmt) {
				$stmt->bind_param('i', $user_id);
				$stmt->execute();
				$row = $stmt->get_result()->fetch_assoc();
				$chat_unread = (int)($row['cnt'] ?? 0);
				$stmt->close();
			}

			// latest chat messages for preview (show most recent, unread first)
			$stmt = $conn->prepare("SELECT cm.id, cm.message, cm.is_read, cm.created_at, u.first_name, u.last_name FROM chat_messages cm JOIN users u ON cm.sender_id = u.id WHERE cm.receiver_id = ? ORDER BY cm.is_read ASC, cm.created_at DESC LIMIT 5");
			if ($stmt) {
				$stmt->bind_param('i', $user_id);
				$stmt->execute();
				$res = $stmt->get_result();
				while ($r = $res->fetch_assoc()) { $chat_preview[] = $r; }
				$stmt->close();
			}
		}
		?>

		<div class="nav-item position-relative">
			<a class="nav-link text-white<?php echo rs_active('chat.php', $currentFile); ?>" href="chat.php" id="sidebarChatToggle">
				<span class="nav-left"><i class="fas fa-comments me-2"></i>Chat</span>
				<span class="badge-container d-flex align-items-center gap-2">
					<?php if ($chat_unread > 0): ?>
						<span class="notification-badge"><?php echo $chat_unread > 99 ? '99+' : $chat_unread; ?></span>
					<?php endif; ?>
					<i class="fas fa-chevron-down small"></i>
				</span>
			</a>

			<!-- Chat preview dropdown -->
			<div id="sidebarChatPreview" class="card bg-white text-dark">
				<div class="card-body p-2">
					<div class="preview-header">
						<div class="title"><i class="fas fa-message"></i><span>Chat messages</span></div>
					</div>
					<?php if (count($chat_preview) === 0): ?>
						<div class="text-center text-muted py-3">No messages yet</div>
					<?php else: ?>
						<?php foreach ($chat_preview as $m): ?>
							<?php $readClass = $m['is_read'] ? 'read' : 'unread'; ?>
							<div class="notif-item <?php echo $readClass; ?>">
								<div class="notif-row">
									<i class="fas fa-user-circle notif-icon"></i>
									<div class="notif-main">
										<p class="notif-title mb-0"><?php echo e(trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')) ?: 'Authority'); ?></p>
										<div class="notif-meta"><?php echo format_ph_date($m['created_at']); ?></div>
										<div class="notif-text"><?php echo e($m['message']); ?></div>
									</div>
									<span class="notif-dot" aria-hidden="true"></span>
								</div>
							</div>
						<?php endforeach; ?>
						<div class="preview-footer">
							<a href="chat.php" class="btn btn-sm btn-outline-primary">Open chat</a>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
        <a class="nav-link text-white<?php echo rs_active('profile.php', $currentFile); ?>" href="profile.php">
            <i class="fas fa-user me-2"></i>Profile
        </a>
        <hr class="bg-white">
        <a class="nav-link text-white" href="../../logout.php">
            <i class="fas fa-sign-out-alt me-2"></i>Logout
        </a>
    </nav>
</div>

<script>
// Sidebar mobile toggle (scoped to resident pages)
(function(){
	try {
		var DOC = document;
		var body = DOC.body;
		// Ensure overlay exists
		var overlay = DOC.querySelector('.sidebar-overlay');
		if (!overlay) {
			overlay = DOC.createElement('div');
			overlay.className = 'sidebar-overlay';
			body.appendChild(overlay);
		}

		// Inject toggle button on mobile
		var toggleBtn = DOC.getElementById('sidebarToggleBtn');
		if (!toggleBtn) {
			toggleBtn = DOC.createElement('button');
			toggleBtn.id = 'sidebarToggleBtn';
			toggleBtn.className = 'sidebar-toggle-btn d-lg-none';
			toggleBtn.setAttribute('aria-label', 'Open menu');
			toggleBtn.setAttribute('aria-expanded', 'false');
			toggleBtn.setAttribute('aria-controls', 'residentSidebar');
			// simple hamburger icon
			toggleBtn.innerHTML = '<span class="bar"></span><span class="bar"></span><span class="bar"></span>';
			// Try to place inside the first page header action row if available
			var headerRow = DOC.querySelector('.p-4 .d-flex.justify-content-between.align-items-center');
			if (headerRow) {
				headerRow.insertBefore(toggleBtn, headerRow.firstChild);
			} else {
				// Fallback: attach to body but keep small and padded
				body.appendChild(toggleBtn);
				toggleBtn.classList.add('fixed');
			}
		}

		// Mark sidebar element with id for aria-controls if not present
		var sidebars = DOC.querySelectorAll('.sidebar');
		var sidebarEl = sidebars && sidebars.length ? sidebars[0] : null;
		if (sidebarEl && !sidebarEl.id) sidebarEl.id = 'residentSidebar';

		function openSidebar(){
			if (!body.classList.contains('sidebar-open')) {
				body.classList.add('sidebar-open');
				toggleBtn.setAttribute('aria-expanded', 'true');
				// focus first link for accessibility
				try { var firstLink = sidebarEl ? sidebarEl.querySelector('.nav-link, a, button, [tabindex]') : null; if (firstLink) firstLink.focus(); } catch (e) {}
			}
		}
		function closeSidebar(){
			if (body.classList.contains('sidebar-open')) {
				body.classList.remove('sidebar-open');
				toggleBtn.setAttribute('aria-expanded', 'false');
			}
		}
		function toggleSidebar(){
			if (body.classList.contains('sidebar-open')) closeSidebar(); else openSidebar();
		}

		// Wire events
		toggleBtn.addEventListener('click', toggleSidebar);
		overlay.addEventListener('click', closeSidebar);
		DOC.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeSidebar(); });

		// Close on link click (improves UX on navigation)
		if (sidebarEl) {
			sidebarEl.addEventListener('click', function(e){
				var t = e.target;
				if (t && t.closest('a.nav-link')) closeSidebar();
			});
		}
	} catch (err) { /* noop */ }
})();
</script>
<script>
// Toggle notifications preview in the sidebar
(function(){
	try {
		var toggle = document.getElementById('sidebarNotificationsToggle');
		var preview = document.getElementById('sidebarNotificationsPreview');
		if (!toggle || !preview) return;

		function hidePreview() { preview.style.display = 'none'; }
		function showPreview() { preview.style.display = 'block'; }
		function togglePreview(e) { e.preventDefault(); if (preview.style.display === 'block') hidePreview(); else showPreview(); }

		// Only toggle preview when clicking the chevron or the badge; otherwise allow normal link navigation (fixes mobile behavior)
		toggle.addEventListener('click', function(e){
			try {
				var clickedChevron = e.target.closest && e.target.closest('.fa-chevron-down');
				var clickedBadge = e.target.closest && e.target.closest('.notification-badge');
				if (clickedChevron || clickedBadge) {
					e.preventDefault();
					togglePreview(e);
				} else {
					// allow default navigation when clicking the label (important for mobile)
					return true;
				}
			} catch (err) { /* fallback to toggle */ togglePreview(e); }
		});

		// Close when clicking outside
		document.addEventListener('click', function(e){
			var t = e.target;
			if (!t.closest) return;
			if (!t.closest('#sidebarNotificationsPreview') && !t.closest('#sidebarNotificationsToggle')) {
				hidePreview();
			}
		});

		// Hide on resize for safety
		window.addEventListener('resize', hidePreview);
	} catch (err) {}
})();
</script>
<script>
// Toggle schedule notifications preview in the sidebar
(function(){
	try {
		var toggle = document.getElementById('sidebarScheduleToggle');
		var preview = document.getElementById('sidebarSchedulePreview');
		if (!toggle || !preview) return;

		function hidePreview() { preview.style.display = 'none'; }
		function showPreview() { preview.style.display = 'block'; }
		function togglePreview(e) { e.preventDefault(); if (preview.style.display === 'block') hidePreview(); else showPreview(); }

		// Only toggle preview when clicking the chevron or the badge; otherwise allow normal link navigation
		toggle.addEventListener('click', function(e){
			try {
				var clickedChevron = e.target.closest && e.target.closest('.fa-chevron-down');
				var clickedBadge = e.target.closest && e.target.closest('.notification-badge');
				if (clickedChevron || clickedBadge) {
					e.preventDefault();
					togglePreview(e);
				} else {
					return true;
				}
			} catch (err) { togglePreview(e); }
		});

		// Close when clicking outside
		document.addEventListener('click', function(e){
			var t = e.target;
			if (!t.closest) return;
			if (!t.closest('#sidebarSchedulePreview') && !t.closest('#sidebarScheduleToggle')) {
				hidePreview();
			}
		});

		// Hide on resize for safety
		window.addEventListener('resize', hidePreview);
	} catch (err) {}
})();
</script>
<script>
// Toggle chat preview in the sidebar
(function(){
	try {
		var toggle = document.getElementById('sidebarChatToggle');
		var preview = document.getElementById('sidebarChatPreview');
		if (!toggle || !preview) return;

		function hidePreview() { preview.style.display = 'none'; }
		function showPreview() { preview.style.display = 'block'; }
		function togglePreview(e) { e.preventDefault(); if (preview.style.display === 'block') hidePreview(); else showPreview(); }

		// Only toggle when clicking the chevron or the badge; allow normal navigation otherwise
		toggle.addEventListener('click', function(e){
			try {
				var clickedChevron = e.target.closest && e.target.closest('.fa-chevron-down');
				var clickedBadge = e.target.closest && e.target.closest('.notification-badge');
				if (clickedChevron || clickedBadge) {
					e.preventDefault();
					togglePreview(e);
				} else {
					return true;
				}
			} catch (err) { togglePreview(e); }
		});

		// Close when clicking outside
		document.addEventListener('click', function(e){
			var t = e.target;
			if (!t.closest) return;
			if (!t.closest('#sidebarChatPreview') && !t.closest('#sidebarChatToggle')) {
				hidePreview();
			}
		});

		// Hide on resize for safety
		window.addEventListener('resize', hidePreview);
	} catch (err) {}
})();
</script>

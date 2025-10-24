<?php
if (!isset($_SESSION)) { session_start(); }

// Determine current filename (for active link highlighting)
$currentFile = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if (!function_exists('auth_active')) {
	function auth_active(string $file, string $current): string {
		return $current === $file ? ' active' : '';
	}
}
?>

<style>
	.sidebar { position: relative; z-index: 3; }
	.sidebar .nav-link { background: transparent; position: relative; display: flex; align-items: center; justify-content: flex-start; min-height: 44px; padding-top: 0.5rem; padding-bottom: 0.5rem; }
	.sidebar .nav-link.active, .sidebar .nav-link:hover { background-color: rgba(255,255,255,0.2); }
	/* Badge + preview shared styles */
	.notification-badge { display: inline-flex; align-items: center; justify-content: center; background: #dc3545; color: #fff; border-radius: 999px; padding: 0 7px; font-size: 12px; line-height: 1; height: 20px; min-width: 20px; margin-left:8px; }
	.nav-item { position: relative; }
	.nav-left { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; max-width: 180px; flex: 1 1 auto; min-width: 0; }
	.badge-container { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); display: inline-flex; align-items: center; gap: 8px; cursor: pointer; }
	.badge-container .fa-chevron-down { margin-left: 4px; }
	.notification-badge, .badge-container .fa-chevron-down { touch-action: manipulation; }
	/* Simple badge for nav items without dropdown */
	.nav-link > .badge-container:not(:has(.fa-chevron-down)) { pointer-events: none; }
	#authNotificationsPreview, #authChatPreview { display:none; position:absolute; left:0; top:48px; width:280px; max-height:60vh; overflow-y:auto; z-index:3000; box-shadow:0 8px 24px rgba(0,0,0,0.12); border-radius:8px; }
	#authNotificationsPreview .card-body, #authChatPreview .card-body { padding: 0.5rem; }
	.preview-header { display:flex; align-items:center; justify-content: space-between; padding: 0.25rem 0.25rem 0.5rem; border-bottom: 1px solid #e9ecef; margin-bottom: 0.5rem; }
	.preview-header .title { display:flex; align-items:center; gap: 8px; font-weight: 600; }
	.preview-header .title i { color:#dc3545; }
	.notif-item { padding: 0.6rem; border-radius: 8px; margin-bottom: 0.45rem; border: 1px solid #eef1f4; background: #fff; }
	.notif-item.unread { background: #eef7ff; border-color: #cfe2ff; }
	.notif-item.read { background: #f9fafb; }
	.notif-row { display:flex; align-items:flex-start; gap:10px; }
	.notif-icon { color:#0d6efd; margin-top:2px; }
	.notif-main { flex:1 1 auto; min-width:0; }
	.notif-title { font-size:0.95rem; font-weight:700; color:#212529; margin:0; }
	.notif-meta { font-size:0.78rem; color:#6c757d; margin-top:2px; }
	.notif-text { font-size:0.86rem; color:#343a40; margin-top:6px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
	.notif-dot { width:8px; height:8px; border-radius:999px; background:#dc3545; margin-left:6px; margin-top:4px; flex:0 0 8px; visibility:hidden; }
	.unread .notif-dot { visibility:visible; }
	.preview-footer { padding-top: 0.25rem; border-top: 1px solid #e9ecef; margin-top: 0.5rem; display:flex; justify-content:center; }
	.preview-footer .btn { min-width: 90%; }
	@media (max-width: 991.98px) {
		.sidebar { z-index: 1050; position: fixed; top: 0; bottom: 0; left: -280px; width: 260px; max-width: 80%; overflow-y: auto; transition: left 0.25s ease-in-out; }
		body.sidebar-open .sidebar { left: 0; }
		.sidebar-overlay { content: ""; position: fixed; inset: 0; background: rgba(0,0,0,0.45); opacity: 0; visibility: hidden; transition: opacity 0.2s ease-in-out, visibility 0.2s ease-in-out; z-index: 1040; }
		body.sidebar-open .sidebar-overlay { opacity: 1; visibility: visible; }
		.sidebar-toggle-btn { border: none; background: #8B7E74; color: #fff; width: 28px; height: 28px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 2px 6px rgba(0,0,0,0.15); padding: 0; margin-right: 10px; }
		.sidebar-toggle-btn .bar { display: block; width: 16px; height: 2px; background: #fff; margin: 2px 0; border-radius: 1px; }
		.sidebar-toggle-btn.fixed { position: fixed; top: 10px; left: 10px; z-index: 1100; }
		.p-4 > .d-flex.justify-content-between.align-items-center { gap: 8px; }
		.badge-container { padding: 6px; border-radius: 6px; }
		#authNotificationsPreview, #authChatPreview { left:0; top:52px; width:100%; position: static; box-shadow:none; max-height:40vh; }
	}
</style>

<?php
// Compute unread counts and previews for authority
$auth_user_id = $_SESSION['user_id'] ?? null;
$auth_notif_unread = 0; $auth_notif_preview = [];
$auth_chat_unread = 0; $auth_chat_preview = [];
$auth_reports_unread = 0;
$auth_collection_unread = 0;
if (!empty($auth_user_id) && isset($conn)) {
    // Notifications
    if ($stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0")) {
        $stmt->bind_param('i', $auth_user_id); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); $auth_notif_unread = (int)($row['cnt'] ?? 0); $stmt->close();
    }
    if ($stmt = $conn->prepare("SELECT id, title, message, type, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY is_read ASC, created_at DESC LIMIT 5")) {
        $stmt->bind_param('i', $auth_user_id); $stmt->execute(); $res = $stmt->get_result(); while ($r = $res->fetch_assoc()) { $auth_notif_preview[] = $r; } $stmt->close();
    }
    // Chat
    if ($stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM chat_messages WHERE receiver_id = ? AND is_read = 0")) {
        $stmt->bind_param('i', $auth_user_id); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); $auth_chat_unread = (int)($row['cnt'] ?? 0); $stmt->close();
    }
    if ($stmt = $conn->prepare("SELECT cm.id, cm.message, cm.is_read, cm.created_at, u.first_name, u.last_name FROM chat_messages cm JOIN users u ON cm.sender_id = u.id WHERE cm.receiver_id = ? ORDER BY cm.is_read ASC, cm.created_at DESC LIMIT 5")) {
        $stmt->bind_param('i', $auth_user_id); $stmt->execute(); $res = $stmt->get_result(); while ($r = $res->fetch_assoc()) { $auth_chat_preview[] = $r; } $stmt->close();
    }
    // Unread report notifications
    if ($stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0 AND reference_type = 'report'")) {
        $stmt->bind_param('i', $auth_user_id); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); $auth_reports_unread = (int)($row['cnt'] ?? 0); $stmt->close();
    }
	// Unread collection notifications (collector updates)
	if ($stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0 AND reference_type = 'collection'")) {
		$stmt->bind_param('i', $auth_user_id); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); $auth_collection_unread = (int)($row['cnt'] ?? 0); $stmt->close();
	}
}
?>

<div class="p-3">
	<h4 class="mb-4"><i class="fas fa-shield-alt me-2"></i><?php echo APP_NAME; ?></h4>
	<hr class="bg-white">
	<nav class="nav flex-column">
		<a class="nav-link text-white<?php echo auth_active('index.php', $currentFile); ?>" href="index.php">
			<i class="fas fa-tachometer-alt me-2"></i>Dashboard
		</a>
		<a class="nav-link text-white<?php echo auth_active('reports.php', $currentFile); ?>" href="reports.php">
			<span class="nav-left"><i class="fas fa-exclamation-triangle me-2"></i>Waste Reports</span>
			<?php if ($auth_reports_unread > 0): ?>
				<span class="badge-container d-flex align-items-center gap-2">
					<span class="notification-badge"><?php echo $auth_reports_unread > 99 ? '99+' : $auth_reports_unread; ?></span>
				</span>
			<?php endif; ?>
		</a>
		<a class="nav-link text-white<?php echo auth_active('schedules.php', $currentFile); ?>" href="schedules.php">
			<span class="nav-left"><i class="fas fa-calendar me-2"></i>Collection Schedules</span>
			<?php if ($auth_collection_unread > 0): ?>
				<span class="badge-container d-flex align-items-center gap-2">
					<span class="notification-badge"><?php echo $auth_collection_unread > 99 ? '99+' : $auth_collection_unread; ?></span>
				</span>
			<?php endif; ?>
		</a>
		<a class="nav-link text-white<?php echo auth_active('collectors.php', $currentFile); ?>" href="collectors.php">
			<i class="fas fa-users me-2"></i>Collectors
		</a>
		<a class="nav-link text-white<?php echo auth_active('tracking.php', $currentFile); ?>" href="tracking.php">
			<i class="fas fa-map-marker-alt me-2"></i>Tracking
		</a>
		<a class="nav-link text-white<?php echo auth_active('residents.php', $currentFile); ?>" href="residents.php">
			<i class="fas fa-home me-2"></i>Residents
		</a>
		<a class="nav-link text-white<?php echo auth_active('analytics.php', $currentFile); ?>" href="analytics.php">
			<i class="fas fa-chart-line me-2"></i>Analytics
		</a>

		<div class="nav-item position-relative">
			<a class="nav-link text-white<?php echo auth_active('notifications.php', $currentFile); ?>" href="notifications.php" id="authNotificationsToggle">
				<span class="nav-left"><i class="fas fa-bell me-2"></i>Notifications</span>
				<span class="badge-container d-flex align-items-center gap-2">
					<?php if ($auth_notif_unread > 0): ?>
						<span class="notification-badge"><?php echo $auth_notif_unread > 99 ? '99+' : $auth_notif_unread; ?></span>
					<?php endif; ?>
					<i class="fas fa-chevron-down small"></i>
				</span>
			</a>

			<!-- Notifications preview -->
			<div id="authNotificationsPreview" class="card bg-white text-dark">
				<div class="card-body p-2">
					<div class="preview-header">
						<div class="title"><i class="fas fa-bell"></i><span>Recent notifications</span></div>
					</div>
					<?php if (count($auth_notif_preview) === 0): ?>
						<div class="text-center text-muted py-3">No notifications</div>
					<?php else: ?>
						<?php foreach ($auth_notif_preview as $n): ?>
							<?php $readClass = $n['is_read'] ? 'read' : 'unread'; ?>
							<div class="notif-item <?php echo $readClass; ?>">
								<div class="notif-row">
									<i class="fas fa-circle-info notif-icon"></i>
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
							<a href="notifications.php" class="btn btn-sm btn-outline-primary">View all</a>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<div class="nav-item position-relative">
			<a class="nav-link text-white<?php echo auth_active('chat.php', $currentFile); ?>" href="chat.php" id="authChatToggle">
				<span class="nav-left"><i class="fas fa-comments me-2"></i>Chat Support</span>
				<span class="badge-container d-flex align-items-center gap-2">
					<?php if ($auth_chat_unread > 0): ?>
						<span class="notification-badge"><?php echo $auth_chat_unread > 99 ? '99+' : $auth_chat_unread; ?></span>
					<?php endif; ?>
					<i class="fas fa-chevron-down small"></i>
				</span>
			</a>

			<!-- Chat preview -->
			<div id="authChatPreview" class="card bg-white text-dark">
				<div class="card-body p-2">
					<div class="preview-header">
						<div class="title"><i class="fas fa-message"></i><span>Chat messages</span></div>
					</div>
					<?php if (count($auth_chat_preview) === 0): ?>
						<div class="text-center text-muted py-3">No messages yet</div>
					<?php else: ?>
						<?php foreach ($auth_chat_preview as $m): ?>
							<?php $readClass = $m['is_read'] ? 'read' : 'unread'; ?>
							<div class="notif-item <?php echo $readClass; ?>">
								<div class="notif-row">
									<i class="fas fa-user-circle notif-icon"></i>
									<div class="notif-main">
										<p class="notif-title mb-0"><?php echo e(trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')) ?: 'Resident'); ?></p>
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
		<a class="nav-link text-white<?php echo auth_active('settings.php', $currentFile); ?>" href="settings.php">
			<i class="fas fa-cog me-2"></i>Settings
		</a>
		<a class="nav-link text-white<?php echo auth_active('profile.php', $currentFile); ?>" href="profile.php">
			<i class="fas fa-user me-2"></i>Profile
		</a>
		<hr class="bg-white">
		<a class="nav-link text-white" href="../../logout.php">
			<i class="fas fa-sign-out-alt me-2"></i>Logout
		</a>
	</nav>
</div>

<script>
// Sidebar mobile toggle (scoped to authority pages)
(function(){
	try {
		var DOC = document;
		var body = DOC.body;
		var overlay = DOC.querySelector('.sidebar-overlay');
		if (!overlay) { overlay = DOC.createElement('div'); overlay.className = 'sidebar-overlay'; body.appendChild(overlay); }
		var toggleBtn = DOC.getElementById('sidebarToggleBtn');
		if (!toggleBtn) {
			toggleBtn = DOC.createElement('button');
			toggleBtn.id = 'sidebarToggleBtn';
			toggleBtn.className = 'sidebar-toggle-btn d-lg-none';
			toggleBtn.setAttribute('aria-label', 'Open menu');
			toggleBtn.setAttribute('aria-expanded', 'false');
			toggleBtn.setAttribute('aria-controls', 'authoritySidebar');
			toggleBtn.innerHTML = '<span class="bar"></span><span class="bar"></span><span class="bar"></span>';
			var headerRow = DOC.querySelector('.p-4 .d-flex.justify-content-between.align-items-center');
			if (headerRow) { headerRow.insertBefore(toggleBtn, headerRow.firstChild); }
			else { body.appendChild(toggleBtn); toggleBtn.classList.add('fixed'); }
		}
		var sidebarEl = DOC.querySelector('.sidebar');
		if (sidebarEl && !sidebarEl.id) sidebarEl.id = 'authoritySidebar';
		function openSidebar(){ if (!body.classList.contains('sidebar-open')) { body.classList.add('sidebar-open'); toggleBtn.setAttribute('aria-expanded', 'true'); try { var firstLink = sidebarEl ? sidebarEl.querySelector('.nav-link, a, button, [tabindex]') : null; if (firstLink) firstLink.focus(); } catch (e) {} } }
		function closeSidebar(){ if (body.classList.contains('sidebar-open')) { body.classList.remove('sidebar-open'); toggleBtn.setAttribute('aria-expanded', 'false'); } }
		function toggleSidebar(){ if (body.classList.contains('sidebar-open')) closeSidebar(); else openSidebar(); }
		toggleBtn.addEventListener('click', toggleSidebar);
		overlay.addEventListener('click', closeSidebar);
		DOC.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeSidebar(); });
		if (sidebarEl) { sidebarEl.addEventListener('click', function(e){ var t = e.target; if (t && t.closest('a.nav-link')) closeSidebar(); }); }
	} catch (err) {}
})();
</script>

<script>
// Toggle authority notifications preview
(function(){
	try {
		var toggle = document.getElementById('authNotificationsToggle');
		var preview = document.getElementById('authNotificationsPreview');
		if (!toggle || !preview) return;

		function hidePreview() { preview.style.display = 'none'; }
		function showPreview() { preview.style.display = 'block'; }
		function togglePreview(e) { e.preventDefault(); if (preview.style.display === 'block') hidePreview(); else showPreview(); }

		toggle.addEventListener('click', function(e){
			try {
				var clickedChevron = e.target.closest && e.target.closest('.fa-chevron-down');
				var clickedBadge = e.target.closest && e.target.closest('.notification-badge');
				if (clickedChevron || clickedBadge) { e.preventDefault(); togglePreview(e); } else { return true; }
			} catch (err) { togglePreview(e); }
		});

		document.addEventListener('click', function(e){
			var t = e.target; if (!t.closest) return;
			if (!t.closest('#authNotificationsPreview') && !t.closest('#authNotificationsToggle')) { hidePreview(); }
		});

		window.addEventListener('resize', hidePreview);
	} catch (err) {}
})();
</script>

<script>
// Toggle authority chat preview
(function(){
	try {
		var toggle = document.getElementById('authChatToggle');
		var preview = document.getElementById('authChatPreview');
		if (!toggle || !preview) return;

		function hidePreview() { preview.style.display = 'none'; }
		function showPreview() { preview.style.display = 'block'; }
		function togglePreview(e) { e.preventDefault(); if (preview.style.display === 'block') hidePreview(); else showPreview(); }

		toggle.addEventListener('click', function(e){
			try {
				var clickedChevron = e.target.closest && e.target.closest('.fa-chevron-down');
				var clickedBadge = e.target.closest && e.target.closest('.notification-badge');
				if (clickedChevron || clickedBadge) { e.preventDefault(); togglePreview(e); } else { return true; }
			} catch (err) { togglePreview(e); }
		});

		document.addEventListener('click', function(e){
			var t = e.target; if (!t.closest) return;
			if (!t.closest('#authChatPreview') && !t.closest('#authChatToggle')) { hidePreview(); }
		});

		window.addEventListener('resize', hidePreview);
	} catch (err) {}
})();
</script>



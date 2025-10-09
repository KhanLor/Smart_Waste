<?php
if (!isset($_SESSION)) { session_start(); }

// Determine current filename (for active link highlighting)
$currentFile = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Helper to mark active links; guard against redeclare
if (!function_exists('admin_active')) {
	function admin_active(string $file, string $current): string {
		return $current === $file ? ' active' : '';
	}
}
?>

<style>
	/* Keep sidebar above content and unify link hover */
	.sidebar { position: relative; z-index: 3; }
	.sidebar .nav-link { background: transparent; }
	.sidebar .nav-link.active, .sidebar .nav-link:hover { background-color: rgba(255,255,255,0.2); }
	/* Mobile off-canvas sidebar */
	@media (max-width: 991.98px) {
		.sidebar { z-index: 1050; position: fixed; top: 0; bottom: 0; left: -280px; width: 260px; max-width: 80%; overflow-y: auto; transition: left 0.25s ease-in-out; }
		body.sidebar-open .sidebar { left: 0; }
		.sidebar-overlay { content: ""; position: fixed; inset: 0; background: rgba(0,0,0,0.45); opacity: 0; visibility: hidden; transition: opacity 0.2s ease-in-out, visibility 0.2s ease-in-out; z-index: 1040; }
		body.sidebar-open .sidebar-overlay { opacity: 1; visibility: visible; }
		.sidebar-toggle-btn { border: none; background: #0d6efd; color: #fff; width: 28px; height: 28px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 2px 6px rgba(0,0,0,0.15); padding: 0; margin-right: 10px; }
		.sidebar-toggle-btn .bar { display: block; width: 16px; height: 2px; background: #fff; margin: 2px 0; border-radius: 1px; }
		.sidebar-toggle-btn.fixed { position: fixed; top: 10px; left: 10px; z-index: 1100; }
		.p-4 > .d-flex.justify-content-between.align-items-center { gap: 8px; }
	}
</style>

<div class="p-3">
	<h4 class="mb-4"><i class="fas fa-user-shield me-2"></i><?php echo APP_NAME; ?></h4>
	<hr class="bg-white">
	<nav class="nav flex-column">
		<a class="nav-link text-white<?php echo admin_active('index.php', $currentFile); ?>" href="index.php">
			<i class="fas fa-tachometer-alt me-2"></i>Dashboard
		</a>
		<a class="nav-link text-white<?php echo admin_active('reports.php', $currentFile); ?>" href="reports.php">
			<i class="fas fa-exclamation-triangle me-2"></i>Waste Reports
		</a>
		<a class="nav-link text-white<?php echo admin_active('schedules.php', $currentFile); ?>" href="schedules.php">
			<i class="fas fa-calendar me-2"></i>Collection Schedules
		</a>
		<a class="nav-link text-white<?php echo admin_active('collectors.php', $currentFile); ?>" href="collectors.php">
			<i class="fas fa-users me-2"></i>Collectors
		</a>
		<a class="nav-link text-white<?php echo admin_active('tracking.php', $currentFile); ?>" href="tracking.php">
			<i class="fas fa-map-marker-alt me-2"></i>Tracking
		</a>
		<a class="nav-link text-white<?php echo admin_active('residents.php', $currentFile); ?>" href="residents.php">
			<i class="fas fa-home me-2"></i>Residents
		</a>
		<a class="nav-link text-white<?php echo admin_active('analytics.php', $currentFile); ?>" href="analytics.php">
			<i class="fas fa-chart-line me-2"></i>Analytics
		</a>
		<a class="nav-link text-white<?php echo admin_active('notifications.php', $currentFile); ?> position-relative" href="notifications.php">
			<i class="fas fa-bell me-2"></i>Notifications
			<?php if (isset($unread_count) && $unread_count > 0): ?>
				<span class="notification-badge"><?php echo $unread_count; ?></span>
			<?php endif; ?>
		</a>
		<a class="nav-link text-white<?php echo admin_active('chat.php', $currentFile); ?>" href="chat.php">
			<i class="fas fa-comments me-2"></i>Chat Support
		</a>
		<a class="nav-link text-white<?php echo admin_active('settings.php', $currentFile); ?>" href="settings.php">
			<i class="fas fa-cog me-2"></i>Settings
		</a>
		<hr class="bg-white">
		<a class="nav-link text-white" href="../../logout.php">
			<i class="fas fa-sign-out-alt me-2"></i>Logout
		</a>
	</nav>
</div>

<script>
// Sidebar mobile toggle (scoped to admin pages)
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
			toggleBtn.setAttribute('aria-controls', 'adminSidebar');
			toggleBtn.innerHTML = '<span class="bar"></span><span class="bar"></span><span class="bar"></span>';
			var headerRow = DOC.querySelector('.p-4 .d-flex.justify-content-between.align-items-center');
			if (headerRow) { headerRow.insertBefore(toggleBtn, headerRow.firstChild); }
			else { body.appendChild(toggleBtn); toggleBtn.classList.add('fixed'); }
		}
		var sidebarEl = DOC.querySelector('.sidebar');
		if (sidebarEl && !sidebarEl.id) sidebarEl.id = 'adminSidebar';
		function openSidebar(){ if (!body.classList.contains('sidebar-open')) { body.classList.add('sidebar-open'); toggleBtn.setAttribute('aria-expanded', 'true'); try { var firstLink = sidebarEl ? sidebarEl.querySelector('.nav-link, a, button, [tabindex]') : null; if (firstLink) firstLink.focus(); } catch (e) {} } }
		function closeSidebar(){ if (body.classList.contains('sidebar-open')) { body.classList.remove('sidebar-open'); toggleBtn.setAttribute('aria-expanded', 'false'); } }
		function toggleSidebar(){ if (body.classList.contains('sidebar-open')) closeSidebar(); else openSidebar(); }
		toggleBtn.addEventListener('click', toggleSidebar);
		overlay.addEventListener('click', closeSidebar);
		DOC.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeSidebar(); });
		if (sidebarEl) {
			sidebarEl.addEventListener('click', function(e){ var t = e.target; if (t && t.closest('a.nav-link')) closeSidebar(); });
		}
	} catch (err) {}
})();
</script>



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
?>

<style>
    /* Ensure sidebar is clickable above overlapping content and consistent link backgrounds */
    .sidebar { position: relative; z-index: 3; }
    .sidebar .nav-link { background: transparent; }
    .sidebar .nav-link.active, .sidebar .nav-link:hover { background-color: rgba(255,255,255,0.2); }
    /* Mobile off-canvas sidebar */
    @media (max-width: 991.98px) {
        .sidebar { z-index: 1050; position: fixed; top: 0; bottom: 0; left: -280px; width: 260px; max-width: 80%; overflow-y: auto; transition: left 0.25s ease-in-out; }
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

<div class="p-3">
    <h4 class="mb-4"><i class="fas fa-recycle me-2"></i><?php echo APP_NAME; ?></h4>
    <hr class="bg-white">
    <nav class="nav flex-column">
        <a class="nav-link text-white<?php echo rs_active('index.php', $currentFile); ?>" href="index.php">
            <i class="fas fa-home me-2"></i>Dashboard
        </a>
        <a class="nav-link text-white<?php echo rs_active('reports.php', $currentFile); ?>" href="reports.php">
            <i class="fas fa-exclamation-circle me-2"></i>My Reports
        </a>
        <a class="nav-link text-white<?php echo rs_active('submit_report.php', $currentFile); ?>" href="submit_report.php">
            <i class="fas fa-plus-circle me-2"></i>Submit Report
        </a>
        <a class="nav-link text-white<?php echo rs_active('schedule.php', $currentFile); ?>" href="schedule.php">
            <i class="fas fa-calendar me-2"></i>Collection Schedule
        </a>
        <a class="nav-link text-white<?php echo rs_active('collections.php', $currentFile); ?>" href="collections.php">
            <i class="fas fa-history me-2"></i>Recent Collections
        </a>
        <a class="nav-link text-white<?php echo rs_active('points.php', $currentFile); ?>" href="points.php">
            <i class="fas fa-leaf me-2"></i>Eco Points
        </a>
        <a class="nav-link text-white<?php echo rs_active('feedback.php', $currentFile); ?>" href="feedback.php">
            <i class="fas fa-comment me-2"></i>Feedback
        </a>
        <a class="nav-link text-white<?php echo rs_active('notifications.php', $currentFile); ?> position-relative" href="notifications.php">
            <i class="fas fa-bell me-2"></i>Notifications
        </a>
        <a class="nav-link text-white<?php echo rs_active('chat.php', $currentFile); ?>" href="chat.php">
            <i class="fas fa-comments me-2"></i>Chat
        </a>
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

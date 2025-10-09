<?php
if (!isset($_SESSION)) { session_start(); }

$currentFile = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if (!function_exists('collector_active')) {
	function collector_active(string $file, string $current): string {
		return $current === $file ? ' active' : '';
	}
}
?>

<style>
	.sidebar { position: relative; z-index: 3; }
	.sidebar .nav-link { background: transparent; }
	.sidebar .nav-link.active, .sidebar .nav-link:hover { background-color: rgba(0,0,0,0.06); }
	@media (max-width: 991.98px) {
		.sidebar { z-index: 1050; position: fixed; top: 0; bottom: 0; left: -280px; width: 260px; max-width: 80%; overflow-y: auto; transition: left 0.25s ease-in-out; }
		body.sidebar-open .sidebar { left: 0; }
		.sidebar-overlay { content: ""; position: fixed; inset: 0; background: rgba(0,0,0,0.45); opacity: 0; visibility: hidden; transition: opacity 0.2s ease-in-out, visibility 0.2s ease-in-out; z-index: 1040; }
		body.sidebar-open .sidebar-overlay { opacity: 1; visibility: visible; }
		.sidebar-toggle-btn { border: none; background: #ffc107; color: #212529; width: 28px; height: 28px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 2px 6px rgba(0,0,0,0.15); padding: 0; margin-right: 10px; }
		.sidebar-toggle-btn .bar { display: block; width: 16px; height: 2px; background: currentColor; margin: 2px 0; border-radius: 1px; }
		.sidebar-toggle-btn.fixed { position: fixed; top: 10px; left: 10px; z-index: 1100; }
		.p-4 > .d-flex.justify-content-between.align-items-center { gap: 8px; }
	}
</style>

<div class="p-3">
	<h4 class="mb-4"><i class="fas fa-truck me-2"></i><?php echo APP_NAME; ?></h4>
	<hr>
	<nav class="nav flex-column">
		<a class="nav-link<?php echo collector_active('index.php', $currentFile); ?>" href="index.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
		<a class="nav-link<?php echo collector_active('routes.php', $currentFile); ?>" href="routes.php"><i class="fas fa-route me-2"></i>My Routes</a>
		<a class="nav-link<?php echo collector_active('collections.php', $currentFile); ?>" href="collections.php"><i class="fas fa-tasks me-2"></i>Collections</a>
		<a class="nav-link<?php echo collector_active('map.php', $currentFile); ?>" href="map.php"><i class="fas fa-map me-2"></i>Map View</a>
		<a class="nav-link<?php echo collector_active('profile.php', $currentFile); ?>" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a>
		<hr>
		<a class="nav-link" href="../../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
	</nav>
</div>

<script>
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
			toggleBtn.setAttribute('aria-controls', 'collectorSidebar');
			toggleBtn.innerHTML = '<span class="bar"></span><span class="bar"></span><span class="bar"></span>';
			var headerRow = DOC.querySelector('.p-4 .d-flex.justify-content-between.align-items-center');
			if (headerRow) { headerRow.insertBefore(toggleBtn, headerRow.firstChild); } else { body.appendChild(toggleBtn); toggleBtn.classList.add('fixed'); }
		}
		var sidebarEl = DOC.querySelector('.sidebar');
		if (sidebarEl && !sidebarEl.id) sidebarEl.id = 'collectorSidebar';
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



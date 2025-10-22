<?php
require_once __DIR__ . '/../../config/config.php';
require_login();

// Check if user is a resident
if (($_SESSION['role'] ?? '') !== 'resident') {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? 'User';
$full_name = $username;

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get user's waste reports
$stmt = $conn->prepare("SELECT * FROM waste_reports WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$reports = $stmt->get_result();

// Get user's collection schedule
$stmt = $conn->prepare("
    SELECT cs.* FROM collection_schedules cs 
    WHERE cs.street_name LIKE ? OR cs.area LIKE ?
    ORDER BY FIELD(cs.collection_day, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday')
");
$address_search = '%' . $user['address'] . '%';
$stmt->bind_param("ss", $address_search, $address_search);
$stmt->execute();
$schedules = $stmt->get_result();

// Get next collection
$next_collection = null;
if ($schedules->num_rows > 0) {
    $schedules->data_seek(0);
    while ($schedule = $schedules->fetch_assoc()) {
        $today = strtolower(date('l'));
        if ($schedule['collection_day'] === $today) {
            $next_collection = $schedule;
            break;
        }
    }
}

// Get unread notifications
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications = $stmt->get_result();
$unread_count = $notifications ? $notifications->num_rows : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-2px);
        }
        .stat-card {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
        }
        .stat-card.warning {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
        }
        .stat-card.success {
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
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
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 10px;
        }
        .schedule-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid #28a745;
        }
        .report-item {
            border-left: 4px solid #007bff;
        }
        .report-item.high {
            border-left-color: #dc3545;
        }
        .report-item.medium {
            border-left-color: #ffc107;
        }
        .report-item.low {
            border-left-color: #28a745;
        }
        /* Mobile responsiveness tweaks */
        @media (max-width: 767.98px) {
            .container-fluid { padding-left: 10px; padding-right: 10px; }
            .sidebar { min-height: auto; position: relative; }
            .card { border-radius: 12px; }
            .stat-card .card-body { padding: 12px; }
            /* Make stat cards full width on small screens */
            .row.mb-4 > .col-md-4 { flex: 0 0 100%; max-width: 100%; }
            /* Quick action buttons: ensure they stack and fill width */
            .row.mb-4 .card .row > .col-md-3 { flex: 0 0 50%; max-width: 50%; }
            @media (max-width: 420px) {
                .row.mb-4 .card .row > .col-md-3 { flex: 0 0 100%; max-width: 100%; }
            }
            /* Map height smaller on mobile */
            #residentMap { height: 210px !important; }
            /* Recent reports and schedule stack full width */
            .col-md-6 { flex: 0 0 100%; max-width: 100%; }
            /* Card header controls wrap */
            .card-header.d-flex { flex-wrap: wrap; gap: 8px; }
            .card-header.d-flex .btn { margin-top: 6px; }
            /* Notifications and alerts full width */
            .alert { font-size: 0.95rem; }
            /* Reduce paddings and text sizes for compactness */
            .p-4 { padding: 12px !important; }
            h2 { font-size: 1.2rem; }
            .h4 { font-size: 1.25rem; }
            .schedule-item { padding: 10px; }
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
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="mb-1">Welcome back, <?php echo e($user['first_name']); ?>!</h2>
                            <p class="text-muted mb-0">Here's what's happening with your waste management</p>
                        </div>
                        <div class="text-end">
                            <div class="h4 text-success mb-0"><?php echo $user['eco_points']; ?></div>
                            <small class="text-muted">Eco Points</small>
                        </div>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-4 mb-3">
                            <div class="card stat-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">Next Collection</h6>
                                            <?php if ($next_collection): ?>
                                                <h4 class="mb-0"><?php echo ucfirst($next_collection['collection_day']); ?></h4>
                                                <small><?php echo format_ph_date($next_collection['collection_time'], 'g:i A'); ?></small>
                                            <?php else: ?>
                                                <h4 class="mb-0">No Schedule</h4>
                                                <small>Check your area</small>
                                            <?php endif; ?>
                                        </div>
                                        <i class="fas fa-truck fa-2x opacity-75"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card stat-card warning">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">Active Reports</h6>
                                            <h4 class="mb-0"><?php echo $reports->num_rows; ?></h4>
                                            <small>Pending resolution</small>
                                        </div>
                                        <i class="fas fa-exclamation-triangle fa-2x opacity-75"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card stat-card success">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">Eco Points</h6>
                                            <h4 class="mb-0"><?php echo $user['eco_points']; ?></h4>
                                            <small>Keep earning!</small>
                                        </div>
                                        <i class="fas fa-leaf fa-2x opacity-75"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3 mb-3">
                                            <a href="submit_report.php" class="btn btn-primary w-100">
                                                <i class="fas fa-exclamation-circle me-2"></i>Report Issue
                                            </a>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <a href="schedule.php" class="btn btn-success w-100">
                                                <i class="fas fa-calendar me-2"></i>View Schedule
                                            </a>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <a href="feedback.php" class="btn btn-info w-100">
                                                <i class="fas fa-comment me-2"></i>Give Feedback
                                            </a>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <a href="chat.php" class="btn btn-warning w-100">
                                                <i class="fas fa-comments me-2"></i>Chat Support
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                                <!-- Recent Reports and Schedule -->
                    <div class="row">
                                    <!-- Live Tracking for Residents -->
                                    <div class="col-md-12 mb-4">
                                        <div class="card">
                                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                                        <h5 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>Nearby Collectors (Live)</h5>
                                                        <div>
                                                            <button id="refreshCollectorsBtn" class="btn btn-sm btn-outline-secondary me-2"><i class="fas fa-rotate me-1"></i>Refresh</button>
                                                            <button id="nearestCollectorBtn" class="btn btn-sm btn-outline-primary"><i class="fas fa-location-crosshairs me-1"></i>Nearest Collector</button>
                                                        </div>
                                                    </div>
                                            <div class="card-body">
                                                <div id="residentMap" style="height:300px;"></div>
                                            </div>
                                        </div>
                                    </div>

                        <!-- Recent Reports -->
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0"><i class="fas fa-exclamation-circle me-2"></i>Recent Reports</h5>
                                </div>
                                <div class="card-body">
                                    <?php if ($reports->num_rows > 0): ?>
                                        <?php while ($report = $reports->fetch_assoc()): ?>
                                            <div class="report-item <?php echo $report['priority']; ?> p-3 mb-2">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="mb-1"><?php echo e($report['title']); ?></h6>
                                                        <small class="text-muted"><?php echo e($report['location']); ?></small>
                                                        <br>
                                                        <span class="badge bg-<?php echo $report['priority'] === 'high' ? 'danger' : ($report['priority'] === 'medium' ? 'warning' : 'success'); ?>">
                                                            <?php echo ucfirst($report['priority']); ?>
                                                        </span>
                                                        <span class="badge bg-secondary ms-1"><?php echo ucfirst($report['status']); ?></span>
                                                    </div>
                                                    <small class="text-muted"><?php echo format_ph_date($report['created_at'], 'M j'); ?></small>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <p class="text-muted text-center">No reports yet. Submit your first report!</p>
                                    <?php endif; ?>
                                    <div class="text-center mt-3">
                                        <a href="reports.php" class="btn btn-outline-primary btn-sm">View All Reports</a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Collection Schedule -->
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0"><i class="fas fa-calendar me-2"></i>This Week's Schedule</h5>
                                </div>
                                <div class="card-body">
                                    <?php if ($schedules->num_rows > 0): ?>
                                        <?php 
                                        $schedules->data_seek(0);
                                        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                                        foreach ($days as $day):
                                            $schedules->data_seek(0);
                                            $daySchedule = null;
                                            while ($schedule = $schedules->fetch_assoc()) {
                                                if ($schedule['collection_day'] === $day) {
                                                    $daySchedule = $schedule;
                                                    break;
                                                }
                                            }
                                        ?>
                                            <div class="schedule-item">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="mb-1"><?php echo ucfirst($day); ?></h6>
                                                        <?php if ($daySchedule): ?>
                                                            <small class="text-muted">
                                                                <?php echo e($daySchedule['waste_type']); ?> - 
                                                                <?php echo format_ph_date($daySchedule['collection_time'], 'g:i A'); ?>
                                                            </small>
                                                        <?php else: ?>
                                                            <small class="text-muted">No collection scheduled</small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if ($daySchedule): ?>
                                                        <span class="badge bg-success"><?php echo ucfirst($daySchedule['waste_type']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-muted text-center">No schedule found for your area.</p>
                                    <?php endif; ?>
                                    <div class="text-center mt-3">
                                        <a href="schedule.php" class="btn btn-outline-success btn-sm">View Full Schedule</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notifications -->
                    <?php if ($notifications->num_rows > 0): ?>
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0"><i class="fas fa-bell me-2"></i>Recent Notifications</h5>
                                </div>
                                <div class="card-body">
                                    <?php while ($notification = $notifications->fetch_assoc()): ?>
                                        <div class="alert alert-<?php echo $notification['type'] === 'success' ? 'success' : ($notification['type'] === 'warning' ? 'warning' : ($notification['type'] === 'error' ? 'danger' : 'info')); ?> alert-dismissible fade show" role="alert">
                                            <strong><?php echo e($notification['title']); ?></strong>
                                            <br>
                                            <?php echo e($notification['message']); ?>
                                            <small class="d-block text-muted mt-1">
                                                <?php echo format_ph_date($notification['created_at']); ?>
                                            </small>
                                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                        </div>
                                    <?php endwhile; ?>
                                    <div class="text-center mt-3">
                                        <a href="notifications.php" class="btn btn-outline-secondary btn-sm">View All Notifications</a>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/notifications.js"></script>
    <script>
        // Inject VAPID public key for the service worker registration script
        window.__VAPID_PUBLIC_KEY__ = <?php echo json_encode(VAPID_PUBLIC_KEY); ?>;
    </script>
    <script src="<?php echo BASE_URL; ?>assets/js/register_sw.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://js.pusher.com/7.2/pusher.min.js"></script>
    <script>
    (function(){
        const map = L.map('residentMap').setView([14.5995,120.9842],12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19}).addTo(map);

    const markers = {};
    const polylinesByCollector = {};
    const MAX_PATH_POINTS = 200;

        // helper: create a DivIcon with rotated truck image
        function createRotatedIcon(angle) {
            const imgUrl = '../../assets/collector_icon.png';
            const size = 40;
            const html = `
                <div style="width:${size}px;height:${size}px;display:flex;align-items:center;justify-content:center;">
                    <img src="${imgUrl}" style="width:${size}px;height:${size}px;transform:rotate(${angle}deg);-webkit-transform:rotate(${angle}deg);"> 
                </div>`;
            return L.divIcon({
                html: html,
                className: '',
                iconSize: [size, size],
                iconAnchor: [size/2, size/2],
                popupAnchor: [0, -size/2]
            });
        }

        // Find the nearest collector to given point (lat,lng). If point is null, use map center.
        function findNearestCollectorPoint(refLatLng) {
            let best = null;
            let bestDist = Infinity;
            for (const id of Object.keys(markers)) {
                try {
                    const m = markers[id];
                    if (!m) continue;
                    const pos = m.getLatLng();
                    if (!pos) continue;
                    const d = refLatLng ? map.distance(refLatLng, pos) : map.distance(map.getCenter(), pos);
                    if (d < bestDist) { bestDist = d; best = pos; }
                } catch (e) { /* ignore */ }
            }
            return best;
        }

        // Center map on nearest collector using browser geolocation when available
        async function centerOnNearestCollector() {
            // helper to actually center
            const doCenter = (ref) => {
                const nearest = findNearestCollectorPoint(ref);
                if (nearest) {
                    map.flyTo(nearest, 15, { duration: 0.8 });
                } else {
                    // no collector points yet; show a small toast or console
                    console.warn('No collector locations available to center on');
                }
            };

            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition((pos) => {
                    const ref = L.latLng(pos.coords.latitude, pos.coords.longitude);
                    doCenter(ref);
                }, (err) => {
                    // fallback to using map center
                    console.warn('Geolocation failed, using map center', err);
                    doCenter(null);
                }, { enableHighAccuracy: true, maximumAge: 5000, timeout: 7000 });
            } else {
                doCenter(null);
            }
        }

        async function load() {
            try {
                const res = await fetch('../../api/get_collectors_locations_public.php');
                const json = await res.json();
                if (!json.success) return;
                        // define collector icon (use collector_icon.png if available, fallback to collector.png)
                        const collectorIconUrl = '../../assets/collector_icon.png';
                        const collectorIconFallback = '../../assets/collector.png';
                        const collectorIcon = L.icon({
                            iconUrl: collectorIconUrl,
                            iconRetinaUrl: collectorIconUrl,
                            iconSize: [40, 40],
                            iconAnchor: [20, 20],
                            popupAnchor: [0, -18]
                        });

                        for (const c of json.collectors) {
                            if (!c.latitude || !c.longitude) continue;
                            const id = String(c.collector_id);
                            const name = c.name || ('Collector ' + id);
                            const lat = parseFloat(c.latitude), lng = parseFloat(c.longitude);
                            const popup = `<strong>${name}</strong><br>Last seen: ${c.updated_at || 'N/A'}`;
                            const heading = (c.heading !== undefined && c.heading !== null) ? parseFloat(c.heading) : null;
                            if (markers[id]) {
                                markers[id].setLatLng([lat,lng]);
                                markers[id].setPopupContent(popup);
                                if (heading !== null) {
                                    // update icon with rotation
                                    markers[id].setIcon(createRotatedIcon(heading));
                                }
                            } else {
                                let marker;
                                if (heading !== null) marker = L.marker([lat,lng], { icon: createRotatedIcon(heading) });
                                else marker = L.marker([lat,lng], { icon: collectorIcon });
                                marker.addTo(map).bindPopup(popup);
                                markers[id] = marker;
                            }
                            // create polyline starting at this location
                            try {
                                if (!polylinesByCollector[id]) polylinesByCollector[id] = L.polyline([[lat,lng]], { color: '#28a745', weight: 4, opacity: 0.8 }).addTo(map);
                            } catch (e) { console.warn('Create polyline error', e); }
                        }
            } catch (e) { console.warn(e); }
        }

        // initial load
        load();

        // refresh button
        try {
            const refreshBtn = document.getElementById('refreshCollectorsBtn');
            if (refreshBtn) refreshBtn.addEventListener('click', async () => {
                refreshBtn.disabled = true;
                const oldHtml = refreshBtn.innerHTML;
                refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Refreshing';
                try { await load(); } finally {
                    refreshBtn.disabled = false;
                    refreshBtn.innerHTML = oldHtml;
                }
            });
        } catch (e) { console.warn('Refresh button hookup failed', e); }

        // wire up nearest collector button
        try {
            const btn = document.getElementById('nearestCollectorBtn');
            if (btn) btn.addEventListener('click', () => {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Locating...';
                centerOnNearestCollector().finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-location-crosshairs me-1"></i>Nearest Collector';
                });
            });
        } catch (e) { console.warn('Nearest Collector button hookup failed', e); }

        // add toggle paths button next to nearestCollectorBtn (inside IIFE so it can access map/polylines)
        try {
            const btn = document.getElementById('nearestCollectorBtn');
            if (btn && btn.parentNode) {
                const toggle = document.createElement('button');
                toggle.className = 'btn btn-sm btn-outline-info ms-2';
                toggle.id = 'togglePathsBtn';
                toggle.innerHTML = '<i class="fas fa-route me-1"></i>Toggle Paths';
                btn.parentNode.appendChild(toggle);
                let visible = true;
                toggle.addEventListener('click', () => {
                    visible = !visible;
                    for (const id in polylinesByCollector) {
                        const p = polylinesByCollector[id];
                        if (!p) continue;
                        if (visible) map.addLayer(p); else map.removeLayer(p);
                    }
                });
            }
        } catch (e) { console.warn('Toggle Paths hookup failed', e); }

        // Pusher realtime updates
        try {
            const PUSHER_KEY = <?php echo json_encode(PUSHER_KEY); ?>;
            const PUSHER_CLUSTER = <?php echo json_encode(PUSHER_CLUSTER); ?>;
            const PUSHER_USE_TLS = <?php echo PUSHER_USE_TLS ? 'true' : 'false'; ?>;
            if (PUSHER_KEY) {
                const pusher = new Pusher(PUSHER_KEY, { cluster: PUSHER_CLUSTER, forceTLS: PUSHER_USE_TLS });
                const channel = pusher.subscribe('collectors-channel');
                channel.bind('collector-location', function(data) {
                    const id = String(data.collector_id);
                    const lat = parseFloat(data.latitude), lng = parseFloat(data.longitude);
                    if (!lat || !lng) return;
                    const popup = `<strong>${data.name || ('Collector ' + id)}</strong><br>Last seen: ${data.updated_at || new Date().toISOString()}`;
                    const heading = (data.heading !== undefined && data.heading !== null) ? parseFloat(data.heading) : null;
                    if (markers[id]) {
                        markers[id].setLatLng([lat,lng]);
                        markers[id].setPopupContent(popup);
                        if (heading !== null) markers[id].setIcon(createRotatedIcon(heading));
                    } else {
                        if (heading !== null) markers[id] = L.marker([lat,lng], { icon: createRotatedIcon(heading) }).addTo(map).bindPopup(popup);
                        else {
                            const collectorIconUrl = '../../assets/collector_icon.png';
                            const collectorIcon = L.icon({
                                iconUrl: collectorIconUrl,
                                iconRetinaUrl: collectorIconUrl,
                                iconSize: [40,40],
                                iconAnchor: [20,20],
                                popupAnchor: [0,-18]
                            });
                            markers[id] = L.marker([lat,lng], { icon: collectorIcon }).addTo(map).bindPopup(popup);
                        }
                    }
                    // append to polyline
                    try {
                        if (!polylinesByCollector[id]) {
                            polylinesByCollector[id] = L.polyline([[lat,lng]], { color: '#28a745', weight: 4, opacity: 0.8 }).addTo(map);
                        } else {
                            const pts = polylinesByCollector[id].getLatLngs();
                            pts.push(L.latLng(lat,lng));
                            if (pts.length > MAX_PATH_POINTS) pts.splice(0, pts.length - MAX_PATH_POINTS);
                            polylinesByCollector[id].setLatLngs(pts);
                        }
                    } catch (e) { console.warn('Polyline update error', e); }
                });
            }
        } catch (e) { console.warn('Pusher init failed', e); }
    })();
    </script>
</body>
</html>

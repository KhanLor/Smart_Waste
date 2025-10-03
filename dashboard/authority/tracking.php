<?php
require_once '../../config/config.php';
require_login();
if (($_SESSION['role'] ?? '') !== 'authority' && ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$username = $_SESSION['username'] ?? 'Authority';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dispatcher Tracking - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <style>
        .sidebar { min-height: 100vh; background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); }
        #map { height: 72vh; }
        .collector-list { max-height: 72vh; overflow: auto; }
        .list-group-item { cursor: pointer; }
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
                        <a class="nav-link text-white" href="index.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
                        <a class="nav-link text-white" href="reports.php"><i class="fas fa-exclamation-triangle me-2"></i>Waste Reports</a>
                        <a class="nav-link text-white" href="schedules.php"><i class="fas fa-calendar me-2"></i>Collection Schedules</a>
                        <a class="nav-link text-white" href="collectors.php"><i class="fas fa-users me-2"></i>Collectors</a>
                        <a class="nav-link text-white active" href="tracking.php"><i class="fas fa-map-marker-alt me-2"></i>Tracking</a>
                        <a class="nav-link text-white" href="residents.php"><i class="fas fa-home me-2"></i>Residents</a>
                        <a class="nav-link text-white" href="analytics.php"><i class="fas fa-chart-line me-2"></i>Analytics</a>
                        <a class="nav-link text-white" href="chat.php"><i class="fas fa-comments me-2"></i>Chat Support</a>
                        <a class="nav-link text-white" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a>
                        <hr class="bg-white">
                        <a class="nav-link text-white" href="../../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2>Live Tracking</h2>
                        <div class="text-muted">Welcome, <?php echo htmlspecialchars($username); ?>!</div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card mb-3">
                                <div class="card-header bg-light"><strong>Collectors</strong></div>
                                <div class="card-body collector-list">
                                    <div class="list-group" id="collectorList"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div id="map" class="mb-3"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://js.pusher.com/7.2/pusher.min.js"></script>
    <script>
    const PUSHER_KEY = <?php echo json_encode(PUSHER_KEY); ?>;
    const PUSHER_CLUSTER = <?php echo json_encode(PUSHER_CLUSTER); ?>;
    const PUSHER_USE_TLS = <?php echo PUSHER_USE_TLS ? 'true' : 'false'; ?>;

    const map = L.map('map').setView([14.5995,120.9842],12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19}).addTo(map);

    const markers = {};
    const polylinesByCollector = {};
    const MAX_PATH_POINTS = 200;

    // Rotated truck icon helper
    function createRotatedIcon(angle) {
        const imgUrl = '../../assets/collector_icon.png';
        const size = 40;
        const html = `
            <div style="width:${size}px;height:${size}px;display:flex;align-items:center;justify-content:center;">
                <img src="${imgUrl}" style="width:${size}px;height:${size}px;transform:rotate(${angle}deg);-webkit-transform:rotate(${angle}deg);"> 
            </div>`;
        return L.divIcon({ html: html, className: '', iconSize: [size,size], iconAnchor: [size/2,size/2], popupAnchor: [0,-size/2] });
    }

    async function loadCollectors(){
        const res = await fetch('../../api/get_collectors_locations.php');
        const json = await res.json();
        if (!json.success) return;
        const list = document.getElementById('collectorList');
        list.innerHTML = '';
        for (const c of json.collectors) {
            const id = c.collector_id;
            const name = c.name || ('Collector ' + id);
            const lat = c.latitude; const lng = c.longitude;
            const updated = c.updated_at ? new Date(c.updated_at).toLocaleString() : 'Never';

            const item = document.createElement('div');
            item.className = 'list-group-item d-flex justify-content-between align-items-center';
            item.setAttribute('data-collector-id', id);
            item.innerHTML = `<div><strong>${name}</strong><br><small class="text-muted">ID: ${id}</small></div><small class="text-muted last-seen">${updated}</small>`;
            item.addEventListener('click', () => {
                if (lat && lng) map.setView([lat,lng],14);
                if (markers[id]) markers[id].openPopup();
            });
            list.appendChild(item);

            if (lat && lng) {
                const heading = (c.heading !== undefined && c.heading !== null) ? parseFloat(c.heading) : null;
                if (markers[id]) {
                    markers[id].setLatLng([lat,lng]);
                    if (heading !== null) markers[id].setIcon(createRotatedIcon(heading));
                } else {
                    if (heading !== null) markers[id] = L.marker([lat,lng], { icon: createRotatedIcon(heading) }).addTo(map).bindPopup(`<strong>${name}</strong><br>${updated}`);
                    else markers[id] = L.marker([lat,lng]).addTo(map).bindPopup(`<strong>${name}</strong><br>${updated}`);
                }
                try { if (!polylinesByCollector[id]) polylinesByCollector[id] = L.polyline([[lat,lng]], { color: '#28a745', weight: 4, opacity: 0.8 }).addTo(map); } catch (e) { console.warn('Create polyline error', e); }
            }
        }
    }

    loadCollectors();

    // Toggle paths button
    (function addToggle(){
        const btn = document.createElement('button');
        btn.className = 'btn btn-outline-info btn-sm';
        btn.style.position = 'absolute';
        btn.style.right = '18px';
        btn.style.top = '18px';
        btn.innerHTML = '<i class="fas fa-route me-1"></i>Toggle Paths';
        document.body.appendChild(btn);
        let visible = true;
        btn.addEventListener('click', () => {
            visible = !visible;
            for (const id in polylinesByCollector) {
                const p = polylinesByCollector[id];
                if (visible) map.addLayer(p); else map.removeLayer(p);
            }
        });
    })();

    // Pusher updates
    try {
        if (typeof Pusher !== 'undefined' && PUSHER_KEY) {
            const pusher = new Pusher(PUSHER_KEY, { cluster: PUSHER_CLUSTER, forceTLS: PUSHER_USE_TLS });
            const channel = pusher.subscribe('collectors-channel');
            channel.bind('collector-location', function(data){
                const id = String(data.collector_id);
                const lat = parseFloat(data.latitude), lng = parseFloat(data.longitude);
                const updated = data.updated_at ? new Date(data.updated_at).toLocaleString() : new Date().toLocaleString();
                if (!lat || !lng) return;
                const heading = (data.heading !== undefined && data.heading !== null) ? parseFloat(data.heading) : null;
                if (markers[id]) {
                    markers[id].setLatLng([lat,lng]);
                    markers[id].setPopupContent((data.name || ('Collector ' + id)) + '<br>' + updated);
                    if (heading !== null) markers[id].setIcon(createRotatedIcon(heading));
                } else {
                    if (heading !== null) markers[id] = L.marker([lat,lng], { icon: createRotatedIcon(heading) }).addTo(map).bindPopup((data.name || ('Collector ' + id)) + '<br>' + updated);
                    else markers[id] = L.marker([lat,lng]).addTo(map).bindPopup((data.name || ('Collector ' + id)) + '<br>' + updated);
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
                // Update list timestamp by collector id
                const item = document.querySelector(`#collectorList [data-collector-id='${id}']`);
                if (item) {
                    const small = item.querySelector('.last-seen');
                    if (small) small.textContent = updated;
                }
            });
        }
    } catch (e) { console.warn('Pusher init failed', e); }
    </script>
</body>
</html>

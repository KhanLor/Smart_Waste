<?php
require_once '../../config/config.php';
require_login();
if (($_SESSION['role'] ?? '') !== 'collector') {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$username = $_SESSION['username'] ?? 'Collector';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Map View - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <style>
        #map { height: 80vh; }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <meta name="referrer" content="no-referrer" />
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
</head>
<body class="role-collector">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-2 sidebar text-dark p-0">
                <div class="p-3">
                    <h4><?php echo APP_NAME; ?></h4>
                    <hr>
                    <nav class="nav flex-column">
                        <a class="nav-link text-dark" href="index.php"><i class="fas fa-truck me-2"></i>Dashboard</a>
                        <a class="nav-link text-dark" href="routes.php"><i class="fas fa-route me-2"></i>My Routes</a>
                        <a class="nav-link text-dark" href="collections.php"><i class="fas fa-tasks me-2"></i>Collections</a>
                        <a class="nav-link text-dark active" href="map.php"><i class="fas fa-map me-2"></i>Map View</a>
                        <a class="nav-link text-dark" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a>
                        <a class="nav-link text-dark" href="../../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
                    </nav>
                </div>
            </div>
            <div class="col-md-10">
                <div class="p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2>Map View</h2>
                        <div class="text-muted">Welcome, <?php echo htmlspecialchars($username); ?>!</div>
                    </div>
                    <div class="mb-3">
                        <button id="locateBtn" class="btn btn-outline-primary btn-sm"><i class="fas fa-location-crosshairs me-1"></i>My Location</button>
                        <button id="reloadBtn" class="btn btn-outline-secondary btn-sm"><i class="fas fa-rotate me-1"></i>Reload Stops</button>
                    </div>
                    <div id="map" class="mb-3"></div>
                    <div id="legend" class="small text-muted">Note: Locations are geocoded from street/area; accuracy may vary.</div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="https://js.pusher.com/7.2/pusher.min.js"></script>
    <script>
    const CURRENT_COLLECTOR_ID = <?php echo json_encode($_SESSION['user_id'] ?? 0); ?>;
    const PUSHER_KEY = <?php echo json_encode(PUSHER_KEY); ?>;
    const PUSHER_CLUSTER = <?php echo json_encode(PUSHER_CLUSTER); ?>;
    const PUSHER_USE_TLS = <?php echo PUSHER_USE_TLS ? 'true' : 'false'; ?>;

    const map = L.map('map').setView([14.5995, 120.9842], 12); // Default: Manila
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    let stopsLayer = L.layerGroup().addTo(map);
    let routeLayer = L.polyline([], { color: '#0d6efd' }).addTo(map);
    let meMarker = null;
    let collectorsLayer = L.layerGroup().addTo(map);
    const markersByCollector = {}; // keyed by collector_id
    const polylinesByCollector = {}; // keyed by collector_id -> L.Polyline
    const MAX_PATH_POINTS = 200; // keep last N points to avoid memory growth

    // Collector truck icon
    const collectorIcon = L.icon({
        iconUrl: '../../assets/collector_icon.png',
        iconSize: [40, 40],
        iconAnchor: [20, 20],
        popupAnchor: [0, -18]
    });
    // Helper: create a rotated icon marker using CSS transform on the DOM element
    function createRotatingMarker(latlng, headingDeg, opts = {}) {
        const el = document.createElement('img');
        el.src = collectorIcon.options.iconUrl;
        el.style.width = (collectorIcon.options.iconSize[0] || 40) + 'px';
        el.style.height = (collectorIcon.options.iconSize[1] || 40) + 'px';
        el.style.transform = `rotate(${headingDeg || 0}deg)`;
        el.style.transformOrigin = '50% 50%';
        el.style.pointerEvents = 'auto';
        const icon = L.divIcon({
            className: 'collector-icon-wrapper',
            html: el.outerHTML,
            iconSize: collectorIcon.options.iconSize,
            iconAnchor: collectorIcon.options.iconAnchor
        });
        return L.marker(latlng, Object.assign({}, opts, { icon }));
    }

    async function geocode(query) {
        const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}`;
        const res = await fetch(url, { headers: { 'Accept': 'application/json' }});
        if (!res.ok) return null;
        const json = await res.json();
        if (!Array.isArray(json) || json.length === 0) return null;
        const best = json[0];
        return [parseFloat(best.lat), parseFloat(best.lon)];
    }

    async function loadStops() {
        try {
            stopsLayer.clearLayers();
            routeLayer.setLatLngs([]);
            const res = await fetch('../../api/get_collector_tasks.php?day=today');
            const data = await res.json();
            if (!data.success) return;

            const coords = [];
            for (const t of data.tasks) {
                let latlng = null;
                if (t.latitude && t.longitude) {
                    latlng = [parseFloat(t.latitude), parseFloat(t.longitude)];
                } else {
                    const q = `${t.street_name || ''} ${t.area || ''}`.trim();
                    if (!q) continue;
                    latlng = await geocode(q);
                    if (latlng) {
                        try {
                            const form = new FormData();
                            form.append('schedule_id', String(t.id));
                            form.append('latitude', String(latlng[0]));
                            form.append('longitude', String(latlng[1]));
                            await fetch('../../api/save_schedule_coords.php', { method: 'POST', body: form });
                        } catch (cacheErr) {
                            console.warn('Failed to cache coords', cacheErr);
                        }
                    }
                }
                try {
                    if (!latlng) continue;
                    const marker = L.marker(latlng).bindPopup(`<strong>${t.collection_time}</strong><br>${(t.area || '')} ${t.street_name ? '- ' + t.street_name : ''}<br>Status: ${(t.status || '').replace('_',' ')}`);
                    marker.addTo(stopsLayer);
                    coords.push(latlng);
                } catch (e) {
                    console.warn('Geocode failed for', q);
                }
            }
            if (coords.length > 0) {
                routeLayer.setLatLngs(coords);
                map.fitBounds(L.latLngBounds(coords), { padding: [20, 20] });
            }
        } catch (e) {
            console.error('Failed to load stops', e);
        }
    }

    function locateMe() {
        if (!navigator.geolocation) return;
        navigator.geolocation.getCurrentPosition((pos) => {
            const latlng = [pos.coords.latitude, pos.coords.longitude];
                    if (!meMarker) {
                meMarker = L.marker(latlng, { icon: collectorIcon }).bindPopup('You are here');
                meMarker.addTo(map);
            } else {
                meMarker.setLatLng(latlng);
            }
            map.setView(latlng, 14);
        }, (err) => console.warn(err), { enableHighAccuracy: true });
    }

    document.getElementById('locateBtn').addEventListener('click', locateMe);
    document.getElementById('reloadBtn').addEventListener('click', loadStops);

    // Toggle to show/hide collector paths
    const togglePathBtn = document.createElement('button');
    togglePathBtn.className = 'btn btn-outline-info btn-sm ms-2';
    togglePathBtn.id = 'togglePathsBtn';
    togglePathBtn.innerHTML = '<i class="fas fa-route me-1"></i>Toggle Paths';
    document.getElementById('reloadBtn').parentNode.appendChild(togglePathBtn);
    let pathsVisible = true;
    togglePathBtn.addEventListener('click', () => {
        pathsVisible = !pathsVisible;
        for (const id in polylinesByCollector) {
            const p = polylinesByCollector[id];
            if (pathsVisible) map.addLayer(p); else map.removeLayer(p);
        }
    });

    loadStops();

    // If URL parameters lat & lng are provided (from 'View on Map'), center on them
    (function centerFromParams(){
        try {
            const params = new URLSearchParams(window.location.search);
            const lat = parseFloat(params.get('lat'));
            const lng = parseFloat(params.get('lng'));
            if (isFinite(lat) && isFinite(lng)) {
                map.setView([lat,lng], 16);
                const m = L.marker([lat,lng]).addTo(map).bindPopup('Selected stop').openPopup();
            }
        } catch (e) { /* ignore */ }
    })();

    // --- Realtime: Pusher subscribe to collector location updates ---
    try {
        if (typeof Pusher !== 'undefined' && PUSHER_KEY) {
            const pusher = new Pusher(PUSHER_KEY, { cluster: PUSHER_CLUSTER, forceTLS: PUSHER_USE_TLS });
            const channel = pusher.subscribe('collectors-channel');
            channel.bind('collector-location', function(data) {
                try {
                    // Debug: show incoming pusher payloads for troubleshooting
                    console.debug('[PUSHER] collector-location received', data);
                    if (!data || !data.collector_id) return;
                    const id = String(data.collector_id);
                    const lat = parseFloat(data.latitude);
                    const lng = parseFloat(data.longitude);
                    if (!isFinite(lat) || !isFinite(lng)) return;

                    // Update or create marker for this collector
                    const heading = parseFloat(data.heading) || 0;
                    if (markersByCollector[id]) {
                        markersByCollector[id].setLatLng([lat, lng]);
                        // Update rotation by replacing icon
                        const newMarker = createRotatingMarker([lat, lng], heading, { title: 'Collector ' + id });
                        newMarker.addTo(collectorsLayer);
                        collectorsLayer.removeLayer(markersByCollector[id]);
                        markersByCollector[id] = newMarker;
                        // Append to polyline for this collector
                        try {
                            if (!polylinesByCollector[id]) {
                                polylinesByCollector[id] = L.polyline([[lat, lng]], { color: '#28a745', weight: 4, opacity: 0.8 }).addTo(map);
                            } else {
                                const pts = polylinesByCollector[id].getLatLngs();
                                pts.push(L.latLng(lat, lng));
                                // limit length
                                if (pts.length > MAX_PATH_POINTS) pts.splice(0, pts.length - MAX_PATH_POINTS);
                                polylinesByCollector[id].setLatLngs(pts);
                            }
                        } catch (e) { console.warn('Polyline update error', e); }
                    } else {
                        const m = createRotatingMarker([lat, lng], heading, { title: 'Collector ' + id }).bindPopup('Collector: ' + id);
                        m.addTo(collectorsLayer);
                        markersByCollector[id] = m;
                        // create polyline starting at this location
                        try {
                            polylinesByCollector[id] = L.polyline([[lat, lng]], { color: '#28a745', weight: 4, opacity: 0.8 }).addTo(map);
                        } catch (e) { console.warn('Create polyline error', e); }
                    }
                } catch (e) { console.warn('Pusher handler error', e); }
            });
        }
    } catch (e) { console.warn('Pusher init failed', e); }

    // --- Realtime: watchPosition (collector) -> POST to server and update local marker ---
    if (navigator.geolocation) {
        const watchId = navigator.geolocation.watchPosition(async (pos) => {
            const latlng = [pos.coords.latitude, pos.coords.longitude];
            const heading = (pos.coords.heading !== null && !isNaN(pos.coords.heading)) ? pos.coords.heading : 0;
            // Debug: show watchPosition update
            console.debug('[GEO] watchPosition', { lat: latlng[0], lng: latlng[1], heading });
            if (!meMarker) {
                meMarker = createRotatingMarker(latlng, heading, { title: 'You' }).bindPopup('You are here');
                meMarker.addTo(map);
            } else {
                meMarker.setLatLng(latlng);
            }
            // Append to my collector polyline as well (if collector id known)
            try {
                const myId = String(CURRENT_COLLECTOR_ID || 'me');
                if (!polylinesByCollector[myId]) {
                    polylinesByCollector[myId] = L.polyline([latlng], { color: '#ffc107', weight: 4, opacity: 0.9 }).addTo(map);
                } else {
                    const pts = polylinesByCollector[myId].getLatLngs();
                    pts.push(L.latLng(latlng[0], latlng[1]));
                    if (pts.length > MAX_PATH_POINTS) pts.splice(0, pts.length - MAX_PATH_POINTS);
                    polylinesByCollector[myId].setLatLngs(pts);
                }
            } catch (e) { console.warn('Update my path failed', e); }
            // center map the first time
            // send to server (best-effort)
            try {
                const payload = { latitude: latlng[0], longitude: latlng[1], heading };
                // Debug: log payload and perform POST
                console.debug('[POST] update_collector_location payload', payload);
                const resp = await fetch('../../api/update_collector_location.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                // Try to parse json response for debugging
                try {
                    const json = await resp.json();
                    console.debug('[POST] update_collector_location response', resp.status, json);
                } catch (parseErr) {
                    console.warn('[POST] update_collector_location non-JSON response', resp.status, resp.statusText);
                }
            } catch (e) {
                console.warn('Failed to post location', e);
            }
        }, (err) => console.warn('watchPosition error', err), { enableHighAccuracy: true, maximumAge: 3000 });
    }
    </script>
</body>
</html>



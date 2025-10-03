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
    <title>My Routes - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
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
                        <a class="nav-link text-dark active" href="routes.php"><i class="fas fa-route me-2"></i>My Routes</a>
                        <a class="nav-link text-dark" href="collections.php"><i class="fas fa-tasks me-2"></i>Collections</a>
                        <a class="nav-link text-dark" href="map.php"><i class="fas fa-map me-2"></i>Map View</a>
                        <a class="nav-link text-dark" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a>
                        <a class="nav-link text-dark" href="../../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
                    </nav>
                </div>
            </div>
            <div class="col-md-10">
                <div class="p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2>My Routes</h2>
                        <div class="text-muted">Welcome, <?php echo htmlspecialchars($username); ?>!</div>
                    </div>
                    <form id="filters" class="row g-2 align-items-end mb-3">
                        <div class="col-sm-4">
                            <label for="day" class="form-label">Day</label>
                            <select id="day" class="form-select">
                                <option value="today">Today</option>
                                <option value="monday">Monday</option>
                                <option value="tuesday">Tuesday</option>
                                <option value="wednesday">Wednesday</option>
                                <option value="thursday">Thursday</option>
                                <option value="friday">Friday</option>
                                <option value="saturday">Saturday</option>
                                <option value="sunday">Sunday</option>
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <label for="status" class="form-label">Status</label>
                            <select id="status" class="form-select">
                                <option value="">All</option>
                                <option value="active">Active</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="pending">Pending</option>
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <button id="apply" type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>Apply</button>
                        </div>
                    </form>
                    <div class="card">
                        <div class="card-body">
                            <div id="summary" class="mb-2 small text-muted"></div>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle">
                                    <thead>
                                        <tr>
                                            <th>Time</th>
                                            <th>Area / Street</th>
                                            <th>Waste</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="rows"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    async function loadRoutes() {
        const day = document.getElementById('day').value;
        const status = document.getElementById('status').value;
        const url = new URL('../../api/get_collector_tasks.php', window.location.origin);
        url.searchParams.set('day', day);
        if (status) url.searchParams.set('status', status);
        const res = await fetch(url);
        const data = await res.json();
        const tbody = document.getElementById('rows');
        const summary = document.getElementById('summary');
        tbody.innerHTML = '';
        if (data.success && data.tasks.length) {
            summary.textContent = `${data.count} tasks for ${data.day}`;
            // compute distance / ETA summary
            try {
                const pts = data.tasks.map(t => (t.latitude && t.longitude) ? [parseFloat(t.latitude), parseFloat(t.longitude)] : null).filter(Boolean);
                const totalMeters = pts.reduce((acc, cur, idx) => {
                    if (idx === 0) return 0;
                    return acc + haversineDistanceMeters(pts[idx-1], cur);
                }, 0);
                const km = (totalMeters/1000).toFixed(2);
                const avgSpeedKmph = 30; // assumption
                const hours = totalMeters/1000/avgSpeedKmph;
                const etaMinutes = Math.round(hours * 60);
                summary.textContent += ` — Distance: ${km} km, ETA: ${etaMinutes} min (est)`;
            } catch (e) { console.warn('Failed to compute ETA', e); }
            for (const t of data.tasks) {
                const tr = document.createElement('tr');
                const lat = t.latitude ? parseFloat(t.latitude) : '';
                const lng = t.longitude ? parseFloat(t.longitude) : '';
                tr.innerHTML = `
                    <td><strong>${t.collection_time}</strong></td>
                    <td>${t.area || ''} ${t.street_name ? '- ' + t.street_name : ''}</td>
                    <td><span class="badge bg-info text-dark">${t.waste_type}</span></td>
                    <td><span class="badge bg-secondary text-uppercase">${(t.status || '').replace('_',' ')}</span></td>
                    <td>
                      <button class="btn btn-sm btn-outline-primary view-map" data-lat="${lat}" data-lng="${lng}">View on Map</button>
                      <a class="btn btn-sm btn-outline-secondary ms-1" target="_blank" href="https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}">Navigate</a>
                      <div class="btn-group ms-2" role="group">
                        <button class="btn btn-sm btn-success action-start" data-id="${t.id}">Start</button>
                        <button class="btn btn-sm btn-warning action-progress" data-id="${t.id}">In Progress</button>
                        <button class="btn btn-sm btn-secondary action-complete" data-id="${t.id}">Complete</button>
                      </div>
                    </td>
                `;
                tbody.appendChild(tr);
            }
            // attach handlers
            attachRowHandlers();
        } else {
            summary.textContent = 'No tasks found.';
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = 4;
            td.className = 'text-center text-muted';
            td.textContent = 'No tasks.';
            tr.appendChild(td);
            tbody.appendChild(tr);
        }
    }

    function haversineDistanceMeters(a, b) {
        // a,b = [lat, lng]
        const R = 6371000;
        const toRad = (d) => d * Math.PI / 180;
        const dLat = toRad(b[0] - a[0]);
        const dLon = toRad(b[1] - a[1]);
        const lat1 = toRad(a[0]);
        const lat2 = toRad(b[0]);
        const sinDLat = Math.sin(dLat/2);
        const sinDLon = Math.sin(dLon/2);
        const aa = sinDLat*sinDLat + Math.cos(lat1)*Math.cos(lat2)*sinDLon*sinDLon;
        const c = 2 * Math.atan2(Math.sqrt(aa), Math.sqrt(1-aa));
        return R * c;
    }

    function attachRowHandlers() {
        document.querySelectorAll('.view-map').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const lat = btn.getAttribute('data-lat');
                const lng = btn.getAttribute('data-lng');
                if (!lat || !lng) { alert('No coordinates for this stop'); return; }
                // open map and pass params so it centers on the stop
                const url = new URL('map.php', window.location.href);
                url.searchParams.set('lat', lat);
                url.searchParams.set('lng', lng);
                window.open(url.toString(), '_blank');
            });
        });

        document.querySelectorAll('.action-start').forEach(b => b.addEventListener('click', () => updateTaskStatus(b.getAttribute('data-id'), 'in_progress')));
        document.querySelectorAll('.action-progress').forEach(b => b.addEventListener('click', () => updateTaskStatus(b.getAttribute('data-id'), 'in_progress')));
        document.querySelectorAll('.action-complete').forEach(b => b.addEventListener('click', () => updateTaskStatus(b.getAttribute('data-id'), 'completed')));

        // export buttons: add if not present
        if (!document.getElementById('exportBtns')) {
            const exportWrap = document.createElement('div');
            exportWrap.id = 'exportBtns';
            exportWrap.className = 'mt-2 mb-2';
            exportWrap.innerHTML = `<button id="exportJson" class="btn btn-outline-primary btn-sm me-2">Export JSON</button><button id="exportGpx" class="btn btn-outline-secondary btn-sm">Export GPX</button>`;
            document.querySelector('.card-body').insertBefore(exportWrap, document.querySelector('.table-responsive'));
            document.getElementById('exportJson').addEventListener('click', exportJson);
            document.getElementById('exportGpx').addEventListener('click', exportGpx);
        }
    }

    async function updateTaskStatus(id, status) {
        try {
            const res = await fetch('../../api/update_task_status.php', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id, status: status })
            });
            const j = await res.json();
            if (j.success) { loadRoutes(); }
            else alert('Failed to update: ' + (j.error || 'unknown'));
        } catch (e) { console.warn('Update failed', e); alert('Update failed'); }
    }

    function exportJson() {
        const day = document.getElementById('day').value;
        fetch(`../../api/get_collector_tasks.php?day=${encodeURIComponent(day)}`).then(r=>r.json()).then(data=>{
            if (!data.success) return alert('No tasks to export');
            const blob = new Blob([JSON.stringify(data.tasks, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a'); a.href = url; a.download = `routes_${day}.json`; a.click(); URL.revokeObjectURL(url);
        });
    }

    function exportGpx() {
        const day = document.getElementById('day').value;
        fetch(`../../api/get_collector_tasks.php?day=${encodeURIComponent(day)}`).then(r=>r.json()).then(data=>{
            if (!data.success) return alert('No tasks to export');
            const gpxParts = ['<?xml version="1.0" encoding="UTF-8"?>','<gpx version="1.1" creator="SmartWaste">','<trk>','<name>Route</name>','<trkseg>'];
            for (const t of data.tasks) {
                if (t.latitude && t.longitude) {
                    gpxParts.push(`<trkpt lat="${t.latitude}" lon="${t.longitude}"><time>${new Date().toISOString()}</time></trkpt>`);
                }
            }
            gpxParts.push('</trkseg>','</trk>','</gpx>');
            const blob = new Blob([gpxParts.join('\n')], { type: 'application/gpx+xml' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a'); a.href = url; a.download = `routes_${day}.gpx`; a.click(); URL.revokeObjectURL(url);
        });
    }

    document.getElementById('filters').addEventListener('submit', (e) => { e.preventDefault(); loadRoutes(); });
    document.addEventListener('DOMContentLoaded', loadRoutes);
    </script>
</body>
</html>



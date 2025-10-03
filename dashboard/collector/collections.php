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
    <title>Collections - <?php echo APP_NAME; ?></title>
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
                        <a class="nav-link text-dark" href="routes.php"><i class="fas fa-route me-2"></i>My Routes</a>
                        <a class="nav-link text-dark active" href="collections.php"><i class="fas fa-tasks me-2"></i>Collections</a>
                        <a class="nav-link text-dark" href="map.php"><i class="fas fa-map me-2"></i>Map View</a>
                        <a class="nav-link text-dark" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a>
                        <a class="nav-link text-dark" href="../../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
                    </nav>
                </div>
            </div>
            <div class="col-md-10">
                <div class="p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2>Collections</h2>
                        <div class="text-muted">Welcome, <?php echo htmlspecialchars($username); ?>!</div>
                    </div>
                    <form id="filters" class="row g-2 align-items-end mb-3">
                        <div class="col-sm-4">
                            <label class="form-label">From</label>
                            <input type="date" id="from" class="form-control" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label">To</label>
                            <input type="date" id="to" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-sm-2">
                            <label class="form-label">Status</label>
                            <select id="status" class="form-select">
                                <option value="">All</option>
                                <option value="completed">Completed</option>
                                <option value="missed">Missed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="col-sm-2">
                            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>Apply</button>
                        </div>
                    </form>
                    <div class="mb-2 d-flex justify-content-between align-items-center">
                        <div id="summary" class="small text-muted"></div>
                        <button id="exportCsv" class="btn btn-sm btn-outline-secondary"><i class="fas fa-file-csv me-1"></i>Export CSV</button>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm align-middle">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
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
    async function loadHistory() {
        const params = new URLSearchParams();
        params.set('from', document.getElementById('from').value);
        params.set('to', document.getElementById('to').value);
        const status = document.getElementById('status').value;
        if (status) params.set('status', status);
        const res = await fetch('../../api/get_collector_history.php?' + params.toString());
        const data = await res.json();
        const tbody = document.getElementById('rows');
        const summary = document.getElementById('summary');
        tbody.innerHTML = '';
                if (data.success && data.items.length) {
            summary.textContent = `${data.count} records`;
            for (const it of data.items) {
                const tr = document.createElement('tr');
                const scheduleId = it.schedule_id || it.id; // fallback if schedule_id missing
                const statusText = (it.status || '').replace('_',' ');

                // Actions: Pending, Start (in_progress), Complete
                let actionsHtml = '';
                if ((it.status || '') === 'completed') {
                    actionsHtml = '<span class="text-success small">Completed</span>';
                } else {
                    // include delete action per history record (use it.id as history id)
                    const historyId = it.id;
                    actionsHtml = `
                        <div class="btn-group" role="group" aria-label="Status actions">
                            <button type="button" class="btn btn-sm btn-outline-secondary status-action" data-id="${scheduleId}" data-status="pending">Pending</button>
                            <button type="button" class="btn btn-sm btn-outline-primary status-action" data-id="${scheduleId}" data-status="in_progress">Start</button>
                            <button type="button" class="btn btn-sm btn-success status-action" data-id="${scheduleId}" data-status="completed">Complete</button>
                            <button type="button" class="btn btn-sm btn-danger ms-2 delete-action" data-history-id="${historyId}">Delete</button>
                        </div>
                    `;
                }

                tr.innerHTML = `
                    <td>${it.date}</td>
                    <td>${it.time || ''}</td>
                    <td>${it.area || ''} ${it.street_name ? '- ' + it.street_name : ''}</td>
                    <td><span class="badge bg-info text-dark">${it.waste_type}</span></td>
                    <td><span class="badge bg-secondary text-uppercase">${statusText}</span></td>
                    <td>${actionsHtml}</td>
                `;
                tbody.appendChild(tr);
            }
                } else {
            summary.textContent = 'No records found.';
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = 6; // include Actions column
            td.className = 'text-center text-muted';
            td.textContent = 'No records.';
            tr.appendChild(td);
            tbody.appendChild(tr);
        }
    }

    function exportCsv() {
        const rows = [['Date','Time','Area/Street','Waste','Status']];
        document.querySelectorAll('#rows tr').forEach(tr => {
            // skip Actions column (last column)
            const tds = Array.from(tr.querySelectorAll('td'));
            if (tds.length < 5) return;
            const cols = tds.slice(0,5).map(td => (td.innerText || '').replace(/\s+/g,' ').trim());
            rows.push(cols);
        });
        const csv = rows.map(r => r.map(v => '"' + v.replace('"','""') + '"').join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'collections.csv';
        a.click();
        URL.revokeObjectURL(url);
    }

    document.getElementById('filters').addEventListener('submit', (e)=>{ e.preventDefault(); loadHistory(); });
    document.getElementById('exportCsv').addEventListener('click', exportCsv);
    document.addEventListener('DOMContentLoaded', loadHistory);

    // Handle status action clicks using event delegation
    document.getElementById('rows').addEventListener('click', async (e) => {
        const btn = e.target.closest('.status-action');
        if (!btn) return;
        const taskId = btn.dataset.id;
        const status = btn.dataset.status;
        if (!taskId || !status) return;
        try {
            btn.disabled = true;
            const form = new FormData();
            form.append('task_id', String(taskId));
            form.append('status', status);
            const res = await fetch('../../api/update_task_status.php', { method: 'POST', body: form });
            const data = await res.json();
            if (data && data.success) {
                await loadHistory();
            } else {
                console.error('Failed to update status', data);
                alert('Failed to update status');
                btn.disabled = false;
            }
        } catch (err) {
            console.error('Error updating status', err);
            alert('Error updating status');
            btn.disabled = false;
        }
    });

    // Delete action handler (delegated)
    document.getElementById('rows').addEventListener('click', async (e) => {
        const del = e.target.closest('.delete-action');
        if (!del) return;
        const historyId = del.dataset.historyId;
        if (!historyId) return;
        if (!confirm('Are you sure you want to delete this collection record? This cannot be undone.')) return;
        try {
            del.disabled = true;
            const form = new FormData();
            form.append('history_id', String(historyId));
            const res = await fetch('../../api/delete_collection.php', { method: 'POST', body: form });
            const data = await res.json();
            if (data && data.success) {
                await loadHistory();
            } else {
                console.error('Failed to delete', data);
                alert('Failed to delete record');
                del.disabled = false;
            }
        } catch (err) {
            console.error('Error deleting record', err);
            alert('Error deleting record');
            del.disabled = false;
        }
    });
    </script>
</body>
</html>



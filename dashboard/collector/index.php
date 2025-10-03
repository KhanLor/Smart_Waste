<?php
require_once '../../config/config.php';
require_login();
if (($_SESSION['role'] ?? '') !== 'collector') {
	header('Location: ' . BASE_URL . 'login.php');
	exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? 'User';
$full_name = $username;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Collector Dashboard - <?php echo APP_NAME; ?></title>
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
                        <a class="nav-link text-dark active" href="index.php"><i class="fas fa-truck me-2"></i>Dashboard</a>
                        <a class="nav-link text-dark" href="routes.php"><i class="fas fa-route me-2"></i>My Routes</a>
                        <a class="nav-link text-dark" href="collections.php"><i class="fas fa-tasks me-2"></i>Collections</a>
                        <a class="nav-link text-dark" href="map.php"><i class="fas fa-map me-2"></i>Map View</a>
                        <a class="nav-link text-dark" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a>
                        <a class="nav-link text-dark" href="../../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
                    </nav>
                </div>
            </div>
            <div class="col-md-10">
                <div class="p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>Collector Dashboard</h2>
                        <div class="text-muted">Welcome, <?php echo htmlspecialchars($full_name); ?>!</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-4">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h4 id="stat-assigned">0</h4>
                                            <p>Today's Collections</p>
                                        </div>
                                        <i class="fas fa-truck fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h4 id="stat-completed">0</h4>
                                            <p>Completed</p>
                                        </div>
                                        <i class="fas fa-check-circle fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card bg-warning text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h4 id="stat-remaining">0</h4>
                                            <p>Remaining</p>
                                        </div>
                                        <i class="fas fa-clock fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h5>Today's Route</h5>
                        </div>
                        <div class="card-body">
                            <p>Your assigned collection route for today:</p>
                            <ul id="today-route-list" class="list-unstyled"></ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1055;">
        <div class="card shadow-sm" id="pushCard" style="min-width: 260px; display:none;">
            <div class="card-body">
                <div class="d-flex align-items-start">
                    <i class="fas fa-bell me-2 mt-1"></i>
                    <div class="flex-grow-1">
                        <div class="fw-semibold">Enable notifications</div>
                        <div class="small text-muted">Get alerts for new/updated assignments.</div>
                    </div>
                </div>
                <div class="mt-2 d-flex justify-content-end">
                    <button class="btn btn-sm btn-outline-secondary me-2" id="dismissPush">Later</button>
                    <button class="btn btn-sm btn-primary" id="enablePush">Enable</button>
                </div>
            </div>
        </div>
    </div>

</body>
<script>
async function loadData() {
    try {
        const [statsRes, tasksRes] = await Promise.all([
            fetch('../../api/get_collector_stats.php'),
            fetch('../../api/get_collector_tasks.php?day=today')
        ]);

        const stats = await statsRes.json();
        if (stats.success) {
            document.getElementById('stat-assigned').textContent = stats.today.assigned ?? 0;
            document.getElementById('stat-completed').textContent = stats.today.completed ?? 0;
            document.getElementById('stat-remaining').textContent = stats.today.remaining ?? 0;
        }

        const tasks = await tasksRes.json();
        const list = document.getElementById('today-route-list');
        list.innerHTML = '';
        if (tasks.success && tasks.tasks.length > 0) {
            tasks.tasks.forEach(t => {
                const li = document.createElement('li');
                li.className = 'mb-2 d-flex justify-content-between align-items-center';
                const left = document.createElement('div');
                const right = document.createElement('div');
                right.className = 'ms-2 flex-shrink-0';

                const statusText = (t.status || '').replace('_',' ');
                left.innerHTML = `<strong>${t.collection_time}</strong> - ${t.area || ''} ${t.street_name ? '- ' + t.street_name : ''} <span class="badge bg-secondary text-uppercase ms-2">${statusText || 'pending'}</span>`;

                // Action buttons
                const startBtn = document.createElement('button');
                startBtn.type = 'button';
                startBtn.className = 'btn btn-sm btn-outline-primary me-2 task-action';
                startBtn.dataset.id = t.id;
                startBtn.dataset.status = 'in_progress';
                startBtn.textContent = 'Start';

                const uploadBtn = document.createElement('button');
                uploadBtn.type = 'button';
                uploadBtn.className = 'btn btn-sm btn-outline-secondary me-2';
                uploadBtn.textContent = 'Upload';
                uploadBtn.addEventListener('click', async () => {
                    try {
                        const input = document.createElement('input');
                        input.type = 'file';
                        input.accept = 'image/*';
                        input.click();
                        input.onchange = async () => {
                            if (!input.files || !input.files[0]) return;
                            const form = new FormData();
                            form.append('task_id', String(t.id));
                            form.append('photo', input.files[0]);
                            const res = await fetch('../../api/upload_evidence.php', { method: 'POST', body: form });
                            const data = await res.json();
                            if (!data || !data.success) {
                                console.error('Upload failed', data);
                            }
                        };
                    } catch(err) {
                        console.error('Upload error', err);
                    }
                });

                const completeBtn = document.createElement('button');
                completeBtn.type = 'button';
                completeBtn.className = 'btn btn-sm btn-success task-action';
                completeBtn.dataset.id = t.id;
                completeBtn.dataset.status = 'completed';
                completeBtn.textContent = 'Complete';

                if ((t.status || '') === 'in_progress') {
                    right.appendChild(uploadBtn);
                    right.appendChild(completeBtn);
                } else if ((t.status || '') === 'completed') {
                    // no actions
                } else {
                    right.appendChild(uploadBtn);
                    right.appendChild(startBtn);
                    right.appendChild(completeBtn);
                }

                li.appendChild(left);
                li.appendChild(right);
                list.appendChild(li);
            });
        } else {
            const li = document.createElement('li');
            li.textContent = 'No tasks assigned for today.';
            list.appendChild(li);
        }
    } catch (e) {
        console.error('Failed to load collector dashboard data', e);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    loadData();

    document.getElementById('today-route-list').addEventListener('click', async (e) => {
        const btn = e.target.closest('.task-action');
        if (!btn) return;
        const taskId = parseInt(btn.dataset.id || '0', 10);
        const status = btn.dataset.status || '';
        if (!taskId || !status) return;
        try {
            btn.disabled = true;
            const form = new FormData();
            form.append('task_id', String(taskId));
            form.append('status', status);
            const res = await fetch('../../api/update_task_status.php', { method: 'POST', body: form });
            const data = await res.json();
            if (data && data.success) {
                await loadData();
            } else {
                console.error('Failed to update status', data);
                btn.disabled = false;
            }
        } catch (err) {
            console.error('Error updating task status', err);
            btn.disabled = false;
        }
    });

    // Push prompt
    try {
        if ('Notification' in window && Notification.permission !== 'granted') {
            const card = document.getElementById('pushCard');
            card.style.display = 'block';
            document.getElementById('dismissPush').onclick = () => card.remove();
            document.getElementById('enablePush').onclick = async () => {
                try {
                    await window.initCollectorPush('<?php echo VAPID_PUBLIC_KEY; ?>');
                } catch (e) { console.error(e); }
                card.remove();
            };
        }
    } catch (e) { /* ignore */ }
});
</script>
<script src="../../assets/js/collector_push.js"></script>
</html>

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
    <style>
        .task-item {
            padding: 16px;
            border: 1px solid #e9ecef;
            border-radius: 12px;
            margin-bottom: 12px;
            background: #fff;
            transition: box-shadow 0.2s ease, transform 0.05s ease;
            display: flex;
            flex-wrap: wrap;
        }
        .task-item:hover {
            box-shadow: 0 6px 18px rgba(16,24,40,0.06);
        }
        .task-info {
            flex: 1 1 280px;
            min-width: 220px;
        }
        .task-time {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
        }
        .task-location {
            color: #6b7280;
            font-size: 0.95rem;
        }
        .task-actions {
            display: flex;
            gap: 8px;
            align-items: center;
            margin-left: auto;
            flex: 0 1 340px;
            justify-content: flex-end;
            flex-wrap: wrap; /* allow buttons to wrap on narrow screens */
        }
        .task-actions button {
            min-width: 80px;
            font-weight: 600;
            transition: transform 0.06s ease, opacity 0.2s ease;
            border-radius: 10px;
        }
        .task-actions button.processing {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .task-actions button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
        .task-actions button.processing {
            pointer-events: none;
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-badge.pending {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-badge.in_progress {
            background-color: #cfe2ff;
            color: #084298;
        }
        .status-badge.completed {
            background-color: #d1e7dd;
            color: #0f5132;
        }
        .status-badge.missed {
            background-color: #f8d7da;
            color: #842029;
        }
        .status-badge.cancelled {
            background-color: #e9ecef; /* light gray */
            color: #495057; /* muted dark */
        }

        /* Stat card improvements */
        .stat-card .card-body {
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 96px;
            padding: 18px;
        }
        .stat-card .stat-left {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .stat-card .stat-left h4 {
            margin: 0;
            font-size: 1.45rem;
        }
        .stat-card .stat-icon {
            font-size: 1.6rem;
            opacity: 0.95;
        }

        /* Make buttons more compact on small screens */
        @media (max-width: 576px) {
            .task-actions button { min-width: 0; flex: 1 1 30%; }
            .stat-card .card-body { min-height: 80px; padding: 12px; }
        }
        #today-route-list:empty::after {
            content: 'No tasks assigned for today.';
            display: block;
            padding: 20px;
            text-align: center;
            color: #6c757d;
        }

        /* Mobile responsiveness for task row */
        @media (max-width: 576px) {
            .task-item { padding: 14px; }
            .task-time { font-size: 1rem; }
            .task-actions {
                flex: 1 1 100%;
                width: 100%;
                margin-top: 10px;
                justify-content: stretch;
            }
            .task-actions button {
                flex: 1 1 33%;
                min-width: 0;
            }
        }
        
        /* Missed Collection Modal Styles */
        .image-preview {
            max-width: 100%;
            max-height: 200px;
            margin-top: 10px;
            border-radius: 8px;
            display: none;
        }
        .image-preview.show {
            display: block;
        }

        /* Small in-page toast notifications for immediate feedback */
        .app-toast {
            min-width: 220px;
            margin-bottom: 8px;
            padding: 10px 14px;
            border-radius: 8px;
            box-shadow: 0 6px 18px rgba(16,24,40,0.08);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: opacity 0.25s ease, transform 0.25s ease;
        }
        .app-toast.success { background-color: #d1e7dd; color: #0f5132; }
        .app-toast.danger { background-color: #f8d7da; color: #842029; }
        .app-toast.default { background-color: #e9ecef; color: #495057; }
        .app-toast.hide { opacity: 0; transform: translateY(-8px); }
    </style>
</head>
<body class="role-collector">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-2 sidebar text-dark p-0">
                <?php include __DIR__ . '/_sidebar.php'; ?>
            </div>
            <div class="col-md-10">
                <div class="p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>Collector Dashboard</h2>
                        <div class="text-muted">Welcome, <?php echo htmlspecialchars($full_name); ?>!</div>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-6 col-sm-4 col-md-3 col-lg-2 mb-3">
                            <div class="card bg-primary text-white stat-card">
                                <div class="card-body">
                                    <div class="stat-left">
                                        <h4 id="stat-assigned">0</h4>
                                        <p class="mb-0">Today's Collections</p>
                                    </div>
                                    <i class="fas fa-truck stat-icon"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-4 col-md-3 col-lg-2 mb-3">
                            <div class="card bg-success text-white stat-card">
                                <div class="card-body">
                                    <div class="stat-left">
                                        <h4 id="stat-completed">0</h4>
                                        <p class="mb-0">Completed</p>
                                    </div>
                                    <i class="fas fa-check-circle stat-icon"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-4 col-md-3 col-lg-2 mb-3">
                            <div class="card bg-danger text-white stat-card">
                                <div class="card-body">
                                    <div class="stat-left">
                                        <h4 id="stat-missed">0</h4>
                                        <p class="mb-0">Missed</p>
                                    </div>
                                    <i class="fas fa-times-circle stat-icon"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-4 col-md-3 col-lg-2 mb-3">
                            <div class="card bg-warning text-white stat-card">
                                <div class="card-body">
                                    <div class="stat-left">
                                        <h4 id="stat-remaining">0</h4>
                                        <p class="mb-0">Remaining</p>
                                    </div>
                                    <i class="fas fa-clock stat-icon"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-4 col-md-3 col-lg-2 mb-3">
                            <div class="card bg-secondary text-white stat-card">
                                <div class="card-body">
                                    <div class="stat-left">
                                        <h4 id="stat-cancelled">0</h4>
                                        <p class="mb-0">Cancelled</p>
                                    </div>
                                    <i class="fas fa-ban stat-icon"></i>
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

    <!-- Missed Collection Modal -->
    <div class="modal fade" id="missedModal" tabindex="-1" aria-labelledby="missedModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="missedModalLabel">
                        <i class="fas fa-exclamation-triangle me-2"></i>Report Missed Collection
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="missedForm">
                        <input type="hidden" id="missedTaskId" name="task_id">
                        
                        <div class="mb-3">
                            <label for="missedReason" class="form-label">
                                Reason for Missing Collection <span class="text-danger">*</span>
                            </label>
                            <textarea 
                                class="form-control" 
                                id="missedReason" 
                                name="reason" 
                                rows="4" 
                                placeholder="Please explain why this collection was missed (e.g., blocked access, vehicle issue, resident not available, etc.)"
                                required
                            ></textarea>
                            <div class="form-text">Provide a detailed explanation of why the collection could not be completed.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="missedImage" class="form-label">
                                Upload Evidence Image <span class="text-muted">(Optional)</span>
                            </label>
                            <input 
                                class="form-control" 
                                type="file" 
                                id="missedImage" 
                                name="image" 
                                accept="image/*"
                            >
                            <div class="form-text">Upload a photo showing the reason for the missed collection (max 5MB).</div>
                            <img id="imagePreview" class="image-preview" alt="Image preview">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-danger" id="submitMissed">
                        <i class="fas fa-check me-1"></i>Submit Report
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Cancel Collection Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-secondary text-white">
                    <h5 class="modal-title" id="cancelModalLabel">
                        <i class="fas fa-ban me-2"></i>Cancel Collection
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="cancelForm">
                        <input type="hidden" id="cancelTaskId" name="task_id">

                        <div class="mb-3">
                            <label for="cancelReason" class="form-label">
                                Reason for Cancellation <span class="text-danger">*</span>
                            </label>
                            <textarea
                                class="form-control"
                                id="cancelReason"
                                name="reason"
                                rows="4"
                                placeholder="Please explain why this collection is being cancelled (e.g., route changed, double-assigned, vehicle breakdown, etc.)"
                                required
                            ></textarea>
                            <div class="form-text">Provide a brief explanation for audit and notification purposes.</div>
                        </div>

                        <div class="mb-3">
                            <label for="cancelImage" class="form-label">
                                Upload Evidence Image <span class="text-muted">(Optional)</span>
                            </label>
                            <input
                                class="form-control"
                                type="file"
                                id="cancelImage"
                                name="image"
                                accept="image/*"
                            >
                            <div class="form-text">Upload a photo showing the reason for the cancellation (max 5MB).</div>
                            <img id="cancelImagePreview" class="image-preview" alt="Image preview">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-dark" id="submitCancel">
                        <i class="fas fa-check me-1"></i>Submit
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- In-page toast container -->
    <div id="appToasts" class="position-fixed bottom-0 end-0 p-3" style="z-index:1060; pointer-events: none;">
        <!-- toasts will be injected here -->
    </div>
</body>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    /* Simple toast helper: create a small message and auto-dismiss after 3s */
    function showToast(kind, text) {
        try {
            const container = document.getElementById('appToasts');
            if (!container) return;
            const div = document.createElement('div');
            const cls = kind === 'success' ? 'success' : (kind === 'danger' ? 'danger' : 'default');
            div.className = `app-toast ${cls}`;
            div.style.pointerEvents = 'auto';
            // Emoji prefixes to match requirement
            let prefix = '';
            if (kind === 'success') prefix = '✅ ';
            else if (kind === 'danger') prefix = '❌ ';
            else prefix = '⚠️ ';
            div.textContent = prefix + text;

            container.appendChild(div);

            // Auto-dismiss after 3 seconds (3000ms)
            setTimeout(() => {
                div.classList.add('hide');
                setTimeout(() => div.remove(), 300);
            }, 3000);
        } catch (e) { console.error('Toast error', e); }
    }

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
            document.getElementById('stat-missed').textContent = stats.today.missed ?? 0;
            document.getElementById('stat-remaining').textContent = stats.today.remaining ?? 0;
            // If API provides cancelled count, show it; otherwise default to 0
            document.getElementById('stat-cancelled').textContent = stats.today.cancelled ?? 0;
        }

        const tasks = await tasksRes.json();
        const list = document.getElementById('today-route-list');
        list.innerHTML = '';
        if (tasks.success && tasks.tasks.length > 0) {
            tasks.tasks.forEach(t => {
                const li = document.createElement('li');
                li.className = 'task-item d-flex justify-content-between align-items-center';
                
                const left = document.createElement('div');
                left.className = 'task-info';
                
                const right = document.createElement('div');
                right.className = 'task-actions';

                // Determine display status: prefer optimistic local 'in_progress' if present
                const optimisticInProgressKey = `${t.id}-in_progress-${todayStr}`;
                const displayStatus = (processedTasks && processedTasks.has && processedTasks.has(optimisticInProgressKey)) ? 'in_progress' : (t.status || 'pending');
                const statusText = (displayStatus || 'pending').replace('_',' ');
                const statusClass = (displayStatus || 'pending').replace('_','_');
                
                left.innerHTML = `
                    <div class="task-time">
                        <i class="fas fa-clock me-2"></i>${t.collection_time || 'N/A'}
                    </div>
                    <div class="task-location">
                        <i class="fas fa-map-marker-alt me-2"></i>${t.area || ''} ${t.street_name ? '- ' + t.street_name : ''}
                    </div>
                    <div class="mt-2">
                        <span class="status-badge ${statusClass}">${statusText}</span>
                    </div>
                `;

                
                // Action buttons with direct click handlers
                const startBtn = document.createElement('button');
                startBtn.type = 'button';
                startBtn.className = 'btn btn-sm btn-outline-primary';
                startBtn.innerHTML = '<i class="fas fa-play me-1"></i> Start';
                startBtn.onclick = () => handleTaskAction(t.id, 'in_progress', li);

                const completeBtn = document.createElement('button');
                completeBtn.type = 'button';
                completeBtn.className = 'btn btn-sm btn-success';
                completeBtn.innerHTML = '<i class="fas fa-check me-1"></i> Complete';
                completeBtn.onclick = () => handleTaskAction(t.id, 'completed', li);

                const missedBtn = document.createElement('button');
                missedBtn.type = 'button';
                missedBtn.className = 'btn btn-sm btn-danger';
                missedBtn.innerHTML = '<i class="fas fa-times me-1"></i> Missed';
                missedBtn.onclick = () => handleTaskAction(t.id, 'missed', li);

                // Cancel button - opens cancel modal to collect reason
                const cancelBtn = document.createElement('button');
                cancelBtn.type = 'button';
                cancelBtn.className = 'btn btn-sm btn-secondary';
                cancelBtn.innerHTML = '<i class="fas fa-ban me-1"></i> Cancel';
                cancelBtn.onclick = () => handleTaskAction(t.id, 'cancelled', li);

                if ((displayStatus || '') === 'in_progress') {
                    right.appendChild(completeBtn);
                    right.appendChild(missedBtn);
                    right.appendChild(cancelBtn);
                } else if ((displayStatus || '') === 'completed') {
                    // Show completed message only - no buttons
                    right.innerHTML = '<span class="text-success fw-bold"><i class="fas fa-check-circle me-1"></i> Completed</span>';
                } else if ((displayStatus || '') === 'missed') {
                    // Show missed message only - no buttons
                    right.innerHTML = '<span class="text-danger fw-bold"><i class="fas fa-exclamation-circle me-1"></i> Missed</span>';
                } else if ((displayStatus || '') === 'cancelled') {
                    // Cancelled - show cancelled message only
                    right.innerHTML = '<span class="text-secondary fw-bold"><i class="fas fa-ban me-1"></i> Cancelled</span>';
                } else if ((displayStatus || '') === 'pending') {
                    // Pending - show only Start. Outcome buttons (Complete/Missed/Cancel)
                    // must be hidden/disabled until the route is started.
                    right.appendChild(startBtn);
                } else {
                    // Unknown status - default to showing only Start to be safe
                    right.appendChild(startBtn);
                }

                li.appendChild(left);
                li.appendChild(right);
                list.appendChild(li);
            });
        }
    } catch (e) {
        console.error('Failed to load collector dashboard data', e);
    }
}

// Global processing flag to prevent concurrent requests
let isProcessing = false;

// Use today's date as part of the stored action key so repeated weekly schedules
// (which reuse the same schedule id) don't remain locked across days.
const todayStr = new Date().toISOString().slice(0, 10); // YYYY-MM-DD
// Load persisted processed actions but ignore entries from previous dates
const _stored = JSON.parse(localStorage.getItem('processedTasks') || '[]');
const processedTasks = new Set((_stored || []).filter(k => typeof k === 'string' && k.endsWith(`-${todayStr}`)));

function mkKey(id, status) {
    return `${id}-${status}-${todayStr}`;
}

// Function to save processed tasks to localStorage
function saveProcessedTasks() {
    localStorage.setItem('processedTasks', JSON.stringify([...processedTasks]));
}

// Re-enable any buttons currently present in the given row (used on failures)
function enableRowButtons(listItem) {
    if (!listItem) return;
    try {
        const btns = listItem.querySelectorAll('button');
        btns.forEach(btn => {
            btn.disabled = false;
            btn.classList.remove('processing');
            btn.style.opacity = '';
            btn.style.pointerEvents = '';
            btn.style.cursor = '';
        });
    } catch (e) { /* ignore */ }
}

// Handle task actions with proper button disabling
async function handleTaskAction(taskId, status, listItem) {
    console.log('=== BUTTON CLICKED ===');
    console.log('Task ID:', taskId, 'Status:', status);
    console.log('Is Processing:', isProcessing);
    
    // Create a unique key for this action (includes today's date)
    const actionKey = mkKey(taskId, status);
    
    // Block if already processing ANY request
    if (isProcessing) {
        console.log('⛔ BLOCKED: Already processing a request');
        return;
    }
    
    // Block if this specific task-status combo was already processed
    if (processedTasks.has(actionKey)) {
        console.log('⛔ BLOCKED: This action was already completed');
        return;
    }
    
    // Get all buttons in this row
    const allButtons = listItem.querySelectorAll('button');
    
    // Check if the specific button for this status is already disabled
    // We'll compute the clicked button by matching text/icon where possible.
    // Fallback: if any button in row is globally disabled, block to avoid race conditions.
    const anyDisabled = Array.from(allButtons).some(b => b.disabled);
    if (anyDisabled) {
        console.log('⛔ BLOCKED: Buttons already disabled');
        return;
    }
    
    console.log('✅ Processing action...');
    
    // Set global flag immediately
    isProcessing = true;

    // Mark this action as processed immediately
    processedTasks.add(actionKey);
    saveProcessedTasks();

    // Immediate feedback toast only for completed (keep missed/cancelled after modal submission)
    if (status === 'completed') {
        showToast('success', 'Collection Completed.');
    }

    // Disable only the button that initiated this action to avoid hiding other choices while modal is open.
    // We try to find a button whose onclick would trigger this combination by matching the status text in its label.
    let initiatingButton = null;
    try {
        initiatingButton = Array.from(allButtons).find(b => {
            const text = (b.textContent || '').toLowerCase();
            return text.includes((status || '').replace('_',' '));
        });
    } catch (e) { initiatingButton = null; }

    if (!initiatingButton) {
        // fallback: disable the first button to avoid multiple submissions
        initiatingButton = allButtons[0];
    }

    if (initiatingButton) {
        initiatingButton.disabled = true;
        initiatingButton.classList.add('processing');
        initiatingButton.style.opacity = '0.5';
        initiatingButton.style.pointerEvents = 'none';
        initiatingButton.style.cursor = 'not-allowed';
    }
    // Optimistic UI: when starting a task, immediately show outcome buttons
    // (Complete / Missed / Cancel). They will be shown disabled while the
    // start request completes to avoid race conditions.
    if (status === 'in_progress') {
        try {
            const actionContainer = listItem.querySelector('.task-actions');
            if (actionContainer) {
                actionContainer.innerHTML = '';
                const createBtn = (cls, html, st) => {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = cls;
                    b.innerHTML = html;
                    b.disabled = true; // disabled while start request is processing
                    b.classList.add('processing');
                    b.style.opacity = '0.5';
                    b.style.pointerEvents = 'none';
                    b.style.cursor = 'not-allowed';
                    b.onclick = () => handleTaskAction(taskId, st, listItem);
                    return b;
                };
                const completeB = createBtn('btn btn-sm btn-success','<i class="fas fa-check me-1"></i> Complete','completed');
                const missedB = createBtn('btn btn-sm btn-danger','<i class="fas fa-times me-1"></i> Missed','missed');
                const cancelB = createBtn('btn btn-sm btn-secondary','<i class="fas fa-ban me-1"></i> Cancel','cancelled');
                actionContainer.appendChild(completeB);
                actionContainer.appendChild(missedB);
                actionContainer.appendChild(cancelB);
            }
        } catch (e) { console.error(e); }
    }
    
    // Special handling: mark final-state actions as processed so they can't be clicked again
    if (status === 'completed') {
        const missedKey = mkKey(taskId, 'missed');
        const cancelledKey = mkKey(taskId, 'cancelled');
        processedTasks.add(missedKey); // Prevent missed from being clicked
        processedTasks.add(cancelledKey); // Prevent cancelled from being clicked after completion
        saveProcessedTasks();
    console.log('🔒 Locked "Missed" and "Cancelled" actions since "Completed" was clicked');
    } else if (status === 'missed') {
        const completedKey = mkKey(taskId, 'completed');
        const cancelledKey = mkKey(taskId, 'cancelled');
        processedTasks.add(completedKey); // Prevent completed from being clicked
        processedTasks.add(cancelledKey); // Prevent cancelled from being clicked after missed
        saveProcessedTasks();
        console.log('🔒 Locked "Completed" and "Cancelled" actions since "Missed" was clicked');
    } else if (status === 'cancelled') {
        // Cancelled is a final state - lock both completed and missed
        const completedKey = mkKey(taskId, 'completed');
        const missedKey = mkKey(taskId, 'missed');
        processedTasks.add(completedKey);
        processedTasks.add(missedKey);
        // cancelled is already the current action; ensure it's present too
        const cancelledKey = mkKey(taskId, 'cancelled');
        processedTasks.add(cancelledKey);
        saveProcessedTasks();
        console.log('🔒 Locked "Completed", "Missed" and "Cancelled" actions since "Cancelled" was clicked');
    }
    
    // Handle missed - show modal for detailed report
    if (status === 'missed') {
        // Store task info for the modal
        window.currentMissedTask = { taskId, listItem, allButtons, actionKey };
        
        // Reset and show the modal
        document.getElementById('missedTaskId').value = taskId;
        document.getElementById('missedReason').value = '';
        document.getElementById('missedImage').value = '';
        document.getElementById('imagePreview').classList.remove('show');
        
        const modal = new bootstrap.Modal(document.getElementById('missedModal'));
        modal.show();
        
        // Reset processing flag so modal can be submitted
        isProcessing = false;
        
        // Keep reference to the initiating button so we can re-enable only it if modal is cancelled
        window.currentMissedTask.initiatingButton = initiatingButton;

        return; // Exit here, will be handled by modal submit
    }
    
    // Handle cancelled - show modal similar to missed (collect reason + optional image)
    if (status === 'cancelled') {
        // Store task info for the modal
        window.currentCancelTask = { taskId, listItem, allButtons, actionKey };

        // Reset and show the modal
        document.getElementById('cancelTaskId').value = taskId;
        document.getElementById('cancelReason').value = '';
        document.getElementById('cancelImage').value = '';
        document.getElementById('cancelImagePreview').classList.remove('show');

        const modal = new bootstrap.Modal(document.getElementById('cancelModal'));
        modal.show();

        // Reset processing flag so modal can be submitted
        isProcessing = false;

        // Keep reference to the initiating button so we can re-enable only it if modal is cancelled
        window.currentCancelTask.initiatingButton = initiatingButton;

        return; // Exit here, will be handled by modal submit
    }
    
    // Handle complete or start
    try {
        console.log('📤 Sending request for', status, '...');
        const form = new FormData();
        form.append('task_id', String(taskId));
        form.append('status', status);
        const res = await fetch('../../api/update_task_status.php', { method: 'POST', body: form });
        const data = await res.json();
        console.log('📥 Response:', data);
        
        if (data && data.success) {
            console.log('✅ Success! Task status updated to', status);
            // Reload data to refresh UI
            await loadData();
            } else {
            console.error('❌ Failed:', data);
            alert('Failed: ' + (data.error || 'Unknown error'));
            // Remove from processed set on failure
            processedTasks.delete(actionKey);
            // Unlock the opposite action if it was locked
                if (status === 'completed') {
                    const missedKey = mkKey(taskId, 'missed');
                    processedTasks.delete(missedKey);
                } else if (status === 'cancelled') {
                    const missedKey = mkKey(taskId, 'missed');
                    const completedKey = mkKey(taskId, 'completed');
                    processedTasks.delete(missedKey);
                    processedTasks.delete(completedKey);
                }
            saveProcessedTasks();
            // Re-enable buttons on failure (handle optimistic UI case)
            enableRowButtons(listItem);
        }
    } catch (err) {
        console.error('❌ Error:', err);
        alert('Error: ' + err.message);
        // Remove from processed set on error
        processedTasks.delete(actionKey);
        // Unlock the opposite action if it was locked
        if (status === 'completed') {
            const missedKey = mkKey(taskId, 'missed');
            processedTasks.delete(missedKey);
        } else if (status === 'cancelled') {
            const missedKey = mkKey(taskId, 'missed');
            const completedKey = mkKey(taskId, 'completed');
            processedTasks.delete(missedKey);
            processedTasks.delete(completedKey);
        }
        saveProcessedTasks();
        // Re-enable buttons on error (handle optimistic UI case)
        enableRowButtons(listItem);
    } finally {
        isProcessing = false;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    loadData();

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

// Image preview handler
document.getElementById('missedImage').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const preview = document.getElementById('imagePreview');
    
    if (file) {
        // Check file size (5MB max)
        if (file.size > 5 * 1024 * 1024) {
            alert('Image file size must be less than 5MB');
            e.target.value = '';
            preview.classList.remove('show');
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.classList.add('show');
        };
        reader.readAsDataURL(file);
    } else {
        preview.classList.remove('show');
    }
});

// Cancel image preview handler (same behavior as missedImage)
document.getElementById('cancelImage').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const preview = document.getElementById('cancelImagePreview');
    
    if (file) {
        // Check file size (5MB max)
        if (file.size > 5 * 1024 * 1024) {
            alert('Image file size must be less than 5MB');
            e.target.value = '';
            preview.classList.remove('show');
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.classList.add('show');
        };
        reader.readAsDataURL(file);
    } else {
        preview.classList.remove('show');
    }
});

// Handle modal cancel
document.getElementById('missedModal').addEventListener('hidden.bs.modal', function() {
    if (window.currentMissedTask && !window.missedSubmitted) {
        // User cancelled the modal, unlock the actions
        const { taskId, listItem, allButtons, actionKey } = window.currentMissedTask;

    processedTasks.delete(actionKey);
    const completedKey = mkKey(taskId, 'completed');
    processedTasks.delete(completedKey);
    const cancelledKey = mkKey(taskId, 'cancelled');
    processedTasks.delete(cancelledKey);
    saveProcessedTasks();

        // Re-enable only the initiating button if present, otherwise re-enable all
        const initBtn = window.currentMissedTask.initiatingButton;
        if (initBtn) {
            initBtn.disabled = false;
            initBtn.classList.remove('processing');
            initBtn.style.opacity = '';
            initBtn.style.pointerEvents = '';
            initBtn.style.cursor = '';
        } else if (allButtons && allButtons.length) {
            // Re-enable current row buttons (handles optimistic UI case)
            enableRowButtons(listItem);
        }

        console.log('❌ Missed modal cancelled - actions unlocked');
    }
    window.currentMissedTask = null;
    window.missedSubmitted = false;
});

// Handle missed form submission
document.getElementById('submitMissed').addEventListener('click', async function() {
    const reason = document.getElementById('missedReason').value.trim();
    
    if (!reason) {
        alert('Please provide a reason for the missed collection');
        return;
    }
    
    if (!window.currentMissedTask) {
        alert('Error: Task information not found');
        return;
    }
    
    const { taskId, listItem, allButtons, actionKey } = window.currentMissedTask;
    
    // Disable submit button
    this.disabled = true;
    this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Submitting...';
    
    try {
        console.log('📤 Sending missed report...');
        
        const formData = new FormData();
        formData.append('task_id', String(taskId));
        formData.append('status', 'missed');
        formData.append('comment', reason);
        
        // Add image if provided
        const imageFile = document.getElementById('missedImage').files[0];
        if (imageFile) {
            formData.append('evidence_image', imageFile);
        }
        
        const res = await fetch('../../api/update_task_status.php', { method: 'POST', body: formData });
        const data = await res.json();
        console.log('📥 Response:', data);
        
        if (data && data.success) {
            console.log('✅ Success! Task marked as missed');
            window.missedSubmitted = true;
            
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('missedModal'));
            modal.hide();
            
            // Show confirmation toast after successful submission
            try { showToast('danger', 'Collection Missed.'); } catch (e) { /* ignore */ }

            // Reload data to refresh UI
            await loadData();
        } else {
            console.error('❌ Failed:', data);
            alert('Failed: ' + (data.error || 'Unknown error'));
            
            // Unlock actions on failure
        processedTasks.delete(actionKey);
        const completedKey = mkKey(taskId, 'completed');
        processedTasks.delete(completedKey);
        const cancelledKey = mkKey(taskId, 'cancelled');
        processedTasks.delete(cancelledKey);
        saveProcessedTasks();
            
            // Re-enable buttons
            allButtons.forEach(btn => {
                btn.disabled = false;
                btn.style.opacity = '';
                btn.style.pointerEvents = '';
                btn.style.cursor = '';
            });
            
            // Re-enable submit button
            this.disabled = false;
            this.innerHTML = '<i class="fas fa-check me-1"></i>Submit Report';
        }
    } catch (err) {
        console.error('❌ Error:', err);
        alert('Error: ' + err.message);
        
    // Unlock actions on error
    processedTasks.delete(actionKey);
    const completedKey = mkKey(taskId, 'completed');
    processedTasks.delete(completedKey);
    saveProcessedTasks();
        
        // Re-enable buttons
        allButtons.forEach(btn => {
            btn.disabled = false;
            btn.style.opacity = '';
            btn.style.pointerEvents = '';
            btn.style.cursor = '';
        });
        
        // Re-enable submit button
        this.disabled = false;
        this.innerHTML = '<i class="fas fa-check me-1"></i>Submit Report';
    }
});

// Modify handleTaskAction to treat cancelled like missed (show modal while preserving processedTasks and button disabling)
// We patch inside the existing function by adding a branch below where missed is handled.

// Handle cancel form submission
document.getElementById('submitCancel').addEventListener('click', async function() {
    const reason = document.getElementById('cancelReason').value.trim();
    if (!reason) {
        alert('Please provide a reason for the cancellation');
        return;
    }

    if (!window.currentCancelTask) {
        alert('Error: Task information not found');
        return;
    }

    const { taskId, listItem, allButtons, actionKey } = window.currentCancelTask;

    // Disable submit button
    this.disabled = true;
    this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Submitting...';

    try {
        const formData = new FormData();
        formData.append('task_id', String(taskId));
        formData.append('status', 'cancelled');
        formData.append('comment', reason);

        // Add image if provided
        const imageFile = document.getElementById('cancelImage').files[0];
        if (imageFile) {
            formData.append('evidence_image', imageFile);
        }

        const res = await fetch('../../api/update_task_status.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data && data.success) {
            // Close modal
            window.cancelSubmitted = true;
            const modal = bootstrap.Modal.getInstance(document.getElementById('cancelModal'));
            modal.hide();
            // Reload data
            // Show confirmation toast after successful cancellation
            try { showToast('default', 'Collection Cancelled.'); } catch (e) { /* ignore */ }
            await loadData();
        } else {
            console.error('❌ Failed:', data);
            alert('Failed: ' + (data.error || 'Unknown error'));

            // Unlock actions on failure
            processedTasks.delete(actionKey);
            const completedKey = mkKey(taskId, 'completed');
            processedTasks.delete(completedKey);
            const missedKey = mkKey(taskId, 'missed');
            processedTasks.delete(missedKey);
            saveProcessedTasks();

            // Re-enable buttons
            if (allButtons && allButtons.length) {
                allButtons.forEach(btn => {
                    btn.disabled = false;
                    btn.style.opacity = '';
                    btn.style.pointerEvents = '';
                    btn.style.cursor = '';
                });
            }

            // Re-enable submit button
            this.disabled = false;
            this.innerHTML = '<i class="fas fa-check me-1"></i>Submit';
            return;
        }
    } catch (err) {
        console.error(err);
        alert('Error: ' + err.message);
    } finally {
        this.disabled = false;
        this.innerHTML = '<i class="fas fa-check me-1"></i>Submit';
        window.currentCancelTask = null;
    }
});

// When cancel modal is hidden without submitting, unlock actions
document.getElementById('cancelModal').addEventListener('hidden.bs.modal', function() {
    if (window.currentCancelTask && !window.cancelSubmitted) {

        const { taskId, allButtons, actionKey, initiatingButton } = window.currentCancelTask;

        // Remove the action key and any locked final-state keys
        processedTasks.delete(actionKey);
        const completedKey = mkKey(taskId, 'completed');
        const missedKey = mkKey(taskId, 'missed');
        processedTasks.delete(completedKey);
        processedTasks.delete(missedKey);
        saveProcessedTasks();

        // Re-enable only the initiating button if present, otherwise re-enable all
        if (initiatingButton) {
            initiatingButton.disabled = false;
            initiatingButton.classList.remove('processing');
            initiatingButton.style.opacity = '';
            initiatingButton.style.pointerEvents = '';
            initiatingButton.style.cursor = '';
        } else if (allButtons && allButtons.length) {
            allButtons.forEach(btn => {
                btn.disabled = false;
                btn.style.opacity = '';
                btn.style.pointerEvents = '';
                btn.style.cursor = '';
            });
        }

        console.log('❌ Cancel modal dismissed - actions unlocked');
    }
    window.currentCancelTask = null;
    window.cancelSubmitted = false;
});
</script>
<script src="../../assets/js/collector_push.js"></script>
</html>

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
        }
        .task-actions button {
            min-width: 96px;
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
                    
                    <div class="row">
                        <div class="col-md-3 mb-4">
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
                        <div class="col-md-3 mb-4">
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
                        <div class="col-md-3 mb-4">
                            <div class="card bg-danger text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h4 id="stat-missed">0</h4>
                                            <p>Missed</p>
                                        </div>
                                        <i class="fas fa-times-circle fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-4">
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

</body>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
            document.getElementById('stat-missed').textContent = stats.today.missed ?? 0;
            document.getElementById('stat-remaining').textContent = stats.today.remaining ?? 0;
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

                const statusText = (t.status || 'pending').replace('_',' ');
                const statusClass = (t.status || 'pending').replace('_','_');
                
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

                if ((t.status || '') === 'in_progress') {
                    right.appendChild(completeBtn);
                    right.appendChild(missedBtn);
                } else if ((t.status || '') === 'completed') {
                    // Show completed message only - no buttons
                    right.innerHTML = '<span class="text-success fw-bold"><i class="fas fa-check-circle me-1"></i> Completed</span>';
                } else if ((t.status || '') === 'missed') {
                    // Show missed message only - no buttons
                    right.innerHTML = '<span class="text-danger fw-bold"><i class="fas fa-exclamation-circle me-1"></i> Missed</span>';
                } else if ((t.status || '') === 'pending') {
                    // Pending - show all action buttons
                    right.appendChild(startBtn);
                    right.appendChild(completeBtn);
                    right.appendChild(missedBtn);
                } else {
                    // Unknown status - show all action buttons
                    right.appendChild(startBtn);
                    right.appendChild(completeBtn);
                    right.appendChild(missedBtn);
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
    
    // Check if any button is already disabled
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
    
    // IMMEDIATELY disable all buttons in this row
    allButtons.forEach(btn => {
        btn.disabled = true;
        btn.style.opacity = '0.5';
        btn.style.pointerEvents = 'none';
        btn.style.cursor = 'not-allowed';
    });
    
    // Special handling: If clicking completed or missed, permanently mark the opposite action as processed
    if (status === 'completed') {
        const missedKey = mkKey(taskId, 'missed');
        processedTasks.add(missedKey); // Prevent missed from being clicked
        saveProcessedTasks();
        console.log('🔒 Locked "Missed" action since "Completed" was clicked');
    } else if (status === 'missed') {
        const completedKey = mkKey(taskId, 'completed');
        processedTasks.add(completedKey); // Prevent completed from being clicked
        saveProcessedTasks();
        console.log('🔒 Locked "Completed" action since "Missed" was clicked');
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
                const missedKey = `${taskId}-missed`;
                processedTasks.delete(missedKey);
            }
            saveProcessedTasks();
            // Re-enable buttons on failure
            allButtons.forEach(btn => {
                btn.disabled = false;
                btn.style.opacity = '';
                btn.style.pointerEvents = '';
                btn.style.cursor = '';
            });
        }
    } catch (err) {
        console.error('❌ Error:', err);
        alert('Error: ' + err.message);
        // Remove from processed set on error
        processedTasks.delete(actionKey);
        // Unlock the opposite action if it was locked
        if (status === 'completed') {
            const missedKey = `${taskId}-missed`;
            processedTasks.delete(missedKey);
        }
        saveProcessedTasks();
        // Re-enable buttons on error
        allButtons.forEach(btn => {
            btn.disabled = false;
            btn.style.opacity = '';
            btn.style.pointerEvents = '';
            btn.style.cursor = '';
        });
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

// Handle modal cancel
document.getElementById('missedModal').addEventListener('hidden.bs.modal', function() {
    if (window.currentMissedTask && !window.missedSubmitted) {
        // User cancelled the modal, unlock the actions
        const { taskId, allButtons, actionKey } = window.currentMissedTask;

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
            
            // Reload data to refresh UI
            await loadData();
        } else {
            console.error('❌ Failed:', data);
            alert('Failed: ' + (data.error || 'Unknown error'));
            
            // Unlock actions on failure
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
</script>
<script src="../../assets/js/collector_push.js"></script>
</html>

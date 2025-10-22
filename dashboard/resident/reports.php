<?php
require_once __DIR__ . '/../../config/config.php';
require_login();

// Check if user is a resident
if (($_SESSION['role'] ?? '') !== 'resident') {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$success_message = '';
$error_message = '';

// Handle report actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'delete_report') {
        $report_id = $_POST['report_id'] ?? null;
        
        if ($report_id) {
            try {
                // Check if report belongs to user
                $stmt = $conn->prepare("SELECT id FROM waste_reports WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ii", $report_id, $user_id);
                $stmt->execute();
                
                if ($stmt->get_result()->num_rows > 0) {
                    // Delete report images first
                    $stmt = $conn->prepare("DELETE FROM report_images WHERE report_id = ?");
                    $stmt->bind_param("i", $report_id);
                    $stmt->execute();
                    
                    // Delete the report
                    $stmt = $conn->prepare("DELETE FROM waste_reports WHERE id = ? AND user_id = ?");
                    $stmt->bind_param("ii", $report_id, $user_id);
                    
                    if ($stmt->execute()) {
                        $success_message = 'Report deleted successfully.';
                    } else {
                        throw new Exception('Failed to delete report.');
                    }
                } else {
                    throw new Exception('Report not found or access denied.');
                }
            } catch (Exception $e) {
                $error_message = 'Error deleting report: ' . $e->getMessage();
            }
        }
    }
}

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Count unread report notifications
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND reference_type = 'report' AND is_read = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$unread_report_notifications = $stmt->get_result()->fetch_assoc()['count'];

// Get user's waste reports with pagination
$page = $_GET['page'] ?? 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$priority_filter = $_GET['priority'] ?? '';
$date_range = $_GET['date_range'] ?? '';

// Build query
$where_conditions = ["user_id = ?"];
$params = [$user_id];
$param_types = "i";

if (!empty($search)) {
    $where_conditions[] = "(title LIKE ? OR description LIKE ? OR location LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "sss";
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

if (!empty($priority_filter)) {
    $where_conditions[] = "priority = ?";
    $params[] = $priority_filter;
    $param_types .= "s";
}

// Date range filter
if (in_array($date_range, ['7','30','90','365'], true)) {
    $where_conditions[] = "created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
    $params[] = (int)$date_range;
    $param_types .= "i";
}

$where_clause = implode(" AND ", $where_conditions);

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM waste_reports WHERE {$where_clause}";
$stmt = $conn->prepare($count_sql);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$total_reports = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_reports / $limit);

// Get reports
$sql = "SELECT * FROM waste_reports WHERE {$where_clause} ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$param_types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$reports = $stmt->get_result();

// Get unread notifications for each report
$report_notifications = [];
if ($reports->num_rows > 0) {
    $report_ids = [];
    $reports->data_seek(0);
    while ($report = $reports->fetch_assoc()) {
        $report_ids[] = $report['id'];
    }
    
    if (!empty($report_ids)) {
        $placeholders = str_repeat('?,', count($report_ids) - 1) . '?';
        $stmt = $conn->prepare("SELECT reference_id, COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND reference_type = 'report' AND reference_id IN ({$placeholders}) AND is_read = 0 GROUP BY reference_id");
        $bind_params = array_merge([$user_id], $report_ids);
        $bind_types = 'i' . str_repeat('i', count($report_ids));
        $stmt->bind_param($bind_types, ...$bind_params);
        $stmt->execute();
        $notif_result = $stmt->get_result();
        
        while ($notif = $notif_result->fetch_assoc()) {
            $report_notifications[$notif['reference_id']] = $notif['unread_count'];
        }
    }
    
    $reports->data_seek(0);
}

// Get report images for each report
$report_images = [];
if ($reports->num_rows > 0) {
    $report_ids = [];
    $reports->data_seek(0);
    while ($report = $reports->fetch_assoc()) {
        $report_ids[] = $report['id'];
    }
    
    if (!empty($report_ids)) {
        $placeholders = str_repeat('?,', count($report_ids) - 1) . '?';
        $stmt = $conn->prepare("SELECT * FROM report_images WHERE report_id IN ({$placeholders})");
        $stmt->bind_param(str_repeat('i', count($report_ids)), ...$report_ids);
        $stmt->execute();
        $images_result = $stmt->get_result();
        
        while ($image = $images_result->fetch_assoc()) {
            $report_images[$image['report_id']][] = $image;
        }
    }
    
    $reports->data_seek(0);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reports - <?php echo APP_NAME; ?></title>
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
        .report-card {
            border-left: 4px solid #007bff;
            transition: transform 0.2s;
        }
        .report-card:hover {
            transform: translateY(-2px);
        }
        .report-card.high {
            border-left-color: #dc3545;
        }
        .report-card.medium {
            border-left-color: #ffc107;
        }
        .report-card.low {
            border-left-color: #28a745;
        }
        .report-card.urgent {
            border-left-color: #6f42c1;
        }
        .status-badge {
            font-size: 0.8rem;
        }
        .image-thumbnail {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            margin: 2px;
        }
        .filter-card {
            background: #f8f9fa;
            border-radius: 10px;
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
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <h2 class="mb-0">My Reports</h2>
                                <?php if ($unread_report_notifications > 0): ?>
                                    <span class="badge bg-danger rounded-pill" style="font-size: 0.75rem; padding: 0.35em 0.65em;">
                                        <?php echo $unread_report_notifications; ?> new update<?php echo $unread_report_notifications > 1 ? 's' : ''; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <p class="text-muted mb-0">View and manage your waste reports</p>
                        </div>
                        <div class="text-end">
                            <div class="h4 text-success mb-0"><?php echo $user['eco_points']; ?></div>
                            <small class="text-muted">Eco Points</small>
                        </div>
                    </div>

                    <!-- Messages -->
                    <?php if ($success_message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?php echo e($success_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($error_message): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo e($error_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Filters -->
                    <div class="card filter-card mb-4">
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-4">
                                    <input type="text" class="form-control" name="search" placeholder="Search reports..." value="<?php echo e($search); ?>">
                                </div>
                                <div class="col-md-3">
                                    <select class="form-select" name="status">
                                        <option value="">All Statuses</option>
                                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="assigned" <?php echo $status_filter === 'assigned' ? 'selected' : ''; ?>>Assigned</option>
                                        <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select class="form-select" name="priority">
                                        <option value="">All Priorities</option>
                                        <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>Low</option>
                                        <option value="medium" <?php echo $priority_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                        <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>High</option>
                                        <option value="urgent" <?php echo $priority_filter === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select class="form-select" name="date_range">
                                        <option value="">All Time</option>
                                        <option value="7" <?php echo $date_range === '7' ? 'selected' : ''; ?>>Last 7 days</option>
                                        <option value="30" <?php echo $date_range === '30' ? 'selected' : ''; ?>>Last 30 days</option>
                                        <option value="90" <?php echo $date_range === '90' ? 'selected' : ''; ?>>Last 90 days</option>
                                        <option value="365" <?php echo $date_range === '365' ? 'selected' : ''; ?>>Last year</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-search me-1"></i>Filter
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Reports List -->
                    <div class="row">
                        <?php if ($reports->num_rows > 0): ?>
                            <?php while ($report = $reports->fetch_assoc()): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card report-card <?php echo $report['priority']; ?> position-relative">
                                        <?php if (isset($report_notifications[$report['id']]) && $report_notifications[$report['id']] > 0): ?>
                                            <div class="position-absolute" style="top: 10px; right: 50px; z-index: 10;">
                                                <span class="badge bg-danger rounded-pill" style="font-size: 0.7rem; padding: 0.3em 0.6em;">
                                                    <i class="fas fa-bell" style="font-size: 0.7rem;"></i> <?php echo $report_notifications[$report['id']]; ?> update<?php echo $report_notifications[$report['id']] > 1 ? 's' : ''; ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="card-title mb-0" style="max-width: 70%; word-wrap: break-word;"><?php echo e($report['title']); ?></h6>
                                                <div class="dropdown ms-auto" style="flex-shrink: 0;">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <li><a class="dropdown-item" href="#" onclick="viewReport(<?php echo $report['id']; ?>)">
                                                            <i class="fas fa-eye me-2"></i>View Details
                                                        </a></li>
                                                        <?php if ($report['status'] === 'pending'): ?>
                                                        <li><a class="dropdown-item text-danger" href="#" onclick="deleteReport(<?php echo $report['id']; ?>)">
                                                            <i class="fas fa-trash me-2"></i>Delete
                                                        </a></li>
                                                        <?php endif; ?>
                                                    </ul>
                                                </div>
                                            </div>
                                            
                                            <p class="card-text text-muted small"><?php echo e(substr($report['description'], 0, 100)) . (strlen($report['description']) > 100 ? '...' : ''); ?></p>
                                            
                                            <div class="mb-2">
                                                <span class="badge bg-<?php echo $report['priority'] === 'high' ? 'danger' : ($report['priority'] === 'medium' ? 'warning' : ($report['priority'] === 'urgent' ? 'dark' : 'success')); ?> status-badge">
                                                    <?php echo ucfirst($report['priority']); ?>
                                                </span>
                                                <span class="badge bg-secondary status-badge">
                                                    <?php echo ucfirst($report['status']); ?>
                                                </span>
                                            </div>
                                            
                                            <div class="mb-2">
                                                <small class="text-muted">
                                                    <i class="fas fa-map-marker-alt me-1"></i><?php echo e($report['location']); ?>
                                                </small>
                                            </div>
                                            
                                            <?php if (isset($report_images[$report['id']])): ?>
                                                <div class="mb-2">
                                                    <?php foreach (array_slice($report_images[$report['id']], 0, 3) as $image): ?>
                                                        <img src="<?php echo BASE_URL . $image['image_path']; ?>" class="image-thumbnail" alt="Report Image">
                                                    <?php endforeach; ?>
                                                    <?php if (count($report_images[$report['id']]) > 3): ?>
                                                        <span class="badge bg-light text-dark">+<?php echo count($report_images[$report['id']]) - 3; ?> more</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    <i class="fas fa-clock me-1"></i><?php echo format_ph_date($report['created_at'], 'M j, Y'); ?>
                                                </small>
                                                <small class="text-muted">
                                                    <i class="fas fa-tag me-1"></i><?php echo ucfirst(str_replace('_', ' ', $report['report_type'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-body text-center py-5">
                                        <i class="fas fa-exclamation-circle fa-4x text-muted mb-4"></i>
                                        <h4>No Reports Found</h4>
                                        <p class="text-muted">You haven't submitted any reports yet, or no reports match your current filters.</p>
                                        <a href="submit_report.php" class="btn btn-primary">
                                            <i class="fas fa-plus me-2"></i>Submit Your First Report
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Reports pagination" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&priority=<?php echo urlencode($priority_filter); ?>&date_range=<?php echo urlencode($date_range); ?>">Previous</a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&priority=<?php echo urlencode($priority_filter); ?>&date_range=<?php echo urlencode($date_range); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&priority=<?php echo urlencode($priority_filter); ?>&date_range=<?php echo urlencode($date_range); ?>">Next</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Details Modal -->
    <div class="modal fade" id="reportModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Report Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="reportModalBody">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this report? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" id="deleteForm" style="display: inline;">
                        <input type="hidden" name="action" value="delete_report">
                        <input type="hidden" name="report_id" id="deleteReportId">
                        <button type="submit" class="btn btn-danger">Delete Report</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewReport(reportId) {
            console.log('Viewing report:', reportId);
            
            // Mark notifications for this report as read
            fetch('../../api/mark_report_notifications_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ report_id: reportId })
            }).then(response => response.json())
            .then(data => {
                if (data.success && data.marked > 0) {
                    // Update badge counts dynamically without reloading
                    updateBadgeCounts();
                }
            })
            .catch(error => console.error('Error marking notifications:', error));
            
            // Show loading state
            document.getElementById('reportModalBody').innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading report details...</p>
                </div>
            `;
            
            const modal = new bootstrap.Modal(document.getElementById('reportModal'));
            modal.show();
            
            const apiUrl = `../../api/get_report_details.php?id=${reportId}`;
            console.log('Fetching from:', apiUrl);
            
            // Fetch report details
            fetch(apiUrl)
                .then(response => {
                    console.log('Response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('API response:', data);
                    if (data.success) {
                        const report = data.report;
                        document.getElementById('reportModalBody').innerHTML = `
                            <div class="row">
                                <div class="col-md-8">
                                    <h5 class="mb-3">${report.title}</h5>
                                    <p class="text-muted mb-3">${report.description}</p>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <strong>Location:</strong><br>
                                            <span class="text-muted">${report.location}</span>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Report Type:</strong><br>
                                            <span class="text-muted">${report.report_type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}</span>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <strong>Priority:</strong><br>
                                            <span class="badge bg-${report.priority === 'high' ? 'danger' : (report.priority === 'medium' ? 'warning' : (report.priority === 'urgent' ? 'dark' : 'success'))}">${report.priority.charAt(0).toUpperCase() + report.priority.slice(1)}</span>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Status:</strong><br>
                                            <span class="badge bg-secondary">${report.status.charAt(0).toUpperCase() + report.status.slice(1)}</span>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <strong>Submitted:</strong><br>
                                            <span class="text-muted">${report.created_at}</span>
                                        </div>
                                        ${report.updated_at ? `
                                        <div class="col-md-6">
                                            <strong>Last Updated:</strong><br>
                                            <span class="text-muted">${report.updated_at}</span>
                                        </div>
                                        ` : ''}
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <h6 class="mb-3">Status History</h6>
                                    ${report.status_history.map(history => `
                                        <div class="card mb-2">
                                            <div class="card-body p-2">
                                                <div class="d-flex justify-content-between">
                                                    <span class="badge bg-secondary">${history.status.charAt(0).toUpperCase() + history.status.slice(1)}</span>
                                                    <small class="text-muted">${history.timestamp}</small>
                                                </div>
                                                ${history.note ? `<small class="text-muted d-block mt-1">${history.note}</small>` : ''}
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                            
                            ${report.images.length > 0 ? `
                            <hr>
                            <h6 class="mb-3">Report Images (${report.images.length})</h6>
                            <div class="row">
                                ${report.images.map(image => `
                                    <div class="col-md-4 mb-3">
                                        <div class="card">
                                            <img src="${image.url}" class="card-img-top" alt="Report Image" style="height: 200px; object-fit: cover;">
                                            <div class="card-body p-2">
                                                <small class="text-muted">${image.filename}</small><br>
                                                <small class="text-muted">${image.uploaded_at}</small>
                                            </div>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                            ` : ''}
                        `;
                    } else {
                        document.getElementById('reportModalBody').innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Error loading report details: ${data.error}
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    document.getElementById('reportModalBody').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error loading report details: ${error.message}
                        </div>
                    `;
                });
        }

        function deleteReport(reportId) {
            document.getElementById('deleteReportId').value = reportId;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        function updateBadgeCounts() {
            // Update the header badge count
            const headerBadge = document.querySelector('.d-flex.align-items-center.gap-2 .badge');
            if (headerBadge) {
                const currentCount = parseInt(headerBadge.textContent.match(/\d+/)[0]);
                const newCount = Math.max(0, currentCount - 1);
                if (newCount > 0) {
                    headerBadge.textContent = newCount + ' new update' + (newCount > 1 ? 's' : '');
                } else {
                    headerBadge.remove();
                }
            }
        }
    </script>
</body>
</html>

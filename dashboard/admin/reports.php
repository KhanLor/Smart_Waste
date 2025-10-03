<?php
require_once '../../config/config.php';
require_login();

// Check if user is an admin
if (($_SESSION['role'] ?? '') !== 'admin') {
	header('Location: ' . BASE_URL . 'login.php');
	exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$success_message = '';
$error_message = '';

// Handle report actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
	if ($_POST['action'] === 'update_status') {
		$report_id = $_POST['report_id'] ?? null;
		$status = $_POST['status'] ?? '';
		$assigned_to = $_POST['assigned_to'] ?? null;
		
		if ($report_id && $status) {
			try {
				$stmt = $conn->prepare("UPDATE waste_reports SET status = ?, assigned_to = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
				$stmt->bind_param("sii", $status, $assigned_to, $report_id);
				
				if ($stmt->execute()) {
					// Create notification for resident
					$stmt = $conn->prepare("
						SELECT user_id, title FROM waste_reports WHERE id = ?
					");
					$stmt->bind_param("i", $report_id);
					$stmt->execute();
					$report_data = $stmt->get_result()->fetch_assoc();
					
					if ($report_data) {
						$notification_title = 'Report Status Updated';
						$notification_message = "Your report '{$report_data['title']}' status has been updated to " . ucfirst($status);
						
						$stmt = $conn->prepare("
							INSERT INTO notifications (user_id, title, message, type, reference_type, reference_id) 
							VALUES (?, ?, ?, 'info', 'report', ?)
						");
						$stmt->bind_param("issi", $report_data['user_id'], $notification_title, $notification_message, $report_id);
						$stmt->execute();
					}
					
					$success_message = 'Report status updated successfully.';
				} else {
					throw new Exception('Failed to update report status.');
				}
			} catch (Exception $e) {
				$error_message = 'Error updating report: ' . $e->getMessage();
			}
		}
	}
}

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get reports with pagination and filters
$page = $_GET['page'] ?? 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$priority_filter = $_GET['priority'] ?? '';
$type_filter = $_GET['type'] ?? '';

// Build query
$where_conditions = ["1=1"];
$params = [];
$param_types = "";

if (!empty($search)) {
	$where_conditions[] = "(wr.title LIKE ? OR wr.description LIKE ? OR wr.location LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
	$search_param = "%{$search}%";
	$params[] = $search_param;
	$params[] = $search_param;
	$params[] = $search_param;
	$params[] = $search_param;
	$params[] = $search_param;
	$param_types .= "sssss";
}

if (!empty($status_filter)) {
	$where_conditions[] = "wr.status = ?";
	$params[] = $status_filter;
	$param_types .= "s";
}

if (!empty($priority_filter)) {
	$where_conditions[] = "wr.priority = ?";
	$params[] = $priority_filter;
	$param_types .= "s";
}

if (!empty($type_filter)) {
	$where_conditions[] = "wr.report_type = ?";
	$params[] = $type_filter;
	$param_types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

// Get total count
$count_sql = "
	SELECT COUNT(*) as total 
	FROM waste_reports wr 
	JOIN users u ON wr.user_id = u.id 
	WHERE {$where_clause}
";
$stmt = $conn->prepare($count_sql);
if (!empty($params)) {
	$stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$total_reports = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_reports / $limit);

// Get reports
$sql = "
	SELECT wr.*, u.first_name, u.last_name, u.email, u.phone, u.address as user_address,
		   assigned.first_name as assigned_first_name, assigned.last_name as assigned_last_name
	FROM waste_reports wr 
	JOIN users u ON wr.user_id = u.id 
	LEFT JOIN users assigned ON wr.assigned_to = assigned.id
	WHERE {$where_clause}
	ORDER BY wr.created_at DESC 
	LIMIT ? OFFSET ?
";
$params[] = $limit;
$params[] = $offset;
$param_types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$reports = $stmt->get_result();

// Get collectors for assignment
$stmt = $conn->prepare("SELECT id, first_name, last_name FROM users WHERE role = 'collector' ORDER BY first_name, last_name");
$stmt->execute();
$collectors = $stmt->get_result();

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
	<title>Waste Reports - <?php echo APP_NAME; ?></title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
	<link rel="stylesheet" href="../../assets/css/dashboard.css">
	<style>
		.sidebar {
			min-height: 100vh;
			background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
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
		.status-badge { font-size: 0.8rem; }
		.image-thumbnail {
			width: 60px; height: 60px; object-fit: cover; border-radius: 8px; margin: 2px;
		}
		.filter-card { background: #f8f9fa; border-radius: 10px; }
		.resident-info { background: #e3f2fd; border-radius: 8px; padding: 10px; margin-bottom: 10px; }
	</style>
</head>
<body>
	<div class="container-fluid">
		<div class="row">
			<!-- Sidebar -->
			<div class="col-md-3 col-lg-2 sidebar text-white p-0">
				<div class="p-3">
					<h4 class="mb-4"><i class="fas fa-user-shield me-2"></i><?php echo APP_NAME; ?></h4>
					<hr class="bg-white">
					<nav class="nav flex-column">
						<a class="nav-link text-white" href="index.php">
							<i class="fas fa-tachometer-alt me-2"></i>Dashboard
						</a>
						<a class="nav-link text-white active" href="reports.php">
							<i class="fas fa-exclamation-triangle me-2"></i>Waste Reports
						</a>
						<a class="nav-link text-white" href="schedules.php">
							<i class="fas fa-calendar me-2"></i>Collection Schedules
						</a>
						<a class="nav-link text-white" href="collectors.php">
							<i class="fas fa-users me-2"></i>Collectors
						</a>
						<a class="nav-link text-white" href="residents.php">
							<i class="fas fa-home me-2"></i>Residents
						</a>
						<a class="nav-link text-white" href="analytics.php">
							<i class="fas fa-chart-line me-2"></i>Analytics
						</a>
						<a class="nav-link text-white" href="chat.php">
							<i class="fas fa-comments me-2"></i>Chat
						</a>
						<hr class="bg-white">
						<a class="nav-link text-white" href="../../logout.php">
							<i class="fas fa-sign-out-alt me-2"></i>Logout
						</a>
					</nav>
				</div>
			</div>

			<!-- Main Content -->
			<div class="col-md-9 col-lg-10">
				<div class="p-4">
					<!-- Header -->
					<div class="d-flex justify-content-between align-items-center mb-4">
						<div>
							<h2 class="mb-1">Waste Reports Management</h2>
							<p class="text-muted mb-0">Manage and track all waste reports from residents</p>
						</div>
						<div class="text-end">
							<div class="h4 text-danger mb-0"><?php echo $total_reports; ?></div>
							<small class="text-muted">Total Reports</small>
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
								<div class="col-md-3">
									<input type="text" class="form-control" name="search" placeholder="Search reports..." value="<?php echo e($search); ?>">
								</div>
								<div class="col-md-2">
									<select class="form-select" name="status">
										<option value="">All Statuses</option>
										<option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
										<option value="assigned" <?php echo $status_filter === 'assigned' ? 'selected' : ''; ?>>Assigned</option>
										<option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
										<option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
										<option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
									</select>
								</div>
								<div class="col-md-2">
									<select class="form-select" name="priority">
										<option value="">All Priorities</option>
										<option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>Low</option>
										<option value="medium" <?php echo $priority_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
										<option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>High</option>
										<option value="urgent" <?php echo $priority_filter === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
									</select>
								</div>
								<div class="col-md-2">
									<select class="form-select" name="type">
										<option value="">All Types</option>
										<option value="overflow" <?php echo $type_filter === 'overflow' ? 'selected' : ''; ?>>Overflow</option>
										<option value="missed_collection" <?php echo $type_filter === 'missed_collection' ? 'selected' : ''; ?>>Missed Collection</option>
										<option value="damaged_bin" <?php echo $type_filter === 'damaged_bin' ? 'selected' : ''; ?>>Damaged Bin</option>
										<option value="illegal_dumping" <?php echo $type_filter === 'illegal_dumping' ? 'selected' : ''; ?>>Illegal Dumping</option>
										<option value="other" <?php echo $type_filter === 'other' ? 'selected' : ''; ?>>Other</option>
									</select>
								</div>
								<div class="col-md-3">
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
									<div class="card report-card <?php echo $report['priority']; ?>">
										<div class="card-body">
											<!-- Resident Info -->
											<div class="resident-info">
												<h6 class="mb-1">
													<i class="fas fa-user me-2"></i>
													<?php echo e($report['first_name'] . ' ' . $report['last_name']); ?>
												</h6>
												<small class="text-muted">
													<i class="fas fa-envelope me-1"></i><?php echo e($report['email']); ?><br>
													<i class="fas fa-map-marker-alt me-1"></i><?php echo e($report['user_address']); ?>
												</small>
											</div>

											<div class="d-flex justify-content-between align-items-start mb-2">
												<h6 class="card-title mb-0"><?php echo e($report['title']); ?></h6>
												<div class="dropdown">
													<button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
														<i class="fas fa-ellipsis-v"></i>
													</button>
													<ul class="dropdown-menu">
														<li><a class="dropdown-item" href="#" onclick="viewReport(<?php echo $report['id']; ?>)">
															<i class="fas fa-eye me-2"></i>View Details
														</a></li>
														<li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $report['id']; ?>)">
															<i class="fas fa-edit me-2"></i>Update Status
														</a></li>
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
												<span class="badge bg-info status-badge">
													<?php echo ucfirst(str_replace('_', ' ', $report['report_type'])); ?>
												</span>
											</div>
											
											<div class="mb-2">
												<small class="text-muted">
													<i class="fas fa-map-marker-alt me-1"></i><?php echo e($report['location']); ?>
												</small>
											</div>

											<?php if ($report['assigned_first_name']): ?>
												<div class="mb-2">
													<small class="text-muted">
														<i class="fas fa-user-tie me-1"></i>Assigned to: <?php echo e($report['assigned_first_name'] . ' ' . $report['assigned_last_name']); ?>
													</small>
												</div>
											<?php endif; ?>
											
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
										<p class="text-muted">No reports match your current filters.</p>
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
										<a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&priority=<?php echo urlencode($priority_filter); ?>&type=<?php echo urlencode($type_filter); ?>">Previous</a>
									</li>
								<?php endif; ?>
								
								<?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
									<li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
										<a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&priority=<?php echo urlencode($priority_filter); ?>&type=<?php echo urlencode($type_filter); ?>"><?php echo $i; ?></a>
									</li>
								<?php endfor; ?>
								
								<?php if ($page < $total_pages): ?>
									<li class="page-item">
										<a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&priority=<?php echo urlencode($priority_filter); ?>&type=<?php echo urlencode($type_filter); ?>">Next</a>
									</li>
								<?php endif; ?>
							</ul>
						</nav>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>

	<!-- Update Status Modal -->
	<div class="modal fade" id="statusModal" tabindex="-1">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Update Report Status</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
				</div>
				<form method="POST" id="statusForm">
					<div class="modal-body">
						<input type="hidden" name="action" value="update_status">
						<input type="hidden" name="report_id" id="statusReportId">
						
						<div class="mb-3">
							<label for="status" class="form-label">Status</label>
							<select class="form-select" name="status" id="status" required>
								<option value="pending">Pending</option>
								<option value="assigned">Assigned</option>
								<option value="in_progress">In Progress</option>
								<option value="completed">Completed</option>
								<option value="cancelled">Cancelled</option>
							</select>
						</div>
						
						<div class="mb-3">
							<label for="assigned_to" class="form-label">Assign to Collector (Optional)</label>
							<select class="form-select" name="assigned_to" id="assigned_to">
								<option value="">Select Collector</option>
								<?php 
								$collectors->data_seek(0);
								while ($collector = $collectors->fetch_assoc()): 
								?>
									<option value="<?php echo $collector['id']; ?>">
										<?php echo e($collector['first_name'] . ' ' . $collector['last_name']); ?>
									</option>
								<?php endwhile; ?>
							</select>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
						<button type="submit" class="btn btn-primary">Update Status</button>
					</div>
				</form>
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

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
	<script>
		function viewReport(reportId) {
			document.getElementById('reportModalBody').innerHTML = `
				<div class="text-center">
					<div class="spinner-border text-primary" role="status">
						<span class="visually-hidden">Loading...</span>
					</div>
					<p class="mt-2">Loading report details...</p>
				</div>
			`;
			new bootstrap.Modal(document.getElementById('reportModal')).show();
			const apiUrl = `../../api/get_report_details.php?id=${reportId}`;
			fetch(apiUrl)
				.then(response => response.json())
				.then(data => {
					if (data.success) {
						const report = data.report;
						document.getElementById('reportModalBody').innerHTML = `
							<div class="row">
								<div class="col-md-8">
									<h5 class="mb-3">${report.title}</h5>
									<p class="text-muted mb-3">${report.description}</p>
									<div class="row mb-3">
										<div class="col-md-6"><strong>Location:</strong><br><span class="text-muted">${report.location}</span></div>
										<div class="col-md-6"><strong>Report Type:</strong><br><span class="text-muted">${report.report_type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}</span></div>
									</div>
									<div class="row mb-3">
										<div class="col-md-6"><strong>Priority:</strong><br><span class="badge bg-${report.priority === 'high' ? 'danger' : (report.priority === 'medium' ? 'warning' : (report.priority === 'urgent' ? 'dark' : 'success'))}">${report.priority.charAt(0).toUpperCase() + report.priority.slice(1)}</span></div>
										<div class="col-md-6"><strong>Status:</strong><br><span class="badge bg-secondary">${report.status.charAt(0).toUpperCase() + report.status.slice(1)}</span></div>
									</div>
									<div class="row mb-3">
										<div class="col-md-6"><strong>Submitted:</strong><br><span class="text-muted">${report.created_at}</span></div>
										${report.updated_at ? `<div class="col-md-6"><strong>Last Updated:</strong><br><span class="text-muted">${report.updated_at}</span></div>` : ''}
									</div>
								</div>
								<div class="col-md-4">
									<h6 class="mb-3">Status History</h6>
									${report.status_history.map(history => `
										<div class="card mb-2"><div class="card-body p-2"><div class="d-flex justify-content-between"><span class="badge bg-secondary">${history.status.charAt(0).toUpperCase() + history.status.slice(1)}</span><small class="text-muted">${history.timestamp}</small></div>${history.note ? `<small class="text-muted d-block mt-1">${history.note}</small>` : ''}</div></div>
									`).join('')}
								</div>
							</div>
							<hr>
							<h6 class="mb-3">Resident Information</h6>
							<div class="card mb-3"><div class="card-body"><div class="row"><div class="col-md-6"><strong>Name:</strong><br><span class="text-muted">${report.resident.name}</span></div><div class="col-md-6"><strong>Email:</strong><br><span class="text-muted">${report.resident.email}</span></div></div><div class="row mt-2"><div class="col-md-6"><strong>Phone:</strong><br><span class="text-muted">${report.resident.phone || 'Not provided'}</span></div><div class="col-md-6"><strong>Address:</strong><br><span class="text-muted">${report.resident.address || 'Not provided'}</span></div></div></div></div>
							${report.assigned_to ? `<h6 class="mb-3">Assigned Collector</h6><div class="card mb-3"><div class="card-body"><div class="row"><div class="col-md-6"><strong>Name:</strong><br><span class="text-muted">${report.assigned_to.name}</span></div><div class="col-md-6"><strong>Email:</strong><br><span class="text-muted">${report.assigned_to.email}</span></div></div><div class="row mt-2"><div class="col-md-6"><strong>Phone:</strong><br><span class="text-muted">${report.assigned_to.phone || 'Not provided'}</span></div></div></div></div>` : ''}
							${report.images.length > 0 ? `<hr><h6 class="mb-3">Report Images (${report.images.length})</h6><div class="row">${report.images.map(image => `<div class="col-md-4 mb-3"><div class="card"><img src="${image.url}" class="card-img-top" alt="Report Image" style="height: 200px; object-fit: cover;"><div class="card-body p-2"><small class="text-muted">${image.filename}</small><br><small class="text-muted">${image.uploaded_at}</small></div></div></div>`).join('')}</div>` : ''}
						`;
					} else {
						document.getElementById('reportModalBody').innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Error loading report details: ${data.error}</div>`;
					}
				})
				.catch(error => {
					document.getElementById('reportModalBody').innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Error loading report details: ${error.message}</div>`;
				});
		}
		function updateStatus(reportId) { document.getElementById('statusReportId').value = reportId; new bootstrap.Modal(document.getElementById('statusModal')).show(); }
	</script>
</body>
</html>

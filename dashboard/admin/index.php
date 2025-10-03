<?php
require_once '../../config/config.php';
require_login();

// Check if user is an admin
if (($_SESSION['role'] ?? '') !== 'admin') {
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

// Get statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total_reports FROM waste_reports");
$stmt->execute();
$total_reports = $stmt->get_result()->fetch_assoc()['total_reports'];

$stmt = $conn->prepare("SELECT COUNT(*) as urgent_reports FROM waste_reports WHERE priority IN ('high', 'urgent') AND status = 'pending'");
$stmt->execute();
$urgent_reports = $stmt->get_result()->fetch_assoc()['urgent_reports'];

$stmt = $conn->prepare("SELECT COUNT(*) as active_schedules FROM collection_schedules WHERE status = 'active'");
$stmt->execute();
$active_schedules = $stmt->get_result()->fetch_assoc()['active_schedules'];

$stmt = $conn->prepare("SELECT COUNT(*) as completed_today FROM collection_history WHERE DATE(collection_date) = CURDATE() AND status = 'completed'");
$stmt->execute();
$completed_today = $stmt->get_result()->fetch_assoc()['completed_today'];

// Get recent reports
$stmt = $conn->prepare("
	SELECT wr.*, u.first_name, u.last_name, u.address as user_address 
	FROM waste_reports wr 
	JOIN users u ON wr.user_id = u.id 
	ORDER BY wr.created_at DESC 
	LIMIT 5
");
$stmt->execute();
$recent_reports = $stmt->get_result();

// Get collection schedules for today
$today = strtolower(date('l'));
$stmt = $conn->prepare("
	SELECT cs.*, u.first_name, u.last_name 
	FROM collection_schedules cs 
	LEFT JOIN users u ON cs.assigned_collector = u.id 
	WHERE cs.collection_day = ? AND cs.status = 'active'
	ORDER BY cs.collection_time
");
$stmt->bind_param("s", $today);
$stmt->execute();
$today_schedules = $stmt->get_result();

// Get unread notifications
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Admin Dashboard - <?php echo APP_NAME; ?></title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
	<link rel="stylesheet" href="../../assets/css/dashboard.css">
	<style>
		/* sidebar colors now handled by shared CSS; keep fallback for legacy */
		.sidebar { min-height: 100vh; }
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
			background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
			color: white;
		}
		.stat-card.primary {
			background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
		}
		.stat-card.success {
			background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
		}
		.stat-card.warning {
			background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
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
		.report-item {
			border-left: 4px solid #007bff;
			transition: all 0.3s;
		}
		.report-item:hover {
			transform: translateX(5px);
		}
		.report-item.high { border-left-color: #dc3545; }
		.report-item.medium { border-left-color: #ffc107; }
		.report-item.low { border-left-color: #28a745; }
		.report-item.urgent { border-left-color: #6f42c1; }
		.schedule-item {
			background: #f8f9fa;
			border-radius: 10px;
			padding: 15px;
			margin-bottom: 10px;
			border-left: 4px solid #17a2b8;
		}
		.priority-badge {
			font-size: 0.75rem;
			padding: 0.25rem 0.5rem;
		}
	</style>
</head>
<body class="role-admin">
	<div class="container-fluid">
		<div class="row">
			<!-- Sidebar -->
			<div class="col-md-3 col-lg-2 sidebar text-white p-0">
				<div class="p-3">
					<h4 class="mb-4"><i class="fas fa-user-shield me-2"></i><?php echo APP_NAME; ?></h4>
					<hr class="bg-white">
					<nav class="nav flex-column">
						<a class="nav-link text-white active" href="index.php">
							<i class="fas fa-tachometer-alt me-2"></i>Dashboard
						</a>
						<a class="nav-link text-white" href="reports.php">
							<i class="fas fa-exclamation-triangle me-2"></i>Waste Reports
						</a>
						<a class="nav-link text-white" href="schedules.php">
							<i class="fas fa-calendar me-2"></i>Collection Schedules
						</a>
						<a class="nav-link text-white" href="collectors.php">
							<i class="fas fa-users me-2"></i>Collectors
						</a>
						<a class="nav-link text-white" href="tracking.php">
							<i class="fas fa-map-marker-alt me-2"></i>Tracking
						</a>
						<a class="nav-link text-white" href="residents.php">
							<i class="fas fa-home me-2"></i>Residents
						</a>
						<a class="nav-link text-white" href="analytics.php">
							<i class="fas fa-chart-line me-2"></i>Analytics
						</a>
						<a class="nav-link text-white" href="chat.php">
							<i class="fas fa-comments me-2"></i>Chat Support
							<?php if ($notifications->num_rows > 0): ?>
								<span class="notification-badge"><?php echo $notifications->num_rows; ?></span>
							<?php endif; ?>
						</a>
						<a class="nav-link text-white" href="settings.php">
							<i class="fas fa-cog me-2"></i>Settings
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
							<h2 class="mb-1">Admin Dashboard</h2>
							<p class="text-muted mb-0">Monitor and manage waste collection operations</p>
						</div>
						<div class="text-end">
							<div class="h4 text-info mb-0"><?php echo get_ph_time('l, M j'); ?></div>
							<small class="text-muted"><?php echo get_ph_time('g:i A'); ?></small>
						</div>
					</div>

					<!-- Statistics Cards -->
					<div class="row mb-4">
						<div class="col-md-3 mb-3">
							<div class="card stat-card">
								<div class="card-body">
									<div class="d-flex justify-content-between align-items-center">
										<div>
											<h6 class="mb-1">Urgent Reports</h6>
											<h4 class="mb-0"><?php echo $urgent_reports; ?></h4>
											<small>High Priority</small>
										</div>
										<i class="fas fa-exclamation-triangle fa-2x opacity-75"></i>
									</div>
								</div>
							</div>
						</div>
						<div class="col-md-3 mb-3">
							<div class="card stat-card primary">
								<div class="card-body">
									<div class="d-flex justify-content-between align-items-center">
										<div>
											<h6 class="mb-1">Total Reports</h6>
											<h4 class="mb-0"><?php echo $total_reports; ?></h4>
											<small>All Time</small>
										</div>
										<i class="fas fa-clipboard-list fa-2x opacity-75"></i>
									</div>
								</div>
							</div>
						</div>
						<div class="col-md-3 mb-3">
							<div class="card stat-card success">
								<div class="card-body">
									<div class="d-flex justify-content-between align-items-center">
										<div>
											<h6 class="mb-1">Active Schedules</h6>
											<h4 class="mb-0"><?php echo $active_schedules; ?></h4>
											<small>Collection Routes</small>
										</div>
										<i class="fas fa-route fa-2x opacity-75"></i>
									</div>
								</div>
							</div>
						</div>
						<div class="col-md-3 mb-3">
							<div class="card stat-card warning">
								<div class="card-body">
									<div class="d-flex justify-content-between align-items-center">
										<div>
											<h6 class="mb-1">Completed Today</h6>
											<h4 class="mb-0"><?php echo $completed_today; ?></h4>
											<small>Collections</small>
										</div>
										<i class="fas fa-check-circle fa-2x opacity-75"></i>
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
											<a href="reports.php" class="btn btn-danger w-100">
												<i class="fas fa-exclamation-triangle me-2"></i>View Reports
											</a>
										</div>
										<div class="col-md-3 mb-3">
											<a href="schedules.php" class="btn btn-primary w-100">
												<i class="fas fa-calendar-plus me-2"></i>Manage Schedules
											</a>
										</div>
										<div class="col-md-3 mb-3">
											<a href="collectors.php" class="btn btn-info w-100">
												<i class="fas fa-user-plus me-2"></i>Assign Collectors
											</a>
										</div>
										<div class="col-md-3 mb-3">
											<a href="tracking.php" class="btn btn-secondary w-100">
												<i class="fas fa-map-marker-alt me-2"></i>Live Tracking
											</a>
										</div>
										<div class="col-md-3 mb-3">
											<a href="analytics.php" class="btn btn-success w-100">
												<i class="fas fa-chart-bar me-2"></i>View Analytics
											</a>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Recent Reports and Today's Schedule -->
					<div class="row">
						<!-- Recent Reports -->
						<div class="col-md-6 mb-4">
							<div class="card">
								<div class="card-header bg-light">
									<h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Recent Reports</h5>
								</div>
								<div class="card-body">
									<?php if ($recent_reports->num_rows > 0): ?>
										<?php while ($report = $recent_reports->fetch_assoc()): ?>
											<div class="report-item <?php echo $report['priority']; ?> p-3 mb-2">
												<div class="d-flex justify-content-between align-items-start">
													<div class="flex-grow-1">
														<h6 class="mb-1"><?php echo e($report['title']); ?></h6>
														<small class="text-muted">
															By: <?php echo e($report['first_name'] . ' ' . $report['last_name']); ?> | 
															<?php echo e($report['location']); ?>
														</small>
														<br>
														<span class="badge bg-<?php echo $report['priority'] === 'high' ? 'danger' : ($report['priority'] === 'medium' ? 'warning' : ($report['priority'] === 'urgent' ? 'purple' : 'success')); ?> priority-badge">
															<?php echo ucfirst($report['priority']); ?>
														</span>
														<span class="badge bg-secondary priority-badge ms-1"><?php echo ucfirst($report['status']); ?></span>
													</div>
													<div class="text-end">
														<small class="text-muted d-block"><?php echo format_ph_date($report['created_at'], 'M j'); ?></small>
														<a href="reports.php?id=<?php echo $report['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
													</div>
												</div>
											</div>
										<?php endwhile; ?>
									<?php else: ?>
										<p class="text-muted text-center">No reports yet.</p>
									<?php endif; ?>
									<div class="text-center mt-3">
										<a href="reports.php" class="btn btn-outline-primary btn-sm">View All Reports</a>
									</div>
								</div>
							</div>
						</div>

						<!-- Today's Collection Schedule -->
						<div class="col-md-6 mb-4">
							<div class="card">
								<div class="card-header bg-light">
									<h5 class="mb-0"><i class="fas fa-calendar me-2"></i>Today's Collections</h5>
								</div>
								<div class="card-body">
									<?php if ($today_schedules->num_rows > 0): ?>
										<?php while ($schedule = $today_schedules->fetch_assoc()): ?>
											<div class="schedule-item">
												<div class="d-flex justify-content-between align-items-center">
													<div>
														<h6 class="mb-1"><?php echo e($schedule['street_name']); ?></h6>
														<small class="text-muted">
															<?php echo e($schedule['waste_type']); ?> - 
															<?php echo format_ph_date($schedule['collection_time'], 'g:i A'); ?>
														</small>
														<?php if ($schedule['assigned_collector']): ?>
															<br>
															<small class="text-info">
																Collector: <?php echo e($schedule['first_name'] . ' ' . $schedule['last_name']); ?>
															</small>
														<?php endif; ?>
													</div>
													<div class="text-end">
														<span class="badge bg-success"><?php echo ucfirst($schedule['waste_type']); ?></span>
														<?php if (!$schedule['assigned_collector']): ?>
															<br>
															<small class="text-warning">Unassigned</small>
														<?php endif; ?>
													</div>
												</div>
											</div>
										<?php endwhile; ?>
									<?php else: ?>
										<p class="text-muted text-center">No collections scheduled for today.</p>
									<?php endif; ?>
									<div class="text-center mt-3">
										<a href="schedules.php" class="btn btn-outline-success btn-sm">Manage Schedules</a>
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
</body>
</html>

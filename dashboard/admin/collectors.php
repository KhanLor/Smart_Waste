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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
	if ($_POST['action'] === 'add_collector') {
		$username = trim($_POST['username'] ?? '');
		$first_name = trim($_POST['first_name'] ?? '');
		$middle_name = trim($_POST['middle_name'] ?? '');
		$last_name = trim($_POST['last_name'] ?? '');
		$email = trim($_POST['email'] ?? '');
		$phone = trim($_POST['phone'] ?? '');
		$address = trim($_POST['address'] ?? '');
		$password = $_POST['password'] ?? '';

		// Validation
		if (empty($username) || empty($first_name) || empty($last_name) || empty($email) || empty($address) || empty($password)) {
			$error_message = 'Please fill in all required fields.';
		} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$error_message = 'Please enter a valid email address.';
		} elseif (strlen($password) < 6) {
			$error_message = 'Password must be at least 6 characters long.';
		} else {
			try {
				// Check if username or email already exists
				$stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
				$stmt->bind_param("ss", $username, $email);
				$stmt->execute();
				
				if ($stmt->get_result()->num_rows > 0) {
					$error_message = 'Username or email already exists.';
				} else {
					// Hash password
					$hashed_password = password_hash($password, PASSWORD_DEFAULT);
					
					// Insert collector
					$stmt = $conn->prepare("INSERT INTO users (username, first_name, middle_name, last_name, email, password, role, address, phone) VALUES (?, ?, ?, ?, ?, ?, 'collector', ?, ?)");
					$stmt->bind_param("ssssssss", $username, $first_name, $middle_name, $last_name, $email, $hashed_password, $address, $phone);
					
					if ($stmt->execute()) {
						$success_message = 'Collector added successfully.';
					} else {
						throw new Exception('Failed to add collector.');
					}
				}
			} catch (Exception $e) {
				$error_message = 'Error adding collector: ' . $e->getMessage();
			}
		}
	} elseif ($_POST['action'] === 'update_collector') {
		$collector_id = $_POST['collector_id'] ?? null;
		$first_name = trim($_POST['first_name'] ?? '');
		$middle_name = trim($_POST['middle_name'] ?? '');
		$last_name = trim($_POST['last_name'] ?? '');
		$email = trim($_POST['email'] ?? '');
		$phone = trim($_POST['phone'] ?? '');
		$address = trim($_POST['address'] ?? '');

		if ($collector_id && !empty($first_name) && !empty($last_name) && !empty($email) && !empty($address)) {
			try {
				// Check if email is already taken by another user
				$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
				$stmt->bind_param("si", $email, $collector_id);
				$stmt->execute();
				
				if ($stmt->get_result()->num_rows > 0) {
					$error_message = 'This email address is already in use by another account.';
				} else {
					// Update collector
					$stmt = $conn->prepare("UPDATE users SET first_name = ?, middle_name = ?, last_name = ?, email = ?, phone = ?, address = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND role = 'collector'");
					$stmt->bind_param("ssssssi", $first_name, $middle_name, $last_name, $email, $phone, $address, $collector_id);
					
					if ($stmt->execute()) {
						$success_message = 'Collector updated successfully.';
					} else {
						throw new Exception('Failed to update collector.');
					}
				}
			} catch (Exception $e) {
				$error_message = 'Error updating collector: ' . $e->getMessage();
			}
		}
	} elseif ($_POST['action'] === 'delete_collector') {
		$collector_id = $_POST['collector_id'] ?? null;
		
		if ($collector_id) {
			try {
				// Check if collector has assigned schedules
				$stmt = $conn->prepare("SELECT COUNT(*) as count FROM collection_schedules WHERE assigned_collector = ?");
				$stmt->bind_param("i", $collector_id);
				$stmt->execute();
				$schedule_count = $stmt->get_result()->fetch_assoc()['count'];
				
				if ($schedule_count > 0) {
					$error_message = 'Cannot delete collector with assigned schedules. Please reassign schedules first.';
				} else {
					$stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'collector'");
					$stmt->bind_param("i", $collector_id);
					
					if ($stmt->execute()) {
						$success_message = 'Collector deleted successfully.';
					} else {
						throw new Exception('Failed to delete collector.');
					}
				}
			} catch (Exception $e) {
				$error_message = 'Error deleting collector: ' . $e->getMessage();
			}
		}
	}
}

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Pagination and filters
$page = $_GET['page'] ?? 1;
$limit = 15;
$offset = ($page - 1) * $limit;
$search = $_GET['search'] ?? '';

$where_conditions = ["role = 'collector'"];
$params = [];
$param_types = "";

if (!empty($search)) {
	$where_conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR address LIKE ?)";
	$search_param = "%{$search}%";
	$params[] = $search_param; $params[] = $search_param; $params[] = $search_param; $params[] = $search_param;
	$param_types .= "ssss";
}

$where_clause = implode(" AND ", $where_conditions);

// Count
$count_sql = "SELECT COUNT(*) as total FROM users WHERE {$where_clause}";
$stmt = $conn->prepare($count_sql);
if (!empty($params)) { $stmt->bind_param($param_types, ...$params); }
$stmt->execute();
$total_collectors = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_collectors / $limit);

// List collectors with stats
$sql = "SELECT u.*, COUNT(cs.id) as assigned_schedules, COUNT(ch.id) as completed_collections FROM users u LEFT JOIN collection_schedules cs ON u.id = cs.assigned_collector LEFT JOIN collection_history ch ON u.id = ch.collector_id AND ch.status = 'completed' WHERE {$where_clause} GROUP BY u.id ORDER BY u.first_name, u.last_name LIMIT ? OFFSET ?";
$params[] = $limit; $params[] = $offset; $param_types .= "ii";
$stmt = $conn->prepare($sql);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$collectors = $stmt->get_result();

// Stats
$stmt = $conn->prepare("SELECT COUNT(*) as total_collectors FROM users WHERE role = 'collector'");
$stmt->execute();
$total_collectors_count = $stmt->get_result()->fetch_assoc()['total_collectors'];

$stmt = $conn->prepare("SELECT COUNT(*) as active_collectors FROM users u JOIN collection_schedules cs ON u.id = cs.assigned_collector WHERE u.role = 'collector' AND cs.status = 'active'");
$stmt->execute();
$active_collectors = $stmt->get_result()->fetch_assoc()['active_collectors'];

$stmt = $conn->prepare("SELECT COUNT(*) as total_collections FROM collection_history ch JOIN users u ON ch.collector_id = u.id WHERE u.role = 'collector' AND DATE(ch.collection_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
$stmt->execute();
$total_collections = $stmt->get_result()->fetch_assoc()['total_collections'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Collectors Management - <?php echo APP_NAME; ?></title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
	<style>
		.sidebar { min-height: 100vh; background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); }
		.card { border: none; border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
		.nav-link { border-radius: 10px; margin: 2px 0; transition: all 0.3s; }
		.nav-link:hover, .nav-link.active { background-color: rgba(255,255,255,0.2); transform: translateX(5px); }
		.collector-card { border-left: 4px solid #17a2b8; transition: transform 0.2s; }
		.collector-card:hover { transform: translateY(-2px); }
		.filter-card { background: #f8f9fa; border-radius: 10px; }
		.stat-card { background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white; }
		.stat-card.success { background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%); }
		.stat-card.warning { background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%); }
		.collector-avatar { width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem; }
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
						<a class="nav-link text-white" href="index.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
						<a class="nav-link text-white" href="reports.php"><i class="fas fa-exclamation-triangle me-2"></i>Waste Reports</a>
						<a class="nav-link text-white" href="schedules.php"><i class="fas fa-calendar me-2"></i>Collection Schedules</a>
						<a class="nav-link text-white active" href="collectors.php"><i class="fas fa-users me-2"></i>Collectors</a>
						<a class="nav-link text-white" href="residents.php"><i class="fas fa-home me-2"></i>Residents</a>
						<a class="nav-link text-white" href="analytics.php"><i class="fas fa-chart-line me-2"></i>Analytics</a>
						<a class="nav-link text-white" href="chat.php"><i class="fas fa-comments me-2"></i>Chat</a>
						<hr class="bg-white">
						<a class="nav-link text-white" href="../../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
					</nav>
				</div>
			</div>

			<!-- Main Content -->
			<div class="col-md-9 col-lg-10">
				<div class="p-4">
					<div class="d-flex justify-content-between align-items-center mb-4">
						<div>
							<h2 class="mb-1">Collectors Management</h2>
							<p class="text-muted mb-0">Manage waste collection staff and assignments</p>
						</div>
						<div>
							<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCollectorModal"><i class="fas fa-user-plus me-2"></i>Add Collector</button>
						</div>
					</div>

					<?php if ($success_message): ?><div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?php echo e($success_message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
					<?php if ($error_message): ?><div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-triangle me-2"></i><?php echo e($error_message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

					<div class="row mb-4">
						<div class="col-md-4 mb-3"><div class="card stat-card"><div class="card-body text-center"><i class="fas fa-users fa-2x mb-2"></i><h4 class="mb-1"><?php echo $total_collectors_count; ?></h4><small>Total Collectors</small></div></div></div>
						<div class="col-md-4 mb-3"><div class="card stat-card success"><div class="card-body text-center"><i class="fas fa-user-check fa-2x mb-2"></i><h4 class="mb-1"><?php echo $active_collectors; ?></h4><small>Active Collectors</small></div></div></div>
						<div class="col-md-4 mb-3"><div class="card stat-card warning"><div class="card-body text-center"><i class="fas fa-truck fa-2x mb-2"></i><h4 class="mb-1"><?php echo $total_collections; ?></h4><small>Collections (30 days)</small></div></div></div>
					</div>

					<div class="card filter-card mb-4"><div class="card-body"><form method="GET" class="row g-3"><div class="col-md-10"><input type="text" class="form-control" name="search" placeholder="Search collectors..." value="<?php echo e($search); ?>"></div><div class="col-md-2"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>Search</button></div></form></div></div>

					<div class="row">
						<?php if ($collectors->num_rows > 0): ?>
							<?php while ($collector = $collectors->fetch_assoc()): ?>
								<div class="col-md-6 col-lg-4 mb-4"><div class="card collector-card"><div class="card-body"><div class="d-flex align-items-start mb-3"><div class="collector-avatar me-3"><i class="fas fa-user"></i></div><div class="flex-grow-1"><h6 class="mb-1"><?php echo e($collector['first_name'] . ' ' . $collector['last_name']); ?></h6><small class="text-muted">@<?php echo e($collector['username']); ?></small></div><div class="dropdown"><button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button><ul class="dropdown-menu"><li><a class="dropdown-item" href="#" onclick="editCollector(<?php echo $collector['id']; ?>)"><i class="fas fa-edit me-2"></i>Edit</a></li><li><a class="dropdown-item text-danger" href="#" onclick="deleteCollector(<?php echo $collector['id']; ?>)"><i class="fas fa-trash me-2"></i>Delete</a></li></ul></div></div><div class="mb-2"><small class="text-muted"><i class="fas fa-envelope me-1"></i><?php echo e($collector['email']); ?></small></div><?php if ($collector['phone']): ?><div class="mb-2"><small class="text-muted"><i class="fas fa-phone me-1"></i><?php echo e($collector['phone']); ?></small></div><?php endif; ?><div class="mb-3"><small class="text-muted"><i class="fas fa-map-marker-alt me-1"></i><?php echo e($collector['address']); ?></small></div><div class="row text-center"><div class="col-6"><div class="border-end"><h6 class="mb-1 text-primary"><?php echo $collector['assigned_schedules']; ?></h6><small class="text-muted">Schedules</small></div></div><div class="col-6"><h6 class="mb-1 text-success"><?php echo $collector['completed_collections']; ?></h6><small class="text-muted">Completed</small></div></div><div class="mt-3"><small class="text-muted"><i class="fas fa-calendar me-1"></i>Joined: <?php echo format_ph_date($collector['created_at'], 'M j, Y'); ?></small></div></div></div></div>
							<?php endwhile; ?>
						<?php else: ?>
							<div class="col-12"><div class="card"><div class="card-body text-center py-5"><i class="fas fa-users fa-4x text-muted mb-4"></i><h4>No Collectors Found</h4><p class="text-muted">No collectors match your search criteria.</p><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCollectorModal"><i class="fas fa-user-plus me-2"></i>Add First Collector</button></div></div></div>
						<?php endif; ?>
					</div>

					<?php if ($total_pages > 1): ?><nav aria-label="Collectors pagination" class="mt-4"><ul class="pagination justify-content-center"><?php if ($page > 1): ?><li class="page-item"><a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">Previous</a></li><?php endif; ?><?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?><li class="page-item <?php echo $i === $page ? 'active' : ''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a></li><?php endfor; ?><?php if ($page < $total_pages): ?><li class="page-item"><a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">Next</a></li><?php endif; ?></ul></nav><?php endif; ?>
				</div>
			</div>
		</div>
	</div>

	<!-- Add Collector Modal -->
	<div class="modal fade" id="addCollectorModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Add New Collector</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form method="POST" id="addCollectorForm"><div class="modal-body"><input type="hidden" name="action" value="add_collector"><div class="mb-3"><label for="username" class="form-label">Username <span class="text-danger">*</span></label><input type="text" class="form-control" id="username" name="username" required></div><div class="row"><div class="col-md-6 mb-3"><label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label><input type="text" class="form-control" id="first_name" name="first_name" required></div><div class="col-md-6 mb-3"><label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label><input type="text" class="form-control" id="last_name" name="last_name" required></div></div><div class="mb-3"><label for="middle_name" class="form-label">Middle Name</label><input type="text" class="form-control" id="middle_name" name="middle_name"></div><div class="mb-3"><label for="email" class="form-label">Email <span class="text-danger">*</span></label><input type="email" class="form-control" id="email" name="email" required></div><div class="mb-3"><label for="phone" class="form-label">Phone</label><input type="tel" class="form-control" id="phone" name="phone"></div><div class="mb-3"><label for="address" class="form-label">Address <span class="text-danger">*</span></label><textarea class="form-control" id="address" name="address" rows="3" required></textarea></div><div class="mb-3"><label for="password" class="form-label">Password <span class="text-danger">*</span></label><input type="password" class="form-control" id="password" name="password" required><div class="form-text">Password must be at least 6 characters long.</div></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Add Collector</button></div></form></div></div></div>

	<!-- Edit Collector Modal -->
	<div class="modal fade" id="editCollectorModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Edit Collector</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form method="POST" id="editCollectorForm"><div class="modal-body"><input type="hidden" name="action" value="update_collector"><input type="hidden" name="collector_id" id="editCollectorId"><div class="row"><div class="col-md-6 mb-3"><label for="edit_first_name" class="form-label">First Name <span class="text-danger">*</span></label><input type="text" class="form-control" id="edit_first_name" name="first_name" required></div><div class="col-md-6 mb-3"><label for="edit_last_name" class="form-label">Last Name <span class="text-danger">*</span></label><input type="text" class="form-control" id="edit_last_name" name="last_name" required></div></div><div class="mb-3"><label for="edit_middle_name" class="form-label">Middle Name</label><input type="text" class="form-control" id="edit_middle_name" name="middle_name"></div><div class="mb-3"><label for="edit_email" class="form-label">Email <span class="text-danger">*</span></label><input type="email" class="form-control" id="edit_email" name="email" required></div><div class="mb-3"><label for="edit_phone" class="form-label">Phone</label><input type="tel" class="form-control" id="edit_phone" name="phone"></div><div class="mb-3"><label for="edit_address" class="form-label">Address <span class="text-danger">*</span></label><textarea class="form-control" id="edit_address" name="address" rows="3" required></textarea></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Update Collector</button></div></form></div></div></div>

	<!-- Delete Confirmation Modal -->
	<div class="modal fade" id="deleteModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Confirm Delete</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><p>Are you sure you want to delete this collector? This action cannot be undone.</p><p class="text-warning"><i class="fas fa-exclamation-triangle me-2"></i>Collectors with assigned schedules cannot be deleted.</p></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><form method="POST" id="deleteForm" style="display: inline;"><input type="hidden" name="action" value="delete_collector"><input type="hidden" name="collector_id" id="deleteCollectorId"><button type="submit" class="btn btn-danger">Delete Collector</button></form></div></div></div></div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
	<script>
		function editCollector(collectorId) { document.getElementById('editCollectorId').value = collectorId; new bootstrap.Modal(document.getElementById('editCollectorModal')).show(); }
		function deleteCollector(collectorId) { document.getElementById('deleteCollectorId').value = collectorId; new bootstrap.Modal(document.getElementById('deleteModal')).show(); }
	</script>
</body>
</html>

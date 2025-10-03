<?php
require_once '../../config/config.php';
require_login();

// Check if user is an authority
if (($_SESSION['role'] ?? '') !== 'authority') {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_resident') {
        $resident_id = $_POST['resident_id'] ?? null;
        $first_name = trim($_POST['first_name'] ?? '');
        $middle_name = trim($_POST['middle_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $eco_points = $_POST['eco_points'] ?? 0;

        if ($resident_id && !empty($first_name) && !empty($last_name) && !empty($email) && !empty($address)) {
            try {
                // Check if email is already taken by another user
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->bind_param("si", $email, $resident_id);
                $stmt->execute();
                
                if ($stmt->get_result()->num_rows > 0) {
                    $error_message = 'This email address is already in use by another account.';
                } else {
                    // Update resident
                    $stmt = $conn->prepare("
                        UPDATE users 
                        SET first_name = ?, middle_name = ?, last_name = ?, email = ?, phone = ?, address = ?, eco_points = ?, updated_at = CURRENT_TIMESTAMP
                        WHERE id = ? AND role = 'resident'
                    ");
                    $stmt->bind_param("ssssssii", $first_name, $middle_name, $last_name, $email, $phone, $address, $eco_points, $resident_id);
                    
                    if ($stmt->execute()) {
                        $success_message = 'Resident updated successfully.';
                    } else {
                        throw new Exception('Failed to update resident.');
                    }
                }
            } catch (Exception $e) {
                $error_message = 'Error updating resident: ' . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'delete_resident') {
        $resident_id = $_POST['resident_id'] ?? null;
        
        if ($resident_id) {
            try {
                // Check if resident has reports
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM waste_reports WHERE user_id = ?");
                $stmt->bind_param("i", $resident_id);
                $stmt->execute();
                $report_count = $stmt->get_result()->fetch_assoc()['count'];
                
                if ($report_count > 0) {
                    $error_message = 'Cannot delete resident with submitted reports. Please handle reports first.';
                } else {
                    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'resident'");
                    $stmt->bind_param("i", $resident_id);
                    
                    if ($stmt->execute()) {
                        $success_message = 'Resident deleted successfully.';
                    } else {
                        throw new Exception('Failed to delete resident.');
                    }
                }
            } catch (Exception $e) {
                $error_message = 'Error deleting resident: ' . $e->getMessage();
            }
        }
    }
}

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get residents with pagination and filters
$page = $_GET['page'] ?? 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$sort_by = $_GET['sort'] ?? 'created_at';
$sort_order = $_GET['order'] ?? 'desc';

// Build query
$where_conditions = ["role = 'resident'"];
$params = [];
$param_types = "";

if (!empty($search)) {
    $where_conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR address LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "ssss";
}

$where_clause = implode(" AND ", $where_conditions);

// Validate sort parameters
$allowed_sorts = ['first_name', 'last_name', 'email', 'eco_points', 'created_at'];
$sort_by = in_array($sort_by, $allowed_sorts) ? $sort_by : 'created_at';
$sort_order = strtolower($sort_order) === 'asc' ? 'ASC' : 'DESC';

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM users WHERE {$where_clause}";
$stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$total_residents = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_residents / $limit);

// Get residents
$sql = "
    SELECT u.*, 
           COUNT(wr.id) as total_reports,
           COUNT(CASE WHEN wr.status = 'completed' THEN 1 END) as completed_reports,
           COUNT(f.id) as total_feedback
    FROM users u 
    LEFT JOIN waste_reports wr ON u.id = wr.user_id
    LEFT JOIN feedback f ON u.id = f.user_id
    WHERE {$where_clause}
    GROUP BY u.id
    ORDER BY u.{$sort_by} {$sort_order}
    LIMIT ? OFFSET ?
";
$params[] = $limit;
$params[] = $offset;
$param_types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$residents = $stmt->get_result();

// Get statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total_residents FROM users WHERE role = 'resident'");
$stmt->execute();
$total_residents_count = $stmt->get_result()->fetch_assoc()['total_residents'];

$stmt = $conn->prepare("
    SELECT COUNT(*) as active_residents 
    FROM users 
    WHERE role = 'resident' AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
");
$stmt->execute();
$active_residents = $stmt->get_result()->fetch_assoc()['active_residents'];

$stmt = $conn->prepare("
    SELECT SUM(eco_points) as total_points 
    FROM users 
    WHERE role = 'resident'
");
$stmt->execute();
$total_points = $stmt->get_result()->fetch_assoc()['total_points'] ?? 0;

$stmt = $conn->prepare("
    SELECT COUNT(*) as total_reports 
    FROM waste_reports wr 
    JOIN users u ON wr.user_id = u.id 
    WHERE u.role = 'resident' AND DATE(wr.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
");
$stmt->execute();
$total_reports = $stmt->get_result()->fetch_assoc()['total_reports'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Residents Management - <?php echo APP_NAME; ?></title>
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
        .resident-card {
            border-left: 4px solid #28a745;
            transition: transform 0.2s;
        }
        .resident-card:hover {
            transform: translateY(-2px);
        }
        .filter-card {
            background: #f8f9fa;
            border-radius: 10px;
        }
        .stat-card {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        .stat-card.info {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
        }
        .stat-card.warning {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
        }
        .resident-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }
        .points-badge {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            color: #000;
            font-weight: bold;
        }
    </style>
</head>
<body class="role-authority">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar text-white p-0">
                <div class="p-3">
                    <h4 class="mb-4"><i class="fas fa-shield-alt me-2"></i><?php echo APP_NAME; ?></h4>
                    <hr class="bg-white">
                    <nav class="nav flex-column">
                        <a class="nav-link text-white" href="index.php">
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
                        <a class="nav-link text-white active" href="residents.php">
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
                            <h2 class="mb-1">Residents Management</h2>
                            <p class="text-muted mb-0">Manage registered residents and their activities</p>
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

                    <!-- Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-home fa-2x mb-2"></i>
                                    <h4 class="mb-1"><?php echo $total_residents_count; ?></h4>
                                    <small>Total Residents</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card info">
                                <div class="card-body text-center">
                                    <i class="fas fa-user-plus fa-2x mb-2"></i>
                                    <h4 class="mb-1"><?php echo $active_residents; ?></h4>
                                    <small>New (30 days)</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card warning">
                                <div class="card-body text-center">
                                    <i class="fas fa-leaf fa-2x mb-2"></i>
                                    <h4 class="mb-1"><?php echo number_format($total_points); ?></h4>
                                    <small>Total Eco Points</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-exclamation-circle fa-2x mb-2"></i>
                                    <h4 class="mb-1"><?php echo $total_reports; ?></h4>
                                    <small>Reports (30 days)</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Search and Sort -->
                    <div class="card filter-card mb-4">
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-6">
                                    <input type="text" class="form-control" name="search" placeholder="Search residents..." value="<?php echo e($search); ?>">
                                </div>
                                <div class="col-md-3">
                                    <select class="form-select" name="sort">
                                        <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Join Date</option>
                                        <option value="first_name" <?php echo $sort_by === 'first_name' ? 'selected' : ''; ?>>First Name</option>
                                        <option value="last_name" <?php echo $sort_by === 'last_name' ? 'selected' : ''; ?>>Last Name</option>
                                        <option value="eco_points" <?php echo $sort_by === 'eco_points' ? 'selected' : ''; ?>>Eco Points</option>
                                        <option value="email" <?php echo $sort_by === 'email' ? 'selected' : ''; ?>>Email</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select class="form-select" name="order">
                                        <option value="desc" <?php echo $sort_order === 'DESC' ? 'selected' : ''; ?>>Descending</option>
                                        <option value="asc" <?php echo $sort_order === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                                    </select>
                                </div>
                                <div class="col-md-1">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Residents List -->
                    <div class="row">
                        <?php if ($residents->num_rows > 0): ?>
                            <?php while ($resident = $residents->fetch_assoc()): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card resident-card">
                                        <div class="card-body">
                                            <div class="d-flex align-items-start mb-3">
                                                <div class="resident-avatar me-3">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1"><?php echo e($resident['first_name'] . ' ' . $resident['last_name']); ?></h6>
                                                    <small class="text-muted">@<?php echo e($resident['username']); ?></small>
                                                </div>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li><a class="dropdown-item" href="#" onclick="editResident(<?php echo $resident['id']; ?>)">
                                                            <i class="fas fa-edit me-2"></i>Edit
                                                        </a></li>
                                                        <li><a class="dropdown-item" href="#" onclick="viewResidentDetails(<?php echo $resident['id']; ?>)">
                                                            <i class="fas fa-eye me-2"></i>View Details
                                                        </a></li>
                                                        <li><a class="dropdown-item text-danger" href="#" onclick="deleteResident(<?php echo $resident['id']; ?>)">
                                                            <i class="fas fa-trash me-2"></i>Delete
                                                        </a></li>
                                                    </ul>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-2">
                                                <small class="text-muted">
                                                    <i class="fas fa-envelope me-1"></i><?php echo e($resident['email']); ?>
                                                </small>
                                            </div>
                                            
                                            <?php if ($resident['phone']): ?>
                                                <div class="mb-2">
                                                    <small class="text-muted">
                                                        <i class="fas fa-phone me-1"></i><?php echo e($resident['phone']); ?>
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="mb-3">
                                                <small class="text-muted">
                                                    <i class="fas fa-map-marker-alt me-1"></i><?php echo e($resident['address']); ?>
                                                </small>
                                            </div>
                                            
                                            <!-- Eco Points -->
                                            <div class="mb-3">
                                                <span class="badge points-badge">
                                                    <i class="fas fa-leaf me-1"></i><?php echo $resident['eco_points']; ?> Eco Points
                                                </span>
                                            </div>
                                            
                                            <!-- Activity Stats -->
                                            <div class="row text-center mb-3">
                                                <div class="col-4">
                                                    <div class="border-end">
                                                        <h6 class="mb-1 text-primary"><?php echo $resident['total_reports']; ?></h6>
                                                        <small class="text-muted">Reports</small>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="border-end">
                                                        <h6 class="mb-1 text-success"><?php echo $resident['completed_reports']; ?></h6>
                                                        <small class="text-muted">Completed</small>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <h6 class="mb-1 text-info"><?php echo $resident['total_feedback']; ?></h6>
                                                    <small class="text-muted">Feedback</small>
                                                </div>
                                            </div>
                                            
                                            <div class="mt-3">
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar me-1"></i>Joined: <?php echo format_ph_date($resident['created_at'], 'M j, Y'); ?>
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
                                        <i class="fas fa-home fa-4x text-muted mb-4"></i>
                                        <h4>No Residents Found</h4>
                                        <p class="text-muted">No residents match your search criteria.</p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Residents pagination" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort_by); ?>&order=<?php echo urlencode($sort_order); ?>">Previous</a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort_by); ?>&order=<?php echo urlencode($sort_order); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort_by); ?>&order=<?php echo urlencode($sort_order); ?>">Next</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Resident Modal -->
    <div class="modal fade" id="editResidentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Resident</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editResidentForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_resident">
                        <input type="hidden" name="resident_id" id="editResidentId">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_middle_name" class="form-label">Middle Name</label>
                            <input type="text" class="form-control" id="edit_middle_name" name="middle_name">
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_phone" class="form-label">Phone</label>
                            <input type="tel" class="form-control" id="edit_phone" name="phone">
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_address" class="form-label">Address <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="edit_address" name="address" rows="3" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_eco_points" class="form-label">Eco Points</label>
                            <input type="number" class="form-control" id="edit_eco_points" name="eco_points" min="0">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Resident</button>
                    </div>
                </form>
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
                    <p>Are you sure you want to delete this resident? This action cannot be undone.</p>
                    <p class="text-warning"><i class="fas fa-exclamation-triangle me-2"></i>Residents with submitted reports cannot be deleted.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" id="deleteForm" style="display: inline;">
                        <input type="hidden" name="action" value="delete_resident">
                        <input type="hidden" name="resident_id" id="deleteResidentId">
                        <button type="submit" class="btn btn-danger">Delete Resident</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Resident Details Modal -->
    <div class="modal fade" id="residentDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Resident Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="residentDetailsBody">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editResident(residentId) {
            // In a real application, you would fetch resident details via AJAX
            document.getElementById('editResidentId').value = residentId;
            new bootstrap.Modal(document.getElementById('editResidentModal')).show();
        }

        function deleteResident(residentId) {
            document.getElementById('deleteResidentId').value = residentId;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        function viewResidentDetails(residentId) {
            // In a real application, you would fetch resident details via AJAX
            document.getElementById('residentDetailsBody').innerHTML = `
                <p>Resident ID: ${residentId}</p>
                <p>This would show detailed resident information including all reports, feedback, and activity history.</p>
            `;
            new bootstrap.Modal(document.getElementById('residentDetailsModal')).show();
        }
    </script>
</body>
</html>

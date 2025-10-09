<?php
require_once __DIR__ . '/../../config/config.php';
require_login();

// Check if user is a resident
if (($_SESSION['role'] ?? '') !== 'resident') {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Check if collection_history table exists
$table_check = $conn->query("SHOW TABLES LIKE 'collection_history'");
$table_exists = $table_check && $table_check->num_rows > 0;

// Initialize default values
$collection_history = null;
$total_count = 0;
$total_pages = 0;
$stats = [
    'total_collections' => 0,
    'completed_collections' => 0,
    'missed_collections' => 0,
    'pending_collections' => 0
];

if ($table_exists) {
    // Get collection history for the user's area with pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;

    $address_search = '%' . $user['address'] . '%';
    $user_address = $user['address'];

    // Get collection history
    $stmt = $conn->prepare("
        SELECT ch.*, cs.street_name, cs.area, cs.waste_type, u.first_name, u.last_name
        FROM collection_history ch
        JOIN collection_schedules cs ON ch.schedule_id = cs.id
        LEFT JOIN users u ON ch.collector_id = u.id
        WHERE 
            cs.street_name LIKE ? OR cs.area LIKE ?
            OR ? LIKE CONCAT('%', cs.street_name, '%')
            OR ? LIKE CONCAT('%', cs.area, '%')
        ORDER BY ch.collection_date DESC
        LIMIT ? OFFSET ?
    ");
    
    if ($stmt) {
        $stmt->bind_param("ssssii", $address_search, $address_search, $user_address, $user_address, $limit, $offset);
        $stmt->execute();
        $collection_history = $stmt->get_result();
    }

    // Get total count for pagination
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM collection_history ch
        JOIN collection_schedules cs ON ch.schedule_id = cs.id
        WHERE 
            cs.street_name LIKE ? OR cs.area LIKE ?
            OR ? LIKE CONCAT('%', cs.street_name, '%')
            OR ? LIKE CONCAT('%', cs.area, '%')
    ");
    
    if ($stmt) {
        $stmt->bind_param("ssss", $address_search, $address_search, $user_address, $user_address);
        $stmt->execute();
        $total_count = $stmt->get_result()->fetch_assoc()['total'];
        $total_pages = ceil($total_count / $limit);
    }

    // Get statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_collections,
            SUM(CASE WHEN ch.status = 'completed' THEN 1 ELSE 0 END) as completed_collections,
            SUM(CASE WHEN ch.status = 'missed' THEN 1 ELSE 0 END) as missed_collections,
            SUM(CASE WHEN ch.status = 'scheduled' THEN 1 ELSE 0 END) as pending_collections
        FROM collection_history ch
        JOIN collection_schedules cs ON ch.schedule_id = cs.id
        WHERE 
            cs.street_name LIKE ? OR cs.area LIKE ?
            OR ? LIKE CONCAT('%', cs.street_name, '%')
            OR ? LIKE CONCAT('%', cs.area, '%')
    ");
    
    if ($stmt) {
        $stmt->bind_param("ssss", $address_search, $address_search, $user_address, $user_address);
        $stmt->execute();
        $stats_result = $stmt->get_result()->fetch_assoc();
        if ($stats_result) {
            $stats = $stats_result;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recent Collections - <?php echo APP_NAME; ?></title>
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
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-2px);
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
        .stat-card {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
        }
        .stat-card.success {
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
        }
        .stat-card.danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }
        .stat-card.warning {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
        }
        .collection-item {
            border-left: 4px solid #007bff;
            transition: all 0.3s;
        }
        .collection-item:hover {
            transform: translateX(5px);
        }
        .collection-item.completed {
            border-left-color: #28a745;
        }
        .collection-item.missed {
            border-left-color: #dc3545;
        }
        .collection-item.pending {
            border-left-color: #ffc107;
        }
        .status-badge {
            font-size: 0.8rem;
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
                            <h2 class="mb-1">Recent Collections</h2>
                            <p class="text-muted mb-0">Your waste collection history and statistics</p>
                        </div>
                        <div class="text-end">
                            <div class="h4 text-success mb-0"><?php echo $user['eco_points']; ?></div>
                            <small class="text-muted">Eco Points</small>
                        </div>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">Total Collections</h6>
                                            <h4 class="mb-0"><?php echo $stats['total_collections']; ?></h4>
                                            <small>All Time</small>
                                        </div>
                                        <i class="fas fa-truck fa-2x opacity-75"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card success">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">Completed</h6>
                                            <h4 class="mb-0"><?php echo $stats['completed_collections']; ?></h4>
                                            <small>Successfully collected</small>
                                        </div>
                                        <i class="fas fa-check-circle fa-2x opacity-75"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card danger">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">Missed</h6>
                                            <h4 class="mb-0"><?php echo $stats['missed_collections']; ?></h4>
                                            <small>Not collected</small>
                                        </div>
                                        <i class="fas fa-times-circle fa-2x opacity-75"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card warning">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">Pending</h6>
                                            <h4 class="mb-0"><?php echo $stats['pending_collections']; ?></h4>
                                            <small>Awaiting collection</small>
                                        </div>
                                        <i class="fas fa-clock fa-2x opacity-75"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Collection History -->
                    <div class="card">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-history me-2"></i>Collection History</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!$table_exists): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-database fa-3x text-muted mb-3"></i>
                                    <h5>Database Not Set Up</h5>
                                    <p class="text-muted">The collection history table has not been created yet. Please contact the administrator.</p>
                                    <a href="schedule.php" class="btn btn-primary">
                                        <i class="fas fa-calendar me-2"></i>View Collection Schedule
                                    </a>
                                </div>
                            <?php elseif ($collection_history && $collection_history->num_rows > 0): ?>
                                <?php while ($history = $collection_history->fetch_assoc()): ?>
                                    <div class="collection-item <?php echo $history['status']; ?> p-3 mb-3">
                                        <div class="row align-items-center">
                                            <div class="col-md-8">
                                                <h6 class="mb-1"><?php echo e($history['street_name']); ?></h6>
                                                <p class="mb-1 text-muted">
                                                    <i class="fas fa-calendar me-1"></i>
                                                    <?php echo format_ph_date($history['collection_date'], 'l, F j, Y'); ?>
                                                    <?php if ($history['collection_time']): ?>
                                                        at <?php echo format_ph_date($history['collection_time'], 'g:i A'); ?>
                                                    <?php endif; ?>
                                                </p>
                                                <?php if ($history['first_name']): ?>
                                                    <p class="mb-0 text-muted">
                                                        <i class="fas fa-user me-1"></i>
                                                        Collector: <?php echo e($history['first_name'] . ' ' . $history['last_name']); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-4 text-end">
                                                <span class="badge bg-<?php echo $history['status'] === 'completed' ? 'success' : ($history['status'] === 'missed' ? 'danger' : 'warning'); ?> status-badge mb-2">
                                                    <?php echo ucfirst($history['status']); ?>
                                                </span>
                                                <br>
                                                <span class="badge bg-info status-badge">
                                                    <?php echo ucfirst($history['waste_type']); ?>
                                                </span>
                                                <?php if ($history['notes']): ?>
                                                    <br>
                                                    <small class="text-muted mt-1 d-block">
                                                        <i class="fas fa-sticky-note me-1"></i>
                                                        <?php echo e($history['notes']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>

                                <!-- Pagination -->
                                <?php if ($total_pages > 1): ?>
                                    <nav aria-label="Collection history pagination">
                                        <ul class="pagination justify-content-center">
                                            <?php if ($page > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                                                </li>
                                            <?php endif; ?>
                                            
                                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            
                                            <?php if ($page < $total_pages): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </nav>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                    <h5>No Collection History</h5>
                                    <p class="text-muted">No collection history found for your area yet.</p>
                                    <a href="schedule.php" class="btn btn-primary">
                                        <i class="fas fa-calendar me-2"></i>View Collection Schedule
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

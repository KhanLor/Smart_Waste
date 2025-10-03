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

// Get points transactions with pagination
$page = $_GET['page'] ?? 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$filter = $_GET['filter'] ?? '';

// Build query
$where_conditions = ["user_id = ?"];
$params = [$user_id];
$param_types = "i";

if (!empty($filter) && $filter !== 'all') {
    $where_conditions[] = "transaction_type = ?";
    $params[] = $filter;
    $param_types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM points_transactions WHERE {$where_clause}";
$stmt = $conn->prepare($count_sql);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$total_transactions = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_transactions / $limit);

// Get transactions
$sql = "SELECT * FROM points_transactions WHERE {$where_clause} ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$param_types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$transactions = $stmt->get_result();

// Calculate statistics
$stmt = $conn->prepare("
    SELECT 
        SUM(CASE WHEN transaction_type = 'earned' THEN points ELSE 0 END) as total_earned,
        SUM(CASE WHEN transaction_type = 'spent' THEN points ELSE 0 END) as total_spent,
        SUM(CASE WHEN transaction_type = 'bonus' THEN points ELSE 0 END) as total_bonus,
        SUM(CASE WHEN transaction_type = 'penalty' THEN points ELSE 0 END) as total_penalty
    FROM points_transactions 
    WHERE user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Get recent achievements (if any)
$stmt = $conn->prepare("
    SELECT * FROM points_transactions 
    WHERE user_id = ? AND transaction_type IN ('earned', 'bonus')
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_achievements = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eco Points - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        .points-card {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        .stat-card {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
        }
        .stat-card.success {
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
        }
        .stat-card.warning {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
        }
        .stat-card.danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }
        .transaction-item {
            border-left: 4px solid #28a745;
            transition: transform 0.2s;
        }
        .transaction-item:hover {
            transform: translateX(5px);
        }
        .transaction-item.earned {
            border-left-color: #28a745;
        }
        .transaction-item.spent {
            border-left-color: #dc3545;
        }
        .transaction-item.bonus {
            border-left-color: #ffc107;
        }
        .transaction-item.penalty {
            border-left-color: #6f42c1;
        }
        .points-badge {
            font-size: 1.1rem;
            font-weight: bold;
        }
        .achievement-badge {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            color: #000;
        }
        .level-progress {
            height: 20px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.2);
        }
        .level-progress .progress-bar {
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar text-white p-0">
                <div class="p-3">
                    <h4 class="mb-4"><i class="fas fa-recycle me-2"></i><?php echo APP_NAME; ?></h4>
                    <hr class="bg-white">
                    <nav class="nav flex-column">
                        <a class="nav-link text-white" href="index.php">
                            <i class="fas fa-home me-2"></i>Dashboard
                        </a>
                        <a class="nav-link text-white" href="reports.php">
                            <i class="fas fa-exclamation-circle me-2"></i>My Reports
                        </a>
                        <a class="nav-link text-white" href="submit_report.php">
                            <i class="fas fa-plus-circle me-2"></i>Submit Report
                        </a>
                        <a class="nav-link text-white" href="schedule.php">
                            <i class="fas fa-calendar me-2"></i>Collection Schedule
                        </a>
                        <a class="nav-link text-white active" href="points.php">
                            <i class="fas fa-leaf me-2"></i>Eco Points
                        </a>
                        <a class="nav-link text-white" href="feedback.php">
                            <i class="fas fa-comment me-2"></i>Feedback
                        </a>
                        <a class="nav-link text-white" href="chat.php">
                            <i class="fas fa-comments me-2"></i>Chat
                        </a>
                        <a class="nav-link text-white" href="profile.php">
                            <i class="fas fa-user me-2"></i>Profile
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
                            <h2 class="mb-1">Eco Points</h2>
                            <p class="text-muted mb-0">Track your environmental contributions and rewards</p>
                        </div>
                        <div class="text-end">
                            <div class="h2 text-success mb-0"><?php echo $user['eco_points']; ?></div>
                            <small class="text-muted">Current Points</small>
                        </div>
                    </div>

                    <!-- Current Points Card -->
                    <div class="card points-card mb-4">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h3 class="mb-2">
                                        <i class="fas fa-leaf me-2"></i>
                                        Your Eco Points
                                    </h3>
                                    <h1 class="mb-2"><?php echo $user['eco_points']; ?></h1>
                                    <p class="mb-0">Keep contributing to earn more points and help the environment!</p>
                                </div>
                                <div class="col-md-4 text-end">
                                    <i class="fas fa-trophy fa-4x opacity-75"></i>
                                </div>
                            </div>
                            
                            <!-- Level Progress -->
                            <div class="mt-4">
                                <?php 
                                $current_level = floor($user['eco_points'] / 100);
                                $next_level_points = ($current_level + 1) * 100;
                                $progress = ($user['eco_points'] % 100);
                                ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>Level <?php echo $current_level; ?></span>
                                    <span><?php echo $user['eco_points']; ?> / <?php echo $next_level_points; ?> points</span>
                                </div>
                                <div class="level-progress">
                                    <div class="progress-bar bg-warning" style="width: <?php echo ($progress / 100) * 100; ?>%"></div>
                                </div>
                                <small class="text-white-50 mt-1 d-block">
                                    <?php echo $next_level_points - $user['eco_points']; ?> points to next level
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card success">
                                <div class="card-body text-center">
                                    <i class="fas fa-plus-circle fa-2x mb-2"></i>
                                    <h4 class="mb-1"><?php echo $stats['total_earned'] ?? 0; ?></h4>
                                    <small>Points Earned</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-gift fa-2x mb-2"></i>
                                    <h4 class="mb-1"><?php echo $stats['total_bonus'] ?? 0; ?></h4>
                                    <small>Bonus Points</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card warning">
                                <div class="card-body text-center">
                                    <i class="fas fa-minus-circle fa-2x mb-2"></i>
                                    <h4 class="mb-1"><?php echo $stats['total_spent'] ?? 0; ?></h4>
                                    <small>Points Spent</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card danger">
                                <div class="card-body text-center">
                                    <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                                    <h4 class="mb-1"><?php echo $stats['total_penalty'] ?? 0; ?></h4>
                                    <small>Penalty Points</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Recent Achievements -->
                        <div class="col-md-4 mb-4">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="fas fa-trophy me-2"></i>Recent Achievements</h6>
                                </div>
                                <div class="card-body">
                                    <?php if ($recent_achievements->num_rows > 0): ?>
                                        <?php while ($achievement = $recent_achievements->fetch_assoc()): ?>
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <div>
                                                    <h6 class="mb-1"><?php echo e($achievement['description']); ?></h6>
                                                    <small class="text-muted">
                                                        <?php echo format_ph_date($achievement['created_at'], 'M j, Y'); ?>
                                                    </small>
                                                </div>
                                                <span class="badge achievement-badge points-badge">
                                                    +<?php echo $achievement['points']; ?>
                                                </span>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <p class="text-muted text-center">No achievements yet. Start earning points!</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Points History -->
                        <div class="col-md-8 mb-4">
                            <div class="card">
                                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><i class="fas fa-history me-2"></i>Points History</h6>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="?filter=all" class="btn btn-outline-secondary <?php echo $filter === 'all' || $filter === '' ? 'active' : ''; ?>">All</a>
                                        <a href="?filter=earned" class="btn btn-outline-success <?php echo $filter === 'earned' ? 'active' : ''; ?>">Earned</a>
                                        <a href="?filter=spent" class="btn btn-outline-danger <?php echo $filter === 'spent' ? 'active' : ''; ?>">Spent</a>
                                        <a href="?filter=bonus" class="btn btn-outline-warning <?php echo $filter === 'bonus' ? 'active' : ''; ?>">Bonus</a>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <?php if ($transactions->num_rows > 0): ?>
                                        <?php while ($transaction = $transactions->fetch_assoc()): ?>
                                            <div class="transaction-item <?php echo $transaction['transaction_type']; ?> p-3 mb-2">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="mb-1"><?php echo e($transaction['description']); ?></h6>
                                                        <small class="text-muted">
                                                            <?php echo format_ph_date($transaction['created_at']); ?>
                                                        </small>
                                                        <?php if ($transaction['reference_type']): ?>
                                                            <br>
                                                            <span class="badge bg-secondary">
                                                                <?php echo ucfirst($transaction['reference_type']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="text-end">
                                                        <span class="badge bg-<?php echo $transaction['transaction_type'] === 'earned' ? 'success' : ($transaction['transaction_type'] === 'spent' ? 'danger' : ($transaction['transaction_type'] === 'bonus' ? 'warning' : 'dark')); ?> points-badge">
                                                            <?php echo $transaction['transaction_type'] === 'earned' || $transaction['transaction_type'] === 'bonus' ? '+' : '-'; ?><?php echo $transaction['points']; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <p class="text-muted text-center">No transactions found.</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Points history pagination" class="mt-3">
                                    <ul class="pagination justify-content-center">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&filter=<?php echo urlencode($filter); ?>">Previous</a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&filter=<?php echo urlencode($filter); ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&filter=<?php echo urlencode($filter); ?>">Next</a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- How to Earn Points -->
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0"><i class="fas fa-lightbulb me-2"></i>How to Earn Eco Points</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="text-center mb-3">
                                        <i class="fas fa-exclamation-circle fa-2x text-primary mb-2"></i>
                                        <h6>Submit Reports</h6>
                                        <p class="mb-0">5 points per report</p>
                                        <small class="text-muted">+2 for high priority, +3 for urgent</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center mb-3">
                                        <i class="fas fa-comment fa-2x text-info mb-2"></i>
                                        <h6>Give Feedback</h6>
                                        <p class="mb-0">3 points per feedback</p>
                                        <small class="text-muted">Help improve our services</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center mb-3">
                                        <i class="fas fa-recycle fa-2x text-success mb-2"></i>
                                        <h6>Proper Disposal</h6>
                                        <p class="mb-0">2 points per week</p>
                                        <small class="text-muted">Follow collection schedules</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center mb-3">
                                        <i class="fas fa-gift fa-2x text-warning mb-2"></i>
                                        <h6>Special Bonuses</h6>
                                        <p class="mb-0">Variable points</p>
                                        <small class="text-muted">Seasonal rewards and milestones</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

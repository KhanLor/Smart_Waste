<?php
require_once '../../config/config.php';
require_login();

// Check if user is an authority
if (($_SESSION['role'] ?? '') !== 'authority') {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get date range for analytics
$date_range = $_GET['range'] ?? '30';
    $start_date = date('Y-m-d', strtotime("-{$date_range} days"));
    $end_date = date('Y-m-d');

// Overall Statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'resident'");
$stmt->execute();
$total_residents = $stmt->get_result()->fetch_assoc()['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'collector'");
$stmt->execute();
$total_collectors = $stmt->get_result()->fetch_assoc()['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM waste_reports");
$stmt->execute();
$total_reports = $stmt->get_result()->fetch_assoc()['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM collection_schedules WHERE status = 'active'");
$stmt->execute();
$active_schedules = $stmt->get_result()->fetch_assoc()['total'];

// Reports Analytics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_reports,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_reports,
        SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) as assigned_reports,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_reports,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_reports,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_reports
    FROM waste_reports 
    WHERE DATE(created_at) BETWEEN ? AND ?
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$reports_stats = $stmt->get_result()->fetch_assoc();

// Reports by Priority
$stmt = $conn->prepare("
    SELECT priority, COUNT(*) as count 
    FROM waste_reports 
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY priority
    ORDER BY FIELD(priority, 'urgent', 'high', 'medium', 'low')
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$priority_stats = $stmt->get_result();

// Reports by Type
$stmt = $conn->prepare("
    SELECT report_type, COUNT(*) as count 
    FROM waste_reports 
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY report_type
    ORDER BY count DESC
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$type_stats = $stmt->get_result();

// Daily Reports Trend
$stmt = $conn->prepare("
    SELECT DATE(created_at) as date, COUNT(*) as count 
    FROM waste_reports 
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY date
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$daily_trend = $stmt->get_result();

// Collection Analytics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_collections,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_collections,
        SUM(CASE WHEN status = 'missed' THEN 1 ELSE 0 END) as missed_collections,
        SUM(CASE WHEN status = 'partial' THEN 1 ELSE 0 END) as partial_collections
    FROM collection_history 
    WHERE DATE(collection_date) BETWEEN ? AND ?
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$collection_stats = $stmt->get_result()->fetch_assoc();

// Top Performing Collectors
$stmt = $conn->prepare("
    SELECT u.first_name, u.last_name, COUNT(ch.id) as completed_collections
    FROM users u
    JOIN collection_history ch ON u.id = ch.collector_id
    WHERE u.role = 'collector' AND ch.status = 'completed' AND DATE(ch.collection_date) BETWEEN ? AND ?
    GROUP BY u.id
    ORDER BY completed_collections DESC
    LIMIT 5
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$top_collectors = $stmt->get_result();

// Most Active Residents
$stmt = $conn->prepare("
    SELECT u.first_name, u.last_name, COUNT(wr.id) as report_count, u.eco_points
    FROM users u
    JOIN waste_reports wr ON u.id = wr.user_id
    WHERE u.role = 'resident' AND DATE(wr.created_at) BETWEEN ? AND ?
    GROUP BY u.id
    ORDER BY report_count DESC
    LIMIT 5
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$active_residents = $stmt->get_result();

// Area-wise Reports
$stmt = $conn->prepare("
    SELECT 
        SUBSTRING_INDEX(SUBSTRING_INDEX(wr.location, ',', 1), ',', -1) as area,
        COUNT(*) as count
    FROM waste_reports wr
    WHERE DATE(wr.created_at) BETWEEN ? AND ?
    GROUP BY area
    ORDER BY count DESC
    LIMIT 10
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$area_stats = $stmt->get_result();

// Response Time Analytics
$stmt = $conn->prepare("
    SELECT 
        AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as avg_response_hours,
        MIN(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as min_response_hours,
        MAX(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as max_response_hours
    FROM waste_reports 
    WHERE status = 'completed' AND DATE(created_at) BETWEEN ? AND ?
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$response_stats = $stmt->get_result()->fetch_assoc();

// Feedback Analytics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_feedback,
        AVG(rating) as avg_rating,
        SUM(CASE WHEN feedback_type = 'suggestion' THEN 1 ELSE 0 END) as suggestions,
        SUM(CASE WHEN feedback_type = 'complaint' THEN 1 ELSE 0 END) as complaints,
        SUM(CASE WHEN feedback_type = 'appreciation' THEN 1 ELSE 0 END) as appreciations,
        SUM(CASE WHEN feedback_type = 'bug_report' THEN 1 ELSE 0 END) as bug_reports
    FROM feedback 
    WHERE DATE(created_at) BETWEEN ? AND ?
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$feedback_stats = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .stat-card.info {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
        }
        .chart-container {
            position: relative;
            height: 300px;
        }
        .analytics-header {
            background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%);
            color: white;
            border-radius: 15px;
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
                        <a class="nav-link text-white" href="residents.php">
                            <i class="fas fa-home me-2"></i>Residents
                        </a>
                        <a class="nav-link text-white active" href="analytics.php">
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
                    <div class="card analytics-header mb-4">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h2 class="mb-2">
                                        <i class="fas fa-chart-line me-2"></i>Analytics Dashboard
                                    </h2>
                                    <p class="mb-0">Comprehensive insights into waste management operations</p>
                                </div>
                                <div class="col-md-4">
                                    <form method="GET" class="d-flex">
                                        <select class="form-select me-2" name="range" onchange="this.form.submit()">
                                            <option value="7" <?php echo $date_range === '7' ? 'selected' : ''; ?>>Last 7 days</option>
                                            <option value="30" <?php echo $date_range === '30' ? 'selected' : ''; ?>>Last 30 days</option>
                                            <option value="90" <?php echo $date_range === '90' ? 'selected' : ''; ?>>Last 90 days</option>
                                            <option value="365" <?php echo $date_range === '365' ? 'selected' : ''; ?>>Last year</option>
                                        </select>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Overall Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-home fa-2x mb-2"></i>
                                    <h4 class="mb-1"><?php echo number_format($total_residents); ?></h4>
                                    <small>Total Residents</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card success">
                                <div class="card-body text-center">
                                    <i class="fas fa-users fa-2x mb-2"></i>
                                    <h4 class="mb-1"><?php echo number_format($total_collectors); ?></h4>
                                    <small>Total Collectors</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card warning">
                                <div class="card-body text-center">
                                    <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                                    <h4 class="mb-1"><?php echo number_format($total_reports); ?></h4>
                                    <small>Total Reports</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card info">
                                <div class="card-body text-center">
                                    <i class="fas fa-calendar fa-2x mb-2"></i>
                                    <h4 class="mb-1"><?php echo number_format($active_schedules); ?></h4>
                                    <small>Active Schedules</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Reports Analytics -->
                    <div class="row mb-4">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Reports Overview (<?php echo $date_range; ?> days)</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="chart-container">
                                                <canvas id="reportsStatusChart"></canvas>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="chart-container">
                                                <canvas id="reportsPriorityChart"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="fas fa-list me-2"></i>Report Statistics</h6>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Total Reports:</span>
                                        <strong><?php echo $reports_stats['total_reports']; ?></strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Pending:</span>
                                        <span class="text-warning"><?php echo $reports_stats['pending_reports']; ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Assigned:</span>
                                        <span class="text-info"><?php echo $reports_stats['assigned_reports']; ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>In Progress:</span>
                                        <span class="text-primary"><?php echo $reports_stats['in_progress_reports']; ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Completed:</span>
                                        <span class="text-success"><?php echo $reports_stats['completed_reports']; ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Cancelled:</span>
                                        <span class="text-danger"><?php echo $reports_stats['cancelled_reports']; ?></span>
                                    </div>
                                    <hr>
                                    <div class="d-flex justify-content-between">
                                        <span>Completion Rate:</span>
                                        <strong class="text-success">
                                            <?php 
                                            $completion_rate = $reports_stats['total_reports'] > 0 ? 
                                                round(($reports_stats['completed_reports'] / $reports_stats['total_reports']) * 100, 1) : 0;
                                            echo $completion_rate . '%';
                                            ?>
                                        </strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Collection Analytics -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0"><i class="fas fa-truck me-2"></i>Collection Performance</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <h4 class="text-success"><?php echo $collection_stats['completed_collections']; ?></h4>
                                            <small class="text-muted">Completed</small>
                                        </div>
                                        <div class="col-4">
                                            <h4 class="text-danger"><?php echo $collection_stats['missed_collections']; ?></h4>
                                            <small class="text-muted">Missed</small>
                                        </div>
                                        <div class="col-4">
                                            <h4 class="text-warning"><?php echo $collection_stats['partial_collections']; ?></h4>
                                            <small class="text-muted">Partial</small>
                                        </div>
                                    </div>
                                    <hr>
                                    <div class="text-center">
                                        <h5 class="text-success">
                                            <?php 
                                            $success_rate = $collection_stats['total_collections'] > 0 ? 
                                                round(($collection_stats['completed_collections'] / $collection_stats['total_collections']) * 100, 1) : 0;
                                            echo $success_rate . '%';
                                            ?>
                                        </h5>
                                        <small class="text-muted">Success Rate</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Response Time</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <h4 class="text-primary"><?php echo round($response_stats['avg_response_hours'], 1); ?></h4>
                                            <small class="text-muted">Avg Hours</small>
                                        </div>
                                        <div class="col-4">
                                            <h4 class="text-success"><?php echo $response_stats['min_response_hours']; ?></h4>
                                            <small class="text-muted">Min Hours</small>
                                        </div>
                                        <div class="col-4">
                                            <h4 class="text-warning"><?php echo $response_stats['max_response_hours']; ?></h4>
                                            <small class="text-muted">Max Hours</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Top Performers -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Top Collectors</h5>
                                </div>
                                <div class="card-body">
                                    <?php if ($top_collectors->num_rows > 0): ?>
                                        <?php $rank = 1; while ($collector = $top_collectors->fetch_assoc()): ?>
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div>
                                                    <span class="badge bg-<?php echo $rank <= 3 ? ($rank === 1 ? 'warning' : ($rank === 2 ? 'secondary' : 'danger')) : 'light text-dark'; ?> me-2">
                                                        #<?php echo $rank; ?>
                                                    </span>
                                                    <?php echo e($collector['first_name'] . ' ' . $collector['last_name']); ?>
                                                </div>
                                                <span class="badge bg-success"><?php echo $collector['completed_collections']; ?> collections</span>
                                            </div>
                                        <?php $rank++; endwhile; ?>
                                    <?php else: ?>
                                        <p class="text-muted text-center">No collection data available.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0"><i class="fas fa-star me-2"></i>Most Active Residents</h5>
                                </div>
                                <div class="card-body">
                                    <?php if ($active_residents->num_rows > 0): ?>
                                        <?php $rank = 1; while ($resident = $active_residents->fetch_assoc()): ?>
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div>
                                                    <span class="badge bg-<?php echo $rank <= 3 ? ($rank === 1 ? 'warning' : ($rank === 2 ? 'secondary' : 'danger')) : 'light text-dark'; ?> me-2">
                                                        #<?php echo $rank; ?>
                                                    </span>
                                                    <?php echo e($resident['first_name'] . ' ' . $resident['last_name']); ?>
                                                </div>
                                                <div class="text-end">
                                                    <span class="badge bg-primary"><?php echo $resident['report_count']; ?> reports</span>
                                                    <br>
                                                    <small class="text-success"><?php echo $resident['eco_points']; ?> points</small>
                                                </div>
                                            </div>
                                        <?php $rank++; endwhile; ?>
                                    <?php else: ?>
                                        <p class="text-muted text-center">No resident activity data available.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Area-wise Reports -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>Top Areas by Reports</h5>
                                </div>
                                <div class="card-body">
                                    <?php if ($area_stats->num_rows > 0): ?>
                                        <?php while ($area = $area_stats->fetch_assoc()): ?>
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span><?php echo e($area['area']); ?></span>
                                                <span class="badge bg-primary"><?php echo $area['count']; ?> reports</span>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <p class="text-muted text-center">No area data available.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0"><i class="fas fa-comment me-2"></i>Feedback Analytics</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row text-center mb-3">
                                        <div class="col-6">
                                            <h4 class="text-primary"><?php echo $feedback_stats['total_feedback']; ?></h4>
                                            <small class="text-muted">Total Feedback</small>
                                        </div>
                                        <div class="col-6">
                                            <h4 class="text-warning"><?php echo round($feedback_stats['avg_rating'], 1); ?></h4>
                                            <small class="text-muted">Avg Rating</small>
                                        </div>
                                    </div>
                                    <hr>
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>Suggestions:</span>
                                        <span class="text-info"><?php echo $feedback_stats['suggestions']; ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>Complaints:</span>
                                        <span class="text-danger"><?php echo $feedback_stats['complaints']; ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>Appreciations:</span>
                                        <span class="text-success"><?php echo $feedback_stats['appreciations']; ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>Bug Reports:</span>
                                        <span class="text-warning"><?php echo $feedback_stats['bug_reports']; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Daily Trend Chart -->
                    <div class="card">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Daily Reports Trend</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="dailyTrendChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Reports Status Chart
        const reportsStatusCtx = document.getElementById('reportsStatusChart').getContext('2d');
        new Chart(reportsStatusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'In Progress', 'Assigned', 'Pending', 'Cancelled'],
                datasets: [{
                    data: [
                        <?php echo $reports_stats['completed_reports']; ?>,
                        <?php echo $reports_stats['in_progress_reports']; ?>,
                        <?php echo $reports_stats['assigned_reports']; ?>,
                        <?php echo $reports_stats['pending_reports']; ?>,
                        <?php echo $reports_stats['cancelled_reports']; ?>
                    ],
                    backgroundColor: ['#28a745', '#007bff', '#17a2b8', '#ffc107', '#dc3545']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Reports Priority Chart
        const reportsPriorityCtx = document.getElementById('reportsPriorityChart').getContext('2d');
        new Chart(reportsPriorityCtx, {
            type: 'bar',
            data: {
                labels: ['Urgent', 'High', 'Medium', 'Low'],
                datasets: [{
                    label: 'Reports by Priority',
                    data: [
                        <?php 
                        $priority_data = ['urgent' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
                        $priority_stats->data_seek(0);
                        while ($priority = $priority_stats->fetch_assoc()) {
                            $priority_data[$priority['priority']] = $priority['count'];
                        }
                        echo implode(',', $priority_data);
                        ?>
                    ],
                    backgroundColor: ['#6f42c1', '#dc3545', '#ffc107', '#28a745']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Daily Trend Chart
        const dailyTrendCtx = document.getElementById('dailyTrendChart').getContext('2d');
        new Chart(dailyTrendCtx, {
            type: 'line',
            data: {
                labels: [
                    <?php 
                    $daily_trend->data_seek(0);
                    $dates = [];
                    $counts = [];
                    while ($trend = $daily_trend->fetch_assoc()) {
                        $dates[] = "'" . format_ph_date($trend['date'], 'M j') . "'";
                        $counts[] = $trend['count'];
                    }
                    echo implode(',', $dates);
                    ?>
                ],
                datasets: [{
                    label: 'Reports per Day',
                    data: [<?php echo implode(',', $counts); ?>],
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>

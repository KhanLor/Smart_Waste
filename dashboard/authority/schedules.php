<?php
require_once '../../config/config.php';
require_login();
require_once __DIR__ . '/../../vendor/autoload.php';

// Check if user is an authority
if (($_SESSION['role'] ?? '') !== 'authority') {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
// Flash messages from previous POST (Post/Redirect/Get)
$success_message = '';
$error_message = '';
if (!empty($_SESSION['flash_success'])) {
    $success_message = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (!empty($_SESSION['flash_error'])) {
    $error_message = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_schedule') {
        $area = trim($_POST['area'] ?? '');
        $street_name = trim($_POST['street_name'] ?? '');
        $collection_day = $_POST['collection_day'] ?? '';
        $collection_time = $_POST['collection_time'] ?? '';
        $waste_type = $_POST['waste_type'] ?? '';
        $assigned_collector = $_POST['assigned_collector'] ?? null;
        $status = $_POST['status'] ?? 'active';

        // Validation
        if (empty($area) || empty($street_name) || empty($collection_day) || empty($collection_time) || empty($waste_type)) {
            $error_message = 'Please fill in all required fields.';
        } else {
            try {
                $stmt = $conn->prepare("
                    INSERT INTO collection_schedules (area, street_name, collection_day, collection_time, waste_type, assigned_collector, status, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("sssssssi", $area, $street_name, $collection_day, $collection_time, $waste_type, $assigned_collector, $status, $user_id);
                
                if ($stmt->execute()) {
                    $new_schedule_id = $conn->insert_id;
                    // Fan-out in-app notifications to residents whose address matches street or area
                    try {
                        $notif_title = 'New Collection Scheduled';
                        $notif_message = sprintf('%s on %s at %s', $street_name, ucfirst(strtolower($collection_day)), $collection_time);
                        $stmtU = $conn->prepare("SELECT id FROM users WHERE role = 'resident' AND (address LIKE ? OR address LIKE ?)");
                        $likeStreet = '%' . $street_name . '%';
                        $likeArea = '%' . $area . '%';
                        $stmtU->bind_param('ss', $likeStreet, $likeArea);
                        $stmtU->execute();
                        $resU = $stmtU->get_result();
                        if ($resU && $resU->num_rows > 0) {
                            $stmtN = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, reference_type, reference_id, created_at) VALUES (?, ?, ?, 'info', 'schedule', ?, CURRENT_TIMESTAMP)");
                            while ($u = $resU->fetch_assoc()) {
                                $uid = (int)$u['id'];
                                $stmtN->bind_param('issi', $uid, $notif_title, $notif_message, $new_schedule_id);
                                $stmtN->execute();
                            }
                            $stmtN->close();
                        }
                        $stmtU->close();
                    } catch (Throwable $e) { /* ignore */ }
                    // Realtime: notify residents about new schedule
                    try {
                        $pusher = new Pusher\Pusher(PUSHER_KEY, PUSHER_SECRET, PUSHER_APP_ID, ['cluster' => PUSHER_CLUSTER, 'useTLS' => PUSHER_USE_TLS]);
                        $pusher->trigger('schedule-global', 'schedule-changed', [
                            'action' => 'added',
                            'area' => $area,
                            'street_name' => $street_name,
                            'collection_day' => $collection_day,
                            'collection_time' => $collection_time,
                            'waste_type' => $waste_type
                        ]);
                        
                        // Send push notifications to residents in the area
                        require_once __DIR__ . '/../../lib/push_notifications.php';
                        $pushNotifier = new PushNotifications($conn);
                        $results = $pushNotifier->notifyArea(
                            $area,
                            'New Collection Schedule',
                            "New {$waste_type} collection scheduled for {$street_name} every {$collection_day} at " . date('g:i A', strtotime("1970-01-01 $collection_time")),
                            [
                                'type' => 'schedule_added',
                                'area' => $area,
                                'street_name' => $street_name,
                                'collection_day' => $collection_day,
                                'collection_time' => $collection_time,
                                'waste_type' => $waste_type
                            ]
                        );
                        
                        // Log notification results for debugging
                        error_log("Push notification results: " . print_r($results, true));
                    } catch (Throwable $e) {}
                    // Use Post/Redirect/Get to avoid browser resubmit on reload
                    $_SESSION['flash_success'] = 'Collection schedule added successfully.';
                    header('Location: ' . $_SERVER['REQUEST_URI']);
                    exit;
                } else {
                    throw new Exception('Failed to add collection schedule.');
                }
            } catch (Exception $e) {
                $error_message = 'Error adding schedule: ' . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'update_schedule') {
        $schedule_id = $_POST['schedule_id'] ?? null;
        $area = trim($_POST['area'] ?? '');
        $street_name = trim($_POST['street_name'] ?? '');
        $collection_day = $_POST['collection_day'] ?? '';
        $collection_time = $_POST['collection_time'] ?? '';
        $waste_type = $_POST['waste_type'] ?? '';
        $assigned_collector = $_POST['assigned_collector'] ?? null;
        $status = $_POST['status'] ?? 'active';

        if ($schedule_id && !empty($area) && !empty($street_name) && !empty($collection_day) && !empty($collection_time) && !empty($waste_type)) {
            try {
                $stmt = $conn->prepare("
                    UPDATE collection_schedules 
                    SET area = ?, street_name = ?, collection_day = ?, collection_time = ?, waste_type = ?, assigned_collector = ?, status = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmt->bind_param("sssssssi", $area, $street_name, $collection_day, $collection_time, $waste_type, $assigned_collector, $status, $schedule_id);
                
                if ($stmt->execute()) {
                    // Use Post/Redirect/Get to avoid browser resubmit on reload
                    $_SESSION['flash_success'] = 'Collection schedule updated successfully.';
                    header('Location: ' . $_SERVER['REQUEST_URI']);
                    exit;
                    // Fan-out in-app notifications to residents whose address matches street or area
                    try {
                        $notif_title = 'Collection Schedule Updated';
                        $notif_message = sprintf('%s on %s at %s', $street_name, ucfirst(strtolower($collection_day)), $collection_time);
                        $stmtU = $conn->prepare("SELECT id FROM users WHERE role = 'resident' AND (address LIKE ? OR address LIKE ?)");
                        $likeStreet = '%' . $street_name . '%';
                        $likeArea = '%' . $area . '%';
                        $stmtU->bind_param('ss', $likeStreet, $likeArea);
                        $stmtU->execute();
                        $resU = $stmtU->get_result();
                        if ($resU && $resU->num_rows > 0) {
                            $stmtN = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, reference_type, reference_id, created_at) VALUES (?, ?, ?, 'info', 'schedule', ?, CURRENT_TIMESTAMP)");
                            while ($u = $resU->fetch_assoc()) {
                                $uid = (int)$u['id'];
                                $stmtN->bind_param('issi', $uid, $notif_title, $notif_message, $schedule_id);
                                $stmtN->execute();
                            }
                            $stmtN->close();
                        }
                        $stmtU->close();
                    } catch (Throwable $e) { /* ignore */ }
                    // Realtime: notify residents about updated schedule
                    try {
                        $pusher = new Pusher\Pusher(PUSHER_KEY, PUSHER_SECRET, PUSHER_APP_ID, ['cluster' => PUSHER_CLUSTER, 'useTLS' => PUSHER_USE_TLS]);
                        $pusher->trigger('schedule-global', 'schedule-changed', [
                            'action' => 'updated',
                            'schedule_id' => (int)$schedule_id,
                            'area' => $area,
                            'street_name' => $street_name,
                            'collection_day' => $collection_day,
                            'collection_time' => $collection_time,
                            'waste_type' => $waste_type,
                            'status' => $status
                        ]);
                    } catch (Throwable $e) {}
                } else {
                    throw new Exception('Failed to update collection schedule.');
                }
            } catch (Exception $e) {
                $error_message = 'Error updating schedule: ' . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'delete_schedule') {
        $schedule_id = $_POST['schedule_id'] ?? null;
        
        if ($schedule_id) {
            try {
                $stmt = $conn->prepare("DELETE FROM collection_schedules WHERE id = ?");
                $stmt->bind_param("i", $schedule_id);
                
                if ($stmt->execute()) {
                    // Use Post/Redirect/Get to avoid browser resubmit on reload
                    $_SESSION['flash_success'] = 'Collection schedule deleted successfully.';
                    header('Location: ' . $_SERVER['REQUEST_URI']);
                    exit;
                    // Realtime: notify residents about deleted schedule
                    try {
                        $pusher = new Pusher\Pusher(PUSHER_KEY, PUSHER_SECRET, PUSHER_APP_ID, ['cluster' => PUSHER_CLUSTER, 'useTLS' => PUSHER_USE_TLS]);
                        $pusher->trigger('schedule-global', 'schedule-changed', [
                            'action' => 'deleted',
                            'schedule_id' => (int)$schedule_id
                        ]);
                        
                        // Send push notifications to all residents about schedule deletion
                        require_once __DIR__ . '/../../lib/push_notifications.php';
                        $pushNotifier = new PushNotifications($conn);
                        $pushNotifier->notifyAllResidents(
                            'Schedule Cancelled',
                            'A collection schedule has been cancelled. Please check for updates.',
                            [
                                'type' => 'schedule_deleted',
                                'schedule_id' => (int)$schedule_id
                            ]
                        );
                    } catch (Throwable $e) {}
                } else {
                    throw new Exception('Failed to delete collection schedule.');
                }
            } catch (Exception $e) {
                $error_message = 'Error deleting schedule: ' . $e->getMessage();
            }
        }
    }
}

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get schedules with pagination and filters
$page = $_GET['page'] ?? 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$day_filter = $_GET['day'] ?? '';
$status_filter = $_GET['status'] ?? '';
$type_filter = $_GET['type'] ?? '';

// Build query
$where_conditions = ["1=1"];
$params = [];
$param_types = "";

if (!empty($search)) {
    $where_conditions[] = "(cs.area LIKE ? OR cs.street_name LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "ssss";
}

if (!empty($day_filter)) {
    $where_conditions[] = "cs.collection_day = ?";
    $params[] = $day_filter;
    $param_types .= "s";
}

if (!empty($status_filter)) {
    $where_conditions[] = "cs.status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

if (!empty($type_filter)) {
    $where_conditions[] = "cs.waste_type = ?";
    $params[] = $type_filter;
    $param_types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

// Get total count
$count_sql = "
    SELECT COUNT(*) as total 
    FROM collection_schedules cs 
    LEFT JOIN users u ON cs.assigned_collector = u.id 
    WHERE {$where_clause}
";
$stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$total_schedules = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_schedules / $limit);

// Get schedules
$sql = "
    SELECT cs.*, u.first_name, u.last_name 
    FROM collection_schedules cs 
    LEFT JOIN users u ON cs.assigned_collector = u.id
    WHERE {$where_clause}
    ORDER BY FIELD(cs.collection_day, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'), cs.collection_time
    LIMIT ? OFFSET ?
";
$params[] = $limit;
$params[] = $offset;
$param_types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$schedules = $stmt->get_result();

// Materialize schedules into array for computing per-schedule unread notifications
$schedule_rows = $schedules->fetch_all(MYSQLI_ASSOC);
$schedule_ids = array_map(fn($r) => (int)$r['id'], $schedule_rows);

// Unread collection notifications for header badge
$unread_collection_count = 0;
if ($stmtCnt = $conn->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND reference_type = 'collection' AND is_read = 0")) {
    $stmtCnt->bind_param('i', $user_id);
    $stmtCnt->execute();
    $rowCnt = $stmtCnt->get_result()->fetch_assoc();
    $unread_collection_count = (int)($rowCnt['cnt'] ?? 0);
    $stmtCnt->close();
}

// Per-schedule unread counts by joining notifications -> collection_history
$per_schedule_unread = [];
if (!empty($schedule_ids)) {
    $placeholders = implode(',', array_fill(0, count($schedule_ids), '?'));
    $types = 'i' . str_repeat('i', count($schedule_ids));
    $sqlUn = "SELECT ch.schedule_id, COUNT(n.id) AS unread_count
              FROM notifications n
              JOIN collection_history ch ON ch.id = n.reference_id
              WHERE n.user_id = ? AND n.reference_type = 'collection' AND n.is_read = 0
                AND ch.schedule_id IN ($placeholders)
              GROUP BY ch.schedule_id";
    $stmtUn = $conn->prepare($sqlUn);
    $bindParams = array_merge([$user_id], $schedule_ids);
    $stmtUn->bind_param($types, ...$bindParams);
    $stmtUn->execute();
    $resUn = $stmtUn->get_result();
    while ($r = $resUn->fetch_assoc()) {
        $per_schedule_unread[(int)$r['schedule_id']] = (int)$r['unread_count'];
    }
    $stmtUn->close();
}

// Get collectors for assignment
$stmt = $conn->prepare("SELECT id, first_name, last_name FROM users WHERE role = 'collector' ORDER BY first_name, last_name");
$stmt->execute();
$collectors = $stmt->get_result();

// (removed duplicate older aggregation) - using the consolidated stats query below
    // Get collection history for statistics (last 30 days)
    $stmt = $conn->prepare(
        "
            SELECT 
                COUNT(*) as total_collections,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_collections,
                SUM(CASE WHEN status = 'missed' THEN 1 ELSE 0 END) as missed_collections,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_collections
            FROM collection_history 
            WHERE DATE(collection_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        "
    );
    $stmt->execute();
    $collection_stats = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Collection Schedules - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css?v=20251024">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #8B7E74 0%, #6B635A 100%);
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            /* allow dropdowns/popovers to overflow card boundaries */
            overflow: visible;
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
        .schedule-card {
            border-left: 4px solid #17a2b8;
            transition: transform 0.2s;
            overflow: visible;
        }
        .schedule-card:hover {
            transform: translateY(-2px);
        }
        .schedule-card.active {
            border-left-color: #28a745;
        }
        .schedule-card.inactive {
            border-left-color: #6c757d;
        }
        .filter-card {
            background: #f8f9fa;
            border-radius: 10px;
        }
        .stat-card {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
        }
        .stat-card.success {
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
        }
        .stat-card.danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }
        .stat-card.cancelled {
            background: linear-gradient(135deg, #6f42c1 0%, #5a2ea6 100%);
        }
        /* Notifications UI clarity */
        .header-update-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 22px;
            height: 22px;
            font-size: 12px;
            margin-left: 8px;
            vertical-align: middle;
        }
        .schedule-card .card-body { position: relative; }
        .notif-badge {
            position: absolute;
            top: 10px;
            right: 50px; /* leave room for the dropdown */
            z-index: 2;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 20px;
            height: 20px;
            font-size: 11px;
            pointer-events: none; /* decorative */
        }
        /* Dropdown and action clarity */
        .dropdown .dropdown-toggle {
            background: #f4f6f8;
            border: 1px solid rgba(0,0,0,0.06);
            padding: 0.35rem 0.5rem;
            border-radius: 8px;
            color: #333;
        }
        .dropdown .dropdown-toggle:focus { box-shadow:none; }
        .dropdown-menu {
            z-index: 3000; /* ensure menu sits above cards */
            min-width: 10rem;
            box-shadow: 0 6px 18px rgba(0,0,0,0.12);
        }
        .card-title { font-weight:600; }
        @media (max-width: 576px) {
            .notif-badge { right: 44px; top: 8px; min-width: 18px; height: 18px; font-size: 10px; }
            .dropdown .btn { padding: 0.25rem 0.4rem; }
            .card .card-title { font-size: 0.95rem; }
        }
    </style>
</head>
<body class="role-authority">
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
                            <h2 class="mb-1 d-flex align-items-center gap-2">Collection Schedules
                                <?php if ($unread_collection_count > 0): ?>
                                    <span class="badge rounded-pill bg-danger header-update-badge" id="headerCollectionBadge" data-bs-toggle="tooltip" data-bs-placement="right" title="New collection updates">
                                        <?php echo $unread_collection_count > 99 ? '99+' : $unread_collection_count; ?>
                                    </span>
                                <?php endif; ?>
                            </h2>
                            <p class="text-muted mb-0">Manage waste collection schedules and assignments</p>
                        </div>
                        <div>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                                <i class="fas fa-plus me-2"></i>Add Schedule
                            </button>
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
                    <div class="row mb-4 gx-3 gy-3">
                        <div class="col-12 col-sm-6 col-md-3">
                            <div class="card stat-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-calendar fa-2x mb-2"></i>
                                    <h4 class="mb-1"><?php echo $total_schedules; ?></h4>
                                    <small>Total Schedules</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-md-3">
                            <div class="card stat-card success">
                                <div class="card-body text-center">
                                    <i class="fas fa-check-circle fa-2x mb-2"></i>
                                    <h4 class="mb-1"><?php echo (int)($collection_stats['completed_collections'] ?? 0); ?></h4>
                                    <small>Completed (30 days)</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-md-3">
                            <div class="card stat-card danger">
                                <div class="card-body text-center">
                                    <i class="fas fa-times-circle fa-2x mb-2"></i>
                                    <h4 class="mb-1"><?php echo (int)($collection_stats['missed_collections'] ?? 0); ?></h4>
                                    <small>Missed (30 days)</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-md-3">
                            <div class="card stat-card cancelled">
                                <div class="card-body text-center">
                                    <i class="fas fa-ban fa-2x mb-2"></i>
                                    <h4 class="mb-1"><?php echo (int)($collection_stats['cancelled_collections'] ?? 0); ?></h4>
                                    <small>Cancelled (30 days)</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="card filter-card mb-4">
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-3">
                                    <input type="text" class="form-control" name="search" placeholder="Search schedules..." value="<?php echo e($search); ?>">
                                </div>
                                <div class="col-md-2">
                                    <select class="form-select" name="day">
                                        <option value="">All Days</option>
                                        <option value="monday" <?php echo $day_filter === 'monday' ? 'selected' : ''; ?>>Monday</option>
                                        <option value="tuesday" <?php echo $day_filter === 'tuesday' ? 'selected' : ''; ?>>Tuesday</option>
                                        <option value="wednesday" <?php echo $day_filter === 'wednesday' ? 'selected' : ''; ?>>Wednesday</option>
                                        <option value="thursday" <?php echo $day_filter === 'thursday' ? 'selected' : ''; ?>>Thursday</option>
                                        <option value="friday" <?php echo $day_filter === 'friday' ? 'selected' : ''; ?>>Friday</option>
                                        <option value="saturday" <?php echo $day_filter === 'saturday' ? 'selected' : ''; ?>>Saturday</option>
                                        <option value="sunday" <?php echo $day_filter === 'sunday' ? 'selected' : ''; ?>>Sunday</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select class="form-select" name="status">
                                        <option value="">All Status</option>
                                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select class="form-select" name="type">
                                        <option value="">All Types</option>
                                        <option value="general" <?php echo $type_filter === 'general' ? 'selected' : ''; ?>>General</option>
                                        <option value="recyclable" <?php echo $type_filter === 'recyclable' ? 'selected' : ''; ?>>Recyclable</option>
                                        <option value="organic" <?php echo $type_filter === 'organic' ? 'selected' : ''; ?>>Organic</option>
                                        <option value="hazardous" <?php echo $type_filter === 'hazardous' ? 'selected' : ''; ?>>Hazardous</option>
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

                    <!-- Schedules List -->
                    <div class="row">
                        <?php if (count($schedule_rows) > 0): ?>
                            <?php foreach ($schedule_rows as $schedule): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card schedule-card <?php echo $schedule['status']; ?>">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="card-title mb-0"><?php echo e($schedule['street_name']); ?></h6>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <li><a class="dropdown-item" href="#" onclick="viewScheduleActivity(<?php echo (int)$schedule['id']; ?>)">
                                                            <i class="fas fa-bell me-2"></i>View Activity
                                                        </a></li>
                                                        <li><a class="dropdown-item" href="#" onclick="editSchedule(<?php echo $schedule['id']; ?>)">
                                                            <i class="fas fa-edit me-2"></i>Edit
                                                        </a></li>
                                                        <li><a class="dropdown-item text-danger" href="#" onclick="deleteSchedule(<?php echo $schedule['id']; ?>)">
                                                            <i class="fas fa-trash me-2"></i>Delete
                                                        </a></li>
                                                    </ul>
                                                </div>
                                            </div>

                                            <?php $uCount = (int)($per_schedule_unread[(int)$schedule['id']] ?? 0); ?>
                                            <?php if ($uCount > 0): ?>
                                                <span class="badge rounded-pill bg-danger notif-badge" id="schedBadge-<?php echo (int)$schedule['id']; ?>" data-bs-toggle="tooltip" title="New updates for this schedule">
                                                    <?php echo $uCount > 99 ? '99+' : $uCount; ?>
                                                </span>
                                            <?php endif; ?>
                                            
                                            <div class="mb-2">
                                                <small class="text-muted">
                                                    <i class="fas fa-map-marker-alt me-1"></i><?php echo e($schedule['area']); ?>
                                                </small>
                                            </div>
                                            
                                            <div class="mb-2">
                                                <span class="badge bg-primary">
                                                    <i class="fas fa-calendar-day me-1"></i><?php echo ucfirst($schedule['collection_day']); ?>
                                                </span>
                                                <span class="badge bg-info">
                                                    <i class="fas fa-clock me-1"></i><?php echo format_ph_date($schedule['collection_time'], 'g:i A'); ?>
                                                </span>
                                            </div>
                                            
                                            <div class="mb-2">
                                                <span class="badge bg-<?php echo $schedule['waste_type'] === 'general' ? 'secondary' : ($schedule['waste_type'] === 'recyclable' ? 'success' : ($schedule['waste_type'] === 'organic' ? 'warning' : 'danger')); ?>">
                                                    <?php echo ucfirst($schedule['waste_type']); ?>
                                                </span>
                                                <span class="badge bg-<?php echo $schedule['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                    <?php echo ucfirst($schedule['status']); ?>
                                                </span>
                                            </div>

                                            <?php if ($schedule['first_name']): ?>
                                                <div class="mb-2">
                                                    <small class="text-muted">
                                                        <i class="fas fa-user-tie me-1"></i>Collector: <?php echo e($schedule['first_name'] . ' ' . $schedule['last_name']); ?>
                                                    </small>
                                                </div>
                                            <?php else: ?>
                                                <div class="mb-2">
                                                    <small class="text-warning">
                                                        <i class="fas fa-exclamation-triangle me-1"></i>No collector assigned
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    <i class="fas fa-clock me-1"></i><?php echo format_ph_date($schedule['created_at'], 'M j, Y'); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-body text-center py-5">
                                        <i class="fas fa-calendar-times fa-4x text-muted mb-4"></i>
                                        <h4>No Schedules Found</h4>
                                        <p class="text-muted">No collection schedules match your current filters.</p>
                                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                                            <i class="fas fa-plus me-2"></i>Add First Schedule
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Schedules pagination" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&day=<?php echo urlencode($day_filter); ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>">Previous</a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&day=<?php echo urlencode($day_filter); ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&day=<?php echo urlencode($day_filter); ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>">Next</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Schedule Modal -->
    <div class="modal fade" id="addScheduleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Collection Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="addScheduleForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_schedule">
                        
                        <div class="mb-3">
                            <label for="area" class="form-label">Area <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="area" name="area" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="street_name" class="form-label">Street Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="street_name" name="street_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="collection_day" class="form-label">Collection Day <span class="text-danger">*</span></label>
                            <select class="form-select" id="collection_day" name="collection_day" required>
                                <option value="">Select Day</option>
                                <option value="monday">Monday</option>
                                <option value="tuesday">Tuesday</option>
                                <option value="wednesday">Wednesday</option>
                                <option value="thursday">Thursday</option>
                                <option value="friday">Friday</option>
                                <option value="saturday">Saturday</option>
                                <option value="sunday">Sunday</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="collection_time" class="form-label">Collection Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="collection_time" name="collection_time" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="waste_type" class="form-label">Waste Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="waste_type" name="waste_type" required>
                                <option value="">Select Type</option>
                                <option value="general">General</option>
                                <option value="recyclable">Recyclable</option>
                                <option value="organic">Organic</option>
                                <option value="hazardous">Hazardous</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="assigned_collector" class="form-label">Assign Collector</label>
                            <select class="form-select" id="assigned_collector" name="assigned_collector">
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
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Schedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Schedule Activity Modal -->
    <div class="modal fade" id="activityModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Schedule Activity</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="activityContent">
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-spinner fa-spin me-2"></i>Loading activity...
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Schedule Modal -->
    <div class="modal fade" id="editScheduleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Collection Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editScheduleForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_schedule">
                        <input type="hidden" name="schedule_id" id="editScheduleId">
                        
                        <div class="mb-3">
                            <label for="edit_area" class="form-label">Area <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_area" name="area" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_street_name" class="form-label">Street Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_street_name" name="street_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_collection_day" class="form-label">Collection Day <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_collection_day" name="collection_day" required>
                                <option value="">Select Day</option>
                                <option value="monday">Monday</option>
                                <option value="tuesday">Tuesday</option>
                                <option value="wednesday">Wednesday</option>
                                <option value="thursday">Thursday</option>
                                <option value="friday">Friday</option>
                                <option value="saturday">Saturday</option>
                                <option value="sunday">Sunday</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_collection_time" class="form-label">Collection Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="edit_collection_time" name="collection_time" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_waste_type" class="form-label">Waste Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_waste_type" name="waste_type" required>
                                <option value="">Select Type</option>
                                <option value="general">General</option>
                                <option value="recyclable">Recyclable</option>
                                <option value="organic">Organic</option>
                                <option value="hazardous">Hazardous</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_assigned_collector" class="form-label">Assign Collector</label>
                            <select class="form-select" id="edit_assigned_collector" name="assigned_collector">
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
                        
                        <div class="mb-3">
                            <label for="edit_status" class="form-label">Status</label>
                            <select class="form-select" id="edit_status" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Schedule</button>
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
                    <p>Are you sure you want to delete this collection schedule? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" id="deleteForm" style="display: inline;">
                        <input type="hidden" name="action" value="delete_schedule">
                        <input type="hidden" name="schedule_id" id="deleteScheduleId">
                        <button type="submit" class="btn btn-danger">Delete Schedule</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Enable Bootstrap tooltips for badges
        (function(){
            try {
                var triggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                triggerList.forEach(function(el){ new bootstrap.Tooltip(el); });
            } catch (e) {}
        })();

        function editSchedule(scheduleId) {
            // In a real application, you would fetch schedule details via AJAX
            // For now, we'll show the modal with empty fields
            document.getElementById('editScheduleId').value = scheduleId;
            new bootstrap.Modal(document.getElementById('editScheduleModal')).show();
        }

        function deleteSchedule(scheduleId) {
            document.getElementById('deleteScheduleId').value = scheduleId;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        async function viewScheduleActivity(scheduleId) {
            try {
                // Load activity list
                const modalEl = document.getElementById('activityModal');
                const contentEl = document.getElementById('activityContent');
                contentEl.innerHTML = '<div class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin me-2"></i>Loading activity...</div>';
                const modal = new bootstrap.Modal(modalEl);
                modal.show();

                const resp = await fetch('../../api/get_schedule_history.php?schedule_id=' + encodeURIComponent(scheduleId));
                const data = await resp.json();
                if (!data.success) {
                    contentEl.innerHTML = '<div class="alert alert-danger">Failed to load activity.</div>';
                } else {
                    if (data.count === 0) {
                        contentEl.innerHTML = '<div class="text-center text-muted py-4">No activity yet for this schedule.</div>';
                    } else {
                        const rows = data.items.map(it => {
                            const badge = it.status === 'completed' ? 'success' : (it.status === 'missed' ? 'danger' : 'secondary');
                            const ev = it.evidence_image ? `<div class="mt-2"><small class="text-muted">Evidence:</small><br><img src="../../${it.evidence_image}" alt="evidence" style="max-width:100%;height:auto;border-radius:6px;border:1px solid #eee;"/></div>` : '';
                            const notes = it.notes ? `<div class="mt-2"><small class="text-muted">Notes:</small><div>${it.notes.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</div></div>` : '';
                            const who = it.collector_name ? `<small class="text-muted ms-2"><i class="fas fa-user-tie me-1"></i>${it.collector_name}</small>` : '';
                            return `
                                <div class="card mb-2">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div>
                                                <span class="badge bg-${badge}">${it.status}</span>
                                                <small class="text-muted ms-2"><i class="fas fa-calendar-day me-1"></i>${it.date}</small>
                                                ${who}
                                            </div>
                                        </div>
                                        ${notes}
                                        ${ev}
                                    </div>
                                </div>`;
                        }).join('');
                        contentEl.innerHTML = rows;
                    }
                }

                // Mark notifications for this schedule as read and update badges
                try {
                    const markResp = await fetch('../../api/mark_schedule_notifications_read.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ schedule_id: scheduleId })
                    });
                    const markData = await markResp.json();
                    if (markData && markData.success) {
                        const marked = Number(markData.marked || 0);
                        if (marked > 0) {
                            // Remove per-card badge
                            const b = document.getElementById('schedBadge-' + scheduleId);
                            if (b) b.remove();
                            // Decrement header badge
                            const headerB = document.getElementById('headerCollectionBadge');
                            if (headerB) {
                                let current = headerB.innerText === '99+' ? 99 : parseInt(headerB.innerText || '0');
                                let next = Math.max(0, current - marked);
                                if (next <= 0) headerB.remove();
                                else headerB.innerText = next > 99 ? '99+' : String(next);
                            }
                        }
                    }
                } catch (e) { /* ignore UI update failure */ }
            } catch (err) {
                alert('Failed to load activity.');
            }
        }

        // notifyCollector removed — notifications to collectors are disabled from the authority UI.
    </script>
</body>
</html>

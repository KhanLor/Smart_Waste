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

// Get user's collection schedule based on their address
$stmt = $conn->prepare("
    SELECT cs.*, u.first_name, u.last_name 
    FROM collection_schedules cs 
    LEFT JOIN users u ON cs.assigned_collector = u.id
    WHERE 
        cs.street_name LIKE ? 
        OR cs.area LIKE ?
        OR ? LIKE CONCAT('%', cs.street_name, '%')
        OR ? LIKE CONCAT('%', cs.area, '%')
    ORDER BY FIELD(cs.collection_day, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday')
");
$address_search = '%' . $user['address'] . '%';
$user_address = $user['address'];
$stmt->bind_param("ssss", $address_search, $address_search, $user_address, $user_address);
$stmt->execute();
$schedules = $stmt->get_result();

// Get collection history for the user's area
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
    LIMIT 10
");
$stmt->bind_param("ssss", $address_search, $address_search, $user_address, $user_address);
$stmt->execute();
$collection_history = $stmt->get_result();

// Get next collection
$next_collection = null;
if ($schedules->num_rows > 0) {
    $schedules->data_seek(0);
    $today = strtolower(date('l'));
    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    $today_index = array_search($today, $days);
    
    // Find next collection day
    for ($i = 0; $i < 7; $i++) {
        $check_day = $days[($today_index + $i) % 7];
        $schedules->data_seek(0);
        while ($schedule = $schedules->fetch_assoc()) {
            if ($schedule['collection_day'] === $check_day) {
                $next_collection = $schedule;
                $next_collection['days_until'] = $i;
                break 2;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Collection Schedule - <?php echo APP_NAME; ?></title>
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
        .schedule-card {
            border-left: 4px solid #28a745;
            transition: transform 0.2s;
        }
        .schedule-card:hover {
            transform: translateY(-2px);
        }
        .schedule-card.today {
            border-left-color: #ffc107;
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
        }
        .schedule-card.tomorrow {
            border-left-color: #17a2b8;
            background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
        }
        .waste-type-badge {
            font-size: 0.8rem;
        }
        .calendar-day {
            text-align: center;
            padding: 10px;
            border-radius: 8px;
            margin: 2px;
            transition: all 0.3s;
        }
        .calendar-day.has-collection {
            background: #28a745;
            color: white;
        }
        .calendar-day.today {
            background: #ffc107;
            color: #000;
            font-weight: bold;
        }
        .calendar-day:hover {
            transform: scale(1.05);
        }
        .next-collection-card {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
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
                        <a class="nav-link text-white active" href="schedule.php">
                            <i class="fas fa-calendar me-2"></i>Collection Schedule
                        </a>
                        <a class="nav-link text-white" href="points.php">
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
                            <h2 class="mb-1">Collection Schedule</h2>
                            <p class="text-muted mb-0">Your waste collection schedule and history</p>
                        </div>
                        <div class="text-end">
                            <div class="h4 text-success mb-0"><?php echo $user['eco_points']; ?></div>
                            <small class="text-muted">Eco Points</small>
                        </div>
                    </div>

                    <!-- Next Collection Card -->
                    <?php if ($next_collection): ?>
                        <div class="card next-collection-card mb-4">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h4 class="mb-2">
                                            <i class="fas fa-truck me-2"></i>
                                            Next Collection
                                        </h4>
                                        <h5 class="mb-1"><?php echo ucfirst($next_collection['collection_day']); ?></h5>
                                        <p class="mb-1">
                                            <i class="fas fa-clock me-2"></i>
                                            <?php echo format_ph_date($next_collection['collection_time'], 'g:i A'); ?>
                                        </p>
                                        <p class="mb-0">
                                            <i class="fas fa-map-marker-alt me-2"></i>
                                            <?php echo e($next_collection['street_name']); ?>
                                        </p>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <div class="h2 mb-0">
                                            <?php if ($next_collection['days_until'] == 0): ?>
                                                <span class="badge bg-warning text-dark">Today</span>
                                            <?php elseif ($next_collection['days_until'] == 1): ?>
                                                <span class="badge bg-info">Tomorrow</span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-dark"><?php echo $next_collection['days_until']; ?> days</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mt-2">
                                            <span class="badge bg-light text-dark waste-type-badge">
                                                <?php echo ucfirst($next_collection['waste_type']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <!-- Weekly Schedule -->
                        <div class="col-md-8 mb-4">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0"><i class="fas fa-calendar-week me-2"></i>Weekly Schedule</h5>
                                </div>
                                <div class="card-body">
                                    <?php if ($schedules->num_rows > 0): ?>
                                        <?php 
                                        $schedules->data_seek(0);
                                        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                                        $today = strtolower(date('l'));
                                        ?>
                                        <div class="row">
                                            <?php foreach ($days as $day): ?>
                                                <?php 
                                                $schedules->data_seek(0);
                                                $daySchedule = null;
                                                while ($schedule = $schedules->fetch_assoc()) {
                                                    if ($schedule['collection_day'] === $day) {
                                                        $daySchedule = $schedule;
                                                        break;
                                                    }
                                                }
                                                $isToday = $day === $today;
                                                ?>
                                                <div class="col-md-6 col-lg-4 mb-3">
                                                    <div class="schedule-card card <?php echo $isToday ? 'today' : ''; ?>">
                                                        <div class="card-body">
                                                            <h6 class="card-title d-flex justify-content-between align-items-center">
                                                                <?php echo ucfirst($day); ?>
                                                                <?php if ($isToday): ?>
                                                                    <span class="badge bg-warning text-dark">Today</span>
                                                                <?php endif; ?>
                                                            </h6>
                                                            <?php if ($daySchedule): ?>
                                                                <p class="mb-1">
                                                                    <i class="fas fa-clock me-1"></i>
                                                                    <?php echo format_ph_date($daySchedule['collection_time'], 'g:i A'); ?>
                                                                </p>
                                                                <p class="mb-1">
                                                                    <i class="fas fa-map-marker-alt me-1"></i>
                                                                    <?php echo e($daySchedule['street_name']); ?>
                                                                </p>
                                                                <span class="badge bg-success waste-type-badge">
                                                                    <?php echo ucfirst($daySchedule['waste_type']); ?>
                                                                </span>
                                                                <?php if ($daySchedule['first_name']): ?>
                                                                    <p class="mb-0 mt-1">
                                                                        <small class="text-muted">
                                                                            <i class="fas fa-user me-1"></i>
                                                                            <?php echo e($daySchedule['first_name'] . ' ' . $daySchedule['last_name']); ?>
                                                                        </small>
                                                                    </p>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <p class="text-muted mb-0">No collection scheduled</p>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                            <h5>No Schedule Found</h5>
                                            <p class="text-muted">No collection schedule found for your area. Please contact the waste management authority.</p>
                                            <a href="chat.php" class="btn btn-primary">
                                                <i class="fas fa-comments me-2"></i>Contact Support
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Collection History -->
                        <div class="col-md-4 mb-4">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="fas fa-history me-2"></i>Recent Collections</h6>
                                </div>
                                <div class="card-body">
                                    <?php if ($collection_history->num_rows > 0): ?>
                                        <?php while ($history = $collection_history->fetch_assoc()): ?>
                                            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                                                <div>
                                                    <h6 class="mb-1"><?php echo e($history['street_name']); ?></h6>
                                                    <small class="text-muted">
                                                        <?php echo format_ph_date($history['collection_date'], 'M j, Y'); ?>
                                                        <?php if ($history['collection_time']): ?>
                                                            at <?php echo format_ph_date($history['collection_time'], 'g:i A'); ?>
                                                        <?php endif; ?>
                                                    </small>
                                                    <br>
                                                    <span class="badge bg-<?php echo $history['status'] === 'completed' ? 'success' : ($history['status'] === 'missed' ? 'danger' : 'warning'); ?>">
                                                        <?php echo ucfirst($history['status']); ?>
                                                    </span>
                                                </div>
                                                <div class="text-end">
                                                    <span class="badge bg-info"><?php echo ucfirst($history['waste_type']); ?></span>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <p class="text-muted text-center">No collection history available.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Collection Tips -->
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Collection Tips</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <h6><i class="fas fa-clock text-primary me-2"></i>Timing</h6>
                                    <ul class="mb-0">
                                        <li>Place bins out the night before</li>
                                        <li>Ensure bins are accessible</li>
                                        <li>Don't overfill bins</li>
                                    </ul>
                                </div>
                                <div class="col-md-4">
                                    <h6><i class="fas fa-recycle text-success me-2"></i>Recycling</h6>
                                    <ul class="mb-0">
                                        <li>Rinse containers before recycling</li>
                                        <li>Remove lids and labels</li>
                                        <li>Check local recycling guidelines</li>
                                    </ul>
                                </div>
                                <div class="col-md-4">
                                    <h6><i class="fas fa-exclamation-triangle text-warning me-2"></i>Safety</h6>
                                    <ul class="mb-0">
                                        <li>Keep hazardous waste separate</li>
                                        <li>Don't block sidewalks or roads</li>
                                        <li>Report missed collections promptly</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://js.pusher.com/8.2/pusher.min.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/notifications.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/register_sw.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Subscribe to global schedule changes and notify resident
        (function(){
            try {
                const pusher = new Pusher('<?php echo PUSHER_KEY; ?>', { cluster: '<?php echo PUSHER_CLUSTER; ?>' });
                const channel = pusher.subscribe('schedule-global');
                channel.bind('schedule-changed', function(data) {
                    const action = (data && data.action) ? data.action : 'updated';
                    const street = data && data.street_name ? data.street_name : 'your area';
                    const day = data && data.collection_day ? (data.collection_day.charAt(0).toUpperCase()+data.collection_day.slice(1)) : 'schedule';
                    const time = data && data.collection_time ? data.collection_time : '';
                    Notifications.requestPermissionOnce().then((granted) => {
                        if (granted) {
                            Notifications.show({
                                title: 'Collection schedule ' + action,
                                body: street + ' - ' + day + (time ? (' at ' + time) : ''),
                                icon: '<?php echo BASE_URL; ?>assets/collector.png',
                                onclick: () => { window.focus(); location.reload(); }
                            });
                        }
                    });
                });
            } catch (e) {}
        })();

        // Push Notification Registration
        (async function() {
            if ('serviceWorker' in navigator && 'PushManager' in window) {
                try {
                    // Register service worker
                    const registration = await navigator.serviceWorker.register('<?php echo BASE_URL; ?>sw.js');
                    console.log('Service Worker registered:', registration);

                    // Check if already subscribed
                    let subscription = await registration.pushManager.getSubscription();
                    
                    if (!subscription) {
                        // Request notification permission
                        const permission = await Notification.requestPermission();
                        if (permission === 'granted') {
                    // Helper function to convert base64 string to Uint8Array
                    function urlBase64ToUint8Array(base64String) {
                        const padding = '='.repeat((4 - base64String.length % 4) % 4);
                        const base64 = (base64String + padding)
                            .replace(/-/g, '+')
                            .replace(/_/g, '/');
                        const rawData = window.atob(base64);
                        const outputArray = new Uint8Array(rawData.length);
                        for (let i = 0; i < rawData.length; ++i) {
                            outputArray[i] = rawData.charCodeAt(i);
                        }
                        return outputArray;
                    }

                    // Convert VAPID public key and subscribe to push notifications
                    const applicationServerKey = urlBase64ToUint8Array('<?php echo VAPID_PUBLIC_KEY; ?>');
                    subscription = await registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: applicationServerKey
                    });
                            
                            // Send subscription to server
                            await fetch('<?php echo BASE_URL; ?>push_subscribe.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({ subscription: subscription.toJSON() })
                            });
                            
                            console.log('Push notification subscription created');
                        }
                    } else {
                        console.log('Already subscribed to push notifications');
                    }
                    
                } catch (error) {
                    console.error('Service Worker registration failed:', error);
                }
            }
        })();
    </script>
</body>
</html>

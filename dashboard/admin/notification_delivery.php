<?php
require_once __DIR__ . '/../../config/config.php';
require_login();
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ' . BASE_URL);
    exit;
}

$jobs = $conn->query("SELECT * FROM notification_jobs ORDER BY created_at DESC LIMIT 50");
$logs = $conn->query("SELECT l.*, j.title, j.target_type, j.target_value FROM notification_send_logs l LEFT JOIN notification_jobs j ON l.job_id = j.id ORDER BY l.created_at DESC LIMIT 100");

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Notification Delivery - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
    <div class="container">
        <h3>Notification Jobs</h3>
        <p>Run the worker to process queued jobs:</p>
        <pre>php scripts\process_notification_jobs.php</pre>
        <table class="table table-sm table-striped">
            <thead><tr><th>ID</th><th>Target</th><th>Title</th><th>Status</th><th>Attempts</th><th>Created</th></tr></thead>
            <tbody>
            <?php while ($row = $jobs->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo e($row['target_type']) . ':' . e($row['target_value']); ?></td>
                    <td><?php echo e($row['title']); ?></td>
                    <td><?php echo e($row['status']); ?></td>
                    <td><?php echo e($row['attempts']); ?>/<?php echo e($row['max_attempts']); ?></td>
                    <td><?php echo e($row['created_at']); ?></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>

        <h4>Recent Send Logs</h4>
        <table class="table table-sm table-striped">
            <thead><tr><th>Time</th><th>Job</th><th>Subscription ID</th><th>Success</th><th>Response</th></tr></thead>
            <tbody>
            <?php while ($l = $logs->fetch_assoc()): ?>
                <tr>
                    <td><?php echo e($l['created_at']); ?></td>
                    <td><?php echo e($l['title']) . ' (' . e($l['target_type']) . ':' . e($l['target_value']) . ')'; ?></td>
                    <td><?php echo e($l['subscription_id']); ?></td>
                    <td><?php echo $l['success'] ? '✔' : '✖'; ?></td>
                    <td><?php echo e(substr($l['response_text'] ?? '', 0, 120)); ?></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>

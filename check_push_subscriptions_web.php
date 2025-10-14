<?php
require_once 'config/config.php';
require_login();

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? '';

echo "<h2>Push Subscription Check</h2>";
echo "<p><strong>User ID:</strong> $user_id</p>";
echo "<p><strong>User Role:</strong> $user_role</p>";
echo "<p><strong>Current Time:</strong> " . date('Y-m-d H:i:s') . "</p>";

// Check if push_subscriptions table exists
$tables_query = "SHOW TABLES LIKE 'push_subscriptions'";
$tables_result = $conn->query($tables_query);

if ($tables_result->num_rows === 0) {
    echo "<p style='color: red;'>❌ ERROR: push_subscriptions table does not exist!</p>";
    echo "<p>You need to create the push_subscriptions table first.</p>";
    exit;
}

echo "<p style='color: green;'>✅ push_subscriptions table exists</p>";

// Check for subscriptions for this user
$stmt = $conn->prepare("SELECT * FROM push_subscriptions WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<p style='color: red;'>❌ No push subscription found for user ID: $user_id</p>";
    echo "<p>This means you haven't subscribed to push notifications yet.</p>";
    
    // Show all subscriptions in the table
    $all_subs = $conn->query("SELECT user_id, endpoint, created_at FROM push_subscriptions ORDER BY created_at DESC LIMIT 10");
    if ($all_subs->num_rows > 0) {
        echo "<h3>Other subscriptions in the system:</h3>";
        echo "<ul>";
        while ($row = $all_subs->fetch_assoc()) {
            echo "<li>User ID: {$row['user_id']}, Created: {$row['created_at']}, Endpoint: " . substr($row['endpoint'], 0, 50) . "...</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>No subscriptions found in the entire system.</p>";
    }
} else {
    echo "<p style='color: green;'>✅ Found " . $result->num_rows . " push subscription(s) for user ID: $user_id</p>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<h3>Subscription Details:</h3>";
        echo "<ul>";
        echo "<li><strong>ID:</strong> {$row['id']}</li>";
        echo "<li><strong>Endpoint:</strong> " . substr($row['endpoint'], 0, 80) . "...</li>";
        echo "<li><strong>Created:</strong> {$row['created_at']}</li>";
        echo "<li><strong>P256DH Key:</strong> " . substr($row['p256dh'], 0, 20) . "...</li>";
        echo "<li><strong>Auth Key:</strong> " . substr($row['auth'], 0, 20) . "...</li>";
        echo "</ul>";
    }
}

// Check user's address for area matching
$user_stmt = $conn->prepare("SELECT address FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();

if ($user_result->num_rows > 0) {
    $user_data = $user_result->fetch_assoc();
    echo "<p><strong>User Address:</strong> " . ($user_data['address'] ?? 'NULL') . "</p>";
} else {
    echo "<p style='color: red;'>❌ User not found in users table</p>";
}

// Check for collection schedules
$schedules_stmt = $conn->query("SELECT area, street_name, collection_day, collection_time FROM collection_schedules WHERE status = 'active' LIMIT 5");
if ($schedules_stmt->num_rows > 0) {
    echo "<h3>Active Collection Schedules:</h3>";
    echo "<ul>";
    while ($schedule = $schedules_stmt->fetch_assoc()) {
        echo "<li>Area: {$schedule['area']}, Street: {$schedule['street_name']}, Day: {$schedule['collection_day']}, Time: {$schedule['collection_time']}</li>";
    }
    echo "</ul>";
} else {
    echo "<p>No active collection schedules found.</p>";
}

$stmt->close();
$conn->close();
?>

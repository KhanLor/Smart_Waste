<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/lib/push_notifications.php';

// Use the database connection from config.php
global $conn;
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Create push notifier instance
$pushNotifier = new PushNotifications($conn);

// Test notification parameters
$area = "Test Area";
$title = "Test Notification";
$body = "This is a test push notification from the Smart Waste system. If you can see this, push notifications are working correctly!";
$data = [
    'type' => 'test',
    'message' => 'System test notification'
];

// Send test notification to specific user (ID:5)
$results = $pushNotifier->notifyUser(5, $title, $body, $data);

echo "Test notification sent. Results:\n";
print_r($results);

// Close connection
$conn->close();

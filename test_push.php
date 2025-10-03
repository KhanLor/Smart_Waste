<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/lib/push_notifications.php';

// Include the config file to get the database connection
require_once __DIR__ . '/config/config.php';

// Use the existing database connection from config.php
$conn = $GLOBALS['conn'];

// Initialize push notification service
$pushNotifier = new PushNotifications($conn);

// Test data
$testArea = "Mintal";  // Using the area of the subscribed resident
$testTitle = "Test Notification";
$testBody = "This is a test push notification for area: $testArea";

echo "Testing push notifications for area: $testArea\n";

// Send test notification
$results = $pushNotifier->notifyArea($testArea, $testTitle, $testBody, [
    'type' => 'test',
    'area' => $testArea
]);

// Output results
echo "Notification results:\n";
print_r($results);

// Check for success
$successCount = count(array_filter($results, function($result) {
    return $result === true;
}));

echo "Sent $successCount successful notifications out of " . count($results) . " subscribers.\n";

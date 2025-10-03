<?php
// send_test_pusher.php
// One-off script to test sending a collector-location event via Pusher.
// Usage (PowerShell):
// C:\xampp\php\php.exe script\send_test_pusher.php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

if (!defined('PUSHER_KEY') || !PUSHER_KEY) {
    echo "PUSHER_KEY not configured in config.php\n";
    exit(1);
}

if (!class_exists('Pusher\Pusher')) {
    echo "Pusher PHP library not found. Run composer install or require pusher/pusher-php-server.\n";
    exit(1);
}

try {
    $options = ['cluster' => PUSHER_CLUSTER, 'useTLS' => PUSHER_USE_TLS];
    $pusher = new Pusher\Pusher(PUSHER_KEY, PUSHER_SECRET, PUSHER_APP_ID, $options);

    $payload = [
        'collector_id' => 999,
        'name' => 'Test Collector',
        'latitude' => 14.6200,
        'longitude' => 120.9800,
        'heading' => 45,
        'updated_at' => date(DATE_ATOM)
    ];

    $result = $pusher->trigger('collectors-channel', 'collector-location', $payload);
    echo "Pusher trigger result: " . json_encode($result) . "\n";
    echo "Payload sent:\n" . json_encode($payload) . "\n";
} catch (Exception $e) {
    echo "Error sending Pusher event: " . $e->getMessage() . "\n";
    exit(1);
}

?>
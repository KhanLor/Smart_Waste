<?php
require_once 'config/config.php';

echo "🔍 Push Notification Diagnostic Report\n";
echo "=====================================\n\n";

// 1. Check VAPID Configuration
echo "1. 🔑 VAPID Configuration:\n";
echo "   Public Key: " . (defined('VAPID_PUBLIC_KEY') ? '✅ Set' : '❌ Not Set') . "\n";
echo "   Private Key: " . (defined('VAPID_PRIVATE_KEY') ? '✅ Set' : '❌ Not Set') . "\n";
echo "   Subject: " . (defined('VAPID_SUBJECT') ? '✅ Set' : '❌ Not Set') . "\n";

if (defined('VAPID_PUBLIC_KEY') && VAPID_PUBLIC_KEY !== 'your-vapid-public-key') {
    echo "   Public Key Length: " . strlen(VAPID_PUBLIC_KEY) . " chars (should be ~87)\n";
    echo "   Private Key Length: " . strlen(VAPID_PRIVATE_KEY) . " chars (should be ~43)\n";
} else {
    echo "   ❌ VAPID keys are still placeholder values!\n";
}

// 2. Check Composer Dependencies
echo "\n2. 📦 Composer Dependencies:\n";
$vendor_autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($vendor_autoload)) {
    echo "   ✅ vendor/autoload.php: EXISTS\n";
    
    // Check specific libraries
    $pusher_path = __DIR__ . '/vendor/pusher/pusher-php-server';
    $webpush_path = __DIR__ . '/vendor/minishlink/web-push';
    
    echo "   Pusher Library: " . (is_dir($pusher_path) ? '✅ Installed' : '❌ Missing') . "\n";
    echo "   Web-Push Library: " . (is_dir($webpush_path) ? '✅ Installed' : '❌ Missing') . "\n";
} else {
    echo "   ❌ vendor/autoload.php: MISSING\n";
    echo "   Run: composer install\n";
}

// 3. Check Database
echo "\n3. 🗄️ Database:\n";
$result = $conn->query("SHOW TABLES LIKE 'push_subscriptions'");
if ($result->num_rows > 0) {
    echo "   ✅ push_subscriptions table: EXISTS\n";
    
    // Check subscriptions
    $result = $conn->query("SELECT COUNT(*) as count FROM push_subscriptions");
    $count = $result->fetch_assoc()['count'];
    echo "   📊 Total subscriptions: {$count}\n";
    
    if ($count > 0) {
        // Show subscription details
        $result = $conn->query("SELECT ps.*, u.email, u.role FROM push_subscriptions ps JOIN users u ON ps.user_id = u.id LIMIT 3");
        echo "   📋 Recent subscriptions:\n";
        while ($row = $result->fetch_assoc()) {
            echo "     - User: {$row['email']} ({$row['role']}) - Created: {$row['created_at']}\n";
        }
    }
} else {
    echo "   ❌ push_subscriptions table: MISSING\n";
}

// 4. Check Service Worker
echo "\n4. 🔧 Service Worker:\n";
$sw_path = __DIR__ . '/sw.js';
if (file_exists($sw_path)) {
    echo "   ✅ sw.js: EXISTS\n";
    $sw_size = filesize($sw_path);
    echo "   📏 File size: {$sw_size} bytes\n";
} else {
    echo "   ❌ sw.js: MISSING\n";
}

// 5. Check Push Endpoints
echo "\n5. 🌐 Push Endpoints:\n";
$push_subscribe = __DIR__ . '/push_subscribe.php';
$push_notifications = __DIR__ . '/lib/push_notifications.php';

echo "   push_subscribe.php: " . (file_exists($push_subscribe) ? '✅ EXISTS' : '❌ MISSING') . "\n";
echo "   push_notifications.php: " . (file_exists($push_notifications) ? '✅ EXISTS' : '❌ MISSING') . "\n";

// 6. Check Current User Session
echo "\n6. 👤 Current User:\n";
if (isset($_SESSION['user_id'])) {
    echo "   ✅ User logged in: ID {$_SESSION['user_id']}\n";
    
    // Check if user has subscription
    $stmt = $conn->prepare("SELECT * FROM push_subscriptions WHERE user_id = ?");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "   ✅ User has push subscription\n";
    } else {
        echo "   ❌ User has NO push subscription\n";
    }
    $stmt->close();
} else {
    echo "   ❌ No user logged in\n";
}

echo "\n=====================================\n";
echo "🏁 Diagnostic complete!\n\n";

// Recommendations
echo "💡 Recommendations:\n";
if (!defined('VAPID_PUBLIC_KEY') || VAPID_PUBLIC_KEY === 'your-vapid-public-key') {
    echo "   • Set your actual VAPID keys in config/config.php\n";
}
if (!file_exists($vendor_autoload)) {
    echo "   • Run: composer install\n";
}
if (!file_exists($sw_path)) {
    echo "   • Check if sw.js was created\n";
}
echo "   • Visit push_status.php to see detailed status\n";
echo "   • Visit test_push.php to test notifications\n";
?>

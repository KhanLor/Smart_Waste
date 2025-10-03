<?php
require_once 'config/config.php';

echo "🔍 Checking Database Tables...\n\n";

// Check if push_subscriptions table exists
$result = $conn->query("SHOW TABLES LIKE 'push_subscriptions'");
if ($result->num_rows > 0) {
    echo "✅ push_subscriptions table: EXISTS\n";
    
    // Check table structure
    $result = $conn->query("DESCRIBE push_subscriptions");
    echo "📋 Table structure:\n";
    while ($row = $result->fetch_assoc()) {
        echo "  - {$row['Field']}: {$row['Type']} {$row['Null']} {$row['Key']}\n";
    }
    
    // Check if there are any subscriptions
    $result = $conn->query("SELECT COUNT(*) as count FROM push_subscriptions");
    $count = $result->fetch_assoc()['count'];
    echo "\n📊 Total subscriptions: {$count}\n";
    
} else {
    echo "❌ push_subscriptions table: MISSING\n";
    echo "\n🔧 To fix this, run the SQL from script/push_subscriptions.sql in your database\n";
}

// Check if users table exists
$result = $conn->query("SHOW TABLES LIKE 'users'");
if ($result->num_rows > 0) {
    echo "\n✅ users table: EXISTS\n";
} else {
    echo "\n❌ users table: MISSING\n";
}

echo "\n🏁 Database check complete!\n";
?>

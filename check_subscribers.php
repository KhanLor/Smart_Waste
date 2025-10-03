<?php
require_once __DIR__ . '/config/config.php';

// Use existing database connection
$conn = $GLOBALS['conn'];

// Check push subscriptions and addresses
$query = "
    SELECT 
        u.id, 
        u.first_name, 
        u.last_name, 
        u.address,
        ps.endpoint,
        ps.created_at
    FROM users u
    LEFT JOIN push_subscriptions ps ON u.id = ps.user_id
    WHERE u.role = 'resident'
";

$result = $conn->query($query);

echo "Push Subscription Status:\n";
echo "ID | Name | Address | Subscription | Created At\n";
echo "-----------------------------------------------\n";

while ($row = $result->fetch_assoc()) {
    $subscribed = $row['endpoint'] ? "Yes" : "No";
    $created_at = $row['created_at'] ? date('Y-m-d H:i', strtotime($row['created_at'])) : 'N/A';
    
    echo "{$row['id']} | {$row['first_name']} {$row['last_name']} | {$row['address']} | $subscribed | $created_at\n";
}

// Check if any San Antonio addresses exist
$testArea = "San Antonio";
$query = "SELECT COUNT(*) AS count FROM users WHERE role = 'resident' AND address LIKE ?";
$stmt = $conn->prepare($query);
$areaSearch = '%' . $testArea . '%';
$stmt->bind_param('s', $areaSearch);
$stmt->execute();
$countResult = $stmt->get_result()->fetch_assoc();

echo "\nResidents in '$testArea': {$countResult['count']}\n";

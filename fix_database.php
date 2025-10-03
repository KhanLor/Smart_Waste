<?php
require_once 'config/config.php';

echo "<h2>Database Fix Script</h2>";

// Test database connection
if (!$conn->ping()) {
    echo "<p style='color: red;'>✗ Database connection failed</p>";
    exit;
}

echo "<p style='color: green;'>✓ Database connection successful</p>";

// Check if tables exist
$tables = ['users', 'waste_reports', 'report_images', 'collection_schedules', 'collection_history', 'points_transactions', 'feedback', 'chat_messages', 'notifications', 'password_resets', 'email_verifications'];

foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '{$table}'");
    if ($result->num_rows > 0) {
        echo "<p style='color: green;'>✓ {$table} table exists</p>";
    } else {
        echo "<p style='color: red;'>✗ {$table} table missing</p>";
    }
}

// Check waste_reports data
$result = $conn->query("SELECT COUNT(*) as total FROM waste_reports");
$total_reports = $result->fetch_assoc()['total'];
echo "<p>Total reports in waste_reports: <strong>{$total_reports}</strong></p>";

if ($total_reports == 0) {
    echo "<h3>No reports found. Creating sample data...</h3>";
    
    // Check if users exist
    $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'resident'");
    $total_residents = $result->fetch_assoc()['total'];
    
    if ($total_residents > 0) {
        // Get first resident
        $result = $conn->query("SELECT id FROM users WHERE role = 'resident' LIMIT 1");
        $resident = $result->fetch_assoc();
        $resident_id = $resident['id'];
        
        // Insert sample reports
        $sample_reports = [
            [
                'user_id' => $resident_id,
                'report_type' => 'overflow',
                'title' => 'Overflowing Bin at Main Street',
                'description' => 'The bin at the corner of Main St and 5th Ave is overflowing with garbage',
                'location' => 'Main Street & 5th Avenue',
                'priority' => 'high',
                'status' => 'pending'
            ],
            [
                'user_id' => $resident_id,
                'report_type' => 'missed_collection',
                'title' => 'Missed Collection on Oak Ave',
                'description' => 'Our street was not collected yesterday as scheduled',
                'location' => 'Oak Avenue',
                'priority' => 'medium',
                'status' => 'pending'
            ],
            [
                'user_id' => $resident_id,
                'report_type' => 'damaged_bin',
                'title' => 'Damaged Recycling Bin',
                'description' => 'The blue recycling bin at Park Road is cracked and needs replacement',
                'location' => 'Park Road',
                'priority' => 'low',
                'status' => 'pending'
            ],
            [
                'user_id' => $resident_id,
                'report_type' => 'illegal_dumping',
                'title' => 'Illegal Dumping in Park',
                'description' => 'Someone has dumped construction waste in the public park',
                'location' => 'Central Park',
                'priority' => 'urgent',
                'status' => 'assigned'
            ]
        ];
        
        foreach ($sample_reports as $report) {
            $stmt = $conn->prepare("
                INSERT INTO waste_reports (user_id, report_type, title, description, location, priority, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param("issssss", 
                $report['user_id'], 
                $report['report_type'], 
                $report['title'], 
                $report['description'], 
                $report['location'], 
                $report['priority'], 
                $report['status']
            );
            
            if ($stmt->execute()) {
                echo "<p style='color: green;'>✓ Created report: {$report['title']}</p>";
            } else {
                echo "<p style='color: red;'>✗ Failed to create report: {$report['title']}</p>";
            }
        }
        
        // Check again
        $result = $conn->query("SELECT COUNT(*) as total FROM waste_reports");
        $new_total = $result->fetch_assoc()['total'];
        echo "<p>New total reports: <strong>{$new_total}</strong></p>";
    } else {
        echo "<p style='color: red;'>✗ No residents found. Please run the installation script first.</p>";
    }
} else {
    echo "<h3>Sample Reports:</h3>";
    $result = $conn->query("SELECT id, title, status, priority, created_at FROM waste_reports ORDER BY created_at DESC LIMIT 5");
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Title</th><th>Status</th><th>Priority</th><th>Created</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['title']}</td>";
        echo "<td>{$row['status']}</td>";
        echo "<td>{$row['priority']}</td>";
        echo "<td>{$row['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Check users
echo "<h3>Users:</h3>";
$result = $conn->query("SELECT id, username, role, email FROM users ORDER BY id");
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>ID</th><th>Username</th><th>Role</th><th>Email</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$row['username']}</td>";
    echo "<td>{$row['role']}</td>";
    echo "<td>{$row['email']}</td>";
    echo "</tr>";
}
echo "</table>";

// Test API endpoint
echo "<h3>Testing API Endpoint:</h3>";
$result = $conn->query("SELECT id FROM waste_reports LIMIT 1");
if ($result->num_rows > 0) {
    $report = $result->fetch_assoc();
    $report_id = $report['id'];
    
    echo "<p>Testing with report ID: {$report_id}</p>";
    echo "<p><a href='api/get_report_details.php?id={$report_id}' target='_blank'>Test API</a></p>";
    
    // Test the API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://localhost/smart_waste/api/get_report_details.php?id={$report_id}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "<p>API Response Code: {$http_code}</p>";
    if ($response) {
        $data = json_decode($response, true);
        if ($data && isset($data['success']) && $data['success']) {
            echo "<p style='color: green;'>✓ API is working correctly</p>";
        } else {
            echo "<p style='color: red;'>✗ API error: " . htmlspecialchars($data['error'] ?? 'Unknown error') . "</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ No response from API</p>";
    }
}

echo "<hr>";
echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li><a href='dashboard/resident/reports.php'>Test Resident Reports</a></li>";
echo "<li><a href='dashboard/authority/reports.php'>Test Authority Reports</a></li>";
echo "<li><a href='debug_reports.php'>Run Debug Script</a></li>";
echo "</ol>";

echo "<p><strong>Note:</strong> If you're still having issues, check the browser console for JavaScript errors when clicking 'View Details' on any report.</p>";
?>

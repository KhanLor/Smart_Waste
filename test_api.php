<?php
require_once 'config/config.php';

echo "<h2>Testing API and Database Connection</h2>";

// Test database connection
if ($conn->ping()) {
    echo "<p style='color: green;'>✓ Database connection successful</p>";
} else {
    echo "<p style='color: red;'>✗ Database connection failed</p>";
    exit;
}

// Check if waste_reports table exists and has data
$result = $conn->query("SHOW TABLES LIKE 'waste_reports'");
if ($result->num_rows > 0) {
    echo "<p style='color: green;'>✓ waste_reports table exists</p>";
    
    // Count total reports
    $result = $conn->query("SELECT COUNT(*) as total FROM waste_reports");
    $total = $result->fetch_assoc()['total'];
    echo "<p>Total reports in database: <strong>{$total}</strong></p>";
    
    // Show sample reports
    if ($total > 0) {
        echo "<h3>Sample Reports:</h3>";
        $result = $conn->query("SELECT id, title, status, priority, created_at FROM waste_reports LIMIT 5");
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
    } else {
        echo "<p style='color: orange;'>⚠ No reports found in database</p>";
    }
} else {
    echo "<p style='color: red;'>✗ waste_reports table does not exist</p>";
}

// Check if users table exists and has data
$result = $conn->query("SHOW TABLES LIKE 'users'");
if ($result->num_rows > 0) {
    echo "<p style='color: green;'>✓ users table exists</p>";
    
    // Count users by role
    $result = $conn->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
    echo "<h3>Users by Role:</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Role</th><th>Count</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['role']}</td>";
        echo "<td>{$row['count']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>✗ users table does not exist</p>";
}

// Test API endpoint
echo "<h3>Testing API Endpoint:</h3>";
if (file_exists('api/get_report_details.php')) {
    echo "<p style='color: green;'>✓ API file exists</p>";
    
    // Test with a sample report ID
    if ($total > 0) {
        $result = $conn->query("SELECT id FROM waste_reports LIMIT 1");
        $report_id = $result->fetch_assoc()['id'];
        
        echo "<p>Testing API with report ID: {$report_id}</p>";
        echo "<p><a href='api/get_report_details.php?id={$report_id}' target='_blank'>Test API Link</a></p>";
    }
} else {
    echo "<p style='color: red;'>✗ API file does not exist</p>";
}

echo "<hr>";
echo "<h3>File Paths Check:</h3>";
echo "<p>Current directory: " . __DIR__ . "</p>";
echo "<p>API path: " . __DIR__ . "/api/get_report_details.php</p>";
echo "<p>Config path: " . __DIR__ . "/config/config.php</p>";

// Check if uploads directory exists
if (is_dir('uploads/reports')) {
    echo "<p style='color: green;'>✓ Uploads directory exists</p>";
} else {
    echo "<p style='color: orange;'>⚠ Uploads directory does not exist</p>";
}
?>

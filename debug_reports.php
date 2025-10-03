<?php
require_once 'config/config.php';

echo "<h2>Debug Report Display Issues</h2>";

// Test database connection
if (!$conn->ping()) {
    echo "<p style='color: red;'>✗ Database connection failed</p>";
    exit;
}

echo "<p style='color: green;'>✓ Database connection successful</p>";

// Check if we're logged in
session_start();
if (isset($_SESSION['user_id'])) {
    echo "<p style='color: green;'>✓ User logged in: {$_SESSION['username']} (Role: {$_SESSION['role']})</p>";
} else {
    echo "<p style='color: orange;'>⚠ No user logged in</p>";
}

// Check waste_reports table
$result = $conn->query("SELECT COUNT(*) as total FROM waste_reports");
$total_reports = $result->fetch_assoc()['total'];
echo "<p>Total reports in database: <strong>{$total_reports}</strong></p>";

if ($total_reports > 0) {
    echo "<h3>All Reports:</h3>";
    $result = $conn->query("SELECT * FROM waste_reports ORDER BY created_at DESC LIMIT 10");
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>User ID</th><th>Title</th><th>Status</th><th>Priority</th><th>Created</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['user_id']}</td>";
        echo "<td>{$row['title']}</td>";
        echo "<td>{$row['status']}</td>";
        echo "<td>{$row['priority']}</td>";
        echo "<td>{$row['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Test API with first report
    $result = $conn->query("SELECT id FROM waste_reports LIMIT 1");
    $first_report = $result->fetch_assoc();
    $report_id = $first_report['id'];
    
    echo "<h3>Testing API with Report ID: {$report_id}</h3>";
    
    // Simulate API call
    $url = "http://localhost/smart_waste/api/get_report_details.php?id={$report_id}";
    echo "<p>API URL: <a href='{$url}' target='_blank'>{$url}</a></p>";
    
    // Test the API directly
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "<p>API Response Code: {$http_code}</p>";
    if ($response) {
        echo "<p>API Response:</p>";
        echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>";
        echo htmlspecialchars($response);
        echo "</pre>";
        
        // Try to decode JSON
        $data = json_decode($response, true);
        if ($data) {
            echo "<p style='color: green;'>✓ API returned valid JSON</p>";
            if (isset($data['success']) && $data['success']) {
                echo "<p style='color: green;'>✓ API call successful</p>";
                echo "<p>Report Title: " . htmlspecialchars($data['report']['title']) . "</p>";
                echo "<p>Report Description: " . htmlspecialchars(substr($data['report']['description'], 0, 100)) . "...</p>";
            } else {
                echo "<p style='color: red;'>✗ API call failed: " . htmlspecialchars($data['error'] ?? 'Unknown error') . "</p>";
            }
        } else {
            echo "<p style='color: red;'>✗ API returned invalid JSON</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ No response from API</p>";
    }
} else {
    echo "<p style='color: orange;'>⚠ No reports found in database</p>";
    
    // Check if users exist
    $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'resident'");
    $total_residents = $result->fetch_assoc()['total'];
    echo "<p>Total residents: {$total_residents}</p>";
    
    if ($total_residents > 0) {
        echo "<p>You can create a test report by:</p>";
        echo "<ol>";
        echo "<li>Logging in as a resident</li>";
        echo "<li>Going to Submit Report page</li>";
        echo "<li>Creating a new report</li>";
        echo "</ol>";
    }
}

// Check for common issues
echo "<h3>Common Issues Check:</h3>";

// Check if uploads directory exists
if (is_dir('uploads/reports')) {
    echo "<p style='color: green;'>✓ Uploads directory exists</p>";
} else {
    echo "<p style='color: red;'>✗ Uploads directory missing</p>";
    echo "<p>Creating uploads directory...</p>";
    mkdir('uploads/reports', 0755, true);
}

// Check file permissions
if (is_readable('api/get_report_details.php')) {
    echo "<p style='color: green;'>✓ API file is readable</p>";
} else {
    echo "<p style='color: red;'>✗ API file is not readable</p>";
}

// Check if config file exists
if (file_exists('config/config.php')) {
    echo "<p style='color: green;'>✓ Config file exists</p>";
} else {
    echo "<p style='color: red;'>✗ Config file missing</p>";
}

echo "<hr>";
echo "<h3>Quick Fixes:</h3>";
echo "<p>If reports are not showing:</p>";
echo "<ol>";
echo "<li>Make sure you're logged in as the correct user type</li>";
echo "<li>Check if there are any reports in the database</li>";
echo "<li>Verify the API endpoint is working</li>";
echo "<li>Check browser console for JavaScript errors</li>";
echo "<li>Ensure the database connection is working</li>";
echo "</ol>";

echo "<p><a href='dashboard/resident/reports.php'>Go to Resident Reports</a> | ";
echo "<a href='dashboard/authority/reports.php'>Go to Authority Reports</a></p>";
?>

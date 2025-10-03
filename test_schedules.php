<?php
require_once 'config/config.php';

echo "<h2>Testing Collection Schedules</h2>";

// Test database connection
if (!$conn->ping()) {
    echo "<p style='color: red;'>✗ Database connection failed</p>";
    exit;
}

echo "<p style='color: green;'>✓ Database connection successful</p>";

// Check if collection_schedules table exists
$result = $conn->query("SHOW TABLES LIKE 'collection_schedules'");
if ($result->num_rows > 0) {
    echo "<p style='color: green;'>✓ collection_schedules table exists</p>";
    
    // Show table structure
    echo "<h3>Table Structure:</h3>";
    $result = $conn->query("DESCRIBE collection_schedules");
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['Field']}</td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Key']}</td>";
        echo "<td>{$row['Default']}</td>";
        echo "<td>{$row['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Count existing schedules
    $result = $conn->query("SELECT COUNT(*) as total FROM collection_schedules");
    $total = $result->fetch_assoc()['total'];
    echo "<p>Total schedules in database: <strong>{$total}</strong></p>";
    
    if ($total == 0) {
        echo "<h3>No schedules found. Creating sample data...</h3>";
        
        // Get first authority user
        $result = $conn->query("SELECT id FROM users WHERE role = 'authority' LIMIT 1");
        if ($result->num_rows > 0) {
            $authority = $result->fetch_assoc();
            $authority_id = $authority['id'];
            
            // Get first collector
            $result = $conn->query("SELECT id FROM users WHERE role = 'collector' LIMIT 1");
            $collector_id = null;
            if ($result->num_rows > 0) {
                $collector = $result->fetch_assoc();
                $collector_id = $collector['id'];
            }
            
            // Sample schedules
            $sample_schedules = [
                [
                    'area' => 'Downtown',
                    'street_name' => 'Main Street',
                    'collection_day' => 'monday',
                    'collection_time' => '08:00:00',
                    'waste_type' => 'general',
                    'assigned_collector' => $collector_id,
                    'status' => 'active',
                    'created_by' => $authority_id
                ],
                [
                    'area' => 'Downtown',
                    'street_name' => 'Main Street',
                    'collection_day' => 'wednesday',
                    'collection_time' => '08:00:00',
                    'waste_type' => 'recyclable',
                    'assigned_collector' => $collector_id,
                    'status' => 'active',
                    'created_by' => $authority_id
                ],
                [
                    'area' => 'Residential Area',
                    'street_name' => 'Oak Avenue',
                    'collection_day' => 'tuesday',
                    'collection_time' => '09:00:00',
                    'waste_type' => 'general',
                    'assigned_collector' => $collector_id,
                    'status' => 'active',
                    'created_by' => $authority_id
                ],
                [
                    'area' => 'Residential Area',
                    'street_name' => 'Oak Avenue',
                    'collection_day' => 'friday',
                    'collection_time' => '09:00:00',
                    'waste_type' => 'organic',
                    'assigned_collector' => $collector_id,
                    'status' => 'active',
                    'created_by' => $authority_id
                ],
                [
                    'area' => 'Business District',
                    'street_name' => 'Commerce Street',
                    'collection_day' => 'monday',
                    'collection_time' => '06:00:00',
                    'waste_type' => 'general',
                    'assigned_collector' => $collector_id,
                    'status' => 'active',
                    'created_by' => $authority_id
                ],
                [
                    'area' => 'Business District',
                    'street_name' => 'Commerce Street',
                    'collection_day' => 'thursday',
                    'collection_time' => '06:00:00',
                    'waste_type' => 'recyclable',
                    'assigned_collector' => $collector_id,
                    'status' => 'active',
                    'created_by' => $authority_id
                ]
            ];
            
            foreach ($sample_schedules as $schedule) {
                $stmt = $conn->prepare("
                    INSERT INTO collection_schedules (area, street_name, collection_day, collection_time, waste_type, assigned_collector, status, created_by, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->bind_param("sssssssi", 
                    $schedule['area'], 
                    $schedule['street_name'], 
                    $schedule['collection_day'], 
                    $schedule['collection_time'], 
                    $schedule['waste_type'], 
                    $schedule['assigned_collector'], 
                    $schedule['status'], 
                    $schedule['created_by']
                );
                
                if ($stmt->execute()) {
                    echo "<p style='color: green;'>✓ Created schedule: {$schedule['street_name']} - {$schedule['collection_day']}</p>";
                } else {
                    echo "<p style='color: red;'>✗ Failed to create schedule: {$schedule['street_name']}</p>";
                }
            }
            
            // Check again
            $result = $conn->query("SELECT COUNT(*) as total FROM collection_schedules");
            $new_total = $result->fetch_assoc()['total'];
            echo "<p>New total schedules: <strong>{$new_total}</strong></p>";
        } else {
            echo "<p style='color: red;'>✗ No authority users found. Please run the installation script first.</p>";
        }
    } else {
        echo "<h3>Existing Schedules:</h3>";
        $result = $conn->query("SELECT * FROM collection_schedules ORDER BY collection_day, collection_time LIMIT 10");
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Area</th><th>Street</th><th>Day</th><th>Time</th><th>Type</th><th>Status</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['area']}</td>";
            echo "<td>{$row['street_name']}</td>";
            echo "<td>{$row['collection_day']}</td>";
            echo "<td>{$row['collection_time']}</td>";
            echo "<td>{$row['waste_type']}</td>";
            echo "<td>{$row['status']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} else {
    echo "<p style='color: red;'>✗ collection_schedules table does not exist</p>";
    echo "<p>Please run the installation script to create the database tables.</p>";
}

echo "<hr>";
echo "<h3>Test Links:</h3>";
echo "<ul>";
echo "<li><a href='dashboard/authority/schedules.php'>Authority Schedules Management</a></li>";
echo "<li><a href='dashboard/resident/schedule.php'>Resident Schedule View</a></li>";
echo "</ul>";

echo "<p><strong>Note:</strong> Make sure you're logged in as the correct user type to access these pages.</p>";
?>

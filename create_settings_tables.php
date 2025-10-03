<?php
require_once 'config/config.php';

echo "<h2>Creating Settings Database Tables</h2>";

// Create user_preferences table
$sql = "
CREATE TABLE IF NOT EXISTS user_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    preference_key VARCHAR(100) NOT NULL,
    preference_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_preference (user_id, preference_key),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($sql)) {
    echo "<p style='color: green;'>✓ user_preferences table created successfully</p>";
} else {
    echo "<p style='color: red;'>✗ Error creating user_preferences table: " . $conn->error . "</p>";
}

// Create system_settings table
$sql = "
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description TEXT,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($sql)) {
    echo "<p style='color: green;'>✓ system_settings table created successfully</p>";
} else {
    echo "<p style='color: red;'>✗ Error creating system_settings table: " . $conn->error . "</p>";
}

// Insert default system settings
$default_settings = [
    'auto_assign_reports' => '0',
    'auto_notify_residents' => '1',
    'collection_reminder_hours' => '24',
    'report_auto_close_days' => '7',
    'max_reports_per_resident' => '10'
];

foreach ($default_settings as $key => $value) {
    $sql = "INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $key, $value);
    
    if ($stmt->execute()) {
        echo "<p style='color: green;'>✓ Default setting '{$key}' inserted</p>";
    } else {
        echo "<p style='color: orange;'>⚠ Setting '{$key}' already exists or error occurred</p>";
    }
}

// Add notification columns to users table if they don't exist
$columns_to_add = [
    'email_notifications' => 'TINYINT(1) DEFAULT 1',
    'sms_notifications' => 'TINYINT(1) DEFAULT 0'
];

foreach ($columns_to_add as $column => $definition) {
    $sql = "SHOW COLUMNS FROM users LIKE '{$column}'";
    $result = $conn->query($sql);
    
    if ($result->num_rows == 0) {
        $sql = "ALTER TABLE users ADD COLUMN {$column} {$definition}";
        if ($conn->query($sql)) {
            echo "<p style='color: green;'>✓ Added column '{$column}' to users table</p>";
        } else {
            echo "<p style='color: red;'>✗ Error adding column '{$column}': " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ Column '{$column}' already exists</p>";
    }
}

echo "<hr>";
echo "<h3>Settings Tables Created Successfully!</h3>";
echo "<p>The following features are now available:</p>";
echo "<ul>";
echo "<li><strong>Profile Management:</strong> Update personal information</li>";
echo "<li><strong>Password Management:</strong> Change account password securely</li>";
echo "<li><strong>Notification Preferences:</strong> Configure email and SMS notifications</li>";
echo "<li><strong>System Configuration:</strong> Manage automation and timing settings</li>";
echo "<li><strong>Data Export:</strong> Export reports, schedules, and user data</li>";
echo "<li><strong>Database Backup:</strong> Create and manage system backups</li>";
echo "</ul>";

echo "<p><a href='dashboard/authority/settings.php' class='btn btn-primary'>Go to Settings Page</a></p>";
?>

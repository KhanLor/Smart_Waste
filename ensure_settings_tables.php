<?php
require_once __DIR__ . '/config/config.php';

// This lightweight migration ensures coordinates columns exist on collection_schedules
try {
    $conn->query("ALTER TABLE collection_schedules ADD COLUMN latitude DECIMAL(10,8) NULL AFTER street_name");
} catch (Exception $e) { /* ignore if exists */ }

try {
    $conn->query("ALTER TABLE collection_schedules ADD COLUMN longitude DECIMAL(11,8) NULL AFTER latitude");
} catch (Exception $e) { /* ignore if exists */ }

echo "OK";
?>

<?php
require_once 'config/config.php';

echo "<h2>Ensuring Settings Tables Exist</h2>";

// Check and create user_preferences table
$sql = "
CREATE TABLE IF NOT EXISTS user_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    preference_key VARCHAR(100) NOT NULL,
    preference_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_preference (user_id, preference_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($sql)) {
    echo "<p style='color: green;'>✓ user_preferences table ready</p>";
    
    // Add foreign key constraint if it doesn't exist
    $fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'user_preferences' AND CONSTRAINT_NAME = 'fk_user_preferences_user_id'");
    if ($fk_check->num_rows == 0) {
        $fk_sql = "ALTER TABLE user_preferences ADD CONSTRAINT fk_user_preferences_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE";
        if ($conn->query($fk_sql)) {
            echo "<p style='color: green;'>✓ Foreign key constraint added to user_preferences</p>";
        } else {
            echo "<p style='color: orange;'>⚠ Could not add foreign key to user_preferences: " . $conn->error . "</p>";
        }
    }
} else {
    echo "<p style='color: red;'>✗ Error with user_preferences table: " . $conn->error . "</p>";
}

// Check and create system_settings table
$sql = "
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description TEXT,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($sql)) {
    echo "<p style='color: green;'>✓ system_settings table ready</p>";
    
    // Add foreign key constraint if it doesn't exist
    $fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'system_settings' AND CONSTRAINT_NAME = 'fk_system_settings_updated_by'");
    if ($fk_check->num_rows == 0) {
        $fk_sql = "ALTER TABLE system_settings ADD CONSTRAINT fk_system_settings_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL";
        if ($conn->query($fk_sql)) {
            echo "<p style='color: green;'>✓ Foreign key constraint added to system_settings</p>";
        } else {
            echo "<p style='color: orange;'>⚠ Could not add foreign key to system_settings: " . $conn->error . "</p>";
        }
    }
} else {
    echo "<p style='color: red;'>✗ Error with system_settings table: " . $conn->error . "</p>";
}

// Check and create backup_logs table
$sql = "
CREATE TABLE IF NOT EXISTS backup_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size BIGINT NOT NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($sql)) {
    echo "<p style='color: green;'>✓ backup_logs table ready</p>";
    
    // Add foreign key constraint if it doesn't exist
    $fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'backup_logs' AND CONSTRAINT_NAME = 'fk_backup_logs_created_by'");
    if ($fk_check->num_rows == 0) {
        $fk_sql = "ALTER TABLE backup_logs ADD CONSTRAINT fk_backup_logs_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL";
        if ($conn->query($fk_sql)) {
            echo "<p style='color: green;'>✓ Foreign key constraint added to backup_logs</p>";
        } else {
            echo "<p style='color: orange;'>⚠ Could not add foreign key to backup_logs: " . $conn->error . "</p>";
        }
    }
} else {
    echo "<p style='color: red;'>✗ Error with backup_logs table: " . $conn->error . "</p>";
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

// Insert default system settings if they don't exist
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
    if ($stmt) {
        $stmt->bind_param("ss", $key, $value);
        if ($stmt->execute()) {
            echo "<p style='color: green;'>✓ Default setting '{$key}' ready</p>";
        } else {
            echo "<p style='color: orange;'>⚠ Setting '{$key}' already exists</p>";
        }
    }
}

// Create backups directory
$backup_dir = 'backups/';
if (!is_dir($backup_dir)) {
    if (mkdir($backup_dir, 0755, true)) {
        echo "<p style='color: green;'>✓ Backups directory created</p>";
    } else {
        echo "<p style='color: red;'>✗ Error creating backups directory</p>";
    }
} else {
    echo "<p style='color: blue;'>ℹ Backups directory already exists</p>";
}

echo "<hr>";
echo "<h3>Settings System Ready!</h3>";
echo "<p>All required tables and directories have been created. You can now access the settings page.</p>";
echo "<p><a href='dashboard/authority/settings.php' class='btn btn-primary'>Go to Settings Page</a></p>";
?>

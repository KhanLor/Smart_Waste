<?php
require_once 'config/config.php';

echo "<h2>Creating Backup Database Tables</h2>";

// Create backup_logs table
$sql = "
CREATE TABLE IF NOT EXISTS backup_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size BIGINT NOT NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($sql)) {
    echo "<p style='color: green;'>✓ backup_logs table created successfully</p>";
} else {
    echo "<p style='color: red;'>✗ Error creating backup_logs table: " . $conn->error . "</p>";
}

// Create backups directory
$backup_dir = 'backups/';
if (!is_dir($backup_dir)) {
    if (mkdir($backup_dir, 0755, true)) {
        echo "<p style='color: green;'>✓ Backups directory created successfully</p>";
    } else {
        echo "<p style='color: red;'>✗ Error creating backups directory</p>";
    }
} else {
    echo "<p style='color: blue;'>ℹ Backups directory already exists</p>";
}

echo "<hr>";
echo "<h3>Backup System Setup Complete!</h3>";
echo "<p>The backup functionality is now available with the following features:</p>";
echo "<ul>";
echo "<li><strong>Manual Backups:</strong> Create database backups on demand</li>";
echo "<li><strong>Backup History:</strong> Track all created backups</li>";
echo "<li><strong>Secure Storage:</strong> Backups stored in protected directory</li>";
echo "<li><strong>File Management:</strong> Download and delete backup files</li>";
echo "</ul>";

echo "<p><a href='dashboard/authority/backup_database.php' class='btn btn-primary'>Go to Backup Page</a></p>";
?>

<?php
// Test upload permissions and configuration
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Upload Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .info { color: blue; }
        pre { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; }
    </style>
</head>
<body>
    <h1>Upload Configuration Test</h1>
    
    <?php
    echo "<h2>PHP Configuration</h2>";
    echo "<pre>";
    echo "PHP Version: " . PHP_VERSION . "\n";
    echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
    echo "post_max_size: " . ini_get('post_max_size') . "\n";
    echo "max_file_uploads: " . ini_get('max_file_uploads') . "\n";
    echo "file_uploads: " . (ini_get('file_uploads') ? 'Enabled' : 'Disabled') . "\n";
    echo "upload_tmp_dir: " . (ini_get('upload_tmp_dir') ?: 'Default') . "\n";
    echo "</pre>";
    
    echo "<h2>GD Library (Image Processing)</h2>";
    echo "<pre>";
    if (extension_loaded('gd')) {
        echo "<span class='success'>✓ GD Extension is loaded</span>\n";
        $gdInfo = gd_info();
        foreach ($gdInfo as $key => $value) {
            echo "$key: " . (is_bool($value) ? ($value ? 'Yes' : 'No') : $value) . "\n";
        }
    } else {
        echo "<span class='error'>✗ GD Extension is NOT loaded</span>\n";
    }
    echo "</pre>";
    
    echo "<h2>Directory Permissions</h2>";
    $directories = [
        'uploads' => __DIR__ . '/uploads',
        'uploads/profiles' => __DIR__ . '/uploads/profiles',
        'uploads/reports' => __DIR__ . '/uploads/reports',
        'uploads/evidence' => __DIR__ . '/uploads/evidence',
    ];
    
    echo "<pre>";
    foreach ($directories as $name => $path) {
        $exists = file_exists($path);
        $isDir = is_dir($path);
        $isWritable = is_writable($path);
        $isReadable = is_readable($path);
        
        echo "\n<strong>$name:</strong>\n";
        echo "  Path: $path\n";
        echo "  Exists: " . ($exists ? "<span class='success'>Yes</span>" : "<span class='error'>No</span>") . "\n";
        
        if ($exists) {
            echo "  Is Directory: " . ($isDir ? "<span class='success'>Yes</span>" : "<span class='error'>No</span>") . "\n";
            echo "  Readable: " . ($isReadable ? "<span class='success'>Yes</span>" : "<span class='error'>No</span>") . "\n";
            echo "  Writable: " . ($isWritable ? "<span class='success'>Yes</span>" : "<span class='error'>No</span>") . "\n";
            echo "  Permissions: " . substr(sprintf('%o', fileperms($path)), -4) . "\n";
        } else {
            echo "  <span class='warning'>Creating directory...</span>\n";
            if (mkdir($path, 0777, true)) {
                echo "  <span class='success'>✓ Directory created successfully</span>\n";
                echo "  Writable: " . (is_writable($path) ? "<span class='success'>Yes</span>" : "<span class='error'>No</span>") . "\n";
            } else {
                echo "  <span class='error'>✗ Failed to create directory</span>\n";
            }
        }
    }
    echo "</pre>";
    
    echo "<h2>Test File Write</h2>";
    $testFile = __DIR__ . '/uploads/profiles/test_' . time() . '.txt';
    echo "<pre>";
    try {
        if (!is_dir(__DIR__ . '/uploads/profiles')) {
            mkdir(__DIR__ . '/uploads/profiles', 0777, true);
        }
        
        if (file_put_contents($testFile, 'Test content')) {
            echo "<span class='success'>✓ Successfully wrote test file: $testFile</span>\n";
            echo "File size: " . filesize($testFile) . " bytes\n";
            
            // Clean up
            unlink($testFile);
            echo "<span class='success'>✓ Successfully deleted test file</span>\n";
        } else {
            echo "<span class='error'>✗ Failed to write test file</span>\n";
        }
    } catch (Exception $e) {
        echo "<span class='error'>✗ Error: " . $e->getMessage() . "</span>\n";
    }
    echo "</pre>";
    
    echo "<h2>Session Status</h2>";
    echo "<pre>";
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    echo "Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? "<span class='success'>Active</span>" : "<span class='error'>Inactive</span>") . "\n";
    echo "Session ID: " . session_id() . "\n";
    echo "Logged in: " . (isset($_SESSION['user_id']) ? "<span class='success'>Yes (User ID: " . $_SESSION['user_id'] . ")</span>" : "<span class='warning'>No</span>") . "\n";
    echo "</pre>";
    ?>
    
    <h2>Recommendations</h2>
    <ul>
        <?php
        if (!extension_loaded('gd')) {
            echo "<li class='error'>Install/Enable GD extension for image processing</li>";
        }
        if (!is_dir(__DIR__ . '/uploads/profiles')) {
            echo "<li class='warning'>Create uploads/profiles directory</li>";
        } elseif (!is_writable(__DIR__ . '/uploads/profiles')) {
            echo "<li class='error'>Make uploads/profiles directory writable (chmod 777 or 755)</li>";
        }
        $upload_max = ini_get('upload_max_filesize');
        $post_max = ini_get('post_max_size');
        if (strpos($upload_max, 'M') !== false && (int)$upload_max < 10) {
            echo "<li class='warning'>Consider increasing upload_max_filesize (currently: $upload_max)</li>";
        }
        ?>
        <li class='info'>If all tests pass but upload still fails, check browser console for JavaScript errors</li>
        <li class='info'>Check PHP error log at: <?php echo ini_get('error_log') ?: 'Not configured'; ?></li>
    </ul>
    
    <p><a href="dashboard/resident/profile.php">Go to Resident Profile</a></p>
</body>
</html>

<?php
/**
 * Smart Waste Management System - Installation Script
 * This script helps set up the database and initial configuration
 */

// Check if already installed
if (file_exists('config/installed.lock')) {
    die('System is already installed. Remove config/installed.lock to reinstall.');
}

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

// Database connection test
$db_host = $_POST['db_host'] ?? 'localhost';
$db_user = $_POST['db_user'] ?? 'root';
$db_pass = $_POST['db_pass'] ?? '';
$db_name = $_POST['db_name'] ?? 'smart_waste';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step == 2) {
    try {
        // Test database connection
        $conn = new mysqli($db_host, $db_user, $db_pass);
        
        if ($conn->connect_error) {
            throw new Exception('Database connection failed: ' . $conn->connect_error);
        }
        
        // Create database if it doesn't exist
        $conn->query("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        $conn->select_db($db_name);
        
        // Import schema
        $schema_file = 'script/schema.sql';
        if (!file_exists($schema_file)) {
            throw new Exception('Schema file not found: ' . $schema_file);
        }
        
        $sql = file_get_contents($schema_file);
        $queries = explode(';', $sql);
        
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                if (!$conn->query($query)) {
                    throw new Exception('SQL Error: ' . $conn->error);
                }
            }
        }
        
        // Create config file
        $config_content = "<?php
// Database configuration and session bootstrap

// App constants
if (!defined('APP_NAME')) {
    define('APP_NAME', 'Smart Waste');
}
if (!defined('BASE_URL')) {
    define('BASE_URL', '/smart_waste/');
}

// Optional absolute public URL for links in emails
if (!defined('APP_PUBLIC_URL')) {
    define('APP_PUBLIC_URL', 'http://localhost/smart_waste/');
}

// Outbound email (Gmail SMTP) - configure these for real email sending
if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', 'smtp.gmail.com');
}
if (!defined('SMTP_PORT')) {
    define('SMTP_PORT', 587);
}
if (!defined('SMTP_SECURE')) {
    define('SMTP_SECURE', 'tls');
}
if (!defined('SMTP_USERNAME')) {
    define('SMTP_USERNAME', 'yourgmail@gmail.com');
}
if (!defined('SMTP_PASSWORD')) {
    define('SMTP_PASSWORD', 'your-app-password');
}
if (!defined('SMTP_FROM_EMAIL')) {
    define('SMTP_FROM_EMAIL', 'yourgmail@gmail.com');
}
if (!defined('SMTP_FROM_NAME')) {
    define('SMTP_FROM_NAME', APP_NAME);
}

// App absolute base URL helper for links in emails
if (!defined('APP_BASE_URL_ABS')) {
    if (defined('APP_PUBLIC_URL') && APP_PUBLIC_URL) {
        define('APP_BASE_URL_ABS', APP_PUBLIC_URL);
    } else {
        \$scheme = (!empty(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        \$host = \$_SERVER['HTTP_HOST'] ?? 'localhost';
        define('APP_BASE_URL_ABS', \$scheme . '://' . \$host . BASE_URL);
    }
}

// Database configuration
\$DB_HOST = '$db_host';
\$DB_USER = '$db_user';
\$DB_PASS = '$db_pass';
\$DB_NAME = '$db_name';

// Start a session if one hasn't been started yet
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Create a reusable MySQLi connection
\$conn = new mysqli(\$DB_HOST, \$DB_USER, \$DB_PASS, \$DB_NAME);

if (\$conn->connect_error) {
    die('Database connection failed: ' . \$conn->connect_error);
}

// Helper to sanitize output
function e(string \$value): string {
    return htmlspecialchars(\$value, ENT_QUOTES, 'UTF-8');
}

// Helper to check if user is logged in
function is_logged_in(): bool {
    return isset(\$_SESSION['user_id']);
}

// Helper to enforce login
function require_login(): void {
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
}
?>";

        if (file_put_contents('config/config.php', $config_content) === false) {
            throw new Exception('Failed to create config file');
        }
        
        // Create uploads directory
        if (!is_dir('uploads/reports')) {
            mkdir('uploads/reports', 0755, true);
        }
        
        // Create installed lock file
        file_put_contents('config/installed.lock', get_ph_time());
        
        $success = 'Installation completed successfully!';
        $step = 3;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Waste Management System - Installation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .install-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .install-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin: 2rem 0;
        }
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: bold;
        }
        .step.active {
            background: #28a745;
            color: white;
        }
        .step.completed {
            background: #20c997;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="install-container">
                    <!-- Header -->
                    <div class="install-header">
                        <i class="fas fa-recycle fa-3x mb-3"></i>
                        <h2>Smart Waste Management System</h2>
                        <p class="mb-0">Installation Wizard</p>
                    </div>
                    
                    <!-- Step Indicators -->
                    <div class="step-indicator">
                        <div class="step <?php echo $step >= 1 ? 'completed' : ''; ?>">1</div>
                        <div class="step <?php echo $step >= 2 ? 'completed' : ''; ?>">2</div>
                        <div class="step <?php echo $step >= 3 ? 'completed' : ''; ?>">3</div>
                    </div>
                    
                    <!-- Content -->
                    <div class="p-4">
                        <?php if ($step == 1): ?>
                            <!-- Welcome Step -->
                            <div class="text-center">
                                <i class="fas fa-rocket fa-3x text-primary mb-3"></i>
                                <h4>Welcome to Smart Waste Management System</h4>
                                <p class="text-muted">This wizard will help you set up your waste management system.</p>
                                
                                <div class="row mt-4">
                                    <div class="col-md-4">
                                        <i class="fas fa-database fa-2x text-info mb-2"></i>
                                        <h6>Database Setup</h6>
                                        <small class="text-muted">Configure MySQL database</small>
                                    </div>
                                    <div class="col-md-4">
                                        <i class="fas fa-cogs fa-2x text-warning mb-2"></i>
                                        <h6>System Configuration</h6>
                                        <small class="text-muted">Set up basic settings</small>
                                    </div>
                                    <div class="col-md-4">
                                        <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                        <h6>Ready to Use</h6>
                                        <small class="text-muted">Start managing waste</small>
                                    </div>
                                </div>
                                
                                <a href="?step=2" class="btn btn-primary btn-lg mt-4">
                                    <i class="fas fa-arrow-right me-2"></i>Get Started
                                </a>
                            </div>
                            
                        <?php elseif ($step == 2): ?>
                            <!-- Database Configuration Step -->
                            <h4><i class="fas fa-database me-2"></i>Database Configuration</h4>
                            
                            <?php if ($error): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST" action="?step=2">
                                <div class="mb-3">
                                    <label for="db_host" class="form-label">Database Host</label>
                                    <input type="text" class="form-control" id="db_host" name="db_host" 
                                           value="<?php echo htmlspecialchars($db_host); ?>" required>
                                    <div class="form-text">Usually 'localhost' for local installations</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="db_user" class="form-label">Database Username</label>
                                    <input type="text" class="form-control" id="db_user" name="db_user" 
                                           value="<?php echo htmlspecialchars($db_user); ?>" required>
                                    <div class="form-text">Usually 'root' for XAMPP/WAMP</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="db_pass" class="form-label">Database Password</label>
                                    <input type="password" class="form-control" id="db_pass" name="db_pass" 
                                           value="<?php echo htmlspecialchars($db_pass); ?>">
                                    <div class="form-text">Leave empty if no password is set</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="db_name" class="form-label">Database Name</label>
                                    <input type="text" class="form-control" id="db_name" name="db_name" 
                                           value="<?php echo htmlspecialchars($db_name); ?>" required>
                                    <div class="form-text">The database will be created if it doesn't exist</div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-database me-2"></i>Test Connection & Install
                                    </button>
                                    <a href="?step=1" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left me-2"></i>Back
                                    </a>
                                </div>
                            </form>
                            
                        <?php elseif ($step == 3): ?>
                            <!-- Success Step -->
                            <div class="text-center">
                                <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                                <h4>Installation Complete!</h4>
                                <p class="text-muted">Your Smart Waste Management System is ready to use.</p>
                                
                                <div class="alert alert-info">
                                    <h6>Default Login Credentials:</h6>
                                    <div class="row text-start">
                                        <div class="col-md-6">
                                            <strong>Admin:</strong> admin@smartwaste.local / Admin@123<br>
                                            <strong>Authority:</strong> authority@smartwaste.local / Authority@123
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Collector:</strong> collector@smartwaste.local / Collector@123<br>
                                            <strong>Resident:</strong> resident@smartwaste.local / Resident@123
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <a href="login.php" class="btn btn-success btn-lg">
                                        <i class="fas fa-sign-in-alt me-2"></i>Go to Login
                                    </a>
                                    <a href="index.php" class="btn btn-outline-primary">
                                        <i class="fas fa-home me-2"></i>View Homepage
                                    </a>
                                </div>
                                
                                <div class="mt-4">
                                    <small class="text-muted">
                                        <strong>Important:</strong> For security, please change the default passwords after your first login.
                                    </small>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Footer -->
                <div class="text-center mt-3">
                    <small class="text-white">
                        Smart Waste Management System &copy; <?php echo get_ph_time('Y'); ?>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
require_once '../../config/config.php';
require_login();

// Check if user is an authority
if (($_SESSION['role'] ?? '') !== 'authority') {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$success_message = '';
$error_message = '';

// Handle backup creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_backup') {
    try {
        // Create backup directory if it doesn't exist
        $backup_dir = '../../backups/';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        // Generate backup filename
        $backup_filename = 'smart_waste_backup_' . date('Y-m-d_H-i-s') . '.sql';
        $backup_path = $backup_dir . $backup_filename;
        
        // Database credentials
        $host = DB_HOST;
        $username = DB_USER;
        $password = DB_PASS;
        $database = DB_NAME;
        
        // Create mysqldump command
        $command = "mysqldump --host={$host} --user={$username} --password={$password} {$database} > {$backup_path}";
        
        // Execute backup command
        $output = [];
        $return_var = 0;
        exec($command, $output, $return_var);
        
        if ($return_var === 0 && file_exists($backup_path)) {
            // Log backup creation
            $stmt = $conn->prepare("
                INSERT INTO backup_logs (filename, file_path, file_size, created_by, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $file_size = filesize($backup_path);
            $stmt->bind_param("sssi", $backup_filename, $backup_path, $file_size, $user_id);
            $stmt->execute();
            
            $success_message = 'Database backup created successfully: ' . $backup_filename;
        } else {
            throw new Exception('Failed to create backup file');
        }
    } catch (Exception $e) {
        $error_message = 'Error creating backup: ' . $e->getMessage();
    }
}

// Get backup history
$backups = [];
try {
    $sql = "
        SELECT bl.*, u.first_name, u.last_name 
        FROM backup_logs bl 
        LEFT JOIN users u ON bl.created_by = u.id 
        ORDER BY bl.created_at DESC 
        LIMIT 10
    ";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $backups[] = $row;
    }
} catch (Exception $e) {
    // Table might not exist
}

// Get database statistics
$stats = [];
try {
    $tables = ['users', 'waste_reports', 'collection_schedules', 'collection_history', 'points_transactions', 'feedback', 'chat_messages', 'notifications'];
    
    foreach ($tables as $table) {
        $result = $conn->query("SELECT COUNT(*) as count FROM {$table}");
        if ($result) {
            $stats[$table] = $result->fetch_assoc()['count'];
        }
    }
} catch (Exception $e) {
    // Handle errors
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Backup - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .nav-link {
            border-radius: 10px;
            margin: 2px 0;
            transition: all 0.3s;
        }
        .nav-link:hover, .nav-link.active {
            background-color: rgba(255, 255, 255, 0.2);
            transform: translateX(5px);
        }
        .stat-card {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
        }
        .backup-card {
            border-left: 4px solid #28a745;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar text-white p-0">
                <div class="p-3">
                    <h4 class="mb-4"><i class="fas fa-shield-alt me-2"></i><?php echo APP_NAME; ?></h4>
                    <hr class="bg-white">
                    <nav class="nav flex-column">
                        <a class="nav-link text-white" href="index.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                        <a class="nav-link text-white" href="reports.php">
                            <i class="fas fa-exclamation-triangle me-2"></i>Waste Reports
                        </a>
                        <a class="nav-link text-white" href="schedules.php">
                            <i class="fas fa-calendar me-2"></i>Collection Schedules
                        </a>
                        <a class="nav-link text-white" href="collectors.php">
                            <i class="fas fa-users me-2"></i>Collectors
                        </a>
                        <a class="nav-link text-white" href="residents.php">
                            <i class="fas fa-home me-2"></i>Residents
                        </a>
                        <a class="nav-link text-white" href="analytics.php">
                            <i class="fas fa-chart-line me-2"></i>Analytics
                        </a>
                        <a class="nav-link text-white" href="chat.php">
                            <i class="fas fa-comments me-2"></i>Chat
                        </a>
                        <a class="nav-link text-white" href="settings.php">
                            <i class="fas fa-cog me-2"></i>Settings
                        </a>
                        <hr class="bg-white">
                        <a class="nav-link text-white" href="../../logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="p-4">
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="mb-1">Database Backup</h2>
                            <p class="text-muted mb-0">Create and manage database backups</p>
                        </div>
                        <div>
                            <a href="settings.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Settings
                            </a>
                        </div>
                    </div>

                    <!-- Messages -->
                    <?php if ($success_message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?php echo e($success_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($error_message): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo e($error_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Database Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-users fa-2x mb-2"></i>
                                    <h4 class="mb-1"><?php echo $stats['users'] ?? 0; ?></h4>
                                    <small>Total Users</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                                    <h4 class="mb-1"><?php echo $stats['waste_reports'] ?? 0; ?></h4>
                                    <small>Waste Reports</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-calendar fa-2x mb-2"></i>
                                    <h4 class="mb-1"><?php echo $stats['collection_schedules'] ?? 0; ?></h4>
                                    <small>Collection Schedules</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-history fa-2x mb-2"></i>
                                    <h4 class="mb-1"><?php echo $stats['collection_history'] ?? 0; ?></h4>
                                    <small>Collection History</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Create Backup -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-database me-2"></i>Create New Backup</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <p class="text-muted">
                                        Create a complete backup of the database including all tables, data, and structure. 
                                        This backup can be used to restore the system if needed.
                                    </p>
                                    <ul class="text-muted">
                                        <li>Backup includes all tables and data</li>
                                        <li>Backup files are stored securely</li>
                                        <li>Automatic backups run daily at 2:00 AM</li>
                                        <li>Manual backups are recommended before updates</li>
                                    </ul>
                                </div>
                                <div class="col-md-4 text-end">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="create_backup">
                                        <button type="submit" class="btn btn-success btn-lg">
                                            <i class="fas fa-save me-2"></i>Create Backup
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Backup History -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-history me-2"></i>Backup History</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($backups)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-database fa-3x text-muted mb-3"></i>
                                    <h5>No Backups Found</h5>
                                    <p class="text-muted">No database backups have been created yet.</p>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="create_backup">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Create First Backup
                                        </button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Filename</th>
                                                <th>Size</th>
                                                <th>Created By</th>
                                                <th>Created Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($backups as $backup): ?>
                                                <tr>
                                                    <td>
                                                        <i class="fas fa-file-archive me-2 text-primary"></i>
                                                        <?php echo e($backup['filename']); ?>
                                                    </td>
                                                    <td>
                                                        <?php echo number_format($backup['file_size'] / 1024, 2); ?> KB
                                                    </td>
                                                    <td>
                                                        <?php echo e($backup['first_name'] . ' ' . $backup['last_name']); ?>
                                                    </td>
                                                    <td>
                                                        <?php echo format_ph_date($backup['created_at']); ?>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <a href="download_backup.php?id=<?php echo $backup['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                                <i class="fas fa-download me-1"></i>Download
                                                            </a>
                                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteBackup(<?php echo $backup['id']; ?>)">
                                                                <i class="fas fa-trash me-1"></i>Delete
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this backup file? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" id="deleteForm" style="display: inline;">
                        <input type="hidden" name="action" value="delete_backup">
                        <input type="hidden" name="backup_id" id="deleteBackupId">
                        <button type="submit" class="btn btn-danger">Delete Backup</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteBackup(backupId) {
            document.getElementById('deleteBackupId').value = backupId;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>
</html>

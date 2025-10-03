<?php
require_once __DIR__ . '/config/config.php';
require_login();

// Check if VAPID keys are configured
$vapid_configured = defined('VAPID_PUBLIC_KEY') && VAPID_PUBLIC_KEY !== 'your-vapid-public-key';

// Check if user has push subscription
$has_subscription = false;
if ($vapid_configured) {
    $stmt = $conn->prepare("SELECT * FROM push_subscriptions WHERE user_id = ?");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $has_subscription = $stmt->get_result()->num_rows > 0;
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Push Notification Status - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-bell"></i> Push Notification Status</h4>
                    </div>
                    <div class="card-body">
                        
                        <!-- Server Configuration Status -->
                        <div class="mb-4">
                            <h6><i class="fas fa-server"></i> Server Configuration</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-<?php echo $vapid_configured ? 'check-circle text-success' : 'times-circle text-danger'; ?> me-2"></i>
                                        <span>VAPID Keys</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-<?php echo file_exists(__DIR__ . '/vendor/autoload.php') ? 'check-circle text-success' : 'times-circle text-danger'; ?> me-2"></i>
                                        <span>Composer Dependencies</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Database Status -->
                        <div class="mb-4">
                            <h6><i class="fas fa-database"></i> Database Status</h6>
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-<?php echo $has_subscription ? 'check-circle text-success' : 'info-circle text-info'; ?> me-2"></i>
                                <span>Your Subscription: <?php echo $has_subscription ? 'Active' : 'Not Found'; ?></span>
                            </div>
                        </div>

                        <!-- Browser Support Check -->
                        <div class="mb-4">
                            <h6><i class="fas fa-globe"></i> Browser Support</h6>
                            <div id="browserSupport">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-spinner fa-spin me-2"></i>
                                    <span>Checking browser support...</span>
                                </div>
                            </div>
                        </div>

                        <!-- Permission Status -->
                        <div class="mb-4">
                            <h6><i class="fas fa-shield-alt"></i> Permission Status</h6>
                            <div id="permissionStatus">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-spinner fa-spin me-2"></i>
                                    <span>Checking permission...</span>
                                </div>
                            </div>
                        </div>

                        <!-- Service Worker Status -->
                        <div class="mb-4">
                            <h6><i class="fas fa-cogs"></i> Service Worker Status</h6>
                            <div id="swStatus">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-spinner fa-spin me-2"></i>
                                    <span>Checking service worker...</span>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="d-grid gap-2">
                            <a href="test_push.php" class="btn btn-primary">
                                <i class="fas fa-vial"></i> Test Push Notifications
                            </a>
                            <a href="dashboard/resident/schedule.php" class="btn btn-success">
                                <i class="fas fa-calendar"></i> Go to Schedule (Auto-register)
                            </a>
                        </div>

                        <!-- Troubleshooting -->
                        <?php if (!$vapid_configured): ?>
                        <div class="alert alert-warning mt-3">
                            <h6><i class="fas fa-exclamation-triangle"></i> VAPID Keys Not Configured</h6>
                            <p>To enable push notifications, you need to:</p>
                            <ol>
                                <li>Generate VAPID keys at <a href="https://web-push-codelab.glitch.me/" target="_blank">https://web-push-codelab.glitch.me/</a></li>
                                <li>Update <code>config/config.php</code> with your keys</li>
                                <li>Run <code>composer install</code> to install dependencies</li>
                            </ol>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Check browser support
        function checkBrowserSupport() {
            const supportDiv = document.getElementById('browserSupport');
            const hasServiceWorker = 'serviceWorker' in navigator;
            const hasPushManager = 'PushManager' in window;
            
            let html = '';
            
            if (hasServiceWorker && hasPushManager) {
                html = `
                    <div class="d-flex align-items-center mb-2">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        <span>Service Worker: Supported</span>
                    </div>
                    <div class="d-flex align-items-center mb-2">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        <span>Push Manager: Supported</span>
                    </div>
                `;
            } else {
                html = `
                    <div class="d-flex align-items-center mb-2">
                        <i class="fas fa-times-circle text-danger me-2"></i>
                        <span>Service Worker: ${hasServiceWorker ? 'Supported' : 'Not Supported'}</span>
                    </div>
                    <div class="d-flex align-items-center mb-2">
                        <i class="fas fa-times-circle text-danger me-2"></i>
                        <span>Push Manager: ${hasPushManager ? 'Supported' : 'Not Supported'}</span>
                    </div>
                `;
            }
            
            supportDiv.innerHTML = html;
        }

        // Check notification permission
        function checkPermission() {
            const permissionDiv = document.getElementById('permissionStatus');
            const permission = Notification.permission;
            
            let icon, text, color;
            
            switch(permission) {
                case 'granted':
                    icon = 'check-circle text-success';
                    text = 'Permission Granted';
                    color = 'success';
                    break;
                case 'denied':
                    icon = 'times-circle text-danger';
                    text = 'Permission Denied';
                    color = 'danger';
                    break;
                case 'default':
                    icon = 'question-circle text-warning';
                    text = 'Permission Not Set';
                    color = 'warning';
                    break;
            }
            
            permissionDiv.innerHTML = `
                <div class="d-flex align-items-center mb-2">
                    <i class="fas fa-${icon} me-2"></i>
                    <span>${text}</span>
                </div>
                ${permission === 'default' ? '<small class="text-muted">Click "Allow" when prompted to enable notifications</small>' : ''}
            `;
        }

        // Check service worker status
        async function checkServiceWorker() {
            const swDiv = document.getElementById('swStatus');
            
            try {
                if ('serviceWorker' in navigator) {
                    const registration = await navigator.serviceWorker.getRegistration();
                    
                    if (registration) {
                        swDiv.innerHTML = `
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <span>Service Worker: Active</span>
                            </div>
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-info-circle text-info me-2"></i>
                                <span>Scope: ${registration.scope}</span>
                            </div>
                        `;
                    } else {
                        swDiv.innerHTML = `
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-times-circle text-danger me-2"></i>
                                <span>Service Worker: Not Registered</span>
                            </div>
                        `;
                    }
                } else {
                    swDiv.innerHTML = `
                        <div class="d-flex align-items-center mb-2">
                            <i class="fas fa-times-circle text-danger me-2"></i>
                            <span>Service Worker: Not Supported</span>
                        </div>
                    `;
                }
            } catch (error) {
                swDiv.innerHTML = `
                    <div class="d-flex align-items-center mb-2">
                        <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                        <span>Service Worker: Error checking</span>
                    </div>
                `;
            }
        }

        // Run all checks when page loads
        document.addEventListener('DOMContentLoaded', () => {
            checkBrowserSupport();
            checkPermission();
            checkServiceWorker();
        });
    </script>
</body>
</html>

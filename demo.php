<?php
require_once 'config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Waste Management System - Demo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .hero-section {
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.9) 0%, rgba(32, 201, 151, 0.9) 100%);
            color: white;
            padding: 4rem 0;
            margin-bottom: 3rem;
        }
        .feature-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s;
            height: 100%;
        }
        .feature-card:hover {
            transform: translateY(-5px);
        }
        .feature-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
        }
        .demo-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
        }
        .role-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .role-resident { background: #28a745; color: white; }
        .role-authority { background: #17a2b8; color: white; }
        .role-collector { background: #6f42c1; color: white; }
        .role-admin { background: #dc3545; color: white; }
        .stats-section {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 2rem;
            margin: 2rem 0;
            color: white;
        }
        .stat-item {
            text-align: center;
            margin-bottom: 1rem;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        .stat-label {
            font-size: 1rem;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-recycle me-2"></i>Smart Waste
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">Home</a>
                <a class="nav-link" href="login.php">Login</a>
                <a class="nav-link" href="register.php">Register</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container text-center">
            <h1 class="display-4 mb-4">
                <i class="fas fa-recycle me-3"></i>
                Smart Waste Management System
            </h1>
            <p class="lead mb-4">
                Revolutionizing waste management with intelligent solutions, real-time monitoring, and community engagement
            </p>
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number">4</div>
                        <div class="stat-label">User Roles</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number">10+</div>
                        <div class="stat-label">Features</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number">100%</div>
                        <div class="stat-label">Mobile Ready</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number">24/7</div>
                        <div class="stat-label">Support</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="container mb-5">
        <h2 class="text-center text-white mb-5">Key Features</h2>
        <div class="row">
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h4>Smart Reporting</h4>
                    <p>Submit waste reports with images, GPS location, and priority levels. Track status in real-time.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h4>Collection Scheduling</h4>
                    <p>Manage waste collection schedules with flexible timing, routes, and collector assignments.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-leaf"></i>
                    </div>
                    <h4>Eco Points System</h4>
                    <p>Gamified reward system encouraging responsible waste management and community participation.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <h4>Real-time Chat</h4>
                    <p>Direct communication between residents and authorities for quick issue resolution.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h4>Analytics Dashboard</h4>
                    <p>Comprehensive reporting and analytics for monitoring waste management operations.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <h4>Mobile Responsive</h4>
                    <p>Access the system from any device with a modern, intuitive mobile-first design.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Demo Access Section -->
    <section class="container mb-5">
        <h2 class="text-center text-white mb-5">Try the System</h2>
        <div class="row">
            <div class="col-md-6 col-lg-3">
                <div class="demo-card">
                    <span class="role-badge role-resident">Resident</span>
                    <h5>Resident Dashboard</h5>
                    <p class="text-muted">Submit reports, view schedules, earn points, and chat with authorities.</p>
                    <div class="mb-3">
                        <small class="text-muted">
                            <strong>Login:</strong> resident@smartwaste.local<br>
                            <strong>Password:</strong> Resident@123
                        </small>
                    </div>
                    <a href="dashboard/resident/" class="btn btn-success w-100">
                        <i class="fas fa-home me-2"></i>Access Dashboard
                    </a>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="demo-card">
                    <span class="role-badge role-authority">Authority</span>
                    <h5>Authority Dashboard</h5>
                    <p class="text-muted">Monitor reports, manage schedules, assign collectors, and view analytics.</p>
                    <div class="mb-3">
                        <small class="text-muted">
                            <strong>Login:</strong> authority@smartwaste.local<br>
                            <strong>Password:</strong> Authority@123
                        </small>
                    </div>
                    <a href="dashboard/authority/" class="btn btn-info w-100">
                        <i class="fas fa-shield-alt me-2"></i>Access Dashboard
                    </a>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="demo-card">
                    <span class="role-badge role-collector">Collector</span>
                    <h5>Collector Dashboard</h5>
                    <p class="text-muted">View assigned routes, update collection status, and manage work schedule.</p>
                    <div class="mb-3">
                        <small class="text-muted">
                            <strong>Login:</strong> collector@smartwaste.local<br>
                            <strong>Password:</strong> Collector@123
                        </small>
                    </div>
                    <a href="dashboard/collector/" class="btn btn-warning w-100">
                        <i class="fas fa-truck me-2"></i>Access Dashboard
                    </a>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="demo-card">
                    <span class="role-badge role-admin">Admin</span>
                    <h5>Admin Dashboard</h5>
                    <p class="text-muted">Full system access, user management, and system configuration.</p>
                    <div class="mb-3">
                        <small class="text-muted">
                            <strong>Login:</strong> admin@smartwaste.local<br>
                            <strong>Password:</strong> Admin@123
                        </small>
                    </div>
                    <a href="dashboard/admin/" class="btn btn-danger w-100">
                        <i class="fas fa-cog me-2"></i>Access Dashboard
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- System Benefits -->
    <section class="container mb-5">
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h4><i class="fas fa-users me-2 text-primary"></i>For Communities</h4>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success me-2"></i>Faster issue resolution</li>
                            <li><i class="fas fa-check text-success me-2"></i>Transparent waste management</li>
                            <li><i class="fas fa-check text-success me-2"></i>Community engagement</li>
                            <li><i class="fas fa-check text-success me-2"></i>Environmental awareness</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h4><i class="fas fa-building me-2 text-info"></i>For Authorities</h4>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success me-2"></i>Efficient operations</li>
                            <li><i class="fas fa-check text-success me-2"></i>Real-time monitoring</li>
                            <li><i class="fas fa-check text-success me-2"></i>Data-driven decisions</li>
                            <li><i class="fas fa-check text-success me-2"></i>Cost optimization</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <section class="container text-center mb-5">
        <div class="card">
            <div class="card-body py-5">
                <h3>Ready to Get Started?</h3>
                <p class="lead">Join the smart waste management revolution today!</p>
                <div class="d-flex justify-content-center gap-3 flex-wrap">
                    <a href="register.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-user-plus me-2"></i>Create Account
                    </a>
                    <a href="login.php" class="btn btn-outline-primary btn-lg">
                        <i class="fas fa-sign-in-alt me-2"></i>Login
                    </a>
                    <a href="install.php" class="btn btn-success btn-lg">
                        <i class="fas fa-download me-2"></i>Install System
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white text-center py-4">
        <div class="container">
            <p class="mb-0">
                <i class="fas fa-recycle me-2"></i>
                Smart Waste Management System &copy; <?php echo date('Y'); ?> - 
                Making waste management smarter, more efficient, and environmentally friendly!
            </p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

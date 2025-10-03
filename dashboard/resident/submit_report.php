<?php
require_once __DIR__ . '/../../config/config.php';
require_login();

// Check if user is a resident
if (($_SESSION['role'] ?? '') !== 'resident') {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $report_type = $_POST['report_type'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;

    // Validation
    if (empty($title) || empty($description) || empty($location)) {
        $error_message = 'Please fill in all required fields.';
    } else {
        try {
            // Start transaction
            $conn->begin_transaction();

            // Insert waste report
            $stmt = $conn->prepare("
                INSERT INTO waste_reports (user_id, report_type, title, description, location, latitude, longitude, priority, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->bind_param("issssdds", $user_id, $report_type, $title, $description, $location, $latitude, $longitude, $priority);
            
            if ($stmt->execute()) {
                $report_id = $conn->insert_id;
                
                // Handle image uploads
                if (!empty($_FILES['images']['name'][0])) {
                    $upload_dir = __DIR__ . '/../../uploads/reports/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                            $file_name = $_FILES['images']['name'][$key];
                            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                            
                            // Validate file type
                            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                            if (in_array($file_ext, $allowed_types)) {
                                $new_file_name = uniqid() . '_' . $file_name;
                                $file_path = $upload_dir . $new_file_name;
                                
                                if (move_uploaded_file($tmp_name, $file_path)) {
                                    // Save image record to database
                                    $stmt = $conn->prepare("
                                        INSERT INTO report_images (report_id, image_path, image_name) 
                                        VALUES (?, ?, ?)
                                    ");
                                    $relative_path = 'uploads/reports/' . $new_file_name;
                                    $stmt->bind_param("iss", $report_id, $relative_path, $file_name);
                                    $stmt->execute();
                                }
                            }
                        }
                    }
                }

                // Award points for submitting report
                $points = 5; // Base points for submitting a report
                if ($priority === 'high') $points += 2;
                if ($priority === 'urgent') $points += 3;
                
                $stmt = $conn->prepare("
                    INSERT INTO points_transactions (user_id, points, transaction_type, description, reference_type, reference_id) 
                    VALUES (?, ?, 'earned', 'Report submission reward', 'report', ?)
                ");
                $stmt->bind_param("iii", $user_id, $points, $report_id);
                $stmt->execute();

                // Update user's eco points
                $stmt = $conn->prepare("UPDATE users SET eco_points = eco_points + ? WHERE id = ?");
                $stmt->bind_param("ii", $points, $user_id);
                $stmt->execute();

                // Create notification
                $stmt = $conn->prepare("
                    INSERT INTO notifications (user_id, title, message, type, reference_type, reference_id) 
                    VALUES (?, ?, ?, 'success', 'report', ?)
                ");
                $notification_title = 'Report Submitted Successfully';
                $notification_message = "Your waste report '{$title}' has been submitted. You earned {$points} eco points!";
                $stmt->bind_param("issi", $user_id, $notification_title, $notification_message, $report_id);
                $stmt->execute();

                $conn->commit();
                $success_message = "Report submitted successfully! You earned {$points} eco points.";
                
                // Redirect to reports page after 2 seconds
                header("refresh:2;url=reports.php");
            } else {
                throw new Exception('Failed to submit report.');
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = 'Error submitting report: ' . $e->getMessage();
        }
    }
}

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Report - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
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
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            transition: border-color 0.3s;
        }
        .form-control:focus, .form-select:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }
        .btn {
            border-radius: 10px;
            padding: 10px 20px;
        }
        .image-preview {
            max-width: 150px;
            max-height: 150px;
            object-fit: cover;
            border-radius: 8px;
            margin: 5px;
        }
        .priority-high { border-left: 4px solid #dc3545; }
        .priority-medium { border-left: 4px solid #ffc107; }
        .priority-low { border-left: 4px solid #28a745; }
        .priority-urgent { border-left: 4px solid #6f42c1; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar text-white p-0">
                <div class="p-3">
                    <h4 class="mb-4"><i class="fas fa-recycle me-2"></i><?php echo APP_NAME; ?></h4>
                    <hr class="bg-white">
                    <nav class="nav flex-column">
                        <a class="nav-link text-white" href="index.php">
                            <i class="fas fa-home me-2"></i>Dashboard
                        </a>
                        <a class="nav-link text-white" href="reports.php">
                            <i class="fas fa-exclamation-circle me-2"></i>My Reports
                        </a>
                        <a class="nav-link text-white active" href="submit_report.php">
                            <i class="fas fa-plus-circle me-2"></i>Submit Report
                        </a>
                        <a class="nav-link text-white" href="schedule.php">
                            <i class="fas fa-calendar me-2"></i>Collection Schedule
                        </a>
                        <a class="nav-link text-white" href="points.php">
                            <i class="fas fa-leaf me-2"></i>Eco Points
                        </a>
                        <a class="nav-link text-white" href="feedback.php">
                            <i class="fas fa-comment me-2"></i>Feedback
                        </a>
                        <a class="nav-link text-white" href="chat.php">
                            <i class="fas fa-comments me-2"></i>Chat
                        </a>
                        <a class="nav-link text-white" href="profile.php">
                            <i class="fas fa-user me-2"></i>Profile
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
                            <h2 class="mb-1">Submit Waste Report</h2>
                            <p class="text-muted mb-0">Report waste management issues in your area</p>
                        </div>
                        <div class="text-end">
                            <div class="h4 text-success mb-0"><?php echo $user['eco_points']; ?></div>
                            <small class="text-muted">Eco Points</small>
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

                    <!-- Report Form -->
                    <div class="card">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Report Details</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data" id="reportForm">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="report_type" class="form-label">Report Type <span class="text-danger">*</span></label>
                                        <select class="form-select" id="report_type" name="report_type" required>
                                            <option value="">Select report type</option>
                                            <option value="overflow">Overflowing Bin</option>
                                            <option value="missed_collection">Missed Collection</option>
                                            <option value="damaged_bin">Damaged Bin</option>
                                            <option value="illegal_dumping">Illegal Dumping</option>
                                            <option value="other">Other Issue</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="priority" class="form-label">Priority Level <span class="text-danger">*</span></label>
                                        <select class="form-select" id="priority" name="priority" required>
                                            <option value="low">Low</option>
                                            <option value="medium" selected>Medium</option>
                                            <option value="high">High</option>
                                            <option value="urgent">Urgent</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="title" class="form-label">Report Title <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="title" name="title" placeholder="Brief description of the issue" required>
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label">Detailed Description <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="description" name="description" rows="4" placeholder="Provide detailed information about the issue..." required></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="location" class="form-label">Location <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="location" name="location" placeholder="Street address or landmark" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="phone" class="form-label">Contact Phone (Optional)</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" placeholder="Your contact number">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="latitude" class="form-label">Latitude (Optional)</label>
                                        <input type="number" class="form-control" id="latitude" name="latitude" step="any" placeholder="GPS latitude">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="longitude" class="form-label">Longitude (Optional)</label>
                                        <input type="number" class="form-control" id="longitude" name="longitude" step="any" placeholder="GPS longitude">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="images" class="form-label">Upload Images (Optional)</label>
                                    <input type="file" class="form-control" id="images" name="images[]" multiple accept="image/*">
                                    <div class="form-text">Upload up to 5 images (JPG, PNG, GIF). Max size: 5MB each.</div>
                                    <div id="imagePreview" class="mt-2"></div>
                                </div>

                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="reports.php" class="btn btn-secondary me-md-2">Cancel</a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-paper-plane me-2"></i>Submit Report
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Tips Card -->
                    <div class="card mt-4">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Tips for Better Reports</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <ul class="mb-0">
                                        <li>Be specific about the location</li>
                                        <li>Include clear, well-lit photos</li>
                                        <li>Describe the issue in detail</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <ul class="mb-0">
                                        <li>Set appropriate priority levels</li>
                                        <li>Provide contact information</li>
                                        <li>Report issues promptly</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Image preview functionality
        document.getElementById('images').addEventListener('change', function(e) {
            const preview = document.getElementById('imagePreview');
            preview.innerHTML = '';
            
            if (this.files) {
                Array.from(this.files).forEach(file => {
                    if (file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const img = document.createElement('img');
                            img.src = e.target.result;
                            img.className = 'image-preview';
                            preview.appendChild(img);
                        }
                        reader.readAsDataURL(file);
                    }
                });
            }
        });

        // Priority-based form styling
        document.getElementById('priority').addEventListener('change', function() {
            const form = document.getElementById('reportForm');
            form.className = 'priority-' + this.value;
        });

        // Form validation
        document.getElementById('reportForm').addEventListener('submit', function(e) {
            const title = document.getElementById('title').value.trim();
            const description = document.getElementById('description').value.trim();
            const location = document.getElementById('location').value.trim();
            const reportType = document.getElementById('report_type').value;
            
            if (!title || !description || !location || !reportType) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }
        });
    </script>
</body>
</html>

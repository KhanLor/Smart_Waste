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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'submit_feedback') {
        $feedback_type = $_POST['feedback_type'] ?? '';
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $rating = $_POST['rating'] ?? null;

        // Validation
        if (empty($subject) || empty($message) || empty($feedback_type)) {
            $error_message = 'Please fill in all required fields.';
        } else {
            try {
                // Start transaction
                $conn->begin_transaction();

                // Insert feedback
                $stmt = $conn->prepare("
                    INSERT INTO feedback (user_id, feedback_type, subject, message, rating, status) 
                    VALUES (?, ?, ?, ?, ?, 'open')
                ");
                $stmt->bind_param("isssi", $user_id, $feedback_type, $subject, $message, $rating);
                
                if ($stmt->execute()) {
                    $feedback_id = $conn->insert_id;
                    
                    // Award points for feedback
                    $points = 3; // Base points for feedback
                    if ($rating && $rating >= 4) $points += 2; // Bonus for high rating
                    
                    $stmt = $conn->prepare("
                        INSERT INTO points_transactions (user_id, points, transaction_type, description, reference_type, reference_id) 
                        VALUES (?, ?, 'earned', 'Feedback submission reward', 'feedback', ?)
                    ");
                    $stmt->bind_param("iii", $user_id, $points, $feedback_id);
                    $stmt->execute();

                    // Update user's eco points
                    $stmt = $conn->prepare("UPDATE users SET eco_points = eco_points + ? WHERE id = ?");
                    $stmt->bind_param("ii", $points, $user_id);
                    $stmt->execute();

                    // Create notification
                    $stmt = $conn->prepare("
                        INSERT INTO notifications (user_id, title, message, type, reference_type, reference_id) 
                        VALUES (?, ?, ?, 'success', 'feedback', ?)
                    ");
                    $notification_title = 'Feedback Submitted Successfully';
                    $notification_message = "Your feedback '{$subject}' has been submitted. You earned {$points} eco points!";
                    $stmt->bind_param("issi", $user_id, $notification_title, $notification_message, $feedback_id);
                    $stmt->execute();

                    $conn->commit();
                    $success_message = "Feedback submitted successfully! You earned {$points} eco points.";
                } else {
                    throw new Exception('Failed to submit feedback.');
                }
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = 'Error submitting feedback: ' . $e->getMessage();
            }
        }
    }
}

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get user's feedback history with pagination
$page = $_GET['page'] ?? 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$filter = $_GET['filter'] ?? '';

// Build query
$where_conditions = ["user_id = ?"];
$params = [$user_id];
$param_types = "i";

if (!empty($filter) && $filter !== 'all') {
    $where_conditions[] = "feedback_type = ?";
    $params[] = $filter;
    $param_types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM feedback WHERE {$where_clause}";
$stmt = $conn->prepare($count_sql);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$total_feedback = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_feedback / $limit);

// Get feedback
$sql = "SELECT * FROM feedback WHERE {$where_clause} ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$param_types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$feedback_history = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback - <?php echo APP_NAME; ?></title>
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
        .feedback-card {
            border-left: 4px solid #007bff;
            transition: transform 0.2s;
        }
        .feedback-card:hover {
            transform: translateY(-2px);
        }
        .feedback-card.suggestion {
            border-left-color: #17a2b8;
        }
        .feedback-card.complaint {
            border-left-color: #dc3545;
        }
        .feedback-card.appreciation {
            border-left-color: #28a745;
        }
        .feedback-card.bug_report {
            border-left-color: #6f42c1;
        }
        .rating-stars {
            color: #ffc107;
        }
        .status-badge {
            font-size: 0.8rem;
        }
        .feedback-type-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        .feedback-type-icon.suggestion {
            background: #17a2b8;
        }
        .feedback-type-icon.complaint {
            background: #dc3545;
        }
        .feedback-type-icon.appreciation {
            background: #28a745;
        }
        .feedback-type-icon.bug_report {
            background: #6f42c1;
        }
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
                        <a class="nav-link text-white" href="submit_report.php">
                            <i class="fas fa-plus-circle me-2"></i>Submit Report
                        </a>
                        <a class="nav-link text-white" href="schedule.php">
                            <i class="fas fa-calendar me-2"></i>Collection Schedule
                        </a>
                        <a class="nav-link text-white" href="points.php">
                            <i class="fas fa-leaf me-2"></i>Eco Points
                        </a>
                        <a class="nav-link text-white active" href="feedback.php">
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
                            <h2 class="mb-1">Feedback</h2>
                            <p class="text-muted mb-0">Share your thoughts and help us improve our services</p>
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

                    <div class="row">
                        <!-- Feedback Form -->
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Submit Feedback</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" id="feedbackForm">
                                        <input type="hidden" name="action" value="submit_feedback">
                                        
                                        <div class="mb-3">
                                            <label for="feedback_type" class="form-label">Feedback Type <span class="text-danger">*</span></label>
                                            <select class="form-select" id="feedback_type" name="feedback_type" required>
                                                <option value="">Select feedback type</option>
                                                <option value="suggestion">Suggestion</option>
                                                <option value="complaint">Complaint</option>
                                                <option value="appreciation">Appreciation</option>
                                                <option value="bug_report">Bug Report</option>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label for="subject" class="form-label">Subject <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="subject" name="subject" placeholder="Brief description of your feedback" required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="message" class="form-label">Message <span class="text-danger">*</span></label>
                                            <textarea class="form-control" id="message" name="message" rows="4" placeholder="Please provide detailed feedback..." required></textarea>
                                        </div>

                                        <div class="mb-3">
                                            <label for="rating" class="form-label">Rating (Optional)</label>
                                            <div class="rating-stars">
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="rating" id="rating1" value="1">
                                                    <label class="form-check-label" for="rating1">1</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="rating" id="rating2" value="2">
                                                    <label class="form-check-label" for="rating2">2</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="rating" id="rating3" value="3">
                                                    <label class="form-check-label" for="rating3">3</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="rating" id="rating4" value="4">
                                                    <label class="form-check-label" for="rating4">4</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="rating" id="rating5" value="5">
                                                    <label class="form-check-label" for="rating5">5</label>
                                                </div>
                                            </div>
                                            <small class="form-text text-muted">Rate your overall experience (1 = Poor, 5 = Excellent)</small>
                                        </div>

                                        <div class="d-grid">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-paper-plane me-2"></i>Submit Feedback
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Feedback History -->
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><i class="fas fa-history me-2"></i>Your Feedback</h6>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="?filter=all" class="btn btn-outline-secondary <?php echo $filter === 'all' || $filter === '' ? 'active' : ''; ?>">All</a>
                                        <a href="?filter=suggestion" class="btn btn-outline-info <?php echo $filter === 'suggestion' ? 'active' : ''; ?>">Suggestions</a>
                                        <a href="?filter=complaint" class="btn btn-outline-danger <?php echo $filter === 'complaint' ? 'active' : ''; ?>">Complaints</a>
                                        <a href="?filter=appreciation" class="btn btn-outline-success <?php echo $filter === 'appreciation' ? 'active' : ''; ?>">Appreciation</a>
                                    </div>
                                </div>
                                <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                                    <?php if ($feedback_history->num_rows > 0): ?>
                                        <?php while ($feedback = $feedback_history->fetch_assoc()): ?>
                                            <div class="feedback-card <?php echo $feedback['feedback_type']; ?> p-3 mb-3">
                                                <div class="d-flex align-items-start">
                                                    <div class="feedback-type-icon <?php echo $feedback['feedback_type']; ?> me-3">
                                                        <i class="fas fa-<?php echo $feedback['feedback_type'] === 'suggestion' ? 'lightbulb' : ($feedback['feedback_type'] === 'complaint' ? 'exclamation-triangle' : ($feedback['feedback_type'] === 'appreciation' ? 'heart' : 'bug')); ?>"></i>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                                            <h6 class="mb-1"><?php echo e($feedback['subject']); ?></h6>
                                                            <span class="badge bg-<?php echo $feedback['status'] === 'resolved' ? 'success' : ($feedback['status'] === 'in_progress' ? 'warning' : 'secondary'); ?> status-badge">
                                                                <?php echo ucfirst($feedback['status']); ?>
                                                            </span>
                                                        </div>
                                                        <p class="mb-2 text-muted small"><?php echo e(substr($feedback['message'], 0, 100)) . (strlen($feedback['message']) > 100 ? '...' : ''); ?></p>
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <small class="text-muted">
                                                                <?php echo format_ph_date($feedback['created_at'], 'M j, Y'); ?>
                                                            </small>
                                                            <?php if ($feedback['rating']): ?>
                                                                <div class="rating-stars">
                                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                        <i class="fas fa-star<?php echo $i <= $feedback['rating'] ? '' : '-o'; ?>"></i>
                                                                    <?php endfor; ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php if ($feedback['response']): ?>
                                                            <div class="mt-2 p-2 bg-light rounded">
                                                                <small class="text-muted">
                                                                    <strong>Response:</strong> <?php echo e($feedback['response']); ?>
                                                                </small>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <p class="text-muted text-center">No feedback submitted yet.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Feedback Guidelines -->
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Feedback Guidelines</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <i class="fas fa-lightbulb fa-2x text-info mb-2"></i>
                                        <h6>Suggestions</h6>
                                        <small class="text-muted">Share ideas to improve our services</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                                        <h6>Complaints</h6>
                                        <small class="text-muted">Report issues or problems you've experienced</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <i class="fas fa-heart fa-2x text-success mb-2"></i>
                                        <h6>Appreciation</h6>
                                        <small class="text-muted">Thank our team for good service</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <i class="fas fa-bug fa-2x text-danger mb-2"></i>
                                        <h6>Bug Reports</h6>
                                        <small class="text-muted">Report technical issues with the system</small>
                                    </div>
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
        // Form validation
        document.getElementById('feedbackForm').addEventListener('submit', function(e) {
            const subject = document.getElementById('subject').value.trim();
            const message = document.getElementById('message').value.trim();
            const feedbackType = document.getElementById('feedback_type').value;
            
            if (!subject || !message || !feedbackType) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }
        });

        // Dynamic form styling based on feedback type
        document.getElementById('feedback_type').addEventListener('change', function() {
            const form = document.getElementById('feedbackForm');
            form.className = 'feedback-' + this.value;
        });
    </script>
</body>
</html>

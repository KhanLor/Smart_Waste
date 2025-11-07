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
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
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
        /* pill-style filter buttons + container */
        .btn-filter-group {
            background: #fff;
            border: 1px solid #e6eef0;
            border-radius: 12px;
            padding: 6px;
            display: inline-flex;
            gap: 6px;
        }
        .btn-filter-group .btn {
            border-radius: 30px;
            padding: 6px 14px;
            margin: 0;
            border-width: 2px;
            font-size: 0.85rem;
        }
        .btn-filter-group .btn.active {
            box-shadow: none;
            color: #fff !important;
        }
        /* turn outline active buttons into filled colored pills */
        .btn-filter-group .btn-outline-info.active,
        .btn-filter-group .btn-outline-info.active:hover { background-color: #17a2b8; border-color: #17a2b8; }
        .btn-filter-group .btn-outline-danger.active,
        .btn-filter-group .btn-outline-danger.active:hover { background-color: #dc3545; border-color: #dc3545; }
        .btn-filter-group .btn-outline-success.active,
        .btn-filter-group .btn-outline-success.active:hover { background-color: #28a745; border-color: #28a745; }
        .btn-filter-group .btn-outline-secondary.active,
        .btn-filter-group .btn-outline-secondary.active:hover { background-color: #6c757d; border-color: #6c757d; }
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
            border-radius: 12px;
            transition: transform 0.18s;
            background: #fff;
            border: 1px solid #eef2f5;
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
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
            font-size: 18px;
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
        .feedback-meta {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .feedback-subject {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        .status-pill {
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 0.75rem;
        }
        .stars i { color: #ffc107; margin-right: 2px; }
        /* Responsive adjustments */
        @media (max-width: 767.98px) {
            .container-fluid { padding-left: 12px; padding-right: 12px; }
            .card { border-radius: 12px; }
            .btn-filter-group { width: 100%; overflow-x: auto; padding: 8px; }
            .btn-filter-group .btn { flex: 0 0 auto; }
            .feedback-card { flex-direction: row; gap: 10px; }
            .feedback-card .feedback-type-icon { width: 44px; height: 44px; font-size: 16px; }
            .feedback-subject { font-size: 1rem; }
            .feedback-meta { font-size: 0.85rem; }
            .status-pill { display: inline-block; margin-top: 6px; }
            .card-body[style] { max-height: none; }
            /* Stack form and history full-width */
            .row > .col-md-6 { flex: 0 0 100%; max-width: 100%; }
            .card-header .btn-filter-group { margin-top: 8px; }
            .stars { margin-top: 6px; }
        }
    </style>
</head>
<body class="role-resident">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar text-white p-0">
                <?php include __DIR__ . '/_sidebar.php'; ?>
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
                                    <div class="btn-filter-group d-flex align-items-center">
                                        <a href="?filter=all" class="btn btn-outline-secondary btn-sm <?php echo $filter === 'all' || $filter === '' ? 'active' : ''; ?>">All</a>
                                        <a href="?filter=suggestion" class="btn btn-outline-info btn-sm <?php echo $filter === 'suggestion' ? 'active' : ''; ?>">Suggestions</a>
                                        <a href="?filter=complaint" class="btn btn-outline-danger btn-sm <?php echo $filter === 'complaint' ? 'active' : ''; ?>">Complaints</a>
                                        <a href="?filter=appreciation" class="btn btn-outline-success btn-sm <?php echo $filter === 'appreciation' ? 'active' : ''; ?>">Appreciation</a>
                                    </div>
                                </div>
                                <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                                    <?php if ($feedback_history->num_rows > 0): ?>
                                        <?php while ($feedback = $feedback_history->fetch_assoc()): ?>
                                            <div id="feedback-<?php echo (int)$feedback['id']; ?>" class="feedback-card p-3 mb-3 d-flex <?php echo $feedback['feedback_type']; ?>">
                                                <div class="me-3">
                                                    <div class="feedback-type-icon <?php echo $feedback['feedback_type']; ?>">
                                                        <i class="fas fa-<?php echo $feedback['feedback_type'] === 'suggestion' ? 'lightbulb' : ($feedback['feedback_type'] === 'complaint' ? 'exclamation-triangle' : ($feedback['feedback_type'] === 'appreciation' ? 'heart' : 'bug')); ?>"></i>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                                        <div>
                                                            <div class="feedback-subject"><?php echo e($feedback['subject']); ?></div>
                                                            <div class="feedback-meta">
                                                                <?php echo e($user['name'] ?? 'You'); ?> &middot; <?php echo format_ph_date($feedback['created_at'], 'M j, Y'); ?>
                                                            </div>
                                                        </div>
                                                        <div class="text-end">
                                                            <span class="status-pill badge bg-<?php echo $feedback['status'] === 'resolved' ? 'success' : ($feedback['status'] === 'in_progress' ? 'warning' : 'secondary'); ?>">
                                                                <?php echo ucfirst($feedback['status']); ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <p class="mb-2 text-muted small"><?php echo e(substr($feedback['message'], 0, 120)) . (strlen($feedback['message']) > 120 ? '...' : ''); ?></p>

                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div class="stars">
                                                            <?php if ($feedback['rating']): ?>
                                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                    <?php if ($i <= $feedback['rating']): ?>
                                                                        <i class="fas fa-star"></i>
                                                                    <?php else: ?>
                                                                        <i class="far fa-star"></i>
                                                                    <?php endif; ?>
                                                                <?php endfor; ?>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php if ($feedback['response']): ?>
                                                            <div class="ms-2 p-2 bg-light rounded">
                                                                <small class="text-muted"><strong>Response:</strong> <?php echo e($feedback['response']); ?></small>
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
    <?php if (!empty($_GET['feedback'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function(){
            try {
                var fid = <?php echo (int)$_GET['feedback']; ?>;
                if (fid > 0) {
                    var el = document.getElementById('feedback-' + fid);
                    if (el) {
                        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        el.style.transition = 'box-shadow 0.3s, transform 0.3s';
                        el.style.boxShadow = '0 8px 30px rgba(2,6,23,0.12)';
                        el.style.transform = 'translateY(-2px)';
                        setTimeout(function(){ el.style.boxShadow = ''; el.style.transform = ''; }, 3000);
                    }
                }
            } catch (e) { console.error(e); }
        });
    </script>
    <?php endif; ?>
</body>
</html>

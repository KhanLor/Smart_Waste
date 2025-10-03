<?php
require_once '../../config/config.php';
require_login();
if (($_SESSION['role'] ?? '') !== 'collector') {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['update_profile'])) {
            $phone = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $stmt = $conn->prepare("UPDATE users SET phone = ?, address = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND role = 'collector'");
            $stmt->bind_param('ssi', $phone, $address, $user_id);
            if ($stmt->execute()) { $success = 'Profile updated.'; } else { throw new Exception('Failed to update profile'); }
        } elseif (isset($_POST['change_password'])) {
            $current = $_POST['current_password'] ?? '';
            $new = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';
            if (strlen($new) < 6 || $new !== $confirm) { throw new Exception('Password mismatch or too short'); }
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $hash = $stmt->get_result()->fetch_assoc()['password'] ?? '';
            if (!password_verify($current, $hash)) { throw new Exception('Current password is incorrect'); }
            $newHash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->bind_param('si', $newHash, $user_id);
            if ($stmt->execute()) { $success = 'Password changed.'; } else { throw new Exception('Failed to change password'); }
        }
    } catch (Exception $e) { $error = $e->getMessage(); }
}

$stmt = $conn->prepare("SELECT username, first_name, last_name, email, phone, address FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
</head>
<body class="role-collector">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-2 sidebar text-dark p-0">
                <div class="p-3">
                    <h4><?php echo APP_NAME; ?></h4>
                    <hr>
                    <nav class="nav flex-column">
                        <a class="nav-link text-dark" href="index.php"><i class="fas fa-truck me-2"></i>Dashboard</a>
                        <a class="nav-link text-dark" href="routes.php"><i class="fas fa-route me-2"></i>My Routes</a>
                        <a class="nav-link text-dark" href="collections.php"><i class="fas fa-tasks me-2"></i>Collections</a>
                        <a class="nav-link text-dark" href="map.php"><i class="fas fa-map me-2"></i>Map View</a>
                        <a class="nav-link text-dark active" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a>
                        <a class="nav-link text-dark" href="../../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
                    </nav>
                </div>
            </div>
            <div class="col-md-10">
                <div class="p-4">
                    <h2 class="mb-3">My Profile</h2>
                    <?php if ($success): ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>
                    <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-header">Contact Information</div>
                                <div class="card-body">
                                    <form method="POST">
                                        <input type="hidden" name="update_profile" value="1">
                                        <div class="mb-3"><label class="form-label">Name</label><input type="text" class="form-control" value="<?php echo e(($user['first_name']??'') . ' ' . ($user['last_name']??'')); ?>" disabled></div>
                                        <div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" value="<?php echo e($user['email'] ?? ''); ?>" disabled></div>
                                        <div class="mb-3"><label class="form-label">Phone</label><input type="tel" class="form-control" name="phone" value="<?php echo e($user['phone'] ?? ''); ?>"></div>
                                        <div class="mb-3"><label class="form-label">Address</label><textarea class="form-control" name="address" rows="3"><?php echo e($user['address'] ?? ''); ?></textarea></div>
                                        <button class="btn btn-primary" type="submit"><i class="fas fa-save me-1"></i>Save Changes</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-header">Change Password</div>
                                <div class="card-body">
                                    <form method="POST">
                                        <input type="hidden" name="change_password" value="1">
                                        <div class="mb-3"><label class="form-label">Current Password</label><input type="password" class="form-control" name="current_password" required></div>
                                        <div class="mb-3"><label class="form-label">New Password</label><input type="password" class="form-control" name="new_password" minlength="6" required></div>
                                        <div class="mb-3"><label class="form-label">Confirm New Password</label><input type="password" class="form-control" name="confirm_password" minlength="6" required></div>
                                        <button class="btn btn-warning" type="submit"><i class="fas fa-key me-1"></i>Update Password</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>



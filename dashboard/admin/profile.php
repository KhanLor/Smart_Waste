<?php
require_once '../../config/config.php';
require_login();
if (($_SESSION['role'] ?? '') !== 'admin') {
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
            $stmt = $conn->prepare("UPDATE users SET phone = ?, address = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND role = 'admin'");
            $stmt->bind_param('ssi', $phone, $address, $user_id);
            if ($stmt->execute()) { $success = 'Profile updated successfully.'; } else { throw new Exception('Failed to update profile'); }
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
            if ($stmt->execute()) { $success = 'Password changed successfully.'; } else { throw new Exception('Failed to change password'); }
        }
    } catch (Exception $e) { $error = $e->getMessage(); }
}

$stmt = $conn->prepare("SELECT username, first_name, last_name, email, phone, address, profile_image FROM users WHERE id = ?");
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
    <style>
        .profile-header {
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: white;
            flex-shrink: 0;
            border: 4px solid rgba(255,255,255,0.3);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            overflow: hidden;
            position: relative;
        }
        .profile-avatar .btn-light {
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
            border: 2px solid white;
        }
        .profile-avatar .btn-light:hover {
            background: #0d6efd !important;
            border-color: #0d6efd !important;
            color: white;
            transform: scale(1.05);
            transition: all 0.2s ease;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
        }
    </style>
</head>
<body class="role-admin">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 col-lg-2 sidebar text-white p-0">
                <?php include __DIR__ . '/_sidebar.php'; ?>
            </div>
            <div class="col-md-9 col-lg-10">
                <div class="p-4">
                    <h2 class="mb-4">My Profile</h2>
                    <?php if ($success): ?><div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?php echo e($success); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
                    <?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-triangle me-2"></i><?php echo e($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
                    
                    <!-- Profile Header -->
                    <div class="profile-header">
                        <div class="d-flex align-items-center">
                            <div class="profile-avatar me-4" id="profileAvatarContainer">
                                <?php if (!empty($user['profile_image']) && file_exists(__DIR__ . '/../../' . $user['profile_image'])): ?>
                                    <img src="<?php echo BASE_URL . $user['profile_image']; ?>" alt="Profile" class="w-100 h-100 rounded-circle" style="object-fit: cover;" id="profileImage">
                                <?php else: ?>
                                    <i class="fas fa-user-shield" id="profileIcon"></i>
                                <?php endif; ?>
                                <button type="button" class="btn btn-sm btn-light position-absolute bottom-0 end-0 rounded-circle" style="width: 35px; height: 35px; padding: 0;" title="Change Photo" onclick="document.getElementById('profileImageInput').click()">
                                    <i class="fas fa-camera"></i>
                                </button>
                                <input type="file" id="profileImageInput" accept="image/*" style="display: none;" onchange="uploadProfileImage(this)">
                            </div>
                            <div>
                                <h3 class="mb-1"><?php echo e(($user['first_name']??'') . ' ' . ($user['last_name']??'')); ?></h3>
                                <p class="mb-1"><i class="fas fa-envelope me-2"></i><?php echo e($user['email'] ?? ''); ?></p>
                                <span class="badge bg-light text-primary"><i class="fas fa-shield-alt me-1"></i>Administrator</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-header bg-primary text-white"><strong><i class="fas fa-user-edit me-2"></i>Contact Information</strong></div>
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
                                <div class="card-header bg-primary text-white"><strong><i class="fas fa-lock me-2"></i>Change Password</strong></div>
                                <div class="card-body">
                                    <form method="POST">
                                        <input type="hidden" name="change_password" value="1">
                                        <div class="mb-3"><label class="form-label">Current Password</label><input type="password" class="form-control" name="current_password" required></div>
                                        <div class="mb-3"><label class="form-label">New Password</label><input type="password" class="form-control" name="new_password" minlength="6" required></div>
                                        <div class="mb-3"><label class="form-label">Confirm New Password</label><input type="password" class="form-control" name="confirm_password" minlength="6" required></div>
                                        <button class="btn btn-danger" type="submit"><i class="fas fa-key me-1"></i>Update Password</button>
                                    </form>
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
        function uploadProfileImage(input) {
            if (!input.files || !input.files[0]) return;
            
            const file = input.files[0];
            const maxSize = 5 * 1024 * 1024;
            
            if (file.size > maxSize) {
                alert('File size must be less than 5MB');
                input.value = '';
                return;
            }
            
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                alert('Please select a valid image file (JPG, PNG, GIF, or WebP)');
                input.value = '';
                return;
            }
            
            const avatarContainer = document.getElementById('profileAvatarContainer');
            const originalContent = avatarContainer.innerHTML;
            avatarContainer.innerHTML = '<div class="spinner-border text-light" role="status"><span class="visually-hidden">Uploading...</span></div>';
            
            const formData = new FormData();
            formData.append('profile_image', file);
            
            fetch('<?php echo BASE_URL; ?>api/upload_profile_image.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    avatarContainer.innerHTML = originalContent;
                    const imgEl = document.getElementById('profileImage');
                    const iconEl = document.getElementById('profileIcon');
                    
                    if (imgEl) {
                        imgEl.src = data.image_url + '?' + new Date().getTime();
                    } else if (iconEl) {
                        const newImg = document.createElement('img');
                        newImg.src = data.image_url + '?' + new Date().getTime();
                        newImg.alt = 'Profile';
                        newImg.className = 'w-100 h-100 rounded-circle';
                        newImg.style.objectFit = 'cover';
                        newImg.id = 'profileImage';
                        iconEl.parentNode.replaceChild(newImg, iconEl);
                    }
                    showAlert('success', data.message);
                } else {
                    avatarContainer.innerHTML = originalContent;
                    showAlert('danger', data.message);
                }
                input.value = '';
            })
            .catch(error => {
                avatarContainer.innerHTML = originalContent;
                showAlert('danger', 'An error occurred while uploading the image');
                input.value = '';
                console.error('Upload error:', error);
            });
        }
        
        function showAlert(type, message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            const container = document.querySelector('.p-4');
            const firstChild = container.firstElementChild.nextElementSibling;
            container.insertBefore(alertDiv, firstChild);
            setTimeout(() => alertDiv.remove(), 5000);
        }
    </script>
</body>
</html>

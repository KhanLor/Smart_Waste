<?php
require_once '../../config/config.php';
require_login();

// Check if user is an admin
if (($_SESSION['role'] ?? '') !== 'admin') {
	header('Location: ' . BASE_URL . 'login.php');
	exit;
}

// Ensure required tables exist
$required_tables = ['user_preferences', 'system_settings', 'backup_logs'];
foreach ($required_tables as $table) {
	$result = $conn->query("SHOW TABLES LIKE '{$table}'");
	if ($result->num_rows == 0) {
		// Redirect to setup page if tables don't exist
		header('Location: ' . BASE_URL . 'ensure_settings_tables.php');
		exit;
	}
}

$user_id = $_SESSION['user_id'] ?? null;
$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
	if ($_POST['action'] === 'update_profile') {
		$first_name = trim($_POST['first_name'] ?? '');
		$last_name = trim($_POST['last_name'] ?? '');
		$email = trim($_POST['email'] ?? '');
		$phone = trim($_POST['phone'] ?? '');
		$address = trim($_POST['address'] ?? '');

		if (!empty($first_name) && !empty($last_name) && !empty($email)) {
			try {
				$stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
				$stmt->bind_param("sssssi", $first_name, $last_name, $email, $phone, $address, $user_id);
				if ($stmt->execute()) { $success_message = 'Profile updated successfully.'; } else { throw new Exception('Failed to update profile.'); }
			} catch (Exception $e) { $error_message = 'Error updating profile: ' . $e->getMessage(); }
		} else { $error_message = 'Please fill in all required fields.'; }
	} elseif ($_POST['action'] === 'change_password') {
		$current_password = $_POST['current_password'] ?? '';
		$new_password = $_POST['new_password'] ?? '';
		$confirm_password = $_POST['confirm_password'] ?? '';
		if (!empty($current_password) && !empty($new_password) && !empty($confirm_password)) {
			if ($new_password === $confirm_password) {
				try {
					$stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
					$stmt->bind_param("i", $user_id);
					$stmt->execute();
					$user = $stmt->get_result()->fetch_assoc();
					if (password_verify($current_password, $user['password'])) {
						$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
						$stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
						$stmt->bind_param("si", $hashed_password, $user_id);
						if ($stmt->execute()) { $success_message = 'Password changed successfully.'; } else { throw new Exception('Failed to change password.'); }
					} else { $error_message = 'Current password is incorrect.'; }
				} catch (Exception $e) { $error_message = 'Error changing password: ' . $e->getMessage(); }
			} else { $error_message = 'New passwords do not match.'; }
		} else { $error_message = 'Please fill in all password fields.'; }
	} elseif ($_POST['action'] === 'update_notifications') {
		$email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
		$sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
		$report_alerts = isset($_POST['report_alerts']) ? 1 : 0;
		$schedule_alerts = isset($_POST['schedule_alerts']) ? 1 : 0;
		$collection_alerts = isset($_POST['collection_alerts']) ? 1 : 0;
		try {
			$stmt = $conn->prepare("UPDATE users SET email_notifications = ?, sms_notifications = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
			$stmt->bind_param("iii", $email_notifications, $sms_notifications, $user_id);
			$stmt->execute();
			$stmt = $conn->prepare("INSERT INTO user_preferences (user_id, preference_key, preference_value, created_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE preference_value = VALUES(preference_value), updated_at = NOW()");
			if ($stmt) {
				$preferences = [ 'report_alerts' => $report_alerts, 'schedule_alerts' => $schedule_alerts, 'collection_alerts' => $collection_alerts ];
				foreach ($preferences as $key => $value) { $stmt->bind_param("iss", $user_id, $key, $value); $stmt->execute(); }
			}
			$success_message = 'Notification settings updated successfully.';
		} catch (Exception $e) { $error_message = 'Error updating notification settings: ' . $e->getMessage(); }
	} elseif ($_POST['action'] === 'update_system_settings') {
		$auto_assign_reports = isset($_POST['auto_assign_reports']) ? 1 : 0;
		$auto_notify_residents = isset($_POST['auto_notify_residents']) ? 1 : 0;
		$collection_reminder_hours = $_POST['collection_reminder_hours'] ?? 24;
		$report_auto_close_days = $_POST['report_auto_close_days'] ?? 7;
		$max_reports_per_resident = $_POST['max_reports_per_resident'] ?? 10;
		try {
			$stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, updated_by, updated_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = NOW()");
			if ($stmt) {
				$settings = [
					'auto_assign_reports' => $auto_assign_reports,
					'auto_notify_residents' => $auto_notify_residents,
					'collection_reminder_hours' => $collection_reminder_hours,
					'report_auto_close_days' => $report_auto_close_days,
					'max_reports_per_resident' => $max_reports_per_resident
				];
				foreach ($settings as $key => $value) { $stmt->bind_param("ssi", $key, $value, $user_id); $stmt->execute(); }
			}
			$success_message = 'System settings updated successfully.';
		} catch (Exception $e) { $error_message = 'Error updating system settings: ' . $e->getMessage(); }
	}
}

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Notification preferences
$notification_preferences = [ 'report_alerts' => 1, 'schedule_alerts' => 1, 'collection_alerts' => 1 ];
try {
	$stmt = $conn->prepare("SELECT preference_key, preference_value FROM user_preferences WHERE user_id = ?");
	if ($stmt) { $stmt->bind_param("i", $user_id); $stmt->execute(); $preferences = $stmt->get_result(); while ($pref = $preferences->fetch_assoc()) { $notification_preferences[$pref['preference_key']] = $pref['preference_value']; } }
} catch (Exception $e) {}

// System settings
$system_settings = [ 'auto_assign_reports' => 0, 'auto_notify_residents' => 1, 'collection_reminder_hours' => 24, 'report_auto_close_days' => 7, 'max_reports_per_resident' => 10 ];
try {
	$stmt = $conn->prepare("SELECT setting_key, setting_value FROM system_settings");
	if ($stmt) { $stmt->execute(); $settings = $stmt->get_result(); while ($setting = $settings->fetch_assoc()) { $system_settings[$setting['setting_key']] = $setting['setting_value']; } }
} catch (Exception $e) {}

// Stats
$reports_30_days = 0; $active_schedules = 0; $total_residents = 0;
try { $stmt = $conn->prepare("SELECT COUNT(*) as total FROM waste_reports WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"); if ($stmt) { $stmt->execute(); $reports_30_days = $stmt->get_result()->fetch_assoc()['total']; } } catch (Exception $e) {}
try { $stmt = $conn->prepare("SELECT COUNT(*) as total FROM collection_schedules WHERE status = 'active'"); if ($stmt) { $stmt->execute(); $active_schedules = $stmt->get_result()->fetch_assoc()['total']; } } catch (Exception $e) {}
try { $stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'resident'"); if ($stmt) { $stmt->execute(); $total_residents = $stmt->get_result()->fetch_assoc()['total']; } } catch (Exception $e) {}
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Settings - <?php echo APP_NAME; ?></title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
	<link rel="stylesheet" href="../../assets/css/dashboard.css">
	<style>
		.sidebar { min-height: 100vh; background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); }
		.card { border: none; border-radius: 15px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
		.nav-link { border-radius: 10px; margin: 2px 0; transition: all 0.3s; }
		.nav-link:hover, .nav-link.active { background-color: rgba(255,255,255,0.2); transform: translateX(5px); }
		.settings-nav { background: #f8f9fa; border-radius: 10px; padding: 20px; }
		.settings-nav .nav-link { color: #495057; border-radius: 8px; margin: 5px 0; padding: 12px 15px; }
		.settings-nav .nav-link.active { background: #007bff; color: white; }
		.settings-nav .nav-link:hover { background: #e9ecef; color: #495057; }
		.settings-nav .nav-link.active:hover { background: #0056b3; color: white; }
		.stat-card { background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white; }
		.form-switch { padding-left: 2.5em; }
		.form-check-input:checked { background-color: #28a745; border-color: #28a745; }
	</style>
</head>
<body>
	<div class="container-fluid">
		<div class="row">
			<div class="col-md-3 col-lg-2 sidebar text-white p-0"><div class="p-3"><h4 class="mb-4"><i class="fas fa-user-shield me-2"></i><?php echo APP_NAME; ?></h4><hr class="bg-white"><nav class="nav flex-column"><a class="nav-link text-white" href="index.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a><a class="nav-link text-white" href="reports.php"><i class="fas fa-exclamation-triangle me-2"></i>Waste Reports</a><a class="nav-link text-white" href="schedules.php"><i class="fas fa-calendar me-2"></i>Collection Schedules</a><a class="nav-link text-white" href="collectors.php"><i class="fas fa-users me-2"></i>Collectors</a><a class="nav-link text-white" href="residents.php"><i class="fas fa-home me-2"></i>Residents</a><a class="nav-link text-white" href="analytics.php"><i class="fas fa-chart-line me-2"></i>Analytics</a><a class="nav-link text-white active" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a><hr class="bg-white"><a class="nav-link text-white" href="../../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></nav></div></div>
			<div class="col-md-9 col-lg-10"><div class="p-4">
				<div class="d-flex justify-content-between align-items-center mb-4"><div><h2 class="mb-1">Settings</h2><p class="text-muted mb-0">Manage your account and system preferences</p></div><div class="text-end"><div class="h4 text-danger mb-0"><?php echo $reports_30_days; ?></div><small class="text-muted">Reports (30 days)</small></div></div>
				<?php if ($success_message): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><i class="fas fa-check-circle me-2"></i><?php echo e($success_message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
				<?php if ($error_message): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><i class="fas fa-exclamation-triangle me-2"></i><?php echo e($error_message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

				<div class="row mb-4"><div class="col-md-4 mb-3"><div class="card stat-card"><div class="card-body text-center"><i class="fas fa-users fa-2x mb-2"></i><h4 class="mb-1"><?php echo $total_residents; ?></h4><small>Total Residents</small></div></div></div><div class="col-md-4 mb-3"><div class="card stat-card"><div class="card-body text-center"><i class="fas fa-calendar fa-2x mb-2"></i><h4 class="mb-1"><?php echo $active_schedules; ?></h4><small>Active Schedules</small></div></div></div><div class="col-md-4 mb-3"><div class="card stat-card"><div class="card-body text-center"><i class="fas fa-exclamation-triangle fa-2x mb-2"></i><h4 class="mb-1"><?php echo $reports_30_days; ?></h4><small>Reports (30 days)</small></div></div></div></div>

				<div class="row"><div class="col-md-3 mb-4"><div class="settings-nav"><h6 class="mb-3"><i class="fas fa-cog me-2"></i>Settings</h6><nav class="nav flex-column"><a class="nav-link active" href="#profile" data-bs-toggle="tab"><i class="fas fa-user me-2"></i>Profile</a><a class="nav-link" href="#password" data-bs-toggle="tab"><i class="fas fa-lock me-2"></i>Password</a><a class="nav-link" href="#notifications" data-bs-toggle="tab"><i class="fas fa-bell me-2"></i>Notifications</a><a class="nav-link" href="#system" data-bs-toggle="tab"><i class="fas fa-cogs me-2"></i>System Settings</a><a class="nav-link" href="#backup" data-bs-toggle="tab"><i class="fas fa-database me-2"></i>Backup & Export</a></nav></div></div><div class="col-md-9"><div class="tab-content">
					<div class="tab-pane fade show active" id="profile"><div class="card"><div class="card-header"><h5 class="mb-0"><i class="fas fa-user me-2"></i>Profile Settings</h5></div><div class="card-body"><form method="POST"><input type="hidden" name="action" value="update_profile"><div class="row"><div class="col-md-6 mb-3"><label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label><input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo e($user['first_name']); ?>" required></div><div class="col-md-6 mb-3"><label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label><input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo e($user['last_name']); ?>" required></div></div><div class="row"><div class="col-md-6 mb-3"><label for="email" class="form-label">Email <span class="text-danger">*</span></label><input type="email" class="form-control" id="email" name="email" value="<?php echo e($user['email']); ?>" required></div><div class="col-md-6 mb-3"><label for="phone" class="form-label">Phone</label><input type="tel" class="form-control" id="phone" name="phone" value="<?php echo e($user['phone']); ?>"></div></div><div class="mb-3"><label for="address" class="form-label">Address</label><textarea class="form-control" id="address" name="address" rows="3"><?php echo e($user['address']); ?></textarea></div><button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Update Profile</button></form></div></div></div>
					<div class="tab-pane fade" id="password"><div class="card"><div class="card-header"><h5 class="mb-0"><i class="fas fa-lock me-2"></i>Change Password</h5></div><div class="card-body"><form method="POST"><input type="hidden" name="action" value="change_password"><div class="mb-3"><label for="current_password" class="form-label">Current Password <span class="text-danger">*</span></label><input type="password" class="form-control" id="current_password" name="current_password" required></div><div class="mb-3"><label for="new_password" class="form-label">New Password <span class="text-danger">*</span></label><input type="password" class="form-control" id="new_password" name="new_password" required><div class="form-text">Password must be at least 8 characters long.</div></div><div class="mb-3"><label for="confirm_password" class="form-label">Confirm New Password <span class="text-danger">*</span></label><input type="password" class="form-control" id="confirm_password" name="confirm_password" required></div><button type="submit" class="btn btn-primary"><i class="fas fa-key me-2"></i>Change Password</button></form></div></div></div>
					<div class="tab-pane fade" id="notifications"><div class="card"><div class="card-header"><h5 class="mb-0"><i class="fas fa-bell me-2"></i>Notification Preferences</h5></div><div class="card-body"><form method="POST"><input type="hidden" name="action" value="update_notifications"><h6 class="mb-3">Notification Channels</h6><div class="mb-3"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" <?php echo $user['email_notifications'] ? 'checked' : ''; ?>><label class="form-check-label" for="email_notifications">Email Notifications</label></div></div><div class="mb-3"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="sms_notifications" name="sms_notifications" <?php echo $user['sms_notifications'] ? 'checked' : ''; ?>><label class="form-check-label" for="sms_notifications">SMS Notifications</label></div></div><hr><h6 class="mb-3">Alert Types</h6><div class="mb-3"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="report_alerts" name="report_alerts" <?php echo $notification_preferences['report_alerts'] ? 'checked' : ''; ?>><label class="form-check-label" for="report_alerts">New Report Alerts</label></div></div><div class="mb-3"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="schedule_alerts" name="schedule_alerts" <?php echo $notification_preferences['schedule_alerts'] ? 'checked' : ''; ?>><label class="form-check-label" for="schedule_alerts">Schedule Changes</label></div></div><div class="mb-3"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="collection_alerts" name="collection_alerts" <?php echo $notification_preferences['collection_alerts'] ? 'checked' : ''; ?>><label class="form-check-label" for="collection_alerts">Collection Reminders</label></div></div><button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save Notification Settings</button></form></div></div></div>
					<div class="tab-pane fade" id="system"><div class="card"><div class="card-header"><h5 class="mb-0"><i class="fas fa-cogs me-2"></i>System Configuration</h5></div><div class="card-body"><form method="POST"><input type="hidden" name="action" value="update_system_settings"><h6 class="mb-3">Automation Settings</h6><div class="mb-3"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="auto_assign_reports" name="auto_assign_reports" <?php echo $system_settings['auto_assign_reports'] ? 'checked' : ''; ?>><label class="form-check-label" for="auto_assign_reports">Auto-assign reports to available collectors</label></div></div><div class="mb-3"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="auto_notify_residents" name="auto_notify_residents" <?php echo $system_settings['auto_notify_residents'] ? 'checked' : ''; ?>><label class="form-check-label" for="auto_notify_residents">Auto-notify residents of status changes</label></div></div><hr><h6 class="mb-3">Timing Settings</h6><div class="row"><div class="col-md-6 mb-3"><label for="collection_reminder_hours" class="form-label">Collection Reminder (hours before)</label><input type="number" class="form-control" id="collection_reminder_hours" name="collection_reminder_hours" value="<?php echo $system_settings['collection_reminder_hours']; ?>" min="1" max="72"></div><div class="col-md-6 mb-3"><label for="report_auto_close_days" class="form-label">Auto-close completed reports (days)</label><input type="number" class="form-control" id="report_auto_close_days" name="report_auto_close_days" value="<?php echo $system_settings['report_auto_close_days']; ?>" min="1" max="30"></div></div><div class="mb-3"><label for="max_reports_per_resident" class="form-label">Maximum reports per resident (per month)</label><input type="number" class="form-control" id="max_reports_per_resident" name="max_reports_per_resident" value="<?php echo $system_settings['max_reports_per_resident']; ?>" min="1" max="50"></div><button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save System Settings</button></form></div></div></div>
					<div class="tab-pane fade" id="backup"><div class="card"><div class="card-header"><h5 class="mb-0"><i class="fas fa-database me-2"></i>Backup & Export</h5></div><div class="card-body"><div class="row"><div class="col-md-6 mb-4"><div class="card border-primary"><div class="card-body text-center"><i class="fas fa-download fa-3x text-primary mb-3"></i><h5>Export Data</h5><p class="text-muted">Download system data in various formats</p><div class="d-grid gap-2"><a href="export_reports.php" class="btn btn-outline-primary"><i class="fas fa-file-csv me-2"></i>Export Reports (CSV)</a><a href="export_schedules.php" class="btn btn-outline-primary"><i class="fas fa-file-excel me-2"></i>Export Schedules (Excel)</a><a href="export_users.php" class="btn btn-outline-primary"><i class="fas fa-file-csv me-2"></i>Export Users (CSV)</a></div></div></div></div><div class="col-md-6 mb-4"><div class="card border-success"><div class="card-body text-center"><i class="fas fa-database fa-3x text-success mb-3"></i><h5>Database Backup</h5><p class="text-muted">Create and manage database backups</p><div class="d-grid gap-2"><a href="backup_database.php" class="btn btn-outline-success"><i class="fas fa-save me-2"></i>Create Backup</a></div></div></div></div></div><div class="alert alert-info"><i class="fas fa-info-circle me-2"></i><strong>Note:</strong> Database backups are automatically created daily at 2:00 AM. Manual backups are recommended before major system updates.</div></div></div></div>
				</div></div></div>
			</div>
		</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			const navLinks = document.querySelectorAll('.settings-nav .nav-link');
			const tabPanes = document.querySelectorAll('.tab-pane');
			navLinks.forEach(link => { link.addEventListener('click', function(e) { e.preventDefault(); navLinks.forEach(l => l.classList.remove('active')); tabPanes.forEach(p => p.classList.remove('show','active')); this.classList.add('active'); const target = this.getAttribute('href').substring(1); document.getElementById(target).classList.add('show','active'); }); });
		});
	</script>
</body>
</html>

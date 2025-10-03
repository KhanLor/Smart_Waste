<?php
require_once __DIR__ . '/config/config.php';

$step = 'request';
$message = '';
$is_success = false;

// Validate token if present
$rawToken = trim($_GET['token'] ?? '');
if ($rawToken !== '') {
	$step = 'reset';
	$tokenHash = hash('sha256', $rawToken);

	// Lookup token
	$stmt = $conn->prepare('SELECT pr.id AS reset_id, pr.expires_at, u.id AS user_id, u.email FROM password_resets pr JOIN users u ON u.id = pr.user_id WHERE pr.token_hash = ? LIMIT 1');
	$stmt->bind_param('s', $tokenHash);
	$stmt->execute();
	$res = $stmt->get_result();
	$resetRow = $res ? $res->fetch_assoc() : null;
	$stmt->close();

	if (!$resetRow) {
		$step = 'invalid';
		$message = 'This reset link is invalid.';
	} else {
		$now = new DateTimeImmutable('now');
		$expires = new DateTimeImmutable($resetRow['expires_at']);
		if ($now > $expires) {
			$step = 'invalid';
			$message = 'This reset link has expired.';
		} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			$newPassword = trim($_POST['password'] ?? '');
			$confirm = trim($_POST['confirm_password'] ?? '');
			if ($newPassword === '' || strlen($newPassword) < 8) {
				$message = 'Password must be at least 8 characters.';
			} else if ($newPassword !== $confirm) {
				$message = 'Passwords do not match.';
			} else {
				// Update user password (hash it)
				$hash = password_hash($newPassword, PASSWORD_DEFAULT);
				$upd = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
				$upd->bind_param('si', $hash, $resetRow['user_id']);
				$upd->execute();
				$upd->close();

				// Invalidate token
				$del = $conn->prepare('DELETE FROM password_resets WHERE id = ?');
				$del->bind_param('i', $resetRow['reset_id']);
				$del->execute();
				$del->close();

				$is_success = true;
				$step = 'done';
				$message = 'Your password has been reset. You can now log in.';
			}
		}
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Reset Password - <?php echo APP_NAME; ?></title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
	<style>
		body { background: #f1f5f9; }
		.card { border: 0; box-shadow: 0 10px 30px rgba(0,0,0,.06); }
		.btn-success { background: #22c55e; border-color: #22c55e; }
		.btn-success:hover { background: #16a34a; border-color: #16a34a; }
		.brand { color: #22c55e; font-weight: 700; text-decoration: none; }
	</style>
</head>
<body>
	<div class="container">
		<div class="row justify-content-center align-items-center" style="min-height: 100vh;">
			<div class="col-12 col-md-8 col-lg-6 col-xl-5">
				<div class="text-center mb-4">
					<a class="brand" href="<?php echo BASE_URL; ?>index.php"><i class="fa-solid fa-recycle"></i> <?php echo APP_NAME; ?></a>
					<h1 class="h4 mt-2">Reset Password</h1>
				</div>
				<div class="card p-4 p-md-5 rounded-4">
					<?php if ($message): ?>
						<div class="alert alert-<?php echo $is_success ? 'success' : 'warning'; ?>"><?php echo e($message); ?></div>
					<?php endif; ?>

					<?php if ($step === 'reset'): ?>
					<form method="post" novalidate>
						<div class="mb-3">
							<label class="form-label">New Password</label>
							<input type="password" name="password" class="form-control form-control-lg" placeholder="At least 8 characters" required>
						</div>
						<div class="mb-4">
							<label class="form-label">Confirm Password</label>
							<input type="password" name="confirm_password" class="form-control form-control-lg" required>
						</div>
						<button class="btn btn-success btn-lg w-100" type="submit">
							<i class="fa-solid fa-key me-2"></i> Update Password
						</button>
					</form>
					<?php elseif ($step === 'done'): ?>
						<div class="text-center">
							<a class="btn btn-success" href="<?php echo BASE_URL; ?>login.php">Proceed to Login</a>
						</div>
					<?php else: ?>
						<div class="text-center">
							<p class="text-muted">Invalid or expired reset link.</p>
							<a class="btn btn-outline-secondary" href="<?php echo BASE_URL; ?>forgot_password.php">Request a new link</a>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>



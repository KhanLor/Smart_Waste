<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/lib/mailer.php';

$message = '';
$is_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$email = trim($_POST['email'] ?? '');
	if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
		$message = 'Please enter a valid email address.';
	} else {
		$user = null;
		$stmt = $conn->prepare('SELECT id, first_name FROM users WHERE email = ? LIMIT 1');
		if ($stmt) {
			$stmt->bind_param('s', $email);
			$stmt->execute();
			$result = $stmt->get_result();
			$user = $result ? $result->fetch_assoc() : null;
			$stmt->close();
		}

		// Always respond the same to avoid user enumeration
		$is_success = true;
		$message = 'If this email exists, a reset link has been sent.';

		if ($user) {
			$rawToken = bin2hex(random_bytes(32));
			$tokenHash = hash('sha256', $rawToken);
			$expiresAt = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

			// Ensure password_resets table exists (in case schema wasn't applied)
			$conn->query("CREATE TABLE IF NOT EXISTS password_resets (
				id INT UNSIGNED NOT NULL AUTO_INCREMENT,
				user_id INT UNSIGNED NOT NULL,
				token_hash VARCHAR(255) NOT NULL,
				expires_at DATETIME NOT NULL,
				created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY idx_password_resets_user_id (user_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

			$ins = $conn->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)');
			if ($ins) {
				$ins->bind_param('iss', $user['id'], $tokenHash, $expiresAt);
				$ins->execute();
				$ins->close();
			}

			$resetUrl = APP_BASE_URL_ABS . 'reset_password.php?token=' . urlencode($rawToken);
			$firstName = $user['first_name'] ?: '';
			$subject = 'Reset your ' . APP_NAME . ' password';
			$html = '<p>Hi ' . e($firstName) . ',</p>' .
				'<p>You requested a password reset. Click the button below to reset your password. This link expires in 1 hour.</p>' .
				'<p><a href="' . e($resetUrl) . '" style="background:#22c55e;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none;display:inline-block">Reset Password</a></p>' .
				'<p>If the button does not work, copy and paste this link into your browser:<br>' . e($resetUrl) . '</p>' .
				'<p>If you did not request this, you can safely ignore this email.</p>' .
				'<p>— ' . e(APP_NAME) . '</p>';
			$text = "Hi $firstName,\n\nVisit this link to reset your password (expires in 1 hour):\n$resetUrl\n\nIf you didn't request this, ignore this email.";

			@send_email($email, $firstName, $subject, $html, $text);
		}
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Forgot Password - Smart Waste</title>
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
					<a class="brand" href="<?php echo BASE_URL; ?>index.php"><i class="fa-solid fa-recycle"></i> Smart Waste</a>
					<h1 class="h4 mt-2">Forgot Password</h1>
					<p class="text-muted">Enter your email to receive reset instructions</p>
				</div>
				<div class="card p-4 p-md-5 rounded-4">
					<?php if ($message): ?>
						<div class="alert alert-<?php echo $is_success ? 'success' : 'danger'; ?>"><?php echo e($message); ?></div>
					<?php endif; ?>
					<form method="post" novalidate>
						<div class="mb-4">
							<label class="form-label">Email</label>
							<input type="email" name="email" class="form-control form-control-lg" placeholder="you@gmail.com" value="<?php echo e($_POST['email'] ?? ''); ?>" required>
						</div>
						<button class="btn btn-success btn-lg w-100" type="submit">
							<i class="fa-solid fa-envelope me-2"></i> Send Reset Link
						</button>
					</form>
					<div class="text-center mt-3">
						<small class="text-muted"><a href="<?php echo BASE_URL; ?>login.php">Back to Login</a></small>
					</div>
				</div>
			</div>
		</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>



<?php
require_once __DIR__ . '/config/config.php';

$message = '';
$ok = false;

$rawToken = trim($_GET['token'] ?? '');
if ($rawToken !== '') {
	$tokenHash = hash('sha256', $rawToken);

	// Ensure table exists if schema wasn't applied
	$conn->query("CREATE TABLE IF NOT EXISTS email_verifications (
		id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id INT UNSIGNED NOT NULL,
		token_hash VARCHAR(255) NOT NULL,
		expires_at DATETIME NOT NULL,
		created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_email_verifications_user_id (user_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

	$stmt = $conn->prepare('SELECT ev.id AS ev_id, ev.expires_at, u.id AS user_id FROM email_verifications ev JOIN users u ON u.id = ev.user_id WHERE ev.token_hash = ? LIMIT 1');
	if ($stmt) {
		$stmt->bind_param('s', $tokenHash);
		$stmt->execute();
		$res = $stmt->get_result();
		$row = $res ? $res->fetch_assoc() : null;
		$stmt->close();
		if ($row) {
			$now = new DateTimeImmutable('now');
			$exp = new DateTimeImmutable($row['expires_at']);
			if ($now <= $exp) {
				// Mark email verified
				$nowStr = (new DateTime())->format('Y-m-d H:i:s');
				$upd = $conn->prepare('UPDATE users SET email_verified_at = ? WHERE id = ?');
				if ($upd) {
					$upd->bind_param('si', $nowStr, $row['user_id']);
					$upd->execute();
					$upd->close();
				}
				// Remove token
				$del = $conn->prepare('DELETE FROM email_verifications WHERE id = ?');
				if ($del) {
					$del->bind_param('i', $row['ev_id']);
					$del->execute();
					$del->close();
				}
				$ok = true;
				$message = 'Your email has been verified. You can now log in.';
			} else {
				$message = 'This verification link has expired.';
			}
		} else {
			$message = 'Invalid verification link.';
		}
	} else {
		$message = 'Invalid verification link.';
	}
} else {
	$message = 'Missing verification token.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Email Verification - <?php echo APP_NAME; ?></title>
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
					<h1 class="h4 mt-2">Email Verification</h1>
				</div>
				<div class="card p-4 p-md-5 rounded-4">
					<div class="alert alert-<?php echo $ok ? 'success' : 'warning'; ?>"><?php echo e($message); ?></div>
					<div class="text-center mt-2">
						<a class="btn btn-success" href="<?php echo BASE_URL; ?>login.php">Go to Login</a>
					</div>
				</div>
			</div>
		</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>



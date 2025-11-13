<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/lib/mailer.php';

function validate_registration_input(array $input): array {
	$errors = [];
	$first_name = trim($input['first_name'] ?? '');
	$middle_name = trim($input['middle_name'] ?? '');
	$last_name = trim($input['last_name'] ?? '');
	$username = trim($input['username'] ?? '');
	$email = trim($input['email'] ?? '');
	$phone = trim($input['phone'] ?? '');
	$password = $input['password'] ?? '';
	$confirm_password = $input['confirm_password'] ?? '';
	$address = trim($input['address'] ?? '');
	$accepted_terms = isset($input['terms']);

	if ($first_name === '') { $errors[] = 'First name is required.'; }
	if ($last_name === '') { $errors[] = 'Last name is required.'; }
	if ($username === '') { $errors[] = 'Username is required.'; }
	if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Valid email is required.'; }
	// Strong password rules: min 8, upper, lower, digit, special
	if (strlen($password) < 8) { $errors[] = 'Password must be at least 8 characters.'; }
	if (!preg_match('/[A-Z]/', $password)) { $errors[] = 'Password must include at least one uppercase letter.'; }
	if (!preg_match('/[a-z]/', $password)) { $errors[] = 'Password must include at least one lowercase letter.'; }
	if (!preg_match('/[0-9]/', $password)) { $errors[] = 'Password must include at least one number.'; }
	if (!preg_match('/[^A-Za-z0-9]/', $password)) { $errors[] = 'Password must include at least one special character.'; }
	if ($confirm_password === '' || $password !== $confirm_password) { $errors[] = 'Passwords do not match.'; }
	if ($address === '') { $errors[] = 'Address is required.'; }
	// Phone: required. Ensure no letters and accept either Philippine international (+63XXXXXXXXXX)
	// or an 11-digit local number (e.g. 09123456789). Normalize formatting for storage.
	if ($phone === '') { 
		$errors[] = 'Phone is required.'; 
	} else {
		// Reject any letters immediately
		if (preg_match('/[A-Za-z]/', $phone)) {
			$errors[] = 'Phone number must not contain letters.';
		} else {
			// Remove common formatting characters (spaces, hyphens, parentheses)
			$normalized = preg_replace('/[ \-()]/', '', $phone);
			// Accept +63 followed by 10 digits OR exactly 11 digits (local format)
			if (!preg_match('/^\+63\d{10}$/', $normalized) && !preg_match('/^\d{11}$/', $normalized)) {
				$errors[] = 'Phone must be a Philippine number (e.g., +639123456789) or 11 digits (e.g., 09123456789).';
			} else {
				// Store normalized phone (keep + if present)
				$phone = $normalized;
			}
		}
	}
	if (!$accepted_terms) { $errors[] = 'You must accept the Terms and Conditions.'; }

	return [$errors, $first_name, $middle_name, $last_name, $username, $email, $password, $address, $phone];
}

function email_exists(mysqli $conn, string $email): bool {
	$check = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
	$check->bind_param('s', $email);
	$check->execute();
	$check->store_result();
	$exists = $check->num_rows > 0;
	$check->close();
	return $exists;
}

function insert_user(
	mysqli $conn,
	string $first_name,
	string $middle_name,
	string $last_name,
	string $username,
	string $email,
	string $password,
	string $address,
	string $phone
): ?int {
	$hash = password_hash($password, PASSWORD_DEFAULT);
	$role = 'resident';
	$stmt = $conn->prepare('INSERT INTO users (username, first_name, middle_name, last_name, email, password, role, address, phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
	$stmt->bind_param('sssssssss', $username, $first_name, $middle_name, $last_name, $email, $hash, $role, $address, $phone);
	if ($stmt->execute()) {
		$insertId = $stmt->insert_id;
		$stmt->close();
		return $insertId;
	}
	$stmt->close();
	return null;
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	[$errors, $first_name, $middle_name, $last_name, $username, $email, $password, $address, $phone] = validate_registration_input($_POST);
	if (empty($errors)) {
		if (email_exists($conn, $email)) {
			$errors[] = 'Email is already registered.';
		} else {
			$userId = insert_user($conn, $first_name, $middle_name, $last_name, $username, $email, $password, $address, $phone);
			if ($userId) {
				// Create email verification token and send email
				$conn->query("CREATE TABLE IF NOT EXISTS email_verifications (
					id INT UNSIGNED NOT NULL AUTO_INCREMENT,
					user_id INT UNSIGNED NOT NULL,
					token_hash VARCHAR(255) NOT NULL,
					expires_at DATETIME NOT NULL,
					created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
					PRIMARY KEY (id),
					KEY idx_email_verifications_user_id (user_id)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

				$rawToken = bin2hex(random_bytes(32));
				$tokenHash = hash('sha256', $rawToken);
				$expiresAt = (new DateTime('+24 hours'))->format('Y-m-d H:i:s');
				$ins = $conn->prepare('INSERT INTO email_verifications (user_id, token_hash, expires_at) VALUES (?, ?, ?)');
				if ($ins) {
					$ins->bind_param('iss', $userId, $tokenHash, $expiresAt);
					$ins->execute();
					$ins->close();
				}

				$verifyUrl = APP_BASE_URL_ABS . 'verify_email.php?token=' . urlencode($rawToken);
				$subject = 'Verify your ' . APP_NAME . ' email';
				$html = '<p>Hi ' . e($first_name) . ',</p>' .
					'<p>Thanks for signing up! Please confirm your email to activate your account.</p>' .
					'<p><a href="' . e($verifyUrl) . '" style="background:#22c55e;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none;display:inline-block">Verify Email</a></p>' .
					'<p>If the button does not work, copy and paste this link into your browser:<br>' . e($verifyUrl) . '</p>' .
					'<p>— ' . e(APP_NAME) . '</p>';
				$text = "Hi $first_name,\n\nVerify your account by visiting:\n$verifyUrl";
				@send_email($email, $first_name, $subject, $html, $text);

				$success = 'Registration successful! Please check your email to verify your account.';
			} else {
				$errors[] = 'Registration failed. Please try again.';
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
	<title>Register - <?php echo APP_NAME; ?></title>
	<meta name="description" content="Smart Waste Management System - Create your account and join our eco-friendly waste management community.">
	<meta name="keywords" content="waste management, register, smart city, environmental, sustainability">
	
	<!-- Bootstrap CSS -->
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<!-- Font Awesome --> 
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
	<!-- Google Fonts  --> 
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
	
	<style>
		:root {
			--primary-color: #22c55e;
			--primary-dark: #16a34a;
			--secondary-color: #10b981;
			--accent-color: #06d6a0;
			--text-dark: #1f2937;
			--text-light: #6b7280;
			--bg-light: #f8fafc;
			--white: #ffffff;
			--shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
			--shadow-lg: 0 20px 40px rgba(0, 0, 0, 0.15);
		}

		* {
			margin: 0;
			padding: 0;
			box-sizing: border-box;
		}

		body {
			font-family: 'Inter', sans-serif;
			line-height: 1.6;
			color: var(--text-dark);
			overflow-x: hidden;
			background: var(--bg-light);
		}

		/* Navigation Styles */
		.navbar {
			background: rgba(255, 255, 255, 0.95) !important;
			backdrop-filter: blur(20px);
			border-bottom: 1px solid rgba(255, 255, 255, 0.1);
			transition: all 0.3s ease;
			padding: 1rem 0;
		}

		.navbar.scrolled {
			background: rgba(255, 255, 255, 0.98) !important;
			box-shadow: var(--shadow);
			padding: 0.5rem 0;
		}

		.navbar-brand {
			font-weight: 700;
			font-size: 1.5rem;
			color: var(--primary-color) !important;
			display: flex;
			align-items: center;
			gap: 0.5rem;
		}

		.navbar-brand i {
			font-size: 2rem;
			background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
			-webkit-background-clip: text;
			-webkit-text-fill-color: transparent;
			background-clip: text;
		}

		.nav-link {
			font-weight: 500;
			color: var(--text-dark) !important;
			transition: all 0.3s ease;
			position: relative;
			margin: 0 0.5rem;
		}

		.nav-link:hover {
			color: var(--primary-color) !important;
		}

		.nav-link::after {
			content: '';
			position: absolute;
			width: 0;
			height: 2px;
			bottom: -5px;
			left: 50%;
			background: var(--primary-color);
			transition: all 0.3s ease;
			transform: translateX(-50%);
		}

		.nav-link:hover::after {
			width: 100%;
		}

		.btn-nav {
			background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
			border: none;
			color: white !important;
			font-weight: 600;
			padding: 0.75rem 1.5rem;
			border-radius: 50px;
			transition: all 0.3s ease;
			text-decoration: none;
		}

		.btn-nav:hover {
			transform: translateY(-2px);
			box-shadow: 0 10px 20px rgba(34, 197, 94, 0.3);
			color: white !important;
		}

		/* Register Form Styles */
		.register-container {
			min-height: 100vh;
			/* small internal offset; actual top spacing is driven by JS setting body padding to navbar height */
			padding-top: 20px;
			padding-bottom: 40px;
		}

		.card {
			border: 0;
			box-shadow: var(--shadow-lg);
			border-radius: 14px;
			overflow: hidden;
			padding: 1.25rem !important;
		}

		.btn-success {
			background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
			border: none;
			font-weight: 600;
			padding: 0.65rem 1rem;
			border-radius: 12px;
			transition: all 0.18s ease;
			font-size: 1rem;
		}

		.btn-success:hover {
			transform: translateY(-2px);
			box-shadow: 0 10px 20px rgba(34, 197, 94, 0.3);
			background: linear-gradient(135deg, var(--primary-dark), var(--primary-color));
		}

		.form-control {
			border: 2px solid #e5e7eb;
			border-radius: 12px;
			padding: 0.7rem 0.9rem;
			transition: all 0.18s ease;
			font-size: 0.95rem;
		}

		.form-label {
			font-weight: 600;
			color: var(--text-dark);
			margin-bottom: 0.35rem;
			/* Ensure labels that wrap to two lines don't push inputs out of alignment */
			min-height: 2.4rem;
			font-size: 0.95rem;
		}

		/* Make the two-column form cells predictable: stack label and input vertically */
		.row.g-3 > .col-md-6 {
			display: flex;
			flex-direction: column;
		}

		/* Add breathing room below the two-column row so single-column fields (Email, Phone) don't crowd */
		.row.g-3 {
			margin-bottom: 1.25rem;
		}

		::placeholder {
			color: #9ca3af; /* slightly darker placeholder for readability */
			opacity: 1;
		}

		.form-control:focus {
			border-color: var(--primary-color);
			box-shadow: 0 0 0 0.2rem rgba(34, 197, 94, 0.25);
		}

		.brand {
			color: var(--primary-color);
			font-weight: 700;
			text-decoration: none;
			font-size: 1.25rem;
			display: inline-flex;
			align-items: center;
			gap: 0.5rem;
		}

		.brand i {
			font-size: 1.6rem;
			background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
			-webkit-background-clip: text;
			-webkit-text-fill-color: transparent;
			background-clip: text;
		}

		/* Row controlling vertical center - let Bootstrap centering do the work and avoid hardcoded offsets */
		.register-row { min-height: auto; }

		/* Mobile / small screens adjustments */
		@media (max-width: 576px) {
			:root { --shadow-lg: 0 8px 16px rgba(0,0,0,0.08); }
			.register-container { padding-top: 30px; padding-bottom: 20px; }
			.register-row { min-height: auto; }
			.card { padding: 1rem !important; border-radius: 12px; }
			.brand { font-size: 1.05rem; }
			.brand i { font-size: 1.4rem; }
			h1.h4 { font-size: 1rem; margin-top: 0.5rem; }
			.form-control { padding: 0.6rem 0.75rem; font-size: 0.95rem; }
			.form-label { min-height: 2rem; font-size: 0.9rem; }
			.btn-success { padding: 0.55rem 0.75rem; font-size: 0.95rem; border-radius: 10px; }
			.navbar { padding: 0.5rem 0; }
			.navbar-brand { font-size: 1.1rem; }
		}

		.form-check-input:checked {
			background-color: var(--primary-color);
			border-color: var(--primary-color);
		}
	</style>
</head>
<body>
	<!-- Navigation -->
	<nav class="navbar navbar-expand-lg fixed-top">
		<div class="container">
			<a class="navbar-brand" href="<?php echo BASE_URL; ?>index.php">
				<i class="fas fa-recycle"></i>
				<?php echo APP_NAME; ?>
			</a>
			<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
				<span class="navbar-toggler-icon"></span>
			</button>
			<div class="collapse navbar-collapse" id="navbarNav">
				<div class="navbar-nav ms-auto align-items-center">
					<a class="nav-link" href="<?php echo BASE_URL; ?>index.php#features">Features</a>
					<a class="nav-link" href="<?php echo BASE_URL; ?>index.php#contact">Contact</a>
<?php if (is_logged_in()): ?>
					<a class="nav-link" href="<?php echo BASE_URL; ?>conversations.php">Messages</a>
<?php else: ?>
					<a class="nav-link" href="<?php echo BASE_URL; ?>login.php">Login</a>
					<a class="btn-nav" href="<?php echo BASE_URL; ?>register.php">Get Started</a>
<?php endif; ?>
				</div>
			</div>
		</div>
	</nav>

	<!-- Register Form -->
	<div class="register-container">
		<div class="container">
			<div class="row justify-content-center align-items-center register-row">
				<div class="col-12 col-md-8 col-lg-6 col-xl-5">
					<div class="text-center mb-4">
						<h1 class="h4 mt-1">Create your account</h1>
						<p class="text-muted">Join our eco-friendly community</p>
					</div>
					<div class="card p-4 p-md-5">
					<?php if (!empty($errors)): ?>
						<div class="alert alert-danger">
							<ul class="mb-0">
								<?php foreach ($errors as $error): ?>
									<li><?php echo e($error); ?></li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>

					<form method="post" novalidate>
						<div class="row g-3">
							<div class="col-md-6">
								<label class="form-label">First Name</label>
								<input type="text" name="first_name" class="form-control form-control-lg" placeholder="Juan" value="<?php echo e($_POST['first_name'] ?? ''); ?>" required>
							</div>
							<div class="col-md-6">
								<label class="form-label">Last Name</label>
								<input type="text" name="last_name" class="form-control form-control-lg" placeholder="Dela Cruz" value="<?php echo e($_POST['last_name'] ?? ''); ?>" required>
							</div>
							<div class="col-md-6">
								<label class="form-label">Middle Name (optional)</label>
								<input type="text" name="middle_name" class="form-control form-control-lg" placeholder="Santos" value="<?php echo e($_POST['middle_name'] ?? ''); ?>">
							</div>
							<div class="col-md-6">
								<label class="form-label">Username</label>
								<input type="text" name="username" class="form-control form-control-lg" placeholder="janedoe" value="<?php echo e($_POST['username'] ?? ''); ?>" required>
							</div>
						</div>
						<div class="mb-3">
							<label class="form-label">Email</label>
							<input type="email" name="email" class="form-control form-control-lg" placeholder="you@gmail.com" value="<?php echo e($_POST['email'] ?? ''); ?>" required>
						</div>
						<div class="mb-3">
							<label class="form-label">Phone</label>
							<input type="tel" name="phone" inputmode="tel" pattern="(\+63\d{10}|\d{11})" class="form-control form-control-lg" placeholder="+63 912 345 6789" value="<?php echo e($_POST['phone'] ?? ''); ?>" required oninput="sanitizePhone(this)">
							<div class="form-text text-muted">Accepts Philippine numbers only: <strong>+63XXXXXXXXXX</strong> or exactly <strong>11 digits</strong> (e.g. 09123456789). Letters are not allowed.</div>
						</div>
						<div class="mb-3">
							<label class="form-label">Password</label>
							<div class="input-group input-group-lg">
								<input type="password" name="password" id="password" class="form-control" placeholder="At least 8 chars, incl. upper, lower, number & symbol" required aria-describedby="pwHelp">
								<button type="button" class="btn btn-outline-secondary" onclick="togglePw('password', this)" aria-label="Toggle password visibility"><i class="fa-regular fa-eye"></i></button>
							</div>
							<small id="pwStrength" class="form-text text-muted" aria-live="polite"></small>
							<small id="pwHelp" class="form-text text-muted">Password must be at least 8 characters and include upper/lowercase letters, a number, and a symbol.</small>
						</div>
						<div class="mb-3">
							<label class="form-label">Confirm Password</label>
							<div class="input-group input-group-lg">
								<input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Re-enter password" required>
								<button type="button" class="btn btn-outline-secondary" onclick="togglePw('confirm_password', this)" aria-label="Toggle password visibility"><i class="fa-regular fa-eye"></i></button>
							</div>
						</div>
						<div class="mb-4">
							<label class="form-label">Address</label>
							<textarea name="address" class="form-control form-control-lg" rows="2" placeholder="Street, City" required><?php echo e($_POST['address'] ?? ''); ?></textarea>
						</div>
						<div class="form-check mb-4">
							<input class="form-check-input" type="checkbox" name="terms" id="terms" <?php echo isset($_POST['terms']) ? 'checked' : ''; ?> required>
							<label class="form-check-label" for="terms">
								I agree to the <a href="<?php echo BASE_URL; ?>terms-of-service.php" target="_blank">Terms</a> and <a href="<?php echo BASE_URL; ?>privacy-policy.php" target="_blank">Privacy Policy</a>.
							</label>
						</div>
						<button class="btn btn-success btn-lg w-100" type="submit">
							<i class="fa-solid fa-user-plus me-2"></i> Create Account
						</button>
					</form>
					<div class="text-center mt-3">
						<small class="text-muted">Already have an account? <a href="<?php echo BASE_URL; ?>login.php">Login</a></small>
					</div>
				</div>
			</div>
		</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
	<script>
		// Ensure the page content is not hidden behind the fixed navbar.
		function adjustBodyForNavbarRegister() {
			const navbar = document.querySelector('.navbar');
			if (!navbar) return;
			// set body padding-top to navbar height so content starts below it
			document.body.style.paddingTop = navbar.offsetHeight + 'px';
			// also ensure the register-row has enough space to vertically center
			const registerRow = document.querySelector('.register-row');
			if (registerRow) {
				registerRow.style.minHeight = 'calc(100vh - ' + (navbar.offsetHeight + 40) + 'px)';
			}
		}
		window.addEventListener('load', adjustBodyForNavbarRegister);
		window.addEventListener('resize', adjustBodyForNavbarRegister);
		// Navbar scroll effect
		window.addEventListener('scroll', function() {
			const navbar = document.querySelector('.navbar');
			if (window.scrollY > 50) {
				navbar.classList.add('scrolled');
			} else {
				navbar.classList.remove('scrolled');
			}
		});

		function togglePw(id, btn) {
			const input = document.getElementById(id);
			const icon = btn.querySelector('i');
			if (input.type === 'password') { input.type = 'text'; icon.classList.replace('fa-eye', 'fa-eye-slash'); }
			else { input.type = 'password'; icon.classList.replace('fa-eye-slash', 'fa-eye'); }
		}

			const pw = document.getElementById('password');
			const meter = document.getElementById('pwStrength');
			if (pw && meter) {
				pw.addEventListener('input', () => {
					const v = pw.value;
					let score = 0;
					// Updated to match server-side rules (min 8)
					if (v.length >= 8) score++;
					if (/[A-Z]/.test(v)) score++;
					if (/[a-z]/.test(v)) score++;
					if (/[0-9]/.test(v)) score++;
					if (/[^A-Za-z0-9]/.test(v)) score++;
					const labels = ['Very weak','Weak','Fair','Good','Strong'];
					// Map score (0-5) to labels index 0-4
					const idx = Math.max(0, Math.min(4, score - 1));
					meter.textContent = v ? `Strength: ${labels[idx]}` : '';
				});
			}

		// Helper function for escaping HTML
		function e(str) {
			return str.replace(/[&<>"']/g, function(match) {
				const escape = {
					'&': '&amp;',
					'<': '&lt;',
					'>': '&gt;',
					'"': '&quot;',
					"'": '&#39;'
				};
				return escape[match];
			});
		}

		// Remove letters and disallowed characters from phone input as user types
		function sanitizePhone(el) {
			let v = el.value || '';
			// Remove letters
			v = v.replace(/[A-Za-z]/g, '');
			// Allow only +, digits, spaces, hyphens and parentheses
			v = v.replace(/[^+\d\s\-()]/g, '');
			// Trim leading/trailing spaces
			v = v.trim();
			el.value = v;
		}
	</script>
</body>
</html>



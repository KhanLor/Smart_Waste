<?php
require_once __DIR__ . '/config/config.php';

$error = '';
$rememberedEmail = $_COOKIE['remember_email'] ?? '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$identifier = trim($_POST['email'] ?? ''); // username or email
	$password = $_POST['password'] ?? '';
	$remember = isset($_POST['remember']);

	if ($identifier === '' || $password === '') {
		$error = 'Please enter your username/email and password.';
	} else {
		$selectedIncludesVerified = true;
		$stmt = $conn->prepare('SELECT id, username, password, role, email_verified_at FROM users WHERE email = ? OR username = ? LIMIT 1');
		if (!$stmt) {
			// Fallback if schema hasn't added email_verified_at yet
			$selectedIncludesVerified = false;
			$stmt = $conn->prepare('SELECT id, username, password, role FROM users WHERE email = ? OR username = ? LIMIT 1');
		}
		if ($stmt) {
			$stmt->bind_param('ss', $identifier, $identifier);
			$stmt->execute();
			$result = $stmt->get_result();
			$user = $result ? $result->fetch_assoc() : null;
			$stmt->close();
		} else {
			$user = null;
		}
		$authOk = false;
		if ($user) {
			// Try hashed verification first
			if (password_verify($password, $user['password'])) {
				$authOk = true;
			} else {
				// Support sample users seeded with plaintext passwords; upgrade to hashed on success
				if (hash_equals($user['password'], $password)) {
					$authOk = true;
					$newHash = password_hash($password, PASSWORD_DEFAULT);
					$upd = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
					$upd->bind_param('si', $newHash, $user['id']);
					$upd->execute();
					$upd->close();
				}
			}
		}
		$verified = $selectedIncludesVerified ? !empty($user['email_verified_at']) : true;
		if ($authOk && $verified) {
			$_SESSION['user_id'] = $user['id'];
			$_SESSION['username'] = $user['username'];
			$_SESSION['role'] = $user['role'] ?? 'resident';
			if ($remember) {
				setcookie('remember_email', $identifier, time() + (86400 * 30), '/');
			} else {
				setcookie('remember_email', '', time() - 3600, '/');
			}
			$role = strtolower($_SESSION['role']);
			if ($role === 'admin') {
				header('Location: ' . BASE_URL . 'dashboard/admin/index.php');
			} elseif ($role === 'authority') {
				header('Location: ' . BASE_URL . 'dashboard/authority/index.php');
			} elseif ($role === 'collector') {
				header('Location: ' . BASE_URL . 'dashboard/collector/index.php');
			} else {
				header('Location: ' . BASE_URL . 'dashboard/resident/index.php');
			}
			exit;
		} else {
			$error = $authOk && !$verified ? 'Please verify your email before logging in.' : 'Invalid credentials.';
		}
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Login - <?php echo APP_NAME; ?></title>
	<meta name="description" content="Smart Waste Management System - Login to access your dashboard and manage waste collection efficiently.">
	<meta name="keywords" content="waste management, login, smart city, environmental, sustainability">
	
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

		/* Login Form Styles */
		.login-container {
			min-height: 100vh;
			padding-top: 100px;
		}

		.card {
			border: 0;
			box-shadow: var(--shadow-lg);
			border-radius: 20px;
			overflow: hidden;
		}

		.btn-success {
			background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
			border: none;
			font-weight: 600;
			padding: 0.75rem 2rem;
			border-radius: 50px;
			transition: all 0.3s ease;
		}

		.btn-success:hover {
			transform: translateY(-2px);
			box-shadow: 0 10px 20px rgba(34, 197, 94, 0.3);
			background: linear-gradient(135deg, var(--primary-dark), var(--primary-color));
		}

		.form-control {
			border: 2px solid #e5e7eb;
			border-radius: 12px;
			padding: 0.75rem 1rem;
			transition: all 0.3s ease;
		}

		.form-control:focus {
			border-color: var(--primary-color);
			box-shadow: 0 0 0 0.2rem rgba(34, 197, 94, 0.25);
		}

		.brand {
			color: var(--primary-color);
			font-weight: 700;
			text-decoration: none;
			font-size: 1.5rem;
			display: inline-flex;
			align-items: center;
			gap: 0.5rem;
		}

		.brand i {
			font-size: 2rem;
			background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
			-webkit-background-clip: text;
			-webkit-text-fill-color: transparent;
			background-clip: text;
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

	<!-- Login Form -->
	<div class="login-container">
		<div class="container">
			<div class="row justify-content-center align-items-center" style="min-height: calc(100vh - 100px);">
				<div class="col-12 col-md-8 col-lg-6 col-xl-5">
					<div class="text-center mb-4">
						<a class="brand" href="<?php echo BASE_URL; ?>index.php">
							<i class="fas fa-recycle"></i>
							<?php echo APP_NAME; ?>
						</a>
						<h1 class="h4 mt-3">Welcome back</h1>
						<p class="text-muted">Login to continue</p>
					</div>
					<div class="card p-4 p-md-5">
						<?php if ($error): ?>
							<div class="alert alert-danger"><?php echo e($error); ?></div>
						<?php endif; ?>
						<form method="post" novalidate>
							<div class="mb-3">
								<label class="form-label">Username or Email</label>
								<input type="text" name="email" class="form-control form-control-lg" placeholder="username or you@gmail.com" value="<?php echo e($_POST['email'] ?? $rememberedEmail); ?>" required>
							</div>
							<div class="mb-4">
								<label class="form-label">Password</label>
								<div class="input-group input-group-lg">
									<input type="password" name="password" id="login_password" class="form-control" placeholder="Your password" required>
									<button type="button" class="btn btn-outline-secondary" onclick="togglePw('login_password', this)" aria-label="Toggle password visibility"><i class="fa-regular fa-eye"></i></button>
								</div>
								<div class="d-flex justify-content-between mt-2">
									<div class="form-check">
										<input class="form-check-input" type="checkbox" name="remember" id="remember" <?php echo isset($_POST['remember']) || $rememberedEmail ? 'checked' : ''; ?>>
										<label class="form-check-label" for="remember">Remember me</label>
									</div>
									<a href="<?php echo BASE_URL; ?>forgot_password.php" class="small">Forgot Password?</a>
								</div>
							</div>
							<button class="btn btn-success btn-lg w-100" type="submit">
								<i class="fa-solid fa-right-to-bracket me-2"></i> Login
							</button>
						</form>
						<div class="text-center mt-3">
							<small class="text-muted">No account? <a href="<?php echo BASE_URL; ?>register.php">Create one</a></small>
							<div class="mt-2">
								<small><a href="<?php echo BASE_URL; ?>index.php">Back to Home</a></small>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
	<script>
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
	</script>
</body>
</html>



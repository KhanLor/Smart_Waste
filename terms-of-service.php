<?php
require_once 'config/config.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Terms of Service - <?php echo APP_NAME; ?></title>
	<meta name="description" content="Terms of Service for Smart Waste Management System">
	
	<!-- Bootstrap CSS -->
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<!-- Font Awesome -->
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
	<!-- Google Fonts  -->
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

	<style>
		:root { --primary-color:#22c55e; --primary-dark:#16a34a; --secondary-color:#10b981; --text-dark:#1f2937; --text-light:#6b7280; --bg-light:#f8fafc; --white:#ffffff; --shadow:0 10px 25px rgba(0,0,0,.1); }
		body { font-family:'Inter',sans-serif; color:var(--text-dark); }
		.navbar { background: rgba(255,255,255,.95) !important; backdrop-filter: blur(20px); border-bottom: 1px solid rgba(255,255,255,.1); transition: all .3s ease; padding: 1rem 0; }
		.navbar.scrolled { background: rgba(255,255,255,.98) !important; box-shadow: var(--shadow); padding:.5rem 0; }
		.navbar-brand { font-weight:700; font-size:1.5rem; color:var(--primary-color) !important; display:flex; align-items:center; gap:.5rem; }
		.nav-link { font-weight:500; color:var(--text-dark) !important; margin:0 .5rem; position:relative; }
		.btn-nav { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); border:none; color:#fff !important; font-weight:600; padding:.75rem 1.5rem; border-radius:50px; text-decoration:none; }
		.page-header { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); color:#fff; padding: 6rem 0 4rem; margin-bottom: 0; }
		.card { border:0; box-shadow: var(--shadow); }
		.footer { background: var(--text-dark); color:#fff; padding: 3rem 0; }
		.footer-link { color: rgba(255,255,255,.75); text-decoration:none; }
		.footer-link:hover { color: var(--primary-color); }
	</style>
</head>
<body>
	<!-- Navigation -->
	<nav class="navbar navbar-expand-lg fixed-top">
		<div class="container">
			<a class="navbar-brand fw-bold" href="<?php echo BASE_URL; ?>index.php">
				<i class="fas fa-recycle me-2"></i>
				<?php echo APP_NAME; ?>
			</a>
			<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
				<span class="navbar-toggler-icon"></span>
			</button>
			<div class="collapse navbar-collapse" id="navbarNav">
				<div class="navbar-nav ms-auto align-items-center">
					<a class="nav-link" href="<?php echo BASE_URL; ?>index.php#features">Features</a>
					<a class="nav-link" href="<?php echo BASE_URL; ?>login.php">Login</a>
					<a class="btn-nav ms-2" href="<?php echo BASE_URL; ?>register.php">Get Started</a>
				</div>
			</div>
		</div>
	</nav>

	<!-- Page Header -->
	<section class="page-header mt-5">
		<div class="container">
			<h1 class="h2 mb-1">Terms of Service</h1>
			<p class="mb-0">Please read these terms carefully before using our service.</p>
		</div>
	</section>

	<!-- Terms of Service Content -->
	<div class="container py-5">
		<div class="row justify-content-center">
			<div class="col-lg-8">
				<div class="card shadow-sm border-0">
					<div class="card-header bg-success text-white">
						<h1 class="h3 mb-0">
							<i class="fas fa-file-contract me-2"></i>
							Terms of Service
						</h1>
					</div>
					<div class="card-body p-4">
						<p class="text-muted mb-4">
							<strong>Last updated:</strong> <?php echo get_ph_time('F d, Y'); ?>
						</p>

						<div class="alert alert-info">
							<i class="fas fa-info-circle me-2"></i>
							Please read these Terms of Service carefully before using our Smart Waste Management System.
						</div>

						<div class="terms-section mb-4">
							<h3 class="h5 text-success mb-3">1. Acceptance of Terms</h3>
							<p>By accessing and using the Smart Waste Management System ("Service"), you accept and agree to be bound by the terms and provision of this agreement. If you do not agree to abide by the above, please do not use this service.</p>
							<p>These Terms of Service ("Terms") govern your use of our website and mobile application operated by <?php echo APP_NAME; ?> ("us", "we", or "our").</p>
						</div>

						<div class="terms-section mb-4">
							<h3 class="h5 text-success mb-3">2. Description of Service</h3>
							<p>Our Smart Waste Management System provides:</p>
							<ul>
								<li>Waste collection scheduling and management</li>
								<li>Real-time tracking of waste collection vehicles</li>
								<li>Community communication platform</li>
								<li>Waste reporting and feedback system</li>
								<li>Analytics and environmental impact reporting</li>
								<li>Mobile and web-based access to all features</li>
							</ul>
						</div>

						<div class="terms-section mb-4">
							<h3 class="h5 text-success mb-3">3. User Accounts and Registration</h3>
							<h6 class="text-dark">Account Creation:</h6>
							<ul>
								<li>You must provide accurate and complete information when creating an account</li>
								<li>You are responsible for maintaining the confidentiality of your account credentials</li>
								<li>You must be at least 13 years old to create an account</li>
								<li>One person may not maintain more than one account</li>
							</ul>

							<h6 class="text-dark mt-3">Account Responsibilities:</h6>
							<ul>
								<li>You are responsible for all activities that occur under your account</li>
								<li>You must notify us immediately of any unauthorized use of your account</li>
								<li>You must keep your contact information up to date</li>
							</ul>
						</div>

						<div class="terms-section mb-4">
							<h3 class="h5 text-success mb-3">4. User Conduct and Prohibited Activities</h3>
							<p>You agree not to use the Service to:</p>
							
							<div class="row">
								<div class="col-md-6">
									<h6 class="text-dark">Prohibited Content:</h6>
									<ul>
										<li>Post false or misleading information</li>
										<li>Upload harmful or malicious content</li>
										<li>Share inappropriate or offensive material</li>
										<li>Violate intellectual property rights</li>
									</ul>
								</div>
								<div class="col-md-6">
									<h6 class="text-dark">Prohibited Actions:</h6>
									<ul>
										<li>Attempt to gain unauthorized access</li>
										<li>Interfere with system operations</li>
										<li>Use automated tools or bots</li>
										<li>Reverse engineer our software</li>
									</ul>
								</div>
							</div>

							<div class="alert alert-warning">
								<i class="fas fa-exclamation-triangle me-2"></i>
								Violation of these terms may result in account suspension or termination.
							</div>
						</div>

						<div class="terms-section mb-4">
							<h3 class="h5 text-success mb-3">5. Service Availability and Modifications</h3>
							<h6 class="text-dark">Service Availability:</h6>
							<ul>
								<li>We strive to maintain 99.9% uptime but cannot guarantee uninterrupted service</li>
								<li>Scheduled maintenance will be announced in advance when possible</li>
								<li>Emergency maintenance may occur without prior notice</li>
							</ul>

							<h6 class="text-dark mt-3">Service Modifications:</h6>
							<ul>
								<li>We reserve the right to modify or discontinue features</li>
								<li>Major changes will be communicated to users in advance</li>
								<li>We may add new features or services at any time</li>
							</ul>
						</div>

						<div class="terms-section mb-4">
							<h3 class="h5 text-success mb-3">6. Privacy and Data Protection</h3>
							<p>Your privacy is important to us. Our collection and use of personal information is governed by our <a href="<?php echo BASE_URL; ?>privacy-policy.php" class="text-success">Privacy Policy</a>, which is incorporated into these Terms by reference.</p>
							
							<h6 class="text-dark">Data Usage:</h6>
							<ul>
								<li>We collect data necessary to provide our services</li>
								<li>Location data is used for waste collection optimization</li>
								<li>Communication data helps improve our platform</li>
								<li>Analytics data helps us understand usage patterns</li>
							</ul>
						</div>

						<div class="terms-section mb-4">
							<h3 class="h5 text-success mb-3">7. Intellectual Property Rights</h3>
							<h6 class="text-dark">Our Rights:</h6>
							<ul>
								<li>All content, features, and functionality are owned by <?php echo APP_NAME; ?></li>
								<li>Our trademarks and logos are protected intellectual property</li>
								<li>The software and technology behind our service is proprietary</li>
							</ul>

							<h6 class="text-dark mt-3">Your Rights:</h6>
							<ul>
								<li>You retain ownership of content you submit to our platform</li>
								<li>You grant us a license to use your content to provide our services</li>
								<li>You can delete your content at any time</li>
							</ul>
						</div>

						<div class="terms-section mb-4">
							<h3 class="h5 text-success mb-3">8. Payment Terms and Billing</h3>
							<p>If you use paid features of our service:</p>
							<ul>
								<li>Fees are charged in advance on a subscription basis</li>
								<li>All fees are non-refundable unless otherwise stated</li>
								<li>We may change our pricing with 30 days notice</li>
								<li>Failed payments may result in service suspension</li>
								<li>You are responsible for all applicable taxes</li>
							</ul>

							<div class="alert alert-info">
								<i class="fas fa-credit-card me-2"></i>
								We use secure, PCI-compliant payment processing for all transactions.
							</div>
						</div>

						<div class="terms-section mb-4">
							<h3 class="h5 text-success mb-3">9. Limitation of Liability</h3>
							<p>To the maximum extent permitted by law:</p>
							<ul>
								<li>We provide our service "as is" without warranties of any kind</li>
								<li>We are not liable for any indirect, incidental, or consequential damages</li>
								<li>Our total liability is limited to the amount you paid for our services</li>
								<li>We are not responsible for third-party content or services</li>
								<li>Some jurisdictions do not allow these limitations</li>
							</ul>
						</div>

						<div class="terms-section mb-4">
							<h3 class="h5 text-success mb-3">10. Indemnification</h3>
							<p>You agree to indemnify and hold harmless <?php echo APP_NAME; ?> and its officers, directors, employees, and agents from any claims, damages, or expenses arising from:</p>
							<ul>
								<li>Your use of our service</li>
								<li>Your violation of these Terms</li>
								<li>Your violation of any rights of another party</li>
								<li>Content you submit to our platform</li>
							</ul>
						</div>

						<div class="terms-section mb-4">
							<h3 class="h5 text-success mb-3">11. Termination</h3>
							<h6 class="text-dark">Termination by You:</h6>
							<ul>
								<li>You may terminate your account at any time</li>
								<li>Termination can be done through your account settings</li>
								<li>Some data may be retained as required by law</li>
							</ul>

							<h6 class="text-dark mt-3">Termination by Us:</h6>
							<ul>
								<li>We may terminate accounts that violate these Terms</li>
								<li>We may suspend service for non-payment</li>
								<li>We will provide notice when possible</li>
								<li>Certain provisions of these Terms survive termination</li>
							</ul>
						</div>

						<div class="terms-section mb-4">
							<h3 class="h5 text-success mb-3">12. Governing Law and Dispute Resolution</h3>
							<p>These Terms are governed by the laws of [Your Jurisdiction]. Any disputes will be resolved through:</p>
							<ul>
								<li>Good faith negotiation as the first step</li>
								<li>Binding arbitration if negotiation fails</li>
								<li>Courts of [Your Jurisdiction] for certain matters</li>
							</ul>
						</div>

						<div class="terms-section mb-4">
							<h3 class="h5 text-success mb-3">13. Changes to Terms</h3>
							<p>We may update these Terms from time to time. When we do:</p>
							<ul>
								<li>We will post the updated Terms on our website</li>
								<li>We will notify users of material changes</li>
								<li>The "Last updated" date will be revised</li>
								<li>Continued use constitutes acceptance of new Terms</li>
							</ul>
						</div>

						<div class="terms-section">
							<h3 class="h5 text-success mb-3">14. Contact Information</h3>
							<p>If you have any questions about these Terms of Service, please contact us:</p>
							<div class="contact-info bg-light p-3 rounded">
								<p class="mb-2"><strong>Email:</strong> khanyaolor123@gmail.com</p>
								<p class="mb-2"><strong>Phone:</strong> +63 9486187359</p>
								<p class="mb-2"><strong>Address:</strong> Prk 24 Sampaguita St. Times Beach Davao City</p>
								<p class="mb-0"><strong>Business Hours:</strong> Monday - Friday, 9:00 AM - 5:00 PM EST</p>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Footer -->
	<footer class="footer">
		<div class="container">
			<div class="row align-items-center">
				<div class="col-md-6">
					<p class="mb-0">&copy; <?php echo get_ph_time('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
				</div>
				<div class="col-md-6 text-md-end">
					<a href="<?php echo BASE_URL; ?>privacy-policy.php" class="footer-link me-3">Privacy Policy</a>
					<a href="<?php echo BASE_URL; ?>terms-of-service.php" class="footer-link">Terms of Service</a>
				</div>
			</div>
		</div>
	</footer>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

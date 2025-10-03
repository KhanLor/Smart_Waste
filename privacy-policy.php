<?php
require_once 'config/config.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Privacy Policy - <?php echo APP_NAME; ?></title>
	<meta name="description" content="Privacy Policy for Smart Waste Management System">
	
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
			<h1 class="h2 mb-1">Privacy Policy</h1>
			<p class="mb-0">Learn how we collect, use, and protect your information.</p>
		</div>
	</section>

	<!-- Privacy Policy Content -->
	<div class="container py-5">
		<div class="row justify-content-center">
			<div class="col-lg-8">
				<div class="card shadow-sm border-0">
					<div class="card-header bg-success text-white">
						<h1 class="h3 mb-0">
							<i class="fas fa-shield-alt me-2"></i>
							Privacy Policy
						</h1>
					</div>
					<div class="card-body p-4">
						<p class="text-muted mb-4">
							<strong>Last updated:</strong> <?php echo get_ph_time('F d, Y'); ?>
						</p>
						<?php /* The detailed sections below are preserved from the original file */ ?>
						<div class="privacy-section mb-4">
							<h3 class="h5 text-success mb-3">1. Information We Collect</h3>
							<p>We collect information you provide directly to us, such as when you:</p>
							<ul>
								<li>Create an account or update your profile</li>
								<li>Submit waste collection reports or feedback</li>
								<li>Communicate with us through our chat system</li>
								<li>Contact our customer support</li>
							</ul>
							<h6 class="text-dark mt-3">Personal Information:</h6>
							<ul>
								<li>Name, email address, phone number</li>
								<li>Address and location data</li>
								<li>Profile photos and uploaded images</li>
								<li>Communication preferences</li>
							</ul>
							<h6 class="text-dark mt-3">Usage Information:</h6>
							<ul>
								<li>Device information and IP address</li>
								<li>Browser type and operating system</li>
								<li>Pages visited and time spent on our platform</li>
								<li>GPS location data (with your permission)</li>
							</ul>
						</div>
						<div class="privacy-section mb-4">
							<h3 class="h5 text-success mb-3">2. How We Use Your Information</h3>
							<p>We use the information we collect to:</p>
							<ul>
								<li>Provide, maintain, and improve our waste management services</li>
								<li>Process waste collection requests and schedule pickups</li>
								<li>Send you notifications about collection schedules and updates</li>
								<li>Respond to your comments, questions, and customer service requests</li>
								<li>Generate analytics and reports for service optimization</li>
								<li>Ensure platform security and prevent fraud</li>
								<li>Comply with legal obligations and enforce our terms</li>
							</ul>
						</div>
						<div class="privacy-section mb-4">
							<h3 class="h5 text-success mb-3">3. Information Sharing and Disclosure</h3>
							<p>We may share your information in the following circumstances:</p>
							<h6 class="text-dark mt-3">With Service Providers:</h6>
							<p>We share information with third-party vendors who perform services on our behalf, such as:</p>
							<ul>
								<li>Cloud hosting and data storage providers</li>
								<li>Email and communication service providers</li>
								<li>Analytics and performance monitoring services</li>
								<li>Payment processing services</li>
							</ul>
							<h6 class="text-dark mt-3">With Waste Collection Partners:</h6>
							<p>We share necessary information with authorized waste collection companies and municipal authorities to facilitate service delivery.</p>
							<h6 class="text-dark mt-3">For Legal Reasons:</h6>
							<p>We may disclose information if required by law or if we believe disclosure is necessary to:</p>
							<ul>
								<li>Comply with legal process or government requests</li>
								<li>Protect the rights, property, or safety of our users</li>
								<li>Investigate potential violations of our terms of service</li>
							</ul>
						</div>
						<div class="privacy-section mb-4">
							<h3 class="h5 text-success mb-3">4. Data Security</h3>
							<p>We implement appropriate technical and organizational measures to protect your personal information:</p>
							<ul>
								<li>Encryption of data in transit and at rest</li>
								<li>Regular security assessments and updates</li>
								<li>Access controls and authentication measures</li>
								<li>Employee training on data protection practices</li>
								<li>Incident response procedures</li>
							</ul>
							<div class="alert alert-info">
								<i class="fas fa-info-circle me-2"></i>
								While we strive to protect your information, no method of transmission over the internet is 100% secure. We cannot guarantee absolute security.
							</div>
						</div>
						<div class="privacy-section mb-4">
							<h3 class="h5 text-success mb-3">5. Your Rights and Choices</h3>
							<p>You have the following rights regarding your personal information:</p>
							<div class="row">
								<div class="col-md-6">
									<h6 class="text-dark">Access and Portability:</h6>
									<ul>
										<li>Request a copy of your data</li>
										<li>Export your information</li>
									</ul>
								</div>
								<div class="col-md-6">
									<h6 class="text-dark">Correction and Deletion:</h6>
									<ul>
										<li>Update incorrect information</li>
										<li>Request account deletion</li>
									</ul>
								</div>
							</div>
							<h6 class="text-dark mt-3">Communication Preferences:</h6>
							<p>You can opt out of promotional communications by:</p>
							<ul>
								<li>Clicking the unsubscribe link in emails</li>
								<li>Updating your notification preferences in your account settings</li>
								<li>Contacting our support team</li>
							</ul>
						</div>
						<div class="privacy-section mb-4">
							<h3 class="h5 text-success mb-3">6. Data Retention</h3>
							<p>We retain your information for as long as necessary to:</p>
							<ul>
								<li>Provide our services and maintain your account</li>
								<li>Comply with legal obligations</li>
								<li>Resolve disputes and enforce agreements</li>
								<li>Improve our services through analytics</li>
							</ul>
							<p>When you delete your account, we will delete or anonymize your personal information within 30 days, except where retention is required by law.</p>
						</div>
						<div class="privacy-section mb-4">
							<h3 class="h5 text-success mb-3">7. Children's Privacy</h3>
							<p>Our service is not intended for children under 13 years of age. We do not knowingly collect personal information from children under 13. If you are a parent or guardian and believe your child has provided us with personal information, please contact us.</p>
						</div>
						<div class="privacy-section mb-4">
							<h3 class="h5 text-success mb-3">8. International Data Transfers</h3>
							<p>Your information may be transferred to and processed in countries other than your own. We ensure appropriate safeguards are in place to protect your information in accordance with applicable data protection laws.</p>
						</div>
						<div class="privacy-section mb-4">
							<h3 class="h5 text-success mb-3">9. Changes to This Privacy Policy</h3>
							<p>We may update this Privacy Policy from time to time. We will notify you of any material changes by:</p>
							<ul>
								<li>Posting the updated policy on our website</li>
								<li>Sending you an email notification</li>
								<li>Displaying a prominent notice in our application</li>
							</ul>
							<p>Your continued use of our services after any changes indicates your acceptance of the updated Privacy Policy.</p>
						</div>
						<div class="privacy-section">
							<h3 class="h5 text-success mb-3">10. Contact Us</h3>
							<p>If you have any questions about this Privacy Policy or our data practices, please contact us:</p>
							<div class="contact-info bg-light p-3 rounded">
								<p class="mb-2"><strong>Email:</strong> khanyaolor123@gmail.com</p>
								<p class="mb-2"><strong>Phone:</strong> +63 9486187359</p>
								<p class="mb-2"><strong>Address:</strong> Prk 24 Sampaguita St. Times Beach Davao City</p>
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

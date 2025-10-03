<?php
require_once 'config/config.php';

// If user is already logged in, redirect to dashboard
if (is_logged_in()) {
    header('Location: ' . BASE_URL . 'dashboard/resident/index.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?></title>
    <meta name="description" content="Smart Waste Management System - Efficient waste collection, real-time tracking, and community engagement for a cleaner future.">
    <meta name="keywords" content="waste management, recycling, smart city, environmental, sustainability">
    
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

        /* Hero Section */
        .hero-section {
            background: linear-gradient(0deg, rgba(0,0,0,0.55), rgba(0,0,0,0.55)), url('<?php echo BASE_URL; ?>assets/collector.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><defs><radialGradient id="a" cx="50%" cy="50%"><stop offset="0%" stop-color="%23ffffff" stop-opacity="0.1"/><stop offset="100%" stop-color="%23ffffff" stop-opacity="0"/></radialGradient></defs><circle cx="200" cy="200" r="100" fill="url(%23a)"/><circle cx="800" cy="300" r="150" fill="url(%23a)"/><circle cx="400" cy="700" r="120" fill="url(%23a)"/></svg>');
            opacity: 0.5;
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .hero-badge {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 700;
            color: white;
            margin-bottom: 1.5rem;
            line-height: 1.2;
        }

        .hero-subtitle {
            font-size: 1.25rem;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 2.5rem;
            line-height: 1.6;
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn-hero {
            padding: 1rem 2rem;
            font-weight: 600;
            border-radius: 50px;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-hero-primary {
            background: white;
            color: var(--primary-color);
        }

        .btn-hero-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(255, 255, 255, 0.3);
            color: var(--primary-color);
        }

        .btn-hero-secondary {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(10px);
        }

        .btn-hero-secondary:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-3px);
            color: white;
        }

        .hero-image {
            position: relative;
            z-index: 2;
        }

        .hero-image img {
            max-width: 100%;
            height: auto;
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
        }

        /* Features Section */
        .features-section {
            padding: 6rem 0;
            background: var(--bg-light);
        }

        .section-badge {
            background: rgba(34, 197, 94, 0.1);
            color: var(--primary-color);
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .section-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 1rem;
        }

        .section-subtitle {
            font-size: 1.125rem;
            color: var(--text-light);
            margin-bottom: 3rem;
        }

        .feature-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            height: 100%;
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-lg);
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            font-size: 2rem;
            color: white;
        }

        .feature-icon.primary { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); }
        .feature-icon.blue { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
        .feature-icon.purple { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .feature-icon.orange { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .feature-icon.red { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .feature-icon.indigo { background: linear-gradient(135deg, #6366f1, #4f46e5); }

        .feature-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 1rem;
        }

        .feature-description {
            color: var(--text-light);
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .feature-tags {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .feature-tag {
            background: rgba(34, 197, 94, 0.1);
            color: var(--primary-color);
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        /* CTA Section */
        .cta-section {
            padding: 6rem 0;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            text-align: center;
        }

        .cta-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .cta-subtitle {
            font-size: 1.125rem;
            opacity: 0.9;
            margin-bottom: 2.5rem;
        }

        /* Footer */
        .footer {
            background: var(--text-dark);
            color: white;
            padding: 4rem 0 2rem;
        }

        .footer-brand {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .footer-brand i {
            color: var(--primary-color);
        }

        .footer-description {
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .footer-title {
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .footer-link {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: color 0.3s ease;
            display: block;
            margin-bottom: 0.5rem;
        }

        .footer-link:hover {
            color: var(--primary-color);
        }

        .social-links a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-radius: 50%;
            text-decoration: none;
            margin-right: 1rem;
            transition: all 0.3s ease;
        }

        .social-links a:hover {
            background: var(--primary-color);
            transform: translateY(-2px);
        }

        .newsletter-form {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .newsletter-input {
            flex: 1;
            padding: 0.75rem 1rem;
            border: none;
            border-radius: 50px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            backdrop-filter: blur(10px);
        }

        .newsletter-input::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        .newsletter-btn {
            background: var(--primary-color);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .newsletter-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .back-to-top {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 50px;
            height: 50px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .back-to-top:hover {
            background: var(--primary-dark);
            transform: translateY(-3px);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .hero-subtitle {
                font-size: 1.125rem;
            }
            
            .hero-buttons {
                justify-content: center;
            }
            
            .section-title {
                font-size: 2rem;
            }
            
            .cta-title {
                font-size: 2rem;
            }
            
            .newsletter-form {
                flex-direction: column;
            }
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in {
            animation: fadeInUp 0.8s ease-out;
        }

        .animate-fade-in-delay {
            animation: fadeInUp 0.8s ease-out 0.2s both;
        }

        .animate-fade-in-delay-2 {
            animation: fadeInUp 0.8s ease-out 0.4s both;
        }
    </style>
</head>
<body>
     Navigation 
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
                    <a class="nav-link" href="#features">Features</a>
                    <a class="nav-link" href="#contact">Contact</a>
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

     Hero Section 
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center min-vh-100">
                <div class="col-lg-6">
                    <div class="hero-content">
                        <div class="hero-badge animate-fade-in">
                            <i class="fas fa-leaf"></i>
                            Eco-Friendly Solution
                        </div>
                        <h1 class="hero-title animate-fade-in">
                            Smart Waste Management System
                        </h1>
                        <p class="hero-subtitle animate-fade-in-delay">
                            Efficient waste collection, real-time tracking, and community engagement 
                            for a cleaner, greener future. Join our community of users making a difference.
                        </p>
                        <div class="hero-buttons animate-fade-in-delay-2">
                            <a href="<?php echo BASE_URL; ?>register.php" class="btn-hero btn-hero-primary">
                                <i class="fas fa-rocket"></i>
                                Get Started Free
                            </a>
                            <a href="<?php echo BASE_URL; ?>login.php" class="btn-hero btn-hero-secondary">
                                <i class="fas fa-sign-in-alt"></i>
                                Login
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="hero-image animate-fade-in-delay">

                    </div>
                </div>
            </div>
        </div>
    </section>

<!-- Feature Section -->
    <section id="features" class="features-section">
        <div class="container">
            <div class="text-center mb-5">
                <div class="section-badge">
                    <i class="fas fa-star"></i>
                    Key Features
                </div>
                <h2 class="section-title">Comprehensive Waste Management Features</h2>
                <p class="section-subtitle">Everything you need for efficient waste management in one platform</p>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon primary">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <h5 class="feature-title">Smart Scheduling</h5>
                        <p class="feature-description">
                            Automated waste collection scheduling based on area, waste type, and optimal routes for maximum efficiency.
                        </p>
                        <div class="feature-tags">
                            <span class="feature-tag">AI-Powered</span>
                            <span class="feature-tag">Automated</span>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon blue">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <h5 class="feature-title">Real-time GPS Tracking</h5>
                        <p class="feature-description">
                            Track waste collection vehicles in real-time with GPS technology and get live updates on collection status.
                        </p>
                        <div class="feature-tags">
                            <span class="feature-tag">Live Updates</span>
                            <span class="feature-tag">GPS</span>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon purple">
                            <i class="fas fa-comments"></i>
                        </div>
                        <h5 class="feature-title">Community Communication</h5>
                        <p class="feature-description">
                            Direct communication hub between residents, authorities, and collectors for seamless coordination.
                        </p>
                        <div class="feature-tags">
                            <span class="feature-tag">Real-time Chat</span>
                            <span class="feature-tag">Notifications</span>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon orange">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <h5 class="feature-title">Analytics & Reports</h5>
                        <p class="feature-description">
                            Comprehensive analytics dashboard with detailed reports on collection efficiency and environmental impact.
                        </p>
                        <div class="feature-tags">
                            <span class="feature-tag">Data Insights</span>
                            <span class="feature-tag">Reports</span>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon red">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h5 class="feature-title">Issue Reporting</h5>
                        <p class="feature-description">
                            Easy-to-use reporting system for waste-related issues with photo uploads and location tracking.
                        </p>
                        <div class="feature-tags">
                            <span class="feature-tag">Photo Upload</span>
                            <span class="feature-tag">Location</span>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon indigo">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <h5 class="feature-title">Mobile Responsive</h5>
                        <p class="feature-description">
                            Fully responsive design that works perfectly on all devices - desktop, tablet, and mobile phones.
                        </p>
                        <div class="feature-tags">
                            <span class="feature-tag">Mobile First</span>
                            <span class="feature-tag">PWA Ready</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

<!-- Footer Section -->
    <footer id="contact" class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <div class="footer-brand">
                        <i class="fas fa-recycle"></i>
                        <?php echo APP_NAME; ?>
                    </div>
                    <p class="footer-description">
                        Making waste management smarter, more efficient, and environmentally friendly through innovative technology solutions.
                    </p>
                    <div class="social-links">
                        <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                        <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 class="footer-title">Quick Links</h6>
                    <a href="<?php echo BASE_URL; ?>index.php" class="footer-link">Home</a>
                    <a href="#features" class="footer-link">Features</a>
                    <a href="<?php echo BASE_URL; ?>login.php" class="footer-link">Login</a>
                </div>
                
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 class="footer-title">Services</h6>
                    <a href="#" class="footer-link">Waste Collection</a>
                    <a href="#" class="footer-link">Recycling</a>
                    <a href="#" class="footer-link">Analytics</a>
                    <a href="#" class="footer-link">Support</a>
                </div>
                
                <div class="col-lg-4 mb-4">
                    <h6 class="footer-title">Contact Info</h6>
                    <p class="footer-description">
                        <i class="fas fa-envelope me-2"></i>
                        khanyaolor123@gmail.com
                    </p>
                    <p class="footer-description">
                        <i class="fas fa-phone me-2"></i>
                        +63 9486187359
                    </p>
                    <p class="footer-description">
                        <i class="fas fa-map-marker-alt me-2"></i>
                        Prk 24 Sampaguita St. Times Beach Davao City
                    </p>
                    
                    <h6 class="footer-title mt-4">Newsletter</h6>
                    <div class="newsletter-form">
                        <input type="email" class="newsletter-input" placeholder="Your email">
                        <button class="newsletter-btn">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <hr style="border-color: rgba(255, 255, 255, 0.1); margin: 2rem 0;">
            
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0" style="color: rgba(255, 255, 255, 0.7);">
                        &copy; <?php echo get_ph_time('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                      <a href="privacy-policy.php" class="text-light text-decoration-none opacity-75 hover-opacity-100 me-3">Privacy Policy</a>
                        <a href="terms-of-service.php" class="text-light text-decoration-none opacity-75 hover-opacity-100">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>

<!-- Bact to top -->
    <button class="back-to-top" id="backToTop">
        <i class="fas fa-arrow-up"></i>
    </button>

<!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            const backToTop = document.getElementById('backToTop');
            
            if (window.scrollY > 100) {
                navbar.classList.add('scrolled');
                backToTop.style.display = 'flex';
            } else {
                navbar.classList.remove('scrolled');
                backToTop.style.display = 'none';
            }
        });

        // Smooth scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Back to top
        document.getElementById('backToTop').addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // Newsletter subscription
        document.querySelector('.newsletter-btn').addEventListener('click', function() {
            const email = document.querySelector('.newsletter-input').value;
            if (email) {
                alert('Thank you for subscribing to our newsletter!');
                document.querySelector('.newsletter-input').value = '';
            }
        });

        // Intersection Observer for animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe feature cards
        document.querySelectorAll('.feature-card').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px)';
            card.style.transition = 'all 0.6s ease';
            observer.observe(card);
        });
    </script>
</body>
</html>
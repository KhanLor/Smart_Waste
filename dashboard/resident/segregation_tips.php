<?php
require_once __DIR__ . '/../../config/config.php';
require_login();

if (($_SESSION['role'] ?? '') !== 'resident') {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$user = $_SESSION;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Waste Segregation Tips - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .tip-card { border-radius:12px; box-shadow:0 10px 20px rgba(18,38,63,0.06); }
        .tip-card .d-flex { gap: 18px; }
        /* make columns equal height and center icon vertically */
        .row.g-3 > .col-md-4 { display:flex; }
        .row.g-3 > .col-md-4 > .tip-card { display:flex; align-items:center; width:100%; padding:20px; }
        .tip-icon { width:54px;height:54px;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px; flex:0 0 54px }
        .tip-icon.org { background:linear-gradient(135deg,#28a745,#20c997); }
        .tip-icon.rec { background:linear-gradient(135deg,#0d6efd,#0069d9); }
        .tip-icon.haz { background:linear-gradient(135deg,#dc3545,#e67700); }
        @media (max-width:575.98px){ .lead { font-size:0.95rem } }
        .hero {
            position: relative;
            background-image: url('../../assets/collector.png');
            background-size: cover;
            background-position: center right;
            min-height: 120px;
            overflow: hidden;
        }
        .hero-overlay {
            position: absolute; inset: 0; background: linear-gradient(90deg, rgba(0,0,0,0.45) 0%, rgba(0,0,0,0.15) 60%);
        }
        .hero-content { position: relative; z-index: 2; }
        .text-white-50 { color: rgba(255,255,255,0.85) !important; }
        @media (max-width:575.98px){
            .hero { background-position: center; min-height: 90px; }
            .hero-content h3 { font-size: 1.15rem; }
            .row.g-3 > .col-md-4 { display:block; }
            .row.g-3 > .col-md-4 > .tip-card { display:block; padding:16px; }
            .tip-icon { width:48px;height:48px;flex:0 0 48px }
        }
    </style>
</head>
<body class="role-resident">
    <div class="container py-4">
        <div class="hero rounded mb-4">
            <div class="hero-overlay"></div>
            <div class="hero-content p-4">
                <h3 class="mb-1 text-white">Waste Segregation Tips</h3>
                <p class="mb-0 text-white-50">Easy rules to help you sort and reduce waste.</p>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-4 col-12">
                <div class="p-3 tip-card bg-white">
                    <div class="d-flex align-items-center">
                        <div class="tip-icon org me-3" aria-hidden="true">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2C8 6 6 10 6 14c0 4 2 6 6 6s6-2 6-6c0-4-2-8-6-12z" fill="currentColor" />
                            </svg>
                        </div>
                        <div>
                            <h5 class="mb-1">Organic</h5>
                            <p class="mb-1 lead text-muted">Food and garden waste — put in the green/organic bin or compost.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4 col-12">
                <div class="p-3 tip-card bg-white">
                    <div class="d-flex align-items-center">
                        <div class="tip-icon rec me-3" aria-hidden="true">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M21.6 7.2L20 8.8 16 4.8V9h-2V2h7v2h-4.1l5.7 5.2zM3 16.8L4.6 15.2 8.6 19.2V15H11v7H4v-2h4.1L2.4 14.8z" fill="currentColor" />
                            </svg>
                        </div>
                        <div>
                            <h5 class="mb-1">Recyclables</h5>
                            <p class="mb-1 lead text-muted">Paper, cans, glass, and plastics — rinse and place in the recycle bin.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4 col-12">
                <div class="p-3 tip-card bg-white">
                    <div class="d-flex align-items-center">
                        <div class="tip-icon haz me-3" aria-hidden="true">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2a2 2 0 100 4 2 2 0 000-4zm4.93 4.36a7 7 0 00-9.86 0l-1.06-1.06A9 9 0 0112 1a9 9 0 017.99 4.3l-1.06 1.06zM4.26 7.06L3.2 8.12A9 9 0 0012 23a9 9 0 008.8-14.88l-1.06-1.06A7 7 0 004.26 7.06z" fill="currentColor" />
                            </svg>
                        </div>
                        <div>
                            <h5 class="mb-1">Hazardous</h5>
                            <p class="mb-1 lead text-muted">Batteries, chemicals and electronics — keep separate and use special collection points.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card mt-2">
                    <div class="card-body">
                        <h6>Quick checklist</h6>
                        <ul>
                            <li>Keep liquids drained and containers empty before recycling.</li>
                            <li>Bag small sharp items (like broken glass) and label as hazardous.</li>
                            <li>Separate bulky items and contact waste services for bulky pickup.</li>
                        </ul>
                        <div class="text-end">
                            <a href="index.php" class="btn btn-success">Back to Dashboard</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

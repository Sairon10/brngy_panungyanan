<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/partials/header.php';
?>

<div class="container py-5 my-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5">
                <div class="text-center mb-5">
                    <img src="public/img/barangaylogo.png" alt="Logo" class="mb-3 rounded-circle shadow-sm" style="width: 80px; height: 80px;">
                    <h1 class="fw-bold text-dark">Terms of Service</h1>
                    <p class="text-muted">Last updated: <?php echo date('F j, Y'); ?></p>
                </div>

                <div class="content lh-lg text-dark">
                    <h4 class="fw-bold mb-3">1. Acceptance of Terms</h4>
                    <p class="mb-4">
                        By accessing and using the Barangay Panungyanan Digital Information System, you accept and agree to be bound by the terms and provision of this agreement. If you do not agree to abide by these terms, please do not use this service.
                    </p>

                    <h4 class="fw-bold mb-3">2. User Account Responsibilities</h4>
                    <p class="mb-4">
                        To access certain features of the platform, you must register for an account. You are responsible for maintaining the confidentiality of your account password and for all activities that occur under your account. You agree to provide accurate, current, and complete information during the registration process.
                    </p>

                    <h4 class="fw-bold mb-3">3. Use of Services</h4>
                    <p class="mb-4">
                        The services provided by this platform, including document requests and incident reporting, must be used for lawful purposes only. Any submission of false information, spam, or malicious reports is strictly prohibited and may result in the suspension of your account and legal action.
                    </p>

                    <h4 class="fw-bold mb-3">4. Modifications to Service</h4>
                    <p class="mb-4">
                        Barangay Panungyanan reserves the right to modify, suspend, or discontinue, temporarily or permanently, the system or any service to which it connects, with or without notice and without liability to you.
                    </p>
                </div>
                
                <div class="text-center mt-5">
                    <a href="index.php" class="btn btn-outline-secondary px-4 py-2 rounded-pill">
                        <i class="fas fa-arrow-left me-2"></i>Back to Home
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>

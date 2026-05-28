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
                    <h1 class="fw-bold text-dark">Privacy Policy</h1>
                    <p class="text-muted">Last updated: <?php echo date('F j, Y'); ?></p>
                </div>

                <div class="content lh-lg text-dark">
                    <h4 class="fw-bold mb-3">1. Information We Collect</h4>
                    <p class="mb-4">
                        When you register for an account on the Barangay Panungyanan Digital Information System, we collect personal information that you voluntarily provide to us. This includes your name, birthdate, address, contact numbers, and other demographic details necessary for barangay records.
                    </p>

                    <h4 class="fw-bold mb-3">2. How We Use Your Information</h4>
                    <p class="mb-4">
                        The information we collect is used solely for official barangay purposes, including but not limited to: verifying your residency, processing document requests (like clearances and certificates), addressing incident reports, and managing community profiling.
                    </p>

                    <h4 class="fw-bold mb-3">3. Data Security and Protection</h4>
                    <p class="mb-4">
                        We implement strict security measures to maintain the safety of your personal information. Your data is stored in secured databases accessible only by authorized barangay officials. We do not sell, trade, or otherwise transfer your personally identifiable information to outside parties without your consent, except as required by law.
                    </p>

                    <h4 class="fw-bold mb-3">4. Consent</h4>
                    <p class="mb-4">
                        By using our system and registering an account, you consent to our Privacy Policy and agree to the collection and use of your information as outlined above in accordance with the Data Privacy Act of 2012 (Republic Act No. 10173).
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

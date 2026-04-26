<?php 
$page_title = 'ID Card';
require_once __DIR__ . '/partials/user_dashboard_header.php'; 
?>
<?php if (!is_logged_in()) redirect('login.php'); ?>
<?php
$pdo = get_db_connection();
$allowed = false;
$check = $pdo->prepare("SELECT COUNT(*) AS c FROM document_requests WHERE user_id=? AND doc_type='Resident ID' AND status IN ('approved','released')");
$check->execute([$_SESSION['user_id']]);
$allowed = ((int) ($check->fetch()['c'] ?? 0)) > 0;

// Get comprehensive resident data
$stmt = $pdo->prepare('
    SELECT 
        u.full_name, u.first_name, u.last_name, u.middle_name, u.email,
        r.address, r.barangay_id, r.birthdate, r.sex, r.citizenship, r.civil_status,
        r.verification_status, r.avatar
    FROM users u 
    LEFT JOIN residents r ON r.user_id = u.id 
    WHERE u.id = ?
');
$stmt->execute([$_SESSION['user_id']]);
$resident = $stmt->fetch();

if (!$resident) {
    redirect('/index.php');
}

// Generate ID number
$uid = date('Y') . '-' . str_pad((string) $_SESSION['user_id'], 4, '0', STR_PAD_LEFT);

// Get base URL for QR code - link to resident info page
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . '://' . $host . dirname($_SERVER['PHP_SELF']);
$qr_verify_url = rtrim($base_url, '/') . '/qr_verify.php?id=' . $_SESSION['user_id'];

// Generate QR code URL (using a free QR code API)
$qr_code_url = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($qr_verify_url);

// Format birthdate
$birthdate_formatted = $resident['birthdate'] ? date('M d, Y', strtotime($resident['birthdate'])) : 'N/A';
$age = $resident['birthdate'] ? date_diff(date_create($resident['birthdate']), date_create('today'))->y : 'N/A';
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Libre+Baskerville:wght@700&family=Roboto:wght@400;500;700;900&display=swap');

.id-card-container {
    perspective: 1000px;
    width: 1000px;
    margin: 0 auto;
    height: 630px;
}

.id-card-inner {
    position: relative;
    width: 100%;
    height: 100%;
    transition: transform 0.8s;
    transform-style: preserve-3d;
    cursor: pointer;
}

.id-card-inner.flipped {
    transform: rotateY(180deg);
}

.id-card-front,
.id-card-back {
    position: absolute;
    width: 100%;
    height: 100%;
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
    border-radius: 25px;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
    overflow: hidden;
}

.id-card-back {
    transform: rotateY(180deg);
}

/* Front Card Styles */
.id-card-front {
    background: linear-gradient(135deg, #104020 0%, #1e5c30 100%);
    display: flex;
    flex-direction: column;
    color: white;
    border: 1px solid #0f3d1e;
}

/* Background Pattern */
.id-card-front::before {
    content: '';
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    width: 60%;
    height: 60%;
    background: url('public/img/barangaylogo.png') no-repeat center center;
    background-size: contain;
    opacity: 0.15;
    z-index: 0;
    filter: grayscale(100%);
}

.id-card-header {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 25px 40px;
    height: 140px;
}

.id-card-logo {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: white;
    object-fit: cover;
    border: 4px solid #fff;
    display: block;
    box-shadow: 0 3px 8px rgba(0,0,0,0.2);
}

.id-card-header-text {
    text-align: center;
    flex: 1;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
    padding: 0 20px;
}

.id-card-header-text h4 {
    margin: 0;
    font-family: 'Libre Baskerville', serif;
    font-size: 28px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 2px;
    line-height: 1.2;
    color: #fff;
}

.id-card-header-text p {
    margin: 5px 0 0;
    font-family: 'Roboto', sans-serif;
    font-size: 15px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* Yellow Bar */
.id-card-title-bar {
    position: relative;
    z-index: 1;
    background: linear-gradient(90deg, #ffcc00, #ffdb4d);
    padding: 12px 0;
    text-align: center;
    box-shadow: 0 4px 6px rgba(0,0,0,0.15);
    border-top: 1px solid #e6b800;
    border-bottom: 1px solid #e6b800;
}

.id-card-title-bar h2 {
    margin: 0;
    color: #fff;
    font-family: 'Roboto', sans-serif;
    font-size: 38px;
    font-weight: 900;
    text-transform: uppercase;
    text-shadow: 1px 1px 3px rgba(0,0,0,0.2);
    letter-spacing: 1px;
    -webkit-text-stroke: 0.5px rgba(0,0,0,0.1);
}

/* Body */
.id-card-body {
    position: relative;
    z-index: 1;
    flex: 1;
    background: rgba(255, 255, 255, 0.95);
    margin: 0;
    padding: 35px 50px;
    display: flex;
    align-items: flex-start;
    gap: 40px;
}

.id-photo-section {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex-shrink: 0;
    width: 200px;
}

.id-photo {
    width: 200px;
    height: 200px;
    border: 5px solid #fff;
    box-shadow: 0 0 0 1px #ccc;
    background: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    margin-bottom: 15px;
}

.id-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.id-photo-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    font-size: 70px;
    color: #ccc;
    background: #f0f0f0;
}

.id-validity {
    font-family: 'Roboto', sans-serif;
    font-size: 13px;
    font-weight: 800;
    color: #000;
    text-transform: uppercase;
    text-align: center;
    margin-top: 5px;
}

.id-details {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding-top: 10px;
}

.id-detail-row {
    display: flex;
    margin-bottom: 25px;
    align-items: flex-start;
}

.id-detail-group {
    margin-right: 40px;
}

.id-label {
    font-family: 'Roboto', sans-serif;
    font-size: 14px;
    font-weight: 700;
    color: #104020;
    text-transform: uppercase;
    margin-bottom: 5px;
    letter-spacing: 0.5px;
}

.id-value {
    font-family: 'Roboto', sans-serif;
    font-size: 22px;
    font-weight: 700;
    color: #000;
    line-height: 1.2;
}

.id-value.xl {
    font-size: 40px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: -0.5px;
}

.qr-box {
    position: absolute;
    bottom: 30px;
    right: 40px;
    width: 130px;
    height: 130px;
    border: 3px solid #333;
    padding: 3px;
    background: white;
}

.qr-box img {
    width: 100%;
    height: 100%;
}

.bg-curve {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 40%;
    background: linear-gradient(to top, #104020 0%, transparent 100%);
    opacity: 0.05;
    z-index: 0;
    pointer-events: none;
}

/* Back Card Styles */
.id-card-back {
    background: white;
    color: #333;
    display: flex;
    flex-direction: column;
    padding: 50px;
    border: 10px solid #104020;
}

.id-card-back-header {
    text-align: center;
    margin-bottom: 40px;
    color: #104020;
}

.id-card-back-header h4 {
    font-size: 28px;
    font-weight: 900;
    text-transform: uppercase;
    margin-bottom: 5px;
}

.id-card-back-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 40px;
    text-align: center;
}

.id-back-terms {
    font-size: 14px;
    line-height: 1.6;
    max-width: 80%;
    color: #555;
    font-weight: 500;
}

.flip-hint {
    position: absolute;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 13px;
    color: white;
    opacity: 0.8;
    background: rgba(0,0,0,0.3);
    padding: 5px 15px;
    border-radius: 20px;
    z-index: 10;
}

@media print {
    body * { visibility: hidden; }
    .id-card-container, .id-card-container * { visibility: visible; }
    .id-card-container {
        position: absolute;
        left: 0; top: 0;
        width: 100%;
        max-width: none;
        margin: 0; padding: 0;
    }
    .id-card-inner { transform: none !important; }
    .id-card-back { transform: rotateY(0deg); page-break-before: always; }
    .flip-hint, .btn, .alert, h2, p { display: none !important; }
    @page { size: landscape; margin: 0; }
    body { margin: 0; -webkit-print-color-adjust: exact; }
}
</style>

<?php if (!$allowed): ?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="alert alert-warning border-0 bg-amber-50 text-amber-700 rounded-4 shadow-sm">
                <div class="d-flex align-items-center gap-3">
                    <i class="fas fa-exclamation-triangle fa-2x"></i>
                    <div>
                        <h5 class="fw-bold mb-2">Resident ID Not Available</h5>
                        <p class="mb-0">Resident ID printing requires an approved or released request for "Resident ID".</p>
                        <a href="/requests.php" class="btn btn-warning btn-sm mt-3 rounded-pill">
                            <i class="fas fa-file-alt me-2"></i>Request Resident ID
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<div class="container py-5">
    <div class="row justify-content-center animate__animated animate__fadeInUp">
        <div class="col-lg-12 d-flex justify-content-center">
            <div class="id-card-container">
                <div class="id-card-inner" id="idCard">
                    <!-- Front of ID Card -->
                    <div class="id-card-front">
                        <!-- Header -->
                        <div class="id-card-header">
                            <img src="public/img/barangaylogo.png" class="id-card-logo" alt="Barangay Logo">
                            <div class="id-card-header-text">
                                <h4>Republic of the Philippines</h4>
                                <p>Barangay Panungyanan - City of General Trias</p>
                            </div>
                            <img src="public/img/gentri.jpg" class="id-card-logo" alt="City Logo">
                        </div>

                        <!-- Title Bar -->
                        <div class="id-card-title-bar">
                            <h2>Barangay Resident's Card</h2>
                        </div>

                        <!-- Body -->
                        <div class="id-card-body">
                            <!-- Photo Section -->
                            <div class="id-photo-section">
                                <div class="id-photo">
                                    <?php 
                                    $photo_path = null;
                                    if (!empty($resident['avatar']) && file_exists(__DIR__ . '/' . $resident['avatar'])) {
                                        $photo_path = $resident['avatar'];
                                    }
                                    
                                    if ($photo_path): ?>
                                        <img src="/<?php echo htmlspecialchars($photo_path); ?>" alt="Photo">
                                    <?php else: ?>
                                        <div class="id-photo-placeholder">
                                            <?php 
                                            $initials = strtoupper(substr($resident['first_name'] ?? '', 0, 1) . substr($resident['last_name'] ?? '', 0, 1));
                                            if (empty($initials)) $initials = 'ID';
                                            echo $initials;
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="id-validity">VALID UNTIL: DEC 2026</div>
                            </div>

                            <!-- Details Section -->
                            <div class="id-details">
                                <div class="id-detail-row">
                                    <div class="id-detail-group">
                                        <div class="id-label">Resident ID No:</div>
                                        <div class="id-value"><?php echo htmlspecialchars($uid); ?></div>
                                    </div>
                                    <div class="id-detail-group ms-auto me-5">
                                        <div class="id-label">Birth Date</div>
                                        <div class="id-value"><?php echo $resident['birthdate'] ? date('M. j, Y', strtotime($resident['birthdate'])) : 'N/A'; ?></div>
                                    </div>
                                </div>

                                <div class="id-detail-row" style="margin-bottom: 5px;">
                                    <div class="id-detail-group w-100">
                                        <div class="id-label">Name:</div>
                                        <div class="id-value xl">
                                            <?php 
                                            // Construct name
                                            $name = ($resident['first_name'] ?? '') . ' ' . ($resident['middle_name'] ? substr($resident['middle_name'],0,1).'. ' : '') . ($resident['last_name'] ?? '');
                                            echo htmlspecialchars($name);
                                            ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="id-detail-row">
                                    <div class="id-detail-group">
                                        <div class="id-label">Address:</div>
                                        <div class="id-value" style="font-size: 16px; max-width: 450px;">
                                            <?php echo htmlspecialchars($resident['address'] ?? 'N/A'); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- QR Code -->
                            <div class="qr-box">
                                <img src="<?php echo htmlspecialchars($qr_code_url); ?>" alt="QR">
                            </div>
                        </div>
                        
                        <div class="bg-curve"></div>
                        
                        <div class="flip-hint">
                            <i class="fas fa-hand-pointer me-1"></i> Click to flip
                        </div>
                    </div>
                    
                    <!-- Back of ID Card -->
                    <div class="id-card-back">
                        <div class="id-card-back-header">
                            <img src="public/img/barangaylogo.png" style="width: 70px; margin-bottom: 10px;">
                            <h4>Barangay Panungyanan</h4>
                        </div>
                        
                        <div class="id-card-back-body">
                            <p class="id-back-terms">
                                This card is non-transferable and must be presented upon request.
                                If found, please return to the Barangay Hall of Panungyanan, General Trias, Cavite.
                            </p>
                            
                            <div style="margin-top: 20px;">
                                <div class="id-label" style="text-align: center;">Emergency Contact</div>
                                <div class="id-value">046-123-4567</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Buttons Removed -->
        </div>
    </div>
</div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const idCard = document.getElementById('idCard');

            // Flip on click
            idCard.addEventListener('click', function () {
                this.classList.toggle('flipped');
            });

            // Prevent flip on QR code click
            const qrContainer = document.querySelector('.qr-code-container');
            if (qrContainer) {
                qrContainer.addEventListener('click', function (e) {
                    e.stopPropagation();
                });
            }
        });
    </script>
<?php endif; ?>

<?php require_once __DIR__ . '/partials/user_dashboard_footer.php'; ?>

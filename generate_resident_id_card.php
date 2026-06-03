<?php
require_once __DIR__ . '/config.php';

if (!is_logged_in() || !is_admin()) {
    die('Access denied. Only administrators can generate generic PDFs.');
}

$pdo = get_db_connection();
$request_id = (int)($_GET['id'] ?? 0);

if ($request_id <= 0) {
    die('Invalid request ID');
}

// Fetch the document request
$stmt = $pdo->prepare("SELECT * FROM document_requests WHERE id = ? AND doc_type = 'Resident ID'");
$stmt->execute([$request_id]);
$req = $stmt->fetch();

if (!$req) {
    die('Document request not found or not a Resident ID request.');
}

if ($req['status'] !== 'approved' && $req['status'] !== 'released') {
    die('Document request not yet approved.');
}

$resident = [];
$is_family_member = ($req['requestor_type'] === 'family_member' && !empty($req['family_member_id']));
$user_id = $req['user_id'];

// Get Head address and phone regardless (fallback)
$head_stmt = $pdo->prepare('SELECT r.address, r.phone FROM residents r WHERE user_id = ?');
$head_stmt->execute([$user_id]);
$head_data = $head_stmt->fetch();

if ($is_family_member) {
    $fm_stmt = $pdo->prepare('SELECT * FROM family_members WHERE id = ?');
    $fm_stmt->execute([$req['family_member_id']]);
    $resident = $fm_stmt->fetch();
    if (!$resident) die('Family member not found.');
    
    // Fallbacks
    $resident['address'] = !empty($resident['address']) ? $resident['address'] : ($head_data['address'] ?? '');
    $resident['phone'] = !empty($resident['phone']) ? $resident['phone'] : ($head_data['phone'] ?? '');
    $resident['birth_place'] = ''; // fm doesn't have birthplace usually
    
    // Construct name
    $resident['first_name'] = $resident['first_name'] ?? '';
    $resident['middle_name'] = $resident['middle_name'] ?? '';
    $resident['last_name'] = $resident['last_name'] ?? '';
    
    $uid_prefix = date('Y', strtotime($resident['created_at'] ?? 'now')) . '-F' . str_pad((string)$req['family_member_id'], 4, '0', STR_PAD_LEFT);
    $qr_id_param = 'fm_id=' . $req['family_member_id'];
} else {
    // Self
    $self_stmt = $pdo->prepare('
        SELECT u.first_name, u.last_name, u.middle_name, u.created_at,
               r.address, r.birthdate, r.avatar, r.phone, r.birth_place
        FROM users u 
        LEFT JOIN residents r ON r.user_id = u.id 
        WHERE u.id = ?
    ');
    $self_stmt->execute([$user_id]);
    $resident = $self_stmt->fetch();
    if (!$resident) die('Resident data not found.');
    
    $uid_prefix = date('Y', strtotime($resident['created_at'] ?? 'now')) . '-' . str_pad((string)$user_id, 4, '0', STR_PAD_LEFT);
    $qr_id_param = 'id=' . $user_id;
}

$uid = $uid_prefix;

// Get base URL for QR code
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . '://' . $host . dirname($_SERVER['PHP_SELF']);
$qr_verify_url = rtrim($base_url, '/') . '/qr_verify.php?' . $qr_id_param;

// Generate QR code URL
$qr_code_url = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($qr_verify_url);

$valid_until = date('F d, Y', strtotime($req['created_at'] . ' +1 year'));
$req_date = $req['created_at'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident ID Card</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    @media print {
        @page { size: landscape; margin: 0; }
        body { margin: 0; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        .no-print { display: none !important; }
    }
    body { background: #f0f2f5; display: flex; flex-direction: column; align-items: center; min-height: 100vh; padding-top: 40px; }
    .print-btn { padding: 12px 24px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; font-size: 16px; margin-bottom: 30px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    .print-btn:hover { background: #0056b3; }
@import url('https://fonts.googleapis.com/css2?family=Libre+Baskerville:wght@700&family=Roboto:wght@400;500;700;900&family=Alex+Brush&family=Libre+Barcode+39&display=swap');

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
    border-radius: 20px;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
    overflow: hidden;
    border: 1px solid #dddddd;
}

/* Front Side Layout */
.id-card-front {
    background: #ffffff;
    color: #333333;
    display: flex;
    flex-direction: row;
    position: absolute;
    font-family: 'Roboto', sans-serif;
}

/* Left Panel (Green Gradient & Waves) */
.left-panel {
    position: relative;
    width: 360px;
    height: 585px; /* Leave space for white footer strip at bottom */
    background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 60%, #4caf50 100%);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    z-index: 1;
}

/* Vector wave details */
.left-panel-wave-1 {
    position: absolute;
    top: -50px;
    right: -100px;
    width: 320px;
    height: 750px;
    background: #4caf50;
    border-radius: 40% 60% 40% 60% / 50% 30% 70% 50%;
    transform: rotate(-15deg);
    opacity: 0.4;
    z-index: 1;
}

.left-panel-wave-2 {
    position: absolute;
    top: -80px;
    right: -40px;
    width: 260px;
    height: 700px;
    background: #81c784;
    border-radius: 50% 40% 60% 50% / 40% 50% 50% 60%;
    transform: rotate(-25deg);
    opacity: 0.3;
    z-index: 2;
}

.left-panel-wave-3 {
    position: absolute;
    bottom: -150px;
    left: -100px;
    width: 300px;
    height: 500px;
    background: #1b5e20;
    border-radius: 50%;
    opacity: 0.3;
    z-index: 0;
}

/* Circular Photo Container */
.id-photo-container {
    position: relative;
    z-index: 10;
    width: 230px;
    height: 230px;
    border-radius: 50%;
    border: 8px solid #ffffff;
    outline: 8px solid #4caf50;
    box-shadow: 0 10px 25px rgba(0,0,0,0.25);
    overflow: hidden;
    background: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
}

.id-photo-container img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* Right Panel Layout */
.right-panel {
    flex: 1;
    height: 585px; /* Leave space for bottom footer */
    padding: 25px 40px;
    display: flex;
    flex-direction: column;
    position: relative;
    background: #ffffff;
    z-index: 2;
}

/* Very faint watermark background for security look */
.right-panel::before {
    content: '';
    position: absolute;
    top: 55%; left: 50%;
    transform: translate(-50%, -50%);
    width: 320px;
    height: 320px;
    background: url('public/img/barangaylogo.png') no-repeat center center;
    background-size: contain;
    opacity: 0.035;
    z-index: 0;
    pointer-events: none;
}

/* Header (Logos + Text) */
.id-header-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    margin-bottom: 15px;
    position: relative;
    z-index: 1;
}

.header-logo {
    object-fit: contain;
}

.logo-left {
    width: 65px;
    height: 65px;
    border-radius: 50%;
}

.logo-middle {
    width: 65px;
    height: 65px;
    border-radius: 50%;
}

.logo-right {
    width: 70px;
    height: 60px;
}

.header-text {
    flex: 1;
    text-align: center;
    padding: 0 10px;
}

.ph-gov {
    font-family: 'Roboto', sans-serif;
    font-size: 10px;
    font-weight: 800;
    color: #1b5e20;
    letter-spacing: 0.5px;
}

.region, .province, .city {
    font-family: 'Roboto', sans-serif;
    font-size: 9px;
    font-weight: 600;
    color: #555555;
    line-height: 1.2;
}

.brgy {
    font-family: 'Roboto', sans-serif;
    font-size: 16px;
    font-weight: 900;
    color: #1b5e20;
    letter-spacing: 0.5px;
    margin-top: 2px;
}

/* Resident Name */
.id-name {
    font-family: 'Roboto', sans-serif;
    font-size: 34px;
    font-weight: 900;
    color: #111111;
    margin-top: 20px;
    text-transform: uppercase;
    position: relative;
    z-index: 1;
}

/* Address Styling */
.id-address {
    font-family: 'Roboto', sans-serif;
    font-size: 14px;
    font-weight: 700;
    color: #555555;
    line-height: 1.4;
    margin-top: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    position: relative;
    z-index: 1;
    max-width: 480px;
}

/* Signature Styling */
.id-signature-container {
    margin-top: 20px;
    width: 250px;
    text-align: center;
    position: relative;
    z-index: 1;
}

.id-signature-text {
    font-family: 'Alex Brush', cursive;
    font-size: 36px;
    color: #000000;
    line-height: 1;
    margin-bottom: -3px;
}

.id-signature-line {
    width: 100%;
    height: 1.5px;
    background-color: #222222;
}

.id-signature-label {
    font-family: 'Roboto', sans-serif;
    font-size: 9px;
    font-weight: 800;
    color: #555555;
    letter-spacing: 1px;
    margin-top: 4px;
}

/* Bottom Details */
.id-bottom-section {
    margin-top: auto;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    position: relative;
    z-index: 1;
}

.id-no {
    font-family: 'Roboto', sans-serif;
    font-size: 15px;
    font-weight: 800;
    color: #111111;
}

.id-barcode {
    font-family: 'Libre Barcode 39', sans-serif;
    font-size: 48px;
    color: #2e7d32;
    margin: 0;
    line-height: 0.9;
}

.id-valid {
    font-family: 'Roboto', sans-serif;
    font-size: 9px;
    font-weight: 700;
    color: #666666;
    letter-spacing: 0.5px;
}

/* White footer strip along bottom */
.id-card-footer {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 45px;
    background: #ffffff;
    border-top: 1px solid #eeeeee;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Roboto', sans-serif;
    font-size: 16px;
    font-weight: 900;
    color: #222222;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    z-index: 5;
}

/* Back Card Styles */
.id-card-back {
    background: #ffffff;
    color: #333333;
    display: flex;
    flex-direction: row; /* Horizontal layout! Left is info, Right is green wave + chairman signature */
    position: absolute;
    transform: rotateY(180deg);
    font-family: 'Roboto', sans-serif;
    overflow: hidden;
    border: 1px solid #dddddd;
}

/* Watermark background on the left side of the back */
.back-left-panel {
    width: 650px;
    height: 100%;
    padding: 35px 50px;
    display: flex;
    flex-direction: column;
    position: relative;
    z-index: 2;
}

.back-left-panel::before {
    content: '';
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    width: 400px;
    height: 400px;
    background: url('public/img/barangaylogo.png') no-repeat center center;
    background-size: contain;
    opacity: 0.05;
    z-index: 0;
    pointer-events: none;
}

/* Right Panel of Back (Green Diagonal Sweep) */
.back-right-panel {
    position: relative;
    width: 350px;
    height: 100%;
    background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 60%, #4caf50 100%);
    z-index: 1;
    overflow: hidden;
}

/* Curve details on the back right panel */
.back-right-panel-wave-1 {
    position: absolute;
    top: -100px;
    left: -150px;
    width: 400px;
    height: 900px;
    background: #4caf50;
    border-radius: 40% 60% 40% 60% / 50% 30% 70% 50%;
    transform: rotate(20deg);
    opacity: 0.4;
    z-index: 1;
}

.back-right-panel-wave-2 {
    position: absolute;
    top: -50px;
    left: -100px;
    width: 300px;
    height: 800px;
    background: #81c784;
    border-radius: 50% 40% 60% 50% / 40% 50% 50% 60%;
    transform: rotate(15deg);
    opacity: 0.3;
    z-index: 2;
}

/* White Cutout on the Bottom-Right for Chairman Signature */
.back-chairman-container {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 340px;
    height: 190px;
    background: #ffffff;
    clip-path: polygon(25% 0%, 100% 0%, 100% 100%, 0% 100%);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding-left: 60px; /* Offset for clip path */
    padding-top: 15px;
    z-index: 10;
}

.back-info-section {
    text-align: left;
    font-size: 15.5px;
    line-height: 1.45;
    color: #333333;
    margin-bottom: 20px;
    position: relative;
    z-index: 2;
}

.back-info-row {
    margin-bottom: 3px;
}

.back-info-label {
    font-weight: 700;
    color: #111111;
}

.back-info-value {
    font-weight: 500;
    color: #333333;
}

.back-emergency-header {
    font-weight: 700;
    color: #555555;
    margin-top: 10px;
    font-size: 14.5px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.back-terms-section {
    text-align: left;
    margin-bottom: 20px;
    position: relative;
    z-index: 2;
}

.back-terms-title {
    font-family: 'Roboto', sans-serif;
    font-size: 15px;
    font-weight: 900;
    color: #111111;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    margin-bottom: 4px;
}

.back-terms-desc {
    font-size: 12.5px;
    line-height: 1.4;
    color: #444444;
    font-weight: 500;
}

.back-footer-section {
    text-align: left;
    margin-top: auto; /* Push to bottom of left panel */
    font-size: 12.5px;
    line-height: 1.4;
    color: #555555;
    font-weight: 600;
    position: relative;
    z-index: 2;
}

.chairman-sig-text {
    font-family: 'Alex Brush', cursive;
    font-size: 32px;
    color: #111111;
    line-height: 0.9;
    margin-bottom: 2px;
}

.chairman-name {
    font-size: 12px;
    font-weight: 900;
    color: #111111;
    border-top: 1.5px solid #222222;
    padding-top: 3px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: inline-block;
    width: 200px;
    text-align: center;
}

.chairman-title {
    font-size: 8.5px;
    font-weight: 800;
    color: #555555;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 1px;
    text-align: center;
}

.flip-hint {
    position: absolute;
    bottom: 12px;
    right: 20px;
    font-size: 12px;
    color: #ffffff;
    opacity: 0.8;
    background: rgba(0,0,0,0.5);
    padding: 4px 12px;
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
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()"><i class="fas fa-print me-2"></i>Print ID Card</button>
<div class="id-card-container">
                <div class="id-card-inner" id="idCard">
                    <!-- Front of ID Card -->
                    <div class="id-card-front">
                        <!-- Left Panel (Green Waves + Photo) -->
                        <div class="left-panel">
                            <div class="left-panel-wave-1"></div>
                            <div class="left-panel-wave-2"></div>
                            <div class="left-panel-wave-3"></div>
                            
                            <div class="id-photo-container">
                                <?php 
                                $photo_path = null;
                                if (!empty($resident['avatar']) && file_exists(__DIR__ . '/' . $resident['avatar'])) {
                                    $photo_path = $resident['avatar'];
                                }
                                
                                if ($photo_path): ?>
                                    <img src="/<?php echo htmlspecialchars($photo_path); ?>" alt="Photo">
                                <?php else: ?>
                                    <div class="id-photo-placeholder" style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; font-size: 70px; color: #ccc; background: #f0f0f0; font-family: 'Roboto', sans-serif;">
                                        <?php 
                                        $initials = strtoupper(substr($resident['first_name'] ?? '', 0, 1) . substr($resident['last_name'] ?? '', 0, 1));
                                        if (empty($initials)) $initials = 'ID';
                                        echo $initials;
                                        ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Right Panel (Info, Signature, Barcode) -->
                        <div class="right-panel">
                            <!-- Header (Logos + Text) -->
                            <div class="id-header-container">
                                <img src="public/img/barangaylogo.png" class="header-logo logo-left" alt="Barangay Logo">
                                <div class="header-text">
                                    <div class="ph-gov">REPUBLIC OF THE PHILIPPINES</div>
                                    <div class="region">Region IV-A CALABARZON</div>
                                    <div class="province">Province of Cavite</div>
                                    <div class="city">CITY OF GENERAL TRIAS</div>
                                    <div class="brgy">BARANGAY PANUNGYANAN</div>
                                </div>
                                <img src="public/img/gentri.jpg" class="header-logo logo-middle" alt="City Logo">
                                <img src="public/img/bagongpilipinas.jpg" class="header-logo logo-right" alt="Bagong Pilipinas Logo">
                            </div>
                            
                            <!-- Resident Name -->
                            <div class="id-name">
                                <?php 
                                $name = ($resident['first_name'] ?? '') . ' ' . ($resident['middle_name'] ? substr($resident['middle_name'],0,1).'. ' : '') . ($resident['last_name'] ?? '');
                                echo htmlspecialchars($name);
                                ?>
                            </div>
                            
                            <!-- Address -->
                            <div class="id-address">
                                <?php 
                                $address_raw = $resident['address'] ?? 'N/A';
                                echo nl2br(htmlspecialchars(strtoupper($address_raw)));
                                ?>
                            </div>
                            
                            <!-- Signature Area (Blank for physical signature) -->
                            <div class="id-signature-container" style="height: 55px; display: flex; flex-direction: column; justify-content: flex-end;">
                                <div class="id-signature-line"></div>
                                <div class="id-signature-label">CARDHOLDER'S SIGNATURE</div>
                            </div>
                            
                            <!-- Bottom Details (ID No. + Validity + QR Code) -->
                            <div class="id-bottom-section" style="width: 100%; display: flex; flex-direction: row; align-items: flex-end; justify-content: space-between;">
                                <div style="display: flex; flex-direction: column; align-items: flex-start;">
                                    <div class="id-no">ID. NO. <?php echo htmlspecialchars($uid); ?></div>
                                    <div class="id-valid" style="margin-top: 5px;">VALID UNTIL: <?php echo strtoupper($valid_until); ?></div>
                                </div>
                                <div class="front-qr-wrapper" style="width: 80px; height: 80px; border: 2px solid #2e7d32; padding: 2px; background: #ffffff; border-radius: 6px; position: relative; z-index: 10; margin-bottom: 5px;">
                                    <img src="<?php echo htmlspecialchars($qr_code_url); ?>" alt="QR" style="width: 100%; height: 100%; object-fit: contain;">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Footer Text along the bottom white border -->
                        <div class="id-card-footer">
                            BARANGAY PANUNGYANAN IDENTIFICATION CARD
                        </div>
                        
                        <div class="flip-hint">
                            <i class="fas fa-hand-pointer me-1"></i> Click to flip
                        </div>
                    </div>
                    
                    <!-- Back of ID Card -->
                    <div class="id-card-back">
                        <!-- Left Panel (Watermark + Resident Info + Emergency Contacts + Terms) -->
                        <div class="back-left-panel">
                            <!-- Personal Info Section -->
                            <div class="back-info-section">
                                <div class="back-info-row">
                                    <span class="back-info-label">Birth Date:</span> 
                                    <span class="back-info-value"><?php echo $resident['birthdate'] ? date('F j, Y', strtotime($resident['birthdate'])) : 'June 26, 1992'; ?></span>
                                </div>
                                <div class="back-info-row">
                                    <span class="back-info-label">Birth Place:</span> 
                                    <span class="back-info-value"><?php echo !empty($resident['birth_place']) ? htmlspecialchars(strtoupper($resident['birth_place'])) : 'MAKATI CITY'; ?></span>
                                </div>
                                <div class="back-info-row">
                                    <span class="back-info-label">Contact No.</span> 
                                    <span class="back-info-value"><?php echo !empty($resident['phone']) ? htmlspecialchars($resident['phone']) : '09663801837'; ?></span>
                                </div>
                            </div>
                            <!-- Terms & Conditions Section -->
                            <div class="back-terms-section">
                                <div class="back-terms-title">THIS CARD IS NON TRANSFERRABLE</div>
                                <div class="back-terms-desc">
                                    Holder is a bonafide constituent of this Barangay<br>
                                    and is entitled to all privileges and services holder<br>
                                    may require
                                </div>
                            </div>
                            
                            <!-- Footer Return Instructions Section -->
                            <div class="back-footer-section">
                                If found, please return to the Barangay Secretariate<br>
                                PANUNGYANAN Multi-Purpose Hall.
                            </div>
                        </div>
                        
                        <!-- Right Panel (Abstract Green Sweep + Chairman Signature Block) -->
                        <div class="back-right-panel">
                            <div class="back-right-panel-wave-1"></div>
                            <div class="back-right-panel-wave-2"></div>
                            
                            <!-- White Cutout for Chairman Signature -->
                            <div class="back-chairman-container">
                                <div style="height: 50px;"></div> <!-- Blank space for physical signature -->
                                <div class="chairman-name">HON. RENATO S. ALMANZOR</div>
                                <div class="chairman-title">BARANGAY CHAIRMAN</div>
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
            if(idCard) {
                idCard.addEventListener('click', function () {
                    this.classList.toggle('flipped');
                });
            }
        });
    </script>
</body>
</html>
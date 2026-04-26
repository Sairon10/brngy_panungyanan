<?php require_once __DIR__ . '/config.php'; ?>
<?php
$pdo = get_db_connection();
$resident = null;
$error = '';

// Get user_id or barangay_id from query parameter
$user_id = $_GET['id'] ?? null;
$barangay_id = $_GET['bid'] ?? null;

if ($user_id) {
    // Get resident by user_id
    $stmt = $pdo->prepare('
        SELECT 
            u.id, u.full_name, u.first_name, u.last_name, u.middle_name, u.email,
            r.address, r.barangay_id, r.birthdate, r.sex, r.citizenship, r.civil_status,
            r.phone, r.purok, r.avatar, r.verification_status
        FROM users u 
        LEFT JOIN residents r ON r.user_id = u.id 
        WHERE u.id = ?
    ');
    $stmt->execute([$user_id]);
    $resident = $stmt->fetch();
} elseif ($barangay_id) {
    // Get resident by barangay_id
    $stmt = $pdo->prepare('
        SELECT 
            u.id, u.full_name, u.first_name, u.last_name, u.middle_name, u.email,
            r.address, r.barangay_id, r.birthdate, r.sex, r.citizenship, r.civil_status,
            r.phone, r.purok, r.avatar, r.verification_status
        FROM users u 
        LEFT JOIN residents r ON r.user_id = u.id 
        WHERE r.barangay_id = ?
    ');
    $stmt->execute([$barangay_id]);
    $resident = $stmt->fetch();
}

if (!$resident) {
    $error = 'Resident information not found.';
}

// Generate formatted barangay ID
$uid = date('Y') . '-' . str_pad((string) ($resident['id'] ?? 0), 4, '0', STR_PAD_LEFT);

// Format birthdate
$birthdate_formatted = $resident['birthdate'] ? date('F j, Y', strtotime($resident['birthdate'])) : 'N/A';
$age = $resident['birthdate'] ? date_diff(date_create($resident['birthdate']), date_create('today'))->y : 'N/A';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Information Verification - Barangay Panungyanan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #104020 0%, #1e5c30 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .verification-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 800px;
            margin: 40px auto;
        }
        .card-header-custom {
            background: linear-gradient(135deg, #104020 0%, #1e5c30 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .card-header-custom h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
        }
        .card-header-custom p {
            margin: 10px 0 0;
            opacity: 0.9;
            font-size: 14px;
        }
        .card-body-custom {
            padding: 40px;
        }
        .info-section {
            margin-bottom: 30px;
        }
        .info-label {
            font-size: 12px;
            font-weight: 700;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        .info-value {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
        }
        .photo-section {
            text-align: center;
            margin-bottom: 30px;
        }
        .photo-circle {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            border: 5px solid #104020;
            margin: 0 auto 20px;
            overflow: hidden;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .photo-circle img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .photo-placeholder {
            font-size: 60px;
            color: #ccc;
        }
        .status-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-top: 10px;
        }
        .status-verified {
            background: #d4edda;
            color: #155724;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        .status-unverified {
            background: #f8d7da;
            color: #721c24;
        }
        .qr-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin-top: 30px;
        }
        .qr-info i {
            font-size: 48px;
            color: #104020;
            margin-bottom: 10px;
        }
        .error-message {
            text-align: center;
            padding: 60px 20px;
            color: #721c24;
        }
        .error-message i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <?php if ($error || !$resident): ?>
            <div class="verification-card">
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h2>Resident Not Found</h2>
                    <p><?php echo htmlspecialchars($error ?: 'The resident information you are looking for does not exist.'); ?></p>
                    <a href="/" class="btn btn-primary mt-3">Go to Homepage</a>
                </div>
            </div>
        <?php else: ?>
            <div class="verification-card animate__animated animate__fadeInUp">
                <div class="card-header-custom">
                    <h1><i class="fas fa-id-card me-2"></i>Resident Information</h1>
                    <p>Barangay Panungyanan - City of General Trias</p>
                </div>
                
                <div class="card-body-custom">
                    <!-- Photo Section -->
                    <div class="photo-section">
                        <div class="photo-circle">
                            <?php if (!empty($resident['avatar']) && file_exists(__DIR__ . '/' . $resident['avatar'])): ?>
                                <img src="/<?php echo htmlspecialchars($resident['avatar']); ?>" alt="Profile Photo">
                            <?php else: ?>
                                <div class="photo-placeholder">
                                    <?php 
                                    $initials = strtoupper(substr($resident['first_name'] ?? '', 0, 1) . substr($resident['last_name'] ?? '', 0, 1));
                                    if (empty($initials)) $initials = 'ID';
                                    echo $initials;
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php 
                        $status = $resident['verification_status'] ?? 'unverified';
                        $statusClass = 'status-' . $status;
                        $statusText = ucfirst($status);
                        ?>
                        <span class="status-badge <?php echo $statusClass; ?>">
                            <i class="fas fa-<?php echo $status === 'verified' ? 'check-circle' : ($status === 'pending' ? 'clock' : 'times-circle'); ?> me-1"></i>
                            <?php echo $statusText; ?>
                        </span>
                    </div>

                    <!-- Information Grid -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-section">
                                <div class="info-label">Resident ID Number</div>
                                <div class="info-value"><?php echo htmlspecialchars($uid); ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-section">
                                <div class="info-label">Full Name</div>
                                <div class="info-value">
                                    <?php 
                                    $name = ($resident['first_name'] ?? '') . ' ' . 
                                            ($resident['middle_name'] ? substr($resident['middle_name'], 0, 1) . '. ' : '') . 
                                            ($resident['last_name'] ?? '');
                                    echo htmlspecialchars(trim($name) ?: $resident['full_name'] ?? 'N/A');
                                    ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-section">
                                <div class="info-label">Birthdate</div>
                                <div class="info-value"><?php echo $birthdate_formatted; ?> (<?php echo $age; ?> years old)</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-section">
                                <div class="info-label">Sex</div>
                                <div class="info-value"><?php echo htmlspecialchars($resident['sex'] ?? 'N/A'); ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-section">
                                <div class="info-label">Citizenship</div>
                                <div class="info-value"><?php echo htmlspecialchars($resident['citizenship'] ?? 'N/A'); ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-section">
                                <div class="info-label">Civil Status</div>
                                <div class="info-value"><?php echo htmlspecialchars($resident['civil_status'] ?? 'N/A'); ?></div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="info-section">
                                <div class="info-label">Address</div>
                                <div class="info-value"><?php echo htmlspecialchars($resident['address'] ?? 'N/A'); ?></div>
                            </div>
                        </div>
                        <?php if (!empty($resident['purok'])): ?>
                        <div class="col-md-6">
                            <div class="info-section">
                                <div class="info-label">Purok</div>
                                <div class="info-value"><?php echo htmlspecialchars($resident['purok']); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($resident['phone'])): ?>
                        <div class="col-md-6">
                            <div class="info-section">
                                <div class="info-label">Phone</div>
                                <div class="info-value"><?php echo htmlspecialchars($resident['phone']); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- QR Code Info -->
                    <div class="qr-info">
                        <i class="fas fa-qrcode"></i>
                        <h5 class="mt-2 mb-2">Verified via QR Code</h5>
                        <p class="text-muted mb-0 small">This information was accessed by scanning the Resident ID QR code</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


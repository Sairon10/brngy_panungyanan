<?php require_once __DIR__ . '/config.php'; ?>
<?php
$info = '';
$infoType = 'info'; // 'info', 'success', 'danger'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        $info = 'Invalid session. Please reload and try again.';
        $infoType = 'danger';
    } else {
        $email = trim($_POST['email'] ?? '');
        if ($email) {
            try {
                $pdo = get_db_connection();
                // Get user with name if available
                $stmt = $pdo->prepare('SELECT id, full_name, first_name, email FROM users WHERE email = ? LIMIT 1');
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // Get user's phone number from residents table
                    $phone_stmt = $pdo->prepare('SELECT phone FROM residents WHERE user_id = ? LIMIT 1');
                    $phone_stmt->execute([$user['id']]);
                    $phone_result = $phone_stmt->fetch();
                    $userPhone = $phone_result['phone'] ?? null;
                    
                    // Generate reset token
                    $token = bin2hex(random_bytes(32));
                    $expires = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');
                    
                    // Delete any existing reset tokens for this user
                    $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$user['id']]);
                    
                    // Insert new reset token
                    $pdo->prepare('INSERT INTO password_resets (user_id, token, expires_at) VALUES (?,?,?)')->execute([$user['id'], $token, $expires]);
                    
                    // Build full reset URL
                    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'];
                    $basePath = dirname($_SERVER['PHP_SELF']);
                    $resetLink = $protocol . '://' . $host . $basePath . '/reset_password.php?token=' . urlencode($token);
                    
                    // Get user's name for email/SMS
                    $userName = !empty($user['full_name']) ? $user['full_name'] : (!empty($user['first_name']) ? $user['first_name'] : 'User');
                    
                    // Send password reset email
                    $emailResult = send_password_reset_email($email, $resetLink, $userName);
                    
                    // Send password reset SMS
                    $smsResult = null;
                    if (!empty($userPhone)) {
                        $smsResult = send_password_reset_sms($userPhone, $resetLink, $userName);
                    }
                    
                    // Always expose direct link on page (useful locally or if email fails)
                    
                    if ($emailResult['success']) {
                        $info = 'Password reset link has been sent to your email address.';
                        if ($smsResult && $smsResult['success']) {
                            $info .= ' An SMS was also sent.';
                        }
                        $infoType = 'success';
                    } else {
                        $info = 'We could not send a reset email. Please try again later or contact the barangay office.';
                        $infoType = 'danger';
                    }

                } else {
                    // Don't reveal if email exists for security
                    $info = 'If the email exists, a password reset link will be sent to your email address.';
                    $infoType = 'info';
                }
            } catch (Exception $e) {
                error_log('Password reset error: ' . $e->getMessage());
                $info = 'Server error. Please try again later.';
                $infoType = 'danger';
            }
        } else {
            $info = 'Please enter your email address.';
            $infoType = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Forgot Password - Brgy. Panungyanan IS</title>
	<!-- Google Fonts -->
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
	<!-- Bootstrap & Icons -->
	<link href="public/bootstrap/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
	<link href="public/css/styles.css" rel="stylesheet">
</head>
<body class="login-page">

<div class="container-fluid min-vh-100 d-flex align-items-center">
	<div class="row w-100">
		<!-- Logo Section - Left Side -->
		<div class="col-lg-6 d-flex align-items-center justify-content-center login-logo-section text-white">
			<div class="text-center position-relative z-1">
				<img src="public/img/barangaylogo.png" alt="Barangay Logo" class="mb-4 login-logo rounded-circle shadow-lg border border-3 border-white p-1 bg-white" style="width: 180px; height: 180px; object-fit: cover;">
				<h1 class="display-5 fw-extrabold text-white mb-2">Barangay Panungyanan</h1>
				<p class="lead text-white-50 mb-0">Digital Information System</p>
			</div>
		</div>
		
		<!-- Forgot Password Form Section - Right Side -->
		<div class="col-lg-6 d-flex align-items-center justify-content-center login-form-section">
			<div class="w-100 p-4" style="max-width: 500px;">
				<div class="card shadow-lg border-0">
					<div class="card-body p-5">
						<div class="mb-4 text-center">
							<h2 class="fw-bold mb-2">Forgot Password</h2>
							<p class="text-muted small">Enter your email and we'll send you a link to reset your password.</p>
						</div>
						
						<?php if ($info): ?>
							<div class="alert alert-<?php echo $infoType === 'success' ? 'success' : ($infoType === 'danger' ? 'danger' : ($infoType === 'warning' ? 'warning' : 'info')); ?> border-0 mb-4">
								<?php echo htmlspecialchars($info); ?>
							</div>
						<?php endif; ?>
						
						<form method="post">
							<?php echo csrf_field(); ?>
							<div class="mb-4">
								<label class="form-label fw-semibold text-secondary small text-uppercase">Email Address</label>
								<input type="email" name="email" class="form-control form-control-lg" placeholder="name@example.com" required>
							</div>
							<div class="d-grid mb-4">
								<button type="submit" class="btn btn-primary btn-lg py-3">Send Reset Link</button>
							</div>
							<div class="text-center">
								<a href="login.php" class="text-secondary small fw-bold text-decoration-none"><i class="fas fa-arrow-left me-1"></i> Back to Login</a>
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

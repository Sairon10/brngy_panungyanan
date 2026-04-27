<?php require_once __DIR__ . '/config.php'; ?>

<?php
error_reporting(E_ALL);
ini_set("display_errors",1);

$token = $_GET['token'] ?? '';
$message = '';
$valid = false;
$reset_success = false;
if ($token) {
	try {
		$pdo = get_db_connection();
		$stmt = $pdo->prepare('SELECT pr.id, pr.user_id, pr.expires_at, pr.used FROM password_resets pr WHERE pr.token = ? LIMIT 1');
		$stmt->execute([$token]);
		$pr = $stmt->fetch();
		if ($pr && !$pr['used'] && new DateTime($pr['expires_at']) > new DateTime()) {
			$valid = true;
		}
	} catch (Exception $e) {
		$message = 'Server error.';
	}
}

if ($valid && $_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!csrf_validate()) {
		$message = 'Invalid session. Please reload and try again.';
	} else {
		$new = $_POST['password'] ?? '';
		$confirm = $_POST['confirm_password'] ?? '';
		$strong = preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/', $new);
		if (!$strong) {
			$message = 'Password must be 8+ chars with uppercase, number, and special character';
		} elseif ($new !== $confirm) {
			$message = 'Passwords do not match';
		} else {
			try {
				$hash = password_hash($new, PASSWORD_BCRYPT);
				$pdo->prepare('UPDATE users u JOIN password_resets pr ON u.id = pr.user_id SET u.password_hash = ? WHERE pr.token = ?')->execute([$hash, $token]);
				$pdo->prepare('UPDATE password_resets SET used = 1 WHERE token = ?')->execute([$token]);
				$reset_success = true;
				$valid = false;
			} catch (Exception $e) {
				$message = 'Server error resetting password.';
			}
		}
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Reset Password - Brgy. Panungyanan IS</title>
	<!-- Google Fonts -->
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
	<!-- Bootstrap & Icons -->
	<link href="public/bootstrap/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
	<link href="public/css/styles.css" rel="stylesheet">
	<link rel="icon" href="public/img/favicon.png">
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
		
		<!-- Reset Password Form Section - Right Side -->
		<div class="col-lg-6 d-flex align-items-center justify-content-center login-form-section">
			<div class="w-100 p-4" style="max-width: 500px;">
				<div class="card shadow-lg border-0">
					<div class="card-body p-5">
						<h2 class="text-center fw-bold mb-4">Reset Password</h2>
						<?php if ($reset_success): ?>
							<div class="text-center">
								<div class="alert alert-success border-0 bg-success bg-opacity-10 text-success mb-4">
									<i class="fas fa-check-circle fa-2x mb-3 d-block"></i>
									<h4 class="h5 fw-bold">Password Reset Successful!</h4>
									<p class="mb-0 small">Your password has been updated. You may now login with your new password.</p>
								</div>
								<div class="d-grid mb-3">
									<a href="login.php" class="btn btn-primary btn-lg"><i class="fas fa-sign-in-alt me-2"></i>Go to Login</a>
								</div>
							</div>
						<?php elseif ($message && !$valid): ?>
							<div class="alert alert-<?php echo strpos($message, 'Error') !== false || strpos($message, 'match') !== false || strpos($message, 'must') !== false ? 'danger' : 'info'; ?> border-0 mb-4"
							><?php echo htmlspecialchars($message); ?></div>
						<?php endif; ?>
						<?php if ($valid): ?>
							<form method="post" novalidate>
								<?php echo csrf_field(); ?>
								<div class="mb-3">
									<label class="form-label fw-semibold text-secondary small text-uppercase">New Password</label>
									<div class="input-group">
										<input type="password" name="password" id="password" class="form-control form-control-lg border-end-0" placeholder="••••••••" required minlength="8" pattern="(?=.*[A-Z])(?=.*\\d)(?=.*[^A-Za-z0-9]).{8,}">
										<button class="btn btn-outline-secondary border-start-0 bg-white" type="button" id="togglePassword">
											<i class="fas fa-eye text-muted" id="toggleIcon"></i>
										</button>
									</div>
									<div class="form-text small mt-2">Min 8 chars, uppercase, number, special char.</div>
								</div>
								<div class="mb-4">
									<label class="form-label fw-semibold text-secondary small text-uppercase">Confirm Password</label>
									<div class="input-group">
										<input type="password" name="confirm_password" id="confirmPassword" class="form-control form-control-lg border-end-0" placeholder="••••••••" required>
										<button class="btn btn-outline-secondary border-start-0 bg-white" type="button" id="toggleConfirmPassword">
											<i class="fas fa-eye text-muted" id="toggleConfirmIcon"></i>
										</button>
									</div>
								</div>
								<div class="d-grid mb-4">
									<button type="submit" class="btn btn-primary btn-lg py-3">Reset Password</button>
								</div>
								<div class="text-center">
									<a href="login.php" class="text-secondary small fw-bold text-decoration-none"><i class="fas fa-arrow-left me-1"></i> Back to Login</a>
								</div>
							</form>
						<?php elseif (!$reset_success): ?>
							<div class="text-center">
								<div class="alert alert-warning border-0 bg-warning bg-opacity-10 text-warning mb-4">
									<i class="fas fa-exclamation-triangle fa-2x mb-3 d-block"></i>
									<h4 class="h5 fw-bold">Invalid or Expired Token</h4>
									<p class="mb-0 small">The password reset link is invalid or has expired. Please request a new password reset.</p>
								</div>
								<div class="d-grid mb-3">
									<a href="forgot_password.php" class="btn btn-primary btn-lg">Request New Reset Link</a>
								</div>
								<div class="text-center">
									<a href="login.php" class="text-secondary small fw-bold text-decoration-none">Back to Login</a>
								</div>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
	<script>
		// Password toggle functionality
		document.getElementById('togglePassword').addEventListener('click', function() {
			const passwordField = document.getElementById('password');
			const toggleIcon = document.getElementById('toggleIcon');
			
			if (passwordField.type === 'password') {
				passwordField.type = 'text';
				toggleIcon.classList.remove('fa-eye');
				toggleIcon.classList.add('fa-eye-slash');
			} else {
				passwordField.type = 'password';
				toggleIcon.classList.remove('fa-eye-slash');
				toggleIcon.classList.add('fa-eye');
			}
		});

		// Confirm password toggle functionality
		document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
			const confirmPasswordField = document.getElementById('confirmPassword');
			const toggleConfirmIcon = document.getElementById('toggleConfirmIcon');
			
			if (confirmPasswordField.type === 'password') {
				confirmPasswordField.type = 'text';
				toggleConfirmIcon.classList.remove('fa-eye');
				toggleConfirmIcon.classList.add('fa-eye-slash');
			} else {
				confirmPasswordField.type = 'password';
				toggleConfirmIcon.classList.remove('fa-eye-slash');
				toggleConfirmIcon.classList.add('fa-eye');
			}
		});
	</script>
</body>
</html>



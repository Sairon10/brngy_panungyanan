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
<body class="login-page d-flex align-items-center justify-content-center min-vh-100">
	<div class="container" style="max-width: 960px;">
		<div class="row justify-content-center">
			<div class="col-lg-10">
				<div class="card border-0 shadow-lg rounded-4 overflow-hidden">
					<div class="row g-0">
						<!-- Left: Brand / Illustration -->
						<div class="col-md-5 d-none d-md-flex align-items-center justify-content-center bg-light border-end">
							<div class="text-center px-4 py-5">
								<img src="public/img/barangaylogo.png" alt="Barangay Logo"
									class="mb-4 rounded-circle shadow-sm bg-white p-2"
									style="width: 110px; height: 110px; object-fit: cover;">
								<h2 class="h4 fw-bold mb-2">Barangay Panungyanan</h2>
								<p class="text-muted small mb-3">Digital Information System</p>
								<ul class="list-unstyled text-start text-muted small mb-0">
									<li class="mb-2"><i class="fas fa-lock text-success me-2"></i>Secure password reset</li>
									<li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Create a new password</li>
									<li><i class="fas fa-shield-alt text-success me-2"></i>Protect your account</li>
								</ul>
							</div>
						</div>

						<!-- Right: Form -->
						<div class="col-md-7 bg-white">
							<div class="p-4 p-md-5">
								<div class="mb-4 text-center text-md-start">
									<h1 class="h3 fw-bold mb-1">Reset Password</h1>
									<p class="text-muted small mb-0">Enter your new password below.</p>
								</div>
								
								<?php if ($reset_success): ?>
									<div class="text-center">
										<div class="alert alert-success border-0 bg-success bg-opacity-10 text-success mb-4 rounded-3 text-start small">
											<i class="fas fa-check-circle me-2"></i> Password Reset Successful! Your password has been updated.
										</div>
										<div class="d-grid mb-3">
											<a href="login.php" class="btn btn-primary rounded-pill"><i class="fas fa-sign-in-alt me-2"></i>Go to Login</a>
										</div>
									</div>
								<?php elseif ($message && !$valid): ?>
									<div class="alert alert-<?php echo strpos($message, 'Error') !== false || strpos($message, 'match') !== false || strpos($message, 'must') !== false ? 'danger' : 'info'; ?> border-0 bg-<?php echo strpos($message, 'Error') !== false || strpos($message, 'match') !== false || strpos($message, 'must') !== false ? 'danger' : 'info'; ?> bg-opacity-10 text-<?php echo strpos($message, 'Error') !== false || strpos($message, 'match') !== false || strpos($message, 'must') !== false ? 'danger' : 'info'; ?> small mb-4 rounded-3 text-start">
										<i class="fas fa-<?php echo strpos($message, 'Error') !== false || strpos($message, 'match') !== false || strpos($message, 'must') !== false ? 'exclamation-circle' : 'info-circle'; ?> me-2"></i> <?php echo htmlspecialchars($message); ?>
									</div>
								<?php endif; ?>

								<?php if ($valid): ?>
									<form method="post" novalidate>
										<?php echo csrf_field(); ?>
										<div class="mb-3">
											<label class="form-label fw-semibold text-secondary small text-uppercase">New Password</label>
											<div class="input-group">
												<input type="password" name="password" id="password" class="form-control form-control-lg border-end-0" placeholder="••••••••" required minlength="8" pattern="(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}">
												<button class="btn border border-start-0 bg-white" type="button" id="togglePassword">
													<i class="fas fa-eye text-muted" id="toggleIcon"></i>
												</button>
											</div>
											<div class="form-text small mt-2" style="font-size: 0.78rem;">Min 8 chars, uppercase, number, special char.</div>
										</div>
										<div class="mb-4">
											<label class="form-label fw-semibold text-secondary small text-uppercase">Confirm Password</label>
											<div class="input-group">
												<input type="password" name="confirm_password" id="confirmPassword" class="form-control form-control-lg border-end-0" placeholder="••••••••" required>
												<button class="btn border border-start-0 bg-white" type="button" id="toggleConfirmPassword">
													<i class="fas fa-eye text-muted" id="toggleConfirmIcon"></i>
												</button>
											</div>
										</div>
										<div class="d-flex justify-content-center mb-4 mt-2">
											<button type="submit" class="btn btn-primary px-4 py-2 rounded-pill w-100">Reset Password</button>
										</div>
										<div class="text-center">
											<a href="login.php" class="text-success small fw-semibold text-decoration-none"><i class="fas fa-arrow-left me-1"></i> Back to Login</a>
										</div>
									</form>
								<?php elseif (!$reset_success): ?>
									<div class="text-center">
										<div class="alert alert-warning border-0 bg-warning bg-opacity-10 text-warning mb-4 rounded-3 text-start small">
											<i class="fas fa-exclamation-triangle me-2"></i> The password reset link is invalid or has expired. Please request a new password reset.
										</div>
										<div class="d-grid mb-3">
											<a href="forgot_password.php" class="btn btn-primary rounded-pill">Request New Reset Link</a>
										</div>
										<div class="text-center">
											<a href="login.php" class="text-success small fw-semibold text-decoration-none"><i class="fas fa-arrow-left me-1"></i> Back to Login</a>
										</div>
									</div>
								<?php endif; ?>
							</div>
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



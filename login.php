<?php
require_once __DIR__ . '/config.php';

error_reporting(E_ALL);
ini_set("display_errors", 1);

$error = '';
$info = $_SESSION['info'] ?? '';
unset($_SESSION['info']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!csrf_validate()) {
		$error = 'Invalid session token. Please reload the page.';
	} else {
		$email = trim($_POST['email'] ?? '');
		$password = $_POST['password'] ?? '';

		// Validate email format
		if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$error = 'Please enter a valid email address';
		} else {
			try {
				$pdo = get_db_connection();

				// Match by email only
				$stmt = $pdo->prepare('SELECT id, password_hash, full_name, first_name, last_name, middle_name, suffix, role FROM users WHERE email = ? LIMIT 1');
				$stmt->execute([$email]);
				$user = $stmt->fetch();
				if ($user && password_verify($password, $user['password_hash'])) {
					// Regenerate session ID on successful login
					session_regenerate_id(true);
					$_SESSION['user_id'] = $user['id'];
					// Use full_name if available, otherwise construct from parts
					$full_name = $user['full_name'];
					if (empty($full_name) && (!empty($user['first_name']) || !empty($user['last_name']))) {
						$name_parts = array_filter([$user['first_name'], $user['middle_name'], $user['last_name'], $user['suffix']]);
						$full_name = implode(' ', $name_parts);
					}
					$_SESSION['full_name'] = $full_name;
					$_SESSION['role'] = $user['role'];

					// Check for saved redirect URL
					$redirectTo = $_SESSION['redirect_to'] ?? null;
					unset($_SESSION['redirect_to']);

					if ($redirectTo) {
						redirect($redirectTo);
					}

					if ($user['role'] === 'resident') {
						$stmt = $pdo->prepare('SELECT verification_status, is_rbi_completed FROM residents WHERE user_id = ? LIMIT 1');
						$stmt->execute([$user['id']]);
						$resident = $stmt->fetch();

						if ($resident && !$resident['is_rbi_completed']) {
							// Force completion of RBI form
							redirect('rbi_form.php');
						} else {
							// Go to landing page after login
							redirect('dashboard.php');
						}
					} else {
						// Admin or other roles: go directly to admin dashboard
						redirect('admin/index.php');
					}
				} else {
					$error = 'Invalid credentials';
				}
			} catch (Exception $e) {
				$error = 'Server error. Please try again.';
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
	<title>Login - Brgy. Panungyanan IS</title>
	<!-- Google Fonts -->
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&display=swap"
		rel="stylesheet">
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
						<div
							class="col-md-5 d-none d-md-flex align-items-center justify-content-center bg-light border-end">
							<div class="text-center px-4 py-5">
								<img src="public/img/barangaylogo.png" alt="Barangay Logo"
									class="mb-4 rounded-circle shadow-sm bg-white p-2"
									style="width: 110px; height: 110px; object-fit: cover;">
								<h2 class="h4 fw-bold mb-2">Barangay Panungyanan</h2>
								<p class="text-muted small mb-3">Digital Information System</p>
								<ul class="list-unstyled text-start text-muted small mb-0">
									<li class="mb-2"><i class="fas fa-check text-success me-2"></i>Fast, secure sign-in
									</li>
									<li class="mb-2"><i class="fas fa-check text-success me-2"></i>Access services
										anytime</li>
									<li><i class="fas fa-check text-success me-2"></i>Track your requests</li>
								</ul>
							</div>
						</div>

						<!-- Right: Form -->
						<div class="col-md-7 bg-white">
							<div class="p-4 p-md-5">
								<div class="mb-4 text-center text-md-start">
									<!-- Back to Home Button -->
									<a href="index.php" class="text-decoration-none small text-black fw-semibold">
										<i class="fas fa-arrow-left me-1"></i>
									</a>
									<h1 class="h3 fw-bold mb-1">Welcome back</h1>
									<p class="text-muted small mb-0">Sign in to continue.</p>
								</div>

								<?php if ($info): ?>
									<div
										class="alert alert-success border-0 bg-success bg-opacity-10 text-success small mb-4">
										<i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($info); ?>
									</div>
								<?php endif; ?>

								<?php if ($error): ?>
									<div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger small mb-4">
										<i
											class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
									</div>
								<?php endif; ?>

								<form method="post" novalidate>
									<?php echo csrf_field(); ?>
									<div class="mb-3">
										<label class="form-label fw-semibold small text-uppercase">Email Address</label>
										<input type="email" name="email" class="form-control form-control-lg"
											placeholder="name@example.com" required>
									</div>
									<div class="mb-4">
										<div class="d-flex justify-content-between align-items-center mb-1">
											<label
												class="form-label fw-semibold small text-uppercase mb-0">Password</label>
											<a href="forgot_password.php"
												class="text-decoration-none small text-primary fw-semibold">Forgot
												password?</a>
										</div>
										<div class="input-group input-group-lg">
											<input type="password" name="password" id="password"
												class="form-control border-end-0" placeholder="••••••••" required>
											<span class="input-group-text bg-white border-start-0" id="togglePassword"
												style="cursor: pointer;">
												<i class="fas fa-eye text-muted" id="toggleIcon"></i>
											</span>
										</div>
									</div>
									<div class="d-grid mb-3">
										<button type="submit" class="btn btn-primary btn-md">Sign
											In</button>
									</div>
									<div class="text-center">
										<p class="text-muted small mb-0">Don't have an account?
											<a href="register.php"
												class="text-primary fw-semibold text-decoration-none">
												Create account
											</a>
										</p>
									</div>
								</form>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
	<script>
		document.getElementById('togglePassword').addEventListener('click', function () {
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
	</script>
</body>

</html>
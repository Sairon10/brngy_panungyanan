<?php require_once __DIR__ . '/config.php'; ?>
<?php
$info = '';
$infoType = 'info';
$selectedMethod = $_POST['reset_method'] ?? 'email';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        $info = 'Invalid session. Please reload and try again.';
        $infoType = 'danger';
    } else {
        $method = $_POST['reset_method'] ?? 'email'; // 'email' or 'sms'
        $identifier = trim($_POST['identifier'] ?? '');

        if (empty($identifier)) {
            $info = $method === 'sms' ? 'Please enter your phone number.' : 'Please enter your email address.';
            $infoType = 'danger';
        } else {
            try {
                $pdo = get_db_connection();
                $user = null;

                if ($method === 'sms') {
                    // Look up by phone number in residents table
                    $stmt = $pdo->prepare('
                        SELECT u.id, u.full_name, u.first_name, u.email, r.phone
                        FROM users u
                        JOIN residents r ON r.user_id = u.id
                        WHERE r.phone = ? LIMIT 1
                    ');
                    $stmt->execute([$identifier]);
                    $user = $stmt->fetch();
                    $userPhone = $user['phone'] ?? null;
                } else {
                    // Look up by email
                    $stmt = $pdo->prepare('SELECT id, full_name, first_name, email FROM users WHERE email = ? LIMIT 1');
                    $stmt->execute([$identifier]);
                    $user = $stmt->fetch();

                    if ($user) {
                        $phone_stmt = $pdo->prepare('SELECT phone FROM residents WHERE user_id = ? LIMIT 1');
                        $phone_stmt->execute([$user['id']]);
                        $phone_result = $phone_stmt->fetch();
                        $userPhone = $phone_result['phone'] ?? null;
                    }
                }

                if ($user) {
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

                    $userName = !empty($user['full_name']) ? $user['full_name'] : (!empty($user['first_name']) ? $user['first_name'] : 'User');

                    if ($method === 'sms') {
                        if (!empty($userPhone)) {
                            $smsResult = send_password_reset_sms($userPhone, $resetLink, $userName);
                            if ($smsResult['success']) {
                                $info = 'Password reset link has been sent to your phone via SMS.';
                                $infoType = 'success';
                            } else {
                                $info = 'We could not send an SMS. Please try the email option or contact the barangay office.';
                                $infoType = 'danger';
                            }
                        } else {
                            $info = 'No phone number is associated with this account. Please use the email option.';
                            $infoType = 'danger';
                        }
                    } else {
                        $emailResult = send_password_reset_email($user['email'], $resetLink, $userName);
                        if ($emailResult['success']) {
                            $info = 'Password reset link has been sent to your email address.';
                            $infoType = 'success';
                        } else {
                            $info = 'We could not send a reset email. Please try again later or contact the barangay office.';
                            $infoType = 'danger';
                        }
                    }
                } else {
                    // Show a clear error that no account was found
                    $info = $method === 'sms'
                        ? 'No account found with that phone number. Please check and try again.'
                        : 'No account found with that email address. Please check and try again.';
                    $infoType = 'danger';
                }
            } catch (Exception $e) {
                error_log('Password reset error: ' . $e->getMessage());
                $info = 'Server error. Please try again later.';
                $infoType = 'danger';
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
	<title>Forgot Password - Brgy. Panungyanan IS</title>
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

	<style>
		.method-btn {
			border: 2px solid #e2e8f0;
			border-radius: 0.75rem;
			padding: 0.85rem 1rem;
			cursor: pointer;
			transition: all 0.2s ease;
			background: #f8fafc;
			text-align: center;
			user-select: none;
		}
		.method-btn:hover {
			border-color: #14b8a6;
			background: rgba(20, 184, 166, 0.04);
		}
		.method-btn.active {
			border-color: #0f766e;
			background: rgba(15, 118, 110, 0.07);
		}
		.method-btn .method-icon {
			font-size: 1.4rem;
			display: block;
			margin-bottom: 0.3rem;
		}
		.method-btn.active .method-icon,
		.method-btn.active .method-label {
			color: #0f766e;
		}
		.method-label {
			font-size: 0.8rem;
			font-weight: 700;
			text-transform: uppercase;
			letter-spacing: 0.03em;
			color: #64748b;
		}
	</style>
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
									<li class="mb-2"><i class="fas fa-envelope text-success me-2"></i>Send via Email or SMS</li>
									<li><i class="fas fa-clock text-success me-2"></i>Link expires in 1 hour</li>
								</ul>
							</div>
						</div>

						<!-- Right: Form -->
						<div class="col-md-7 bg-white">
							<div class="p-4 p-md-5">
								<div class="mb-4 text-center text-md-start">
									<h1 class="h3 fw-bold mb-1">Forgot Password?</h1>
									<p class="text-muted small mb-0">Choose how you want to receive your reset link.</p>
								</div>

								<?php if ($info): ?>
									<div class="alert border-0 bg-<?php echo $infoType === 'success' ? 'success' : ($infoType === 'danger' ? 'danger' : 'primary'); ?> bg-opacity-10 text-<?php echo $infoType === 'success' ? 'success' : ($infoType === 'danger' ? 'danger' : 'primary'); ?> small mb-4">
										<i class="fas fa-<?php echo $infoType === 'success' ? 'check-circle' : ($infoType === 'danger' ? 'exclamation-circle' : 'info-circle'); ?> me-2"></i><?php echo htmlspecialchars($info); ?>
									</div>
								<?php endif; ?>

								<form method="post" novalidate id="resetForm">
									<?php echo csrf_field(); ?>
									<input type="hidden" name="reset_method" id="resetMethod" value="<?php echo htmlspecialchars($selectedMethod); ?>">

									<!-- Method Toggle -->
									<div class="mb-4">
										<label class="form-label fw-semibold text-secondary small text-uppercase mb-2">Send Reset Link Via</label>
										<div class="row g-2">
											<div class="col-6">
												<div class="method-btn <?php echo $selectedMethod === 'email' ? 'active' : ''; ?>" id="btnEmail" onclick="selectMethod('email')">
													<span class="method-icon"><i class="fas fa-envelope"></i></span>
													<span class="method-label">Email</span>
												</div>
											</div>
											<div class="col-6">
												<div class="method-btn <?php echo $selectedMethod === 'sms' ? 'active' : ''; ?>" id="btnSms" onclick="selectMethod('sms')">
													<span class="method-icon"><i class="fas fa-sms"></i></span>
													<span class="method-label">SMS</span>
												</div>
											</div>
										</div>
									</div>

									<!-- Email Input -->
									<div class="mb-3" id="emailField" style="<?php echo $selectedMethod === 'sms' ? 'display:none;' : ''; ?>">
										<label class="form-label fw-semibold text-secondary small text-uppercase">Email Address</label>
										<input type="email" name="identifier" id="emailInput" class="form-control form-control-lg"
											placeholder="name@example.com"
											value="<?php echo $selectedMethod === 'email' ? htmlspecialchars($_POST['identifier'] ?? '') : ''; ?>">
									</div>

				<!-- SMS / Phone Input -->
								<div class="mb-3" id="smsField" style="<?php echo $selectedMethod === 'email' ? 'display:none;' : ''; ?>">
									<label class="form-label fw-semibold text-secondary small text-uppercase">Phone Number</label>
									<input type="tel" name="identifier_sms" id="smsInput" class="form-control form-control-lg"
										placeholder="09XXXXXXXXX"
										maxlength="11"
										pattern="^09[0-9]{9}$"
										inputmode="numeric"
										oninput="enforcePhone(this)"
										value="<?php echo $selectedMethod === 'sms' ? htmlspecialchars($_POST['identifier'] ?? '') : ''; ?>">
									<div class="form-text text-muted" style="font-size:0.78rem;">Philippine mobile number format: <strong>09XXXXXXXXX</strong> (11 digits)</div>
									<div id="phoneError" class="text-danger small mt-1" style="display:none;"></div>
								</div>

									<div class="d-flex justify-content-center mb-2">
									<button type="submit" class="btn btn-primary px-4 py-2 rounded-pill" id="submitBtn">
										<i class="fas fa-paper-plane me-2"></i><span id="submitLabel">Send Reset Link</span>
									</button>
								</div>
									<div class="text-center">
										<p class="text-muted small mb-0">
											<a href="login.php" class="text-success fw-semibold text-decoration-none">
												<i class="fas fa-arrow-left me-1"></i>Back to Login
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
		function selectMethod(method) {
			document.getElementById('resetMethod').value = method;

			const emailField = document.getElementById('emailField');
			const smsField   = document.getElementById('smsField');
			const emailInput = document.getElementById('emailInput');
			const smsInput   = document.getElementById('smsInput');
			const btnEmail   = document.getElementById('btnEmail');
			const btnSms     = document.getElementById('btnSms');
			const submitLabel = document.getElementById('submitLabel');

			if (method === 'email') {
				emailField.style.display = '';
				smsField.style.display   = 'none';
				emailInput.required = true;
				smsInput.required   = false;
				btnEmail.classList.add('active');
				btnSms.classList.remove('active');
				submitLabel.textContent = 'Send Reset Link via Email';
			} else {
				emailField.style.display = 'none';
				smsField.style.display   = '';
				emailInput.required = false;
				smsInput.required   = true;
				btnEmail.classList.remove('active');
				btnSms.classList.add('active');
				submitLabel.textContent = 'Send Reset Link via SMS';
			}
		}

		// On submit, copy the active input to a unified 'identifier' field
		document.getElementById('resetForm').addEventListener('submit', function(e) {
			const method = document.getElementById('resetMethod').value;
			if (method === 'sms') {
				const smsVal = document.getElementById('smsInput').value.trim();
				const phoneError = document.getElementById('phoneError');

				// Validate Philippine mobile number
				if (!smsVal) {
					e.preventDefault();
					phoneError.textContent = 'Please enter your phone number.';
					phoneError.style.display = '';
					document.getElementById('smsInput').classList.add('is-invalid');
					return;
				}
				if (!/^09[0-9]{9}$/.test(smsVal)) {
					e.preventDefault();
					phoneError.textContent = 'Please enter a valid 11-digit Philippine mobile number (e.g. 09XXXXXXXXX).';
					phoneError.style.display = '';
					document.getElementById('smsInput').classList.add('is-invalid');
					return;
				}

				phoneError.style.display = 'none';
				document.getElementById('smsInput').classList.remove('is-invalid');
				document.getElementById('emailInput').value = '';
				// Rename sms input to 'identifier' so it's picked up by PHP
				document.getElementById('smsInput').name = 'identifier';
			} else {
				const emailVal = document.getElementById('emailInput').value.trim();
				if (!emailVal) {
					e.preventDefault();
					document.getElementById('emailInput').classList.add('is-invalid');
					return;
				}
				document.getElementById('emailInput').classList.remove('is-invalid');
			}
		});

		function enforcePhone(input) {
			// Strip non-digits
			let val = input.value.replace(/\D/g, '');
			// Enforce starts with 09
			if (val.length >= 2 && val.substring(0, 2) !== '09') {
				val = '09' + val.replace(/^0*9*/, '');
			}
			// Max 11 digits
			input.value = val.substring(0, 11);

			// Live feedback
			const phoneError = document.getElementById('phoneError');
			if (input.value.length === 11 && /^09[0-9]{9}$/.test(input.value)) {
				input.classList.remove('is-invalid');
				input.classList.add('is-valid');
				phoneError.style.display = 'none';
			} else if (input.value.length > 0) {
				input.classList.remove('is-valid');
			}
		}

		// Init button label on page load
		selectMethod(document.getElementById('resetMethod').value);
	</script>
</body>

</html>


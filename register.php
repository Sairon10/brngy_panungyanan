<?php require_once __DIR__ . '/config.php'; ?>

<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!csrf_validate()) {
		$errors[] = 'Invalid CSRF token. Please refresh the page and try again.';
	}
	$first_name = trim($_POST['first_name'] ?? '');
	$last_name = trim($_POST['last_name'] ?? '');
	$middle_name = trim($_POST['middle_name'] ?? '');
	$suffix = trim($_POST['suffix'] ?? '');
	$birthdate = trim($_POST['birthdate'] ?? '');
	$citizenship = trim($_POST['citizenship'] ?? '');
	$civil_status = trim($_POST['civil_status'] ?? '');
	$sex = trim($_POST['sex'] ?? '');
	$email = trim($_POST['email'] ?? '');
	$phone = trim($_POST['phone'] ?? '');
	$province = trim($_POST['province'] ?? '');
	$municipality = trim($_POST['municipality'] ?? '');
	$barangay = trim($_POST['barangay'] ?? '');
	$is_solo_parent = isset($_POST['is_solo_parent']) ? 1 : 0;
	$is_pwd = isset($_POST['is_pwd']) ? 1 : 0;
	$is_senior = isset($_POST['is_senior']) ? 1 : 0;
    $street = trim($_POST['street'] ?? '');
    $address = implode(', ', array_filter([$street, $barangay, $municipality, $province]));
	$purok = trim($_POST['purok'] ?? '');
	$password = $_POST['password'] ?? '';
	$confirm = $_POST['confirm_password'] ?? '';

	// Build full_name from parts for backward compatibility
	$name_parts = array_filter([$first_name, $middle_name, $last_name, $suffix]);
	$full_name = implode(' ', $name_parts);

	// Validation
	if ($first_name === '')
		$errors[] = 'First name is required';
	if ($last_name === '')
		$errors[] = 'Last name is required';
	if ($birthdate === '')
		$errors[] = 'Birthdate is required';
	if ($citizenship === '')
		$errors[] = 'Citizenship is required';
	if ($civil_status === '')
		$errors[] = 'Civil status is required';
	if ($sex === '')
		$errors[] = 'Sex is required';
	if ($email === '')
		$errors[] = 'Email address is required';
	if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))
		$errors[] = 'Valid email is required';
	if ($phone === '')
		$errors[] = 'Phone number is required';
	if ($phone !== '' && !preg_match('/^[0-9]{11}$/', $phone))
		$errors[] = 'Phone number must be exactly 11 digits (e.g., 09123456789)';
	if ($province === '')
		$errors[] = 'Province is required';
	if ($municipality === '')
		$errors[] = 'Municipality is required';
	if ($barangay === '')
		$errors[] = 'Barangay is required';
	if ($purok === '')
		$errors[] = 'Purok is required';
	if (!isset($_POST['terms_agreement']))
		$errors[] = 'You must agree to the Terms of Service and Privacy Policy';
	if ($password !== $confirm)
		$errors[] = 'Passwords do not match';
	// Strong password: min 8, uppercase, number, special char
	$strong = preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/', $password);
	if (!$strong)
		$errors[] = 'Password must be 8+ chars with uppercase, number, and special character';
	
	// Validate birthdate format
	if ($birthdate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthdate)) {
		$errors[] = 'Invalid birthdate format';
	}
	
	// Validate age: must be between 18 and 59 years old
	if ($birthdate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthdate)) {
		$birth_date = new DateTime($birthdate);
		$today = new DateTime();
		$age = $today->diff($birth_date)->y;
		
		if ($age < 18) {
			$errors[] = 'You must be at least 18 years old to register';
		}
	}

	if (!$errors) {
		try {
			$pdo = get_db_connection();

			// Check if email is already registered
			if ($email !== '') {
				$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
				$stmt->execute([$email]);
				if ($stmt->fetch()) {
					$errors[] = 'This email is already registered. Please login or use a different email.';
				}
			}

			// Check if full name is already taken by a user
			$stmt = $pdo->prepare('SELECT id FROM users WHERE full_name = ? LIMIT 1');
			$stmt->execute([$full_name]);
			if ($stmt->fetch()) {
				$errors[] = 'This name is already registered. Please login or use a different name.';
			}

			// Check if phone number is already registered
			if ($phone !== '' && !empty($errors) == false) {
				$stmt = $pdo->prepare('SELECT id FROM residents WHERE phone = ? LIMIT 1');
				$stmt->execute([$phone]);
				if ($stmt->fetch()) {
					$errors[] = 'This phone number is already registered. Please use a different number.';
				}
			}

			if ($errors) {
				// Do not proceed with registration
			} else {
				// Registration is valid, create user account
				$hash = password_hash($password, PASSWORD_BCRYPT);
				$stmt = $pdo->prepare('INSERT INTO users (email, password_hash, full_name, first_name, last_name, middle_name, suffix, role) VALUES (?,?,?,?,?,?,?,\'resident\')');
				$stmt->execute([$email ?: null, $hash, $full_name, $first_name, $last_name, $middle_name ?: null, $suffix ?: null]);

				// Create resident profile
				$user_id = $pdo->lastInsertId();
				$stmt = $pdo->prepare('INSERT INTO residents (user_id, address, phone, birthdate, citizenship, civil_status, sex, purok, verification_status, is_solo_parent, is_pwd, is_senior) VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'pending\', ?, ?, ?)');
				$stmt->execute([
					$user_id, 
					$address, 
					$phone ?: null,
					$birthdate ?: null,
					$citizenship ?: null,
					$civil_status ?: null,
					$sex ?: null,
					$purok ?: null,
					$is_solo_parent,
					$is_pwd,
					$is_senior
				]);

				$success = 'Registration successful! Please login and upload your ID for verification.';
			}
		} catch (Exception $e) {
			$errors[] = 'Server error. Please try again.';
		}
	}
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Register - Brgy. Panungyanan IS</title>
	<!-- Google Fonts -->
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&display=swap"
		rel="stylesheet">
	<!-- Bootstrap & Icons -->
	<link href="public/bootstrap/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
	<link href="public/css/styles.css" rel="stylesheet">
	<style>
		.step-indicator {
			display: flex;
			flex-direction: column;
			align-items: center;
			flex: 1;
			position: relative;
		}
		.step-number {
			width: 40px;
			height: 40px;
			border-radius: 50%;
			background-color: #e9ecef;
			color: #475569;
			display: flex;
			align-items: center;
			justify-content: center;
			font-weight: 600;
			margin-bottom: 8px;
			transition: all 0.3s ease;
		}
		.step-indicator.active .step-number {
			background-color: #0f766e;
			color: white;
			box-shadow: 0 4px 6px -1px rgba(15, 118, 110, 0.3);
		}
		.step-indicator.completed .step-number {
			background-color: #10b981;
			color: white;
		}
		.step-label {
			font-size: 0.75rem;
			color: #475569;
			text-align: center;
			font-weight: 500;
		}
		.step-indicator.active .step-label {
			color: #0f766e;
			font-weight: 600;
		}
		.step-indicator.completed .step-label {
			color: #10b981;
		}
		.step-line {
			flex: 1;
			height: 2px;
			background-color: #e9ecef;
			margin: 0 10px;
			margin-top: 20px;
			transition: all 0.3s ease;
		}
		.step-line.completed {
			background-color: #10b981;
		}
		.step-content {
			min-height: 300px;
			animation: fadeIn 0.3s ease;
		}
		@keyframes fadeIn {
			from {
				opacity: 0;
				transform: translateY(10px);
			}
			to {
				opacity: 1;
				transform: translateY(0);
			}
		}
		.btn-primary {
			background-color: #0f766e;
			border-color: #0f766e;
		}
		.btn-primary:hover {
			background-color: #0d5c56;
			border-color: #0d5c56;
		}
		.btn-outline-secondary {
			border-color: #e2e8f0;
			color: #475569;
		}
		.btn-outline-secondary:hover {
			background-color: #f8fafc;
			border-color: #0f766e;
			color: #0f766e;
		}
		.form-control:focus, .form-select:focus {
			border-color: #14b8a6;
			box-shadow: 0 0 0 0.2rem rgba(20, 184, 166, 0.25);
		}
		.text-primary {
			color: #0f766e !important;
		}
		
		.form-check-input:checked {
			background-color: #0f766e;
			border-color: #0f766e;
		}
		
		.form-check-input.is-invalid {
			border-color: #dc3545;
		}
		
		.form-check-label a {
			text-decoration: none;
		}
		
		.form-check-label a:hover {
			text-decoration: underline;
		}

		/* Classification Card Checkboxes (kept for potential reuse) */

		/* Inline Classification Checkboxes */
		.classify-inline-item {
			display: flex;
			align-items: center;
			gap: 6px;
		}
		.classify-inline-check {
			width: 16px;
			height: 16px;
			accent-color: #0f766e;
			cursor: pointer;
			flex-shrink: 0;
		}
		.classify-inline-label {
			display: flex;
			align-items: center;
			gap: 6px;
			font-size: 0.875rem;
			font-weight: 500;
			color: #475569;
			cursor: pointer;
			user-select: none;
		}
		.classify-inline-icon {
			font-size: 1rem;
		}
		.icon-solo   { color: #e11d48; }
		.icon-pwd    { color: #2563eb; }
		.icon-senior { color: #d97706; }
	</style>
</head>

<body class="login-page d-flex align-items-center justify-content-center min-vh-100">
	<div class="container" style="max-width: 1080px;">
		<div class="row justify-content-center">
			<div class="col-lg-12">
				<div class="card border-0 shadow-lg rounded-4 overflow-hidden">
					<div class="row g-0">
						<!-- Left: Brand / Illustration -->
						<div
							class="col-md-4 d-none d-md-flex align-items-center justify-content-center bg-light border-end">
							<div class="text-center px-4 py-5">
								<img src="public/img/barangaylogo.png" alt="Barangay Logo"
									class="mb-4 rounded-circle shadow-sm bg-white p-2"
									style="width: 110px; height: 110px; object-fit: cover;">
								<h2 class="h4 fw-bold mb-2">Barangay Panungyanan</h2>
								<p class="text-muted small mb-3">Digital Information System</p>
								<ul class="list-unstyled text-start text-muted small mb-0">
									<li class="mb-2"><i class="fas fa-check text-success me-2"></i>Secure online
										registration</li>
									<li class="mb-2"><i class="fas fa-check text-success me-2"></i>Access documents and
										services</li>
									<li><i class="fas fa-check text-success me-2"></i>Track requests anytime</li>
								</ul>
							</div>
						</div>

						<!-- Right: Form -->
						<div class="col-md-8 bg-white">
							<div class="p-4 p-md-5">
								<div class="mb-4 text-center text-md-start">
									<h1 class="h3 fw-bold mb-1">Create your account</h1>
									<p class="text-muted small mb-0">It only takes a minute to get started.</p>
								</div>

								<?php if ($errors): ?>
									<div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger small">
										<ul class="mb-0 ps-3">
											<?php foreach ($errors as $e): ?>
												<li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
										</ul>
									</div>
								<?php endif; ?>
								<?php if ($success): ?>
									<div class="alert alert-success border-0 bg-success bg-opacity-10 text-success small">
										<?php echo htmlspecialchars($success); ?>
									</div>
								<?php endif; ?>

								<!-- Progress Indicator -->
								<div class="mb-4">
									<div class="d-flex justify-content-between align-items-center mb-2">
										<div class="step-indicator active" data-step="1">
											<div class="step-number">1</div>
											<div class="step-label d-none d-md-block">Name</div>
										</div>
										<div class="step-line"></div>
										<div class="step-indicator" data-step="2">
											<div class="step-number">2</div>
											<div class="step-label d-none d-md-block">Details</div>
										</div>
										<div class="step-line"></div>
										<div class="step-indicator" data-step="3">
											<div class="step-number">3</div>
											<div class="step-label d-none d-md-block">Contact</div>
										</div>
										<div class="step-line"></div>
										<div class="step-indicator" data-step="4">
											<div class="step-number">4</div>
											<div class="step-label d-none d-md-block">Security</div>
										</div>
									</div>
									<div class="text-center">
										<small class="text-muted">Step <span id="current-step">1</span> of 4</small>
									</div>
								</div>

								<form method="post" novalidate id="registrationForm" class="mt-3">
									<?php echo csrf_field(); ?>
									
									<!-- Step 1: Personal Information (Name) -->
									<div class="step-content" data-step="1">
										<h5 class="mb-4 fw-semibold" style="color: #0f766e;">Personal Information</h5>
										<div class="row g-3">
											<div class="col-md-12">
												<label class="form-label fw-semibold text-secondary small text-uppercase">First Name <span class="text-danger">*</span></label>
												<input type="text" name="first_name" id="first_name" class="form-control form-control-lg" placeholder="e.g. Juan" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
											</div>
											<div class="col-md-12">
												<label class="form-label fw-semibold text-secondary small text-uppercase">Last Name <span class="text-danger">*</span></label>
												<input type="text" name="last_name" id="last_name" class="form-control form-control-lg" placeholder="e.g. Dela Cruz" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
											</div>
											<div class="col-md-8">
												<label class="form-label fw-semibold text-secondary small text-uppercase">Middle Name</label>
												<input type="text" name="middle_name" id="middle_name" class="form-control form-control-lg" placeholder="e.g. Santos" value="<?php echo htmlspecialchars($_POST['middle_name'] ?? ''); ?>">
											</div>
											<div class="col-md-4">
												<label class="form-label fw-semibold text-secondary small text-uppercase">Suffix</label>
												<input type="text" name="suffix" id="suffix" class="form-control form-control-lg" placeholder="e.g. Jr., Sr., III" value="<?php echo htmlspecialchars($_POST['suffix'] ?? ''); ?>">
											</div>
										</div>
									</div>

									<!-- Step 2: Personal Details -->
									<div class="step-content d-none" data-step="2">
										<h5 class="mb-4 fw-semibold" style="color: #0f766e;">Personal Details</h5>
										<div class="row g-3">
											<div class="col-md-6">
												<label class="form-label fw-semibold text-secondary small text-uppercase">Birthdate <span class="text-danger">*</span></label>
												<input type="date" name="birthdate" id="birthdate" class="form-control form-control-lg" value="<?php echo htmlspecialchars($_POST['birthdate'] ?? ''); ?>" required>
												<div class="form-text small text-muted">Must be at least 18 years old</div>
											</div>
											<div class="col-md-6">
												<label class="form-label fw-semibold text-secondary small text-uppercase">Sex <span class="text-danger">*</span></label>
												<select name="sex" id="sex" class="form-select form-select-lg" required>
													<option value="">Select Sex</option>
													<option value="Male" <?php echo (isset($_POST['sex']) && $_POST['sex'] === 'Male') ? 'selected' : ''; ?>>Male</option>
													<option value="Female" <?php echo (isset($_POST['sex']) && $_POST['sex'] === 'Female') ? 'selected' : ''; ?>>Female</option>
												</select>
											</div>
											<div class="col-md-6">
												<label class="form-label fw-semibold text-secondary small text-uppercase">Citizenship <span class="text-danger">*</span></label>
												<input type="text" name="citizenship" id="citizenship" class="form-control form-control-lg" placeholder="e.g. Filipino" value="<?php echo htmlspecialchars($_POST['citizenship'] ?? ''); ?>" required>
											</div>
											<div class="col-md-6">
												<label class="form-label fw-semibold text-secondary small text-uppercase">Civil Status <span class="text-danger">*</span></label>
												<select name="civil_status" id="civil_status" class="form-select form-select-lg" required>
													<option value="">Select Civil Status</option>
													<option value="Single" <?php echo (isset($_POST['civil_status']) && $_POST['civil_status'] === 'Single') ? 'selected' : ''; ?>>Single</option>
													<option value="Married" <?php echo (isset($_POST['civil_status']) && $_POST['civil_status'] === 'Married') ? 'selected' : ''; ?>>Married</option>
													<option value="Widowed" <?php echo (isset($_POST['civil_status']) && $_POST['civil_status'] === 'Widowed') ? 'selected' : ''; ?>>Widowed</option>
													<option value="Divorced" <?php echo (isset($_POST['civil_status']) && $_POST['civil_status'] === 'Divorced') ? 'selected' : ''; ?>>Divorced</option>
													<option value="Separated" <?php echo (isset($_POST['civil_status']) && $_POST['civil_status'] === 'Separated') ? 'selected' : ''; ?>>Separated</option>
												</select>
											</div>
											<div class="col-12 mt-2">
												<label class="form-label fw-semibold text-secondary small text-uppercase">Special Classification (Optional)</label>
												<div class="d-flex flex-wrap gap-3 mt-1">
													<div class="classify-inline-item">
														<input type="checkbox" name="is_solo_parent" id="is_solo_parent" value="1" class="classify-inline-check" <?php echo isset($_POST['is_solo_parent']) ? 'checked' : ''; ?>>
														<label for="is_solo_parent" class="classify-inline-label">
															<i class="fas fa-user-shield classify-inline-icon icon-solo"></i>
															Solo Parent
														</label>
													</div>
													<div class="classify-inline-item">
														<input type="checkbox" name="is_pwd" id="is_pwd" value="1" class="classify-inline-check" <?php echo isset($_POST['is_pwd']) ? 'checked' : ''; ?>>
														<label for="is_pwd" class="classify-inline-label">
															<i class="fas fa-wheelchair classify-inline-icon icon-pwd"></i>
															PWD
														</label>
													</div>
													<div class="classify-inline-item">
														<input type="checkbox" name="is_senior" id="is_senior" value="1" class="classify-inline-check" <?php echo isset($_POST['is_senior']) ? 'checked' : ''; ?>>
														<label for="is_senior" class="classify-inline-label">
															<i class="fas fa-user-clock classify-inline-icon icon-senior"></i>
															Senior Citizen
														</label>
													</div>
												</div>
											</div>
										</div>
									</div>

									<!-- Step 3: Contact Information -->
									<div class="step-content d-none" data-step="3">
										<h5 class="mb-4 fw-semibold" style="color: #0f766e;">Contact Information</h5>
										<div class="row g-3">
											<div class="col-12">
												<label class="form-label fw-semibold text-secondary small text-uppercase">Email Address <span class="text-danger">*</span></label>
												<input type="email" name="email" id="email" class="form-control form-control-lg" placeholder="name@example.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
											</div>
											<div class="col-12">
												<label class="form-label fw-semibold text-secondary small text-uppercase">Phone Number <span class="text-danger">*</span></label>
												<input type="tel" name="phone" id="phone" class="form-control form-control-lg" placeholder="e.g. 09123456789" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required maxlength="11" pattern="[0-9]{11}" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
											</div>
											<div class="col-md-12">
												<label class="form-label fw-semibold text-secondary small text-uppercase">Province <span class="text-danger">*</span></label>
												<select name="province" id="province" class="form-select form-select-lg" required>
													<option value="">Select Province</option>
												</select>
											</div>
											<div class="col-md-12">
												<label class="form-label fw-semibold text-secondary small text-uppercase">Municipality <span class="text-danger">*</span></label>
												<select name="municipality" id="municipality" class="form-select form-select-lg" required disabled>
													<option value="">Select Municipality</option>
												</select>
											</div>
											<div class="col-md-12">
												<label class="form-label fw-semibold text-secondary small text-uppercase">Barangay <span class="text-danger">*</span></label>
												<select name="barangay" id="barangay" class="form-select form-select-lg" required disabled>
													<option value="">Select Barangay</option>
												</select>
											</div>
											<div class="col-12">
												<label class="form-label fw-semibold text-secondary small text-uppercase">Purok <span class="text-danger">*</span></label>
												<input type="text" name="purok" id="purok" class="form-control form-control-lg" placeholder="e.g. Purok 1, Purok 2" value="<?php echo htmlspecialchars($_POST['purok'] ?? ''); ?>" required>
											</div>
											<div class="col-12">
												<label class="form-label fw-semibold text-secondary small text-uppercase">Household/Block/lot/street</label>
												<input type="text" name="street" id="street" class="form-control form-control-lg" placeholder="e.g. Blk 1 Lot 2 Phase 3" value="<?php echo htmlspecialchars($_POST['street'] ?? ''); ?>">
											</div>
										</div>
									</div>

									<!-- Step 4: Security (Password) -->
									<div class="step-content d-none" data-step="4">
										<h5 class="mb-4 fw-semibold" style="color: #0f766e;">Create Password</h5>
										<div class="row g-3">
											<div class="col-md-12">
												<label class="form-label fw-semibold text-secondary small text-uppercase">Password</label>
												<div class="input-group input-group-lg">
													<input type="password" name="password" id="password" class="form-control border-end-0" placeholder="••••••••" required minlength="8">
													<span class="input-group-text bg-white border-start-0" id="togglePassword" style="cursor: pointer;">
														<i class="fas fa-eye text-muted" id="toggleIcon"></i>
													</span>
												</div>
											</div>
											<div class="col-md-12">
												<label class="form-label fw-semibold text-secondary small text-uppercase">Confirm Password</label>
												<div class="input-group input-group-lg">
													<input type="password" name="confirm_password" id="confirmPassword" class="form-control border-end-0" placeholder="••••••••" required>
													<span class="input-group-text bg-white border-start-0" id="toggleConfirmPassword" style="cursor: pointer;">
														<i class="fas fa-eye text-muted" id="toggleConfirmIcon"></i>
													</span>
												</div>
											</div>
											<div class="col-12">
												<div class="form-text small text-muted">
													Password must be at least 8 characters and include an uppercase letter, a number, and a special character.
												</div>
											</div>
											<div class="col-12">
												<div class="form-check">
													<input type="checkbox" class="form-check-input" name="terms_agreement" id="terms_agreement" required>
													<label class="form-check-label" for="terms_agreement">
														I confirm that all information provided is accurate and I agree to the <a href="#" class="text-primary">Terms of Service</a> and <a href="#" class="text-primary">Privacy Policy</a>
													</label>
												</div>
											</div>
										</div>
									</div>

									<!-- Navigation Buttons -->
									<div class="d-flex justify-content-between mt-4">
										<button type="button" class="btn btn-outline-secondary btn-lg rounded-pill" id="prevBtn" style="display: none;">
											<i class="fas fa-arrow-left me-2"></i>Previous
										</button>
										<button type="button" class="btn btn-primary btn-lg rounded-pill ms-auto" id="nextBtn">
											Next<i class="fas fa-arrow-right ms-2"></i>
										</button>
										<button type="submit" class="btn btn-primary btn-lg rounded-pill ms-auto" id="submitBtn" style="display: none;">
											Create Account<i class="fas fa-check ms-2"></i>
										</button>
									</div>

									<div class="text-center mt-3">
										<p class="text-muted small mb-0">Already have an account? <a href="login.php" class="text-primary fw-semibold text-decoration-none">Sign in</a></p>
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
		<?php
		$error_step = 1;
		if (!empty($errors)) {
			$err_str = strtolower(implode(" ", $errors));
			if (strpos($err_str, 'password') !== false || strpos($err_str, 'agree') !== false) {
				$error_step = 4;
			} elseif (strpos($err_str, 'email') !== false || strpos($err_str, 'phone') !== false || strpos($err_str, 'province') !== false || strpos($err_str, 'municipality') !== false || strpos($err_str, 'barangay') !== false || strpos($err_str, 'purok') !== false) {
				$error_step = 3;
			} elseif (strpos($err_str, 'birthdate') !== false || strpos($err_str, 'sex') !== false || strpos($err_str, 'citizenship') !== false || strpos($err_str, 'civil') !== false || strpos($err_str, '18 years') !== false) {
				$error_step = 2;
			} elseif (strpos($err_str, 'name') !== false) {
				$error_step = 1;
			}
		}
		?>
		// Multi-step form functionality
		let currentStep = <?php echo $error_step; ?>;
		const totalSteps = 4;

		function showStep(step) {
			// Hide all steps
			document.querySelectorAll('.step-content').forEach(content => {
				content.classList.add('d-none');
			});

			// Show current step
			document.querySelector(`.step-content[data-step="${step}"]`).classList.remove('d-none');

			// Update step indicators
			document.querySelectorAll('.step-indicator').forEach((indicator, index) => {
				const stepNum = index + 1;
				indicator.classList.remove('active', 'completed');
				if (stepNum < step) {
					indicator.classList.add('completed');
				} else if (stepNum === step) {
					indicator.classList.add('active');
				}
			});

			// Update step lines
			document.querySelectorAll('.step-line').forEach((line, index) => {
				if (index + 1 < step) {
					line.classList.add('completed');
				} else {
					line.classList.remove('completed');
				}
			});

			// Update current step display
			document.getElementById('current-step').textContent = step;

			// Update navigation buttons
			document.getElementById('prevBtn').style.display = step === 1 ? 'none' : 'block';
			document.getElementById('nextBtn').style.display = step === totalSteps ? 'none' : 'block';
			document.getElementById('submitBtn').style.display = step === totalSteps ? 'block' : 'none';
		}

		function validateStep(step) {
			const stepContent = document.querySelector(`.step-content[data-step="${step}"]`);
			const requiredFields = stepContent.querySelectorAll('[required]');
			let isValid = true;

			requiredFields.forEach(field => {
				// Handle checkboxes differently
				if (field.type === 'checkbox') {
					if (!field.checked) {
						isValid = false;
						field.classList.add('is-invalid');
					} else {
						field.classList.remove('is-invalid');
					}
				} else if (!field.value.trim()) {
					isValid = false;
					field.classList.add('is-invalid');
				} else {
					field.classList.remove('is-invalid');
				}

				// Additional validation for email
				if (field.type === 'email' && field.value && !field.checkValidity()) {
					isValid = false;
					field.classList.add('is-invalid');
				}

				// Additional validation for date (age 18-59)
				if (field.type === 'date' && field.value) {
					const date = new Date(field.value);
					const today = new Date();
					const age = today.getFullYear() - date.getFullYear();
					const monthDiff = today.getMonth() - date.getMonth();
					const dayDiff = today.getDate() - date.getDate();
					
					// Calculate exact age
					let exactAge = age;
					if (monthDiff < 0 || (monthDiff === 0 && dayDiff < 0)) {
						exactAge--;
					}
					
					if (date >= today) {
						isValid = false;
						field.classList.add('is-invalid');
						field.setCustomValidity('Birthdate must be in the past');
					} else if (exactAge < 18) {
						isValid = false;
						field.classList.add('is-invalid');
						field.setCustomValidity('You must be at least 18 years old to register');
					} else {
						field.setCustomValidity('');
					}
				}
			});

			return isValid;
		}

		// Next button handler
		document.getElementById('nextBtn').addEventListener('click', function() {
			if (validateStep(currentStep)) {
				if (currentStep < totalSteps) {
					currentStep++;
					showStep(currentStep);
				}
			} else {
				// Show validation message
				const stepContent = document.querySelector(`.step-content[data-step="${currentStep}"]`);
				const invalidFields = stepContent.querySelectorAll('.is-invalid');
				if (invalidFields.length > 0) {
					invalidFields[0].focus();
				}
			}
		});

		// Previous button handler
		document.getElementById('prevBtn').addEventListener('click', function() {
			if (currentStep > 1) {
				currentStep--;
				showStep(currentStep);
			}
		});

		// Password toggle functionality
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

		// Confirm password toggle functionality
		document.getElementById('toggleConfirmPassword').addEventListener('click', function () {
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

		// Form submission validation
		document.getElementById('registrationForm').addEventListener('submit', function(e) {
			// Validate all steps before submission
			let allValid = true;
			for (let i = 1; i <= totalSteps; i++) {
				if (!validateStep(i)) {
					allValid = false;
					if (i !== currentStep) {
						currentStep = i;
						showStep(currentStep);
					}
					break;
				}
			}

			// Check terms agreement checkbox
			const termsCheckbox = document.getElementById('terms_agreement');
			if (!termsCheckbox.checked) {
				allValid = false;
				if (currentStep !== 4) {
					currentStep = 4;
					showStep(currentStep);
				}
				termsCheckbox.classList.add('is-invalid');
				termsCheckbox.focus();
			}

			if (!allValid) {
				e.preventDefault();
				return false;
			}

			// Additional password validation
			const password = document.getElementById('password').value;
			const confirmPassword = document.getElementById('confirmPassword').value;

			if (password !== confirmPassword) {
				e.preventDefault();
				document.getElementById('confirmPassword').classList.add('is-invalid');
				document.getElementById('confirmPassword').setCustomValidity('Passwords do not match');
				return false;
			} else {
				document.getElementById('confirmPassword').setCustomValidity('');
			}

			// Password strength validation
			const strongPassword = /^(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/;
			if (!strongPassword.test(password)) {
				e.preventDefault();
				document.getElementById('password').classList.add('is-invalid');
				document.getElementById('password').setCustomValidity('Password must be 8+ chars with uppercase, number, and special character');
				return false;
			} else {
				document.getElementById('password').setCustomValidity('');
			}
		});

		// Remove invalid class on input
		document.querySelectorAll('input, select').forEach(field => {
			if (field.type === 'checkbox') {
				field.addEventListener('change', function() {
					this.classList.remove('is-invalid');
				});
			} else {
				field.addEventListener('input', function() {
					this.classList.remove('is-invalid');
				});
			}
		});

		// Set date input limits for age 18-59
		const birthdateInput = document.getElementById('birthdate');
		const today = new Date();
		const maxDate = new Date(today.getFullYear() - 18, today.getMonth(), today.getDate());
		birthdateInput.setAttribute('max', maxDate.toISOString().split('T')[0]);
		// Removed min date limit for seniors

		// ===== PSGC API Cascading Dropdowns =====
		const PSGC_API = 'https://psgc.gitlab.io/api';
		const provinceSelect = document.getElementById('province');
		const municipalitySelect = document.getElementById('municipality');
		const barangaySelect = document.getElementById('barangay');

		const initialProvince = <?php echo json_encode($_POST['province'] ?? ''); ?>;
		const initialMunicipality = <?php echo json_encode($_POST['municipality'] ?? ''); ?>;
		const initialBarangay = <?php echo json_encode($_POST['barangay'] ?? ''); ?>;

		// Load all provinces on page load
		fetch(`${PSGC_API}/provinces/`)
			.then(res => res.json())
			.then(data => {
				data.sort((a, b) => a.name.localeCompare(b.name));
				data.forEach(prov => {
					const opt = document.createElement('option');
					opt.value = prov.name;
					opt.textContent = prov.name;
					opt.dataset.code = prov.code;
					if (prov.name === initialProvince) opt.selected = true;
					provinceSelect.appendChild(opt);
				});
				if (initialProvince) {
					provinceSelect.dispatchEvent(new Event('change'));
				}
			})
			.catch(() => {
				// Fallback: allow text input if API fails
				provinceSelect.outerHTML = '<input type="text" name="province" id="province" class="form-control form-control-lg" placeholder="e.g. Cavite" required>';
				municipalitySelect.outerHTML = '<input type="text" name="municipality" id="municipality" class="form-control form-control-lg" placeholder="e.g. General Trias" required>';
				barangaySelect.outerHTML = '<input type="text" name="barangay" id="barangay" class="form-control form-control-lg" placeholder="e.g. Panungyanan" required>';
			});

		// When province changes, load municipalities
		provinceSelect.addEventListener('change', function () {
			const selected = this.options[this.selectedIndex];
			const code = selected.dataset.code;

			// Reset municipality and barangay
			municipalitySelect.innerHTML = '<option value="">Loading...</option>';
			municipalitySelect.disabled = true;
			barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
			barangaySelect.disabled = true;

			if (!code) {
				municipalitySelect.innerHTML = '<option value="">Select Municipality</option>';
				return;
			}

			fetch(`${PSGC_API}/provinces/${code}/cities-municipalities/`)
				.then(res => res.json())
				.then(data => {
					municipalitySelect.innerHTML = '<option value="">Select Municipality</option>';
					data.sort((a, b) => a.name.localeCompare(b.name));
					data.forEach(mun => {
						const opt = document.createElement('option');
						opt.value = mun.name;
						opt.textContent = mun.name;
						opt.dataset.code = mun.code;
						if (mun.name === initialMunicipality) opt.selected = true;
						municipalitySelect.appendChild(opt);
					});
					municipalitySelect.disabled = false;
					if (initialMunicipality && initialProvince === provinceSelect.value) {
						municipalitySelect.dispatchEvent(new Event('change'));
					}
				});
		});

		// When municipality changes, load barangays
		municipalitySelect.addEventListener('change', function () {
			const selected = this.options[this.selectedIndex];
			const code = selected.dataset.code;

			// Reset barangay
			barangaySelect.innerHTML = '<option value="">Loading...</option>';
			barangaySelect.disabled = true;

			if (!code) {
				barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
				return;
			}

			fetch(`${PSGC_API}/cities-municipalities/${code}/barangays/`)
				.then(res => res.json())
				.then(data => {
					barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
					data.sort((a, b) => a.name.localeCompare(b.name));
					data.forEach(brgy => {
						const opt = document.createElement('option');
						opt.value = brgy.name;
						opt.textContent = brgy.name;
						if (brgy.name === initialBarangay) opt.selected = true;
						barangaySelect.appendChild(opt);
					});
					barangaySelect.disabled = false;
				});
		});

		// Initialize right step
		showStep(currentStep);
	</script>
</body>

</html>

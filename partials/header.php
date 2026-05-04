<?php require_once __DIR__ . '/../config.php'; ?>
<?php
// Fetch user avatar if logged in
$user_avatar = null;
if (is_logged_in()) {
	try {
		$pdo = get_db_connection();
		$stmt = $pdo->prepare('SELECT avatar FROM residents WHERE user_id = ? LIMIT 1');
		$stmt->execute([$_SESSION['user_id']]);
		$resident = $stmt->fetch();
		$user_avatar = $resident['avatar'] ?? null;
	} catch (Exception $e) {
		// Silently fail if there's an error
		$user_avatar = null;
	}
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Information System - Brgy. Panungyanan</title>
	<!-- Google Fonts -->
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&display=swap"
		rel="stylesheet">
	<!-- Bootstrap & Icons -->
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
	<!-- Animations -->
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
	<!-- Custom Styles -->
	<link href="public/css/styles.css?v=<?php echo time(); ?>" rel="stylesheet">
	<link href="public/css/navbar-enhancements.css" rel="stylesheet">
	<link rel="icon" href="public/img/favicon.png">
</head>

<body>
	<nav class="navbar navbar-expand-lg fixed-top navbar-clean">
		<div class="container">
			<!-- Brand with Logo -->
			<a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
				<img src="public/img/barangaylogo.png" alt="Barangay Logo" class="navbar-logo">
				<div class="d-flex flex-column lh-1">
					<span class="brand-title">Brgy. Panungyanan</span>
				</div>
			</a>

			<!-- Mobile Actions Area -->
			<div class="d-flex align-items-center gap-2 ms-auto">
				<?php if (is_logged_in()): ?>
					<!-- User Dropdown (Pill) - Mobile Only -->
					<div class="dropdown d-lg-none">
						<a class="nav-link dropdown-toggle d-flex align-items-center gap-2 p-1 pe-2 rounded-pill user-pill shadow-sm"
							href="#" id="userDropdownMobile" role="button" data-bs-toggle="dropdown"
							aria-expanded="false" style="background: rgba(0, 0, 0, 0.03); border: 1px solid rgba(0, 0, 0, 0.08);">
							<?php if (!empty($user_avatar)): ?>
								<img src="/<?php echo htmlspecialchars($user_avatar); ?>" alt="Profile"
									class="avatar-circle"
									style="width: 30px; height: 30px; object-fit: cover; border-radius: 50%;">
							<?php else: ?>
								<div class="avatar-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold" style="width: 30px; height: 30px; border-radius: 50%; font-size: 0.75rem;">
									<?php echo strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1)); ?>
								</div>
							<?php endif; ?>
						</a>
						<ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg mt-2 p-2 rounded-4"
							aria-labelledby="userDropdownMobile">
							<li class="px-3 py-2 border-bottom mb-2">
								<span class="fw-bold d-block text-dark small"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></span>
								<small class="text-muted">Resident</small>
							</li>
							<li><a class="dropdown-item rounded-3" href="profile.php"><i class="fas fa-user me-2 text-muted"></i> Profile</a></li>
							<li><a class="dropdown-item rounded-3" href="dashboard.php"><i class="fas fa-layer-group me-2 text-muted"></i> Dashboard</a></li>
							<li><hr class="dropdown-divider my-2"></li>
							<li><a class="dropdown-item rounded-3 text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Log Out</a></li>
						</ul>
					</div>
				<?php endif; ?>

				<!-- Chatbot Icon (Main Navbar) -->
				<style>
					@media (max-width: 768px) {
						.chatbot-panel {
							left: 10px !important;
							right: 10px !important;
							width: auto !important;
							max-width: none !important;
							height: calc(100vh - 85px) !important;
							top: 75px !important;
						}
						.chatbot-header, .chatbot-input-container, .chatbot-suggestions {
							flex-shrink: 0 !important;
							z-index: 10 !important;
						}
						.chatbot-messages {
							flex: 1 1 auto !important;
							overflow-y: auto !important;
						}
					}
				</style>
				<div id="chatbot-root" data-user-logged-in="<?php echo is_logged_in() ? 'true' : 'false'; ?>"></div>

				<!-- Mobile Toggle Button -->
				<button class="navbar-toggler border-0 shadow-none ms-2" type="button" data-bs-toggle="offcanvas"
					data-bs-target="#navbarNav" aria-controls="navbarNav" aria-label="Toggle navigation">
					<div class="hamburger-icon">
						<span></span>
						<span></span>
						<span></span>
					</div>
				</button>
			</div>

			<!-- Navigation Menu -->
			<div class="offcanvas offcanvas-start bg-white" tabindex="-1" id="navbarNav"
				aria-labelledby="navbarNavLabel">
				<div class="offcanvas-header border-bottom px-4 py-3">
					<a href="index.php" class="d-flex align-items-center gap-2 text-decoration-none">
						<img src="public/img/barangaylogo.png" alt="Logo" class="navbar-logo"
							style="width: 40px; height: 40px;">
						<h5 class="offcanvas-title fw-bold text-primary mb-0 text-success" id="navbarNavLabel"
							style="font-size: 1.15rem;">Brgy. Panungyanan</h5>
					</a>
					<button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#navbarNav"
						aria-label="Close"></button>
				</div>
				<div class="offcanvas-body px-4 pt-lg-0 px-lg-0">
					<?php $current_page = basename($_SERVER['PHP_SELF']); ?>
					<ul class="navbar-nav mx-auto mb-2 mb-lg-0 gap-lg-1">
						<li class="nav-item">
							<a class="nav-link <?php echo ($current_page === 'index.php') ? 'active' : ''; ?>" href="index.php">Home</a>
						</li>
						<li class="nav-item">
							<a class="nav-link" href="index.php#services">Services</a>
						</li>
						<li class="nav-item">
							<a class="nav-link <?php echo ($current_page === 'announcements.php') ? 'active' : ''; ?>"
								href="<?php echo (is_logged_in() && is_admin()) ? 'admin/announcements.php' : 'announcements.php'; ?>">Announcements</a>
						</li>
						<li class="nav-item">
							<a class="nav-link <?php echo ($current_page === 'barangay_officials.php') ? 'active' : ''; ?>" href="barangay_officials.php">Barangay Officials</a>
						</li>
						<li class="nav-item">
							<a class="nav-link" href="index.php#about">About Us</a>
						</li>
						<?php if (is_logged_in() && is_admin()): ?>
							<li class="nav-item">
								<a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? 'active' : ''; ?>" href="admin/index.php">Admin Panel</a>
							</li>
						<?php endif; ?>
					</ul>

					<!-- Right Side Navigation -->
					<ul
						class="navbar-nav ms-auto align-items-lg-center mt-3 mt-lg-0 border-top border-lg-0 pt-3 pt-lg-0 border-light border-opacity-10">
						<?php if (!is_logged_in()): ?>
							<li class="nav-item me-lg-2">
								<a class="nav-link fw-semibold" href="login.php">Log In</a>
							</li>
							<li class="nav-item mt-2 mt-lg-0">
								<a class="btn btn-primary px-4" href="register.php">Get Started</a>
							</li>
						<?php else: ?>
							<!-- Access Portal Button -->
							<li class="nav-item me-lg-3 ms-lg-2 mt-3 mt-lg-0 mb-3 mb-lg-0 d-flex align-items-center">
								<a class="btn btn-primary rounded-pill px-3 shadow-sm text-nowrap" href="dashboard.php"
									style="background:#14b8a6; border-color:#14b8a6; font-size: 0.85rem;">
									<i class="fas fa-layer-group me-2"></i>Access Portal
								</a>
							</li>

							<!-- User Dropdown (Pill) - Desktop Only -->
							<li class="nav-item dropdown d-none d-lg-flex align-items-center">
								<a class="nav-link dropdown-toggle d-flex align-items-center gap-2 p-1 pe-2 pe-md-3 rounded-pill user-pill shadow-sm"
									href="#" id="userDropdown" role="button" data-bs-toggle="dropdown"
									aria-expanded="false" style="background: rgba(0, 0, 0, 0.03); border: 1px solid rgba(0, 0, 0, 0.08);">
									<?php if (!empty($user_avatar)): ?>
										<img src="/<?php echo htmlspecialchars($user_avatar); ?>" alt="Profile"
											class="avatar-circle"
											style="width: 32px; height: 32px; object-fit: cover; border-radius: 50%;">
									<?php else: ?>
										<div class="avatar-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; border-radius: 50%; font-size: 0.8rem;">
											<?php echo strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1)); ?>
										</div>
									<?php endif; ?>
								</a>
								<ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg mt-2 p-2 rounded-4"
									aria-labelledby="userDropdown">
									<li class="px-3 py-2">
										<div class="d-flex align-items-center gap-3">
											<?php if (!empty($user_avatar)): ?>
												<img src="/<?php echo htmlspecialchars($user_avatar); ?>" alt="Profile"
													class="avatar-circle"
													style="width: 48px; height: 48px; object-fit: cover; border-radius: 50%;">
											<?php else: ?>
												<div class="avatar-circle bg-light text-primary d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; border-radius: 50%;">
													<i class="fas fa-user"></i>
												</div>
											<?php endif; ?>
											<div class="d-flex flex-column">
												<span class="fw-bold text-dark"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></span>
												<small class="text-muted"><?php echo is_admin() ? 'Administrator' : 'Resident'; ?></small>
											</div>
										</div>
									</li>
									<li><hr class="dropdown-divider my-2"></li>
									<li><a class="dropdown-item rounded-3" href="profile.php"><i class="fas fa-user me-2 text-muted"></i> Profile</a></li>
									<li><a class="dropdown-item rounded-3" href="id_print.php"><i class="fas fa-print me-2 text-muted"></i> ID Card</a></li>
									<li><hr class="dropdown-divider my-2"></li>
									<li><a class="dropdown-item rounded-3 text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Log Out</a></li>
								</ul>
							</li>
						<?php endif; ?>
					</ul>
				</div>
			</div>
		</div>
	</nav>

	<main style="padding-top: 80px;">
<?php
require_once __DIR__ . '/../config.php';
if (!is_logged_in()) {
	redirect('login.php');
}
// Prevent admins from accessing resident pages
if (is_admin()) {
	redirect('admin/index.php');
}

// Fetch user data for navbar
$pdo = get_db_connection();
$stmt = $pdo->prepare('SELECT avatar FROM residents WHERE user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user_nav_data = $stmt->fetch();
$nav_avatar = $user_nav_data['avatar'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' : ''; ?>Resident - Brgy. Panungyanan
	</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
	<link href="public/css/styles.css?v=<?php echo time(); ?>" rel="stylesheet">
	<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
		integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
	<link rel="icon" href="public/img/favicon.png">
	<style>
		/* User Dashboard specific styles mimicking Admin layout */
		html,
		body {
			height: 100%;
			overflow-x: hidden;
			background-color: #f8f9fa;
		}

		.container-fluid {
			min-height: 100vh;
			display: flex;
		}

		.container-fluid>.row {
			width: 100%;
			min-height: 100vh;
			margin: 0;
		}

		.sidebar-col {
			height: 100%;
			min-height: 100vh;
			transition: max-width 0.3s ease-in-out, width 0.3s ease-in-out, flex 0.3s ease-in-out;
			overflow-x: hidden;
			background: #062b29;
		}

		.admin-sidebar {
			background: transparent;
			height: 100vh;
			position: sticky;
			top: 0;
			display: flex;
			flex-direction: column;
			box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
		}

		.admin-sidebar .sidebar-header {
			padding: 1.5rem;
			border-bottom: 1px solid rgba(255, 255, 255, 0.05);
			flex-shrink: 0;
		}

		.admin-sidebar .sidebar-brand {
			color: white;
			text-decoration: none;
			font-weight: 600;
			font-size: 1.1rem;
			transition: all 0.3s ease;
		}

		.brand-text {
			white-space: nowrap;
			overflow: hidden;
			transition: all 0.3s ease;
			opacity: 1;
		}

		.admin-sidebar .sidebar-brand img {
			width: 40px;
			height: 40px;
			margin-right: 12px;
			background: white;
			border-radius: 50%;
			padding: 2px;
		}

		.admin-sidebar .nav-link {
			color: rgba(255, 255, 255, 0.6);
			padding: 0.85rem 1.25rem;
			margin: 4px 15px;
			border-radius: 12px;
			transition: all 0.2s ease;
			display: flex;
			align-items: center;
			white-space: nowrap;
			overflow: hidden;
			text-decoration: none;
			font-weight: 500;
		}

		.admin-sidebar .nav-link:hover {
			color: #fff;
			background-color: rgba(255, 255, 255, 0.05);
		}

		.admin-sidebar .nav-link.active {
			color: #fff !important;
			background-color: #14b8a6 !important;
			box-shadow: 0 4px 12px rgba(20, 184, 166, 0.25);
		}

		.admin-sidebar .nav-link i {
			width: 20px;
			font-size: 1.1rem;
			margin-right: 12px;
		}

		.admin-sidebar .nav {
			flex: 1;
			overflow-y: auto;
			overflow-x: hidden;
			scrollbar-width: none;
		}

		.admin-sidebar .nav::-webkit-scrollbar {
			display: none;
		}

		.content-col {
			transition: max-width 0.3s ease-in-out, width 0.3s ease-in-out, flex 0.3s ease-in-out;
		}

		.admin-topbar>div {
			width: 100%;
		}

		.admin-topbar {
			background: white;
			box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
			padding: 1rem 2rem;
			margin-bottom: 2rem;
		}

		.admin-content {
			padding: 0 2rem 2rem;
			overflow-y: auto;
			height: calc(100vh - 120px);
		}

		.admin-stats-card {
			color: white;
			border-radius: 15px;
			padding: 1.5rem;
			margin-bottom: 1rem;
			box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
		}

		.admin-stats-card.success {
			background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
		}

		.admin-stats-card.warning {
			background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
		}

		.admin-stats-card.info {
			background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
		}

		.admin-stats-card.danger {
			background: linear-gradient(135deg, #f53d2d 0%, #c41e0f 100%);
		}

		.breadcrumb {
			background: transparent;
			padding: 0;
			margin-bottom: 1rem;
		}

		.breadcrumb-item a {
			color: #007bff;
			text-decoration: none;
		}

		.breadcrumb-item.active {
			color: #6c757d;
		}

		/* Mobile responsive */
		@media (max-width: 767.98px) {
			.sidebar-col {
				position: fixed !important;
				top: 0;
				left: 0;
				height: 100vh !important;
				width: 260px !important;
				flex: 0 0 260px !important;
				max-width: 260px !important;
				z-index: 1045;
				transform: translateX(-100%);
				transition: transform 0.3s ease !important;
			}

			body.mobile-sidebar-open .sidebar-col {
				transform: translateX(0) !important;
			}

			.content-col {
				width: 100% !important;
				flex: 0 0 100% !important;
				max-width: 100% !important;
				margin-left: 0 !important;
			}

			.admin-content {
				height: auto;
				padding: 0 0.75rem 1rem !important;
				overflow-x: hidden;
			}

			.admin-topbar {
				padding: 0.6rem 1rem !important;
				margin-bottom: 1rem;
			}

			.admin-topbar h4 {
				font-size: 1rem;
			}

			.sidebar-overlay {
				display: none;
				position: fixed;
				inset: 0;
				background: rgba(0, 0, 0, 0.5);
				z-index: 1040;
			}

			body.mobile-sidebar-open .sidebar-overlay {
				display: block;
			}

			#mobileSidebarToggle {
				display: inline-flex !important;
			}

			#sidebarToggle {
				display: none !important;
			}
		}

		/* Collapsed Sidebar Styles */
		@media (min-width: 768px) {
			body.sidebar-collapsed .sidebar-col {
				width: 80px !important;
				flex: 0 0 80px !important;
				max-width: 80px !important;
			}

			body.sidebar-collapsed .content-col {
				width: calc(100% - 80px) !important;
				flex: 0 0 calc(100% - 80px) !important;
				max-width: calc(100% - 80px) !important;
			}

			body.sidebar-collapsed .brand-text {
				opacity: 0 !important;
				width: 0 !important;
				display: none;
			}

			body.sidebar-collapsed .admin-sidebar .sidebar-brand {
				justify-content: center !important;
				margin: 0 !important;
				padding: 0 !important;
			}

			body.sidebar-collapsed .admin-sidebar .sidebar-brand img {
				margin-right: 0 !important;
			}

			body.sidebar-collapsed .admin-sidebar .sidebar-header {
				padding: 1.25rem 0 !important;
				justify-content: center !important;
				flex-direction: column !important;
			}

			body.sidebar-collapsed #sidebarToggle {
				margin-top: 15px;
				margin-left: 0 !important;
				margin-right: 0 !important;
			}

			body.sidebar-collapsed .admin-sidebar .nav-link {
				text-align: center !important;
				padding: 0.9rem 0 !important;
				font-size: 0 !important;
				display: flex !important;
				justify-content: center !important;
				align-items: center !important;
			}

			body.sidebar-collapsed .admin-sidebar .nav-link i {
				margin-right: 0 !important;
				font-size: 1.25rem !important;
				display: block !important;
			}
		}

		.chatbot-top-btn {
			width: 40px;
			height: 40px;
		}
		
		@media (max-width: 767.98px) {
			.chatbot-top-btn {
				width: 35px;
				height: 35px;
			}
			.chatbot-top-btn i {
				font-size: 0.9rem;
			}
		}

		/* Hide floating toggle in dashboard to avoid redundancy with top bar button */
		.chatbot-toggle {
			display: none !important;
		}
	</style>
</head>

<body>
	<script>
		if (localStorage.getItem('userSidebarCollapsed') === 'true') {
			document.body.classList.add('sidebar-collapsed');
		}
	</script>
	<script>
		document.addEventListener("DOMContentLoaded", function () {
			var mobileBtn = document.getElementById("mobileSidebarToggle");
			var overlay = document.getElementById("sidebarOverlay");
			if (mobileBtn) {
				mobileBtn.addEventListener("click", function () { document.body.classList.toggle("mobile-sidebar-open"); });
			}
			if (overlay) {
				overlay.addEventListener("click", function () { document.body.classList.remove("mobile-sidebar-open"); });
			}
		});
	</script>

	<div class="container-fluid p-0">
		<div class="row">
			<!-- User Sidebar -->
			<div class="sidebar-overlay" id="sidebarOverlay"></div>
			<div class="col-md-3 col-lg-2 px-0 sidebar-col">
				<div class="admin-sidebar">
					<div class="sidebar-header d-flex justify-content-between align-items-center px-3 py-4">
						<a href="index.php" class="sidebar-brand d-flex align-items-center text-decoration-none me-2">
							<img src="public/img/barangaylogo.png" alt="Barangay Logo">
							<div class="brand-text">
								<div class="fw-bold fs-5">Resident</div>
							</div>
						</a>
						<button class="btn btn-sm text-white d-none d-md-block" id="sidebarToggle"
							title="Toggle Sidebar"
							style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); cursor: pointer; z-index: 1050; position: relative;"
							onclick="document.body.classList.toggle('sidebar-collapsed'); localStorage.setItem('userSidebarCollapsed', document.body.classList.contains('sidebar-collapsed'));">
							<i class="fas fa-bars"></i>
						</button>
					</div>

					<nav class="nav flex-column flex-nowrap w-100 mt-2" style="overflow-x: hidden;">
						<a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>"
							href="dashboard.php">
							<i class="fas fa-layer-group"></i> Dashboard
						</a>
						<a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>"
							href="profile.php">
							<i class="fas fa-user-circle"></i> Profile
						</a>
						<a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'family_members.php') ? 'active' : ''; ?>"
							href="family_members.php">
							<i class="fas fa-users"></i> Family Member
						</a>
						<a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'portal_officials.php' ? 'active' : ''; ?>"
							href="portal_officials.php">
							<i class="fas fa-user-shield"></i> Barangay Officials
						</a>
						<a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'portal_announcements.php') ? 'active' : ''; ?>"
							href="portal_announcements.php">
							<i class="fas fa-bullhorn"></i> Announcements
						</a>
						<a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'requests.php' ? 'active' : ''; ?>"
							href="requests.php">
							<i class="fas fa-file-alt"></i> Documents
						</a>
						<a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'incidents.php' ? 'active' : ''; ?>"
							href="incidents.php">
							<i class="fas fa-exclamation-triangle"></i> Report Incident
						</a>
						<script>
							// Script for chevron rotation
							document.addEventListener('DOMContentLoaded', function () {
								var profileMenu = document.getElementById('profileSubmenu');
								if (profileMenu) {
									profileMenu.addEventListener('show.bs.collapse', function () {
										document.querySelector('[data-bs-target="#profileSubmenu"] .dropdown-icon').style.transform = 'rotate(180deg)';
									});
									profileMenu.addEventListener('hide.bs.collapse', function () {
										document.querySelector('[data-bs-target="#profileSubmenu"] .dropdown-icon').style.transform = 'rotate(0deg)';
									});
								}
							});
						</script>
					</nav>

					<div class="mt-auto border-top border-light border-opacity-10 w-100">
						<a class="nav-link py-3 w-100" href="logout.php">
							<i class="fas fa-sign-out-alt"></i> Logout
						</a>
					</div>
				</div>
			</div>

			<!-- Main Content Area -->
			<div class="col-md-9 col-lg-10 content-col">
				<!-- Top Bar -->
				<div class="admin-topbar">
					<div class="d-flex justify-content-between align-items-center">
						<div class="d-flex align-items-center gap-2">
							<button class="btn btn-sm btn-outline-secondary d-md-none" id="mobileSidebarToggle"
								style="display:none;">
								<i class="fas fa-bars"></i>
							</button>
							<div>
								<h4 class="mb-0 fw-bold">
									<?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Dashboard'; ?></h4>
							</div>
						</div>
						<div class="d-flex align-items-center gap-3">
							<div class="d-flex align-items-center gap-2">
								<button
									class="shadow rounded-circle d-flex align-items-center justify-content-center pt-1 chatbot-top-btn"
									style="background-color: #11998e; color: white; border: none; cursor: pointer; transition: transform 0.2s;"
									onmouseover="this.style.transform='scale(1.05)'"
									onmouseout="this.style.transform='scale(1)'" onclick="toggleCustomChatbot(event)"
									title="Ask AI Assistant">
									<i class="fas fa-comments"></i>
								</button>
								<script>
									function toggleCustomChatbot(e) {
										if (e) e.stopPropagation();
										var p = document.getElementById('chatbotPanel');
										if (p) {
											p.classList.toggle('active');
											if (p.classList.contains('active')) {
												var input = document.getElementById('chatbotInput');
												if (input) setTimeout(() => input.focus(), 100);
											}
										} else {
											alert('Pindotin ulit, naglo-load pa po ang chatbot UI...');
										}
									}
								</script>
							</div>

							<!-- Global Notification Dropdown -->
							<div class="dropdown" id="topbarNotifDropdown">
								<button class="btn btn-outline-secondary position-relative border-0" type="button"
									data-bs-toggle="dropdown" aria-expanded="false" id="notif-bell-btn">
									<i class="fas fa-bell fs-5"></i>
									<span
										class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-white"
										id="topbarNotifBadge"
										style="display: none; font-size: 0.65rem; margin-left: -5px; margin-top: 5px;">
										0
									</span>
								</button>
								<div class="dropdown-menu dropdown-menu-end shadow-lg border-0 p-0"
									style="width: 320px; max-height: 450px; overflow: hidden;">
									<div
										class="p-3 border-bottom d-flex justify-content-between align-items-center bg-light">
										<h6 class="mb-0 fw-bold">Notifications</h6>
										<button class="btn btn-sm text-primary p-0 bg-transparent border-0"
											onclick="markAllRead()" style="font-size: 0.75rem;">Mark all read</button>
									</div>
									<div id="topbarNotifList" class="list-group list-group-flush"
										style="max-height: 350px; overflow-y: auto;">
										<div class="p-4 text-center text-muted">
											<i class="fas fa-spinner fa-spin mb-2"></i><br>
											<small>Loading notifications...</small>
										</div>
									</div>
									<div
										class="p-2 border-top d-flex justify-content-between align-items-center bg-light px-3">
										<button
											class="btn btn-sm text-secondary p-0 bg-transparent border-0 small hover-opacity-75"
											onclick="deleteReadNotifications()" id="clear-read-btn"
											title="Delete notifications you have already read">
											<i class="fas fa-trash-alt me-1"></i>Clear Read
										</button>
										<a href="dashboard.php"
											class="text-primary text-decoration-none small fw-bold">View Dashboard</a>
									</div>
								</div>
							</div>

							<div class="dropdown">
								<button
									class="btn border-0 p-1 pe-3 text-dark dropdown-toggle d-flex align-items-center gap-2 rounded-pill shadow-sm bg-light"
									type="button" data-bs-toggle="dropdown">
									<?php if ($nav_avatar && file_exists(__DIR__ . '/../' . $nav_avatar)): ?>
										<img src="<?php echo htmlspecialchars($nav_avatar); ?>" 
											class="rounded-circle shadow-sm"
											style="width: 36px; height: 36px; object-fit: cover; border: 2px solid white;">
									<?php else: ?>
										<div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold"
											style="width: 36px; height: 36px; font-size: 0.9rem;">
											<?php echo strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1)); ?>
										</div>
									<?php endif; ?>
								</button>
								<ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2 p-2 rounded-4">
									<li class="px-3 py-2 d-md-none">
										<span class="fw-bold d-block text-dark small"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></span>
										<small class="text-muted" style="font-size: 0.7rem;">Resident</small>
										<hr class="my-2">
									</li>
									<li><a class="dropdown-item rounded-3 py-2" href="profile.php"><i
												class="fas fa-user me-2 text-muted"></i> Profile</a></li>
									<li><a class="dropdown-item rounded-3 py-2" href="id_print.php"><i
												class="fas fa-print me-2 text-muted"></i> ID Card</a></li>
									<li>
										<hr class="dropdown-divider">
									</li>
									<li><a class="dropdown-item py-2 text-danger rounded-3" href="logout.php"><i
												class="fas fa-sign-out-alt me-2"></i>Log Out</a></li>
								</ul>
							</div>
						</div>
					</div>
				</div>

				<!-- Content Area -->
				<div class="admin-content">
<?php
ob_start();
require_once __DIR__ . '/../config.php';

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
	<title>Admin Dashboard - Brgy. Panungyanan IS</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
	<link href="../public/css/styles.css?v=<?php echo time(); ?>" rel="stylesheet">
	<link href="../public/css/navbar-enhancements.css" rel="stylesheet">
	<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
		integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
	<link rel="icon" href="../public/img/favicon.png">
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
	<style>
		/* Admin-specific styles */
		html,
		body {
			height: 100%;
			overflow-x: hidden;
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
		}

		.admin-sidebar {
			background: #0d2c2a;
			min-height: 100vh;
			height: 100%;
			display: flex;
			flex-direction: column;
			box-shadow: 1px 0 0 rgba(0, 0, 0, 0.1);
			border-right: 1px solid rgba(255, 255, 255, 0.05);
		}

		.admin-sidebar .sidebar-header {
			padding: 1.25rem;
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 10px;
		}

		.admin-sidebar .sidebar-header img {
			width: 42px;
			height: 42px;
			object-fit: contain;
			background: white;
			border-radius: 50%;
			padding: 2px;
			box-shadow: 0 0 10px rgba(255, 255, 255, 0.2);
		}

		.brand-text {
			color: #fff;
			white-space: nowrap;
			overflow: hidden;
			transition: all 0.3s ease;
		}

		.admin-sidebar .nav-link {
			color: rgba(255, 255, 255, 0.6);
			padding: 0.85rem 1.25rem;
			margin: 2px 12px;
			border-radius: 10px;
			transition: all 0.2s ease;
			display: flex;
			align-items: center;
			gap: 12px;
			text-decoration: none;
			font-size: 0.9rem;
			font-weight: 500;
		}

		.admin-sidebar .nav-link:hover {
			color: #fff;
			background-color: rgba(255, 255, 255, 0.05);
		}

		.admin-sidebar .nav-link.active i {
			color: #fff;
		}

		.admin-sidebar .nav-link.active {
			color: #fff !important;
			background-color: #14b8a6 !important;
			box-shadow: 0 4px 12px rgba(20, 184, 166, 0.25);
		}

		/*
		 * Document Requests submenu: non-active links were picking up the same hover style as .active
		 * (yellow bar + panel), so “All Requests” looked selected on filtered pages. Only the item with
		 * .active gets the full highlight; others get a lighter hover.
		 */
		#requestsSubmenu .nav-link:not(.active),
		#incidentsSubmenu .nav-link:not(.active) {
			border-left-color: transparent;
			background-color: transparent;
		}

		#requestsSubmenu .nav-link:not(.active):hover,
		#incidentsSubmenu .nav-link:not(.active):hover {
			color: rgba(255, 255, 255, 0.95);
			background-color: rgba(255, 255, 255, 0.06);
			border-left-color: rgba(255, 255, 255, 0.22);
		}

		#requestsSubmenu .nav-link.active,
		#incidentsSubmenu .nav-link.active {
			color: white;
			background-color: rgba(255, 255, 255, 0.1);
			border-left-color: #ffc107;
		}

		/* Manual toggle fallbacks - scoped to sidebar */
		.admin-sidebar .collapse.show {
			display: block !important;
		}

		.admin-sidebar .collapse:not(.show) {
			display: none !important;
		}

		.admin-sidebar .nav-link i {
			width: 20px;
			margin-right: 10px;
			pointer-events: none;
			/* Icon won't block the link click */
		}

		.admin-sidebar .nav {
			flex: 1;
			overflow-y: auto;
			overflow-x: hidden;
			scrollbar-width: none;
			/* Firefox */
		}

		.admin-sidebar .nav::-webkit-scrollbar {
			display: none;
			/* Chrome/Safari */
		}

		.admin-sidebar .sidebar-header {
			flex-shrink: 0;
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

		/* Text Truncation Utility */
		.text-truncate-2 {
			display: -webkit-box;
			-webkit-line-clamp: 2;
			-webkit-box-orient: vertical;
			overflow: hidden;
		}

		.admin-stats-card {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: white;
			border-radius: 15px;
			padding: 1.5rem;
			margin-bottom: 1rem;
			box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
		}

		/* Make dashboard stats cards clickable */
		.admin-stats-link {
			display: block;
			text-decoration: none;
			color: inherit;
		}

		.admin-stats-link .admin-stats-card {
			cursor: pointer;
			transition: transform 0.15s ease, box-shadow 0.15s ease, opacity 0.15s ease;
		}

		.admin-stats-link:hover .admin-stats-card {
			transform: translateY(-2px);
			box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
			opacity: 0.98;
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

		.admin-stats-card .stats-icon {
			font-size: 2.5rem;
			opacity: 0.8;
		}

		.admin-stats-card .stats-number {
			font-size: 2rem;
			font-weight: 700;
			margin: 0.5rem 0;
		}

		.admin-stats-card .stats-label {
			font-size: 0.9rem;
			opacity: 0.9;
		}

		.admin-quick-actions {
			background: white;
			border-radius: 15px;
			padding: 1.5rem;
			box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
			margin-bottom: 2rem;
		}

		.admin-quick-actions .action-card {
			background: #f8f9fa;
			border-radius: 10px;
			padding: 1.5rem;
			text-decoration: none;
			color: #333;
			transition: all 0.3s ease;
			border: 2px solid transparent;
		}

		.admin-quick-actions .action-card:hover {
			background: #e9ecef;
			border-color: #007bff;
			transform: translateY(-2px);
			box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
		}

		.admin-quick-actions .action-card i {
			font-size: 2rem;
			color: #007bff;
			margin-bottom: 1rem;
		}

		.admin-table {
			background: white;
			border-radius: 15px;
			overflow: hidden;
			box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
		}

		.admin-table .table {
			margin-bottom: 0;
		}

		.admin-table .table thead th {
			background: #f8f9fa;
			border: none;
			font-weight: 600;
			color: #495057;
			padding: 1rem;
		}

		.admin-table .table tbody td {
			padding: 1rem;
			border-color: #e9ecef;
		}

		.admin-table .table tbody tr:hover {
			background-color: #f8f9fa;
		}



		/* Mobile responsive admin layout */
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

			.container-fluid {
				height: auto;
			}

			.admin-sidebar {
				height: 100vh;
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

		/* Support Chats Mobile Responsive */
		@media (max-width: 991.98px) {
			#chatListContainer {
				display: block;
			}

			#chatWindowContainer {
				display: none;
			}

			#chatListContainer .card-body {
				max-height: calc(100vh - 300px) !important;
			}

			#chatWindowContainer .card {
				position: fixed;
				top: 0;
				left: 0;
				right: 0;
				bottom: 0;
				border-radius: 0;
				z-index: 1050;
			}

			#chatWindowContainer #chatMessages {
				max-height: calc(100vh - 200px) !important;
			}

			.btn-group-sm .btn {
				font-size: 0.7rem;
				padding: 0.25rem 0.5rem;
			}

			.list-group-item {
				padding: 0.75rem;
			}

			.list-group-item h6 {
				font-size: 0.9rem;
			}

			.list-group-item p {
				font-size: 0.8rem;
				margin-bottom: 0.25rem !important;
			}

			.list-group-item small {
				font-size: 0.7rem;
			}
		}

		@media (max-width: 575.98px) {
			.admin-content {
				padding: 0 0.75rem 1rem !important;
			}

			.admin-topbar>div {
				width: 100%;
			}

			.admin-topbar {
				padding: 0.75rem 1rem !important;
				margin-bottom: 1rem !important;
			}

			.admin-topbar h4 {
				font-size: 1.1rem;
			}



			.card-header h5 {
				font-size: 1rem;
			}

			.btn-group-sm .btn {
				font-size: 0.65rem;
				padding: 0.2rem 0.4rem;
			}

			#chatListContainer .card-body {
				max-height: calc(100vh - 250px) !important;
			}

			.message-bubble {
				max-width: 90% !important;
				padding: 0.5rem 0.75rem !important;
				font-size: 0.85rem;
			}

			#chatInputContainer {
				padding: 0.75rem !important;
			}

			#chatInputContainer .form-control {
				font-size: 0.9rem;
			}

			#chatInputContainer .btn {
				padding: 0.5rem 0.75rem;
				font-size: 0.85rem;
			}
		}

		/* Chat message bubbles responsive */
		@media (max-width: 767.98px) {
			.message-bubble {}
		}

		/* Collapsed Sidebar Styles */
		.sidebar-col {
			transition: max-width 0.3s ease-in-out, width 0.3s ease-in-out, flex 0.3s ease-in-out;
			overflow-x: hidden;
		}

		.content-col {
			transition: max-width 0.3s ease-in-out, width 0.3s ease-in-out, flex 0.3s ease-in-out;
		}

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

			body.sidebar-collapsed .admin-sidebar .nav-link span {
				display: none !important;
			}

			body.sidebar-collapsed .admin-sidebar .nav-link i {
				margin-right: 0 !important;
				font-size: 1.2rem !important;
				display: block !important;
			}

			body.sidebar-collapsed .mt-auto a.nav-link {
				justify-content: center !important;
				padding: 0.9rem 0 !important;
				font-size: 0 !important;
			}

			body.sidebar-collapsed .mt-auto a.nav-link i {
				margin-right: 0 !important;
				font-size: 1.2rem !important;
			}

			/* Hide submenus and arrows when sidebar is collapsed */
			body.sidebar-collapsed .admin-sidebar .collapse,
			body.sidebar-collapsed .admin-sidebar .fa-chevron-down {
				display: none !important;
			}
		}

		/* Hide floating toggle in admin dashboard */
		.chatbot-toggle {
			display: none !important;
		}
	</style>
</head>

<body>
	<script>
		if (localStorage.getItem('sidebarCollapsed') === 'true') {
			document.body.classList.add('sidebar-collapsed');
		}
	</script>
	<script>
		// Mobile sidebar toggle
		document.addEventListener("DOMContentLoaded", function () {
			var mobileBtn = document.getElementById("mobileSidebarToggle");
			var overlay = document.getElementById("sidebarOverlay");
			if (mobileBtn) {
				mobileBtn.addEventListener("click", function () {
					document.body.classList.toggle("mobile-sidebar-open");
				});
			}
			if (overlay) {
				overlay.addEventListener("click", function () {
					document.body.classList.remove("mobile-sidebar-open");
				});
			}
		});
	</script>
	<div class="container-fluid p-0">
		<div class="row">
			<!-- Admin Sidebar -->
			<div class="sidebar-overlay" id="sidebarOverlay"></div>
			<div class="col-md-3 col-lg-2 px-0 sidebar-col">
				<div class="admin-sidebar">
					<div class="sidebar-header d-flex justify-content-between align-items-center px-3 py-4">
						<a href="index.php" class="sidebar-brand d-flex align-items-center text-decoration-none me-2"
							title="Brgy. Panungyanan Admin">
							<img src="../public/img/barangaylogo.png" alt="Barangay Logo">
							<div class="brand-text">
								<div class="fw-bold fs-5"><?php echo ($_SESSION['user_id'] == 1) ? 'Admin' : 'Sub-Admin'; ?></div>
							</div>
						</a>
						<button class="btn btn-sm text-white d-none d-md-block" id="sidebarToggle"
							title="Toggle Sidebar"
							style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); cursor: pointer; z-index: 1050; position: relative;"
							onclick="document.body.classList.toggle('sidebar-collapsed'); localStorage.setItem('sidebarCollapsed', document.body.classList.contains('sidebar-collapsed'));">
							<i class="fas fa-bars"></i>
						</button>
					</div>

					<nav class="nav flex-column flex-nowrap w-100" style="overflow-x: hidden;">
						<?php
						$admin_req_basename = basename($_SERVER['PHP_SELF']);
						$admin_docreq_nav = [
							'requests.php' => '',
							'requests_pending.php' => 'pending',
							'requests_approved.php' => 'approved',
							'requests_released.php' => 'released',
							'requests_rejected.php' => 'rejected',
							'requests_cancelled.php' => 'canceled',
						];
						$admin_req_status_filter = $admin_docreq_nav[$admin_req_basename] ?? '';
						$admin_req_in_docreq_section = isset($admin_docreq_nav[$admin_req_basename]) || $admin_req_basename === 'barangay_clearances.php';

						$admin_inc_basename = basename($_SERVER['PHP_SELF']);
						$admin_inc_nav = [
							'incidents.php' => '',
							'incidents_pending.php' => 'submitted',
							'incidents_review.php' => 'in_review',
							'incidents_resolved.php' => 'resolved',
							'incidents_rejected.php' => 'closed',
							'incidents_cancelled.php' => 'canceled',
						];
						$admin_inc_status_filter = $admin_inc_nav[$admin_inc_basename] ?? '';
						$admin_inc_in_inc_section = isset($admin_inc_nav[$admin_inc_basename]);
						?>
						<a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>"
							href="index.php" title="Dashboard">
							<i class="fas fa-tachometer-alt"></i>
							<span>Dashboard</span>
						</a>

						<?php if ($_SESSION['user_id'] == 1): ?>
						<?php
						$is_acc_mgmt_section = (in_array($admin_req_basename, ['register_account.php', 'resident_records.php', 'account_management.php', 'admin_info.php', 'sub_admin_management.php', 'admin_info_view.php']));
						$is_admin_acc_section = (in_array($admin_req_basename, ['admin_info.php', 'sub_admin_management.php', 'admin_info_view.php']));
						?>
						<a class="nav-link <?php echo $is_acc_mgmt_section ? 'active' : ''; ?>"
							href="javascript:void(0)" onclick="manualSidebarCollapse('accMgmtSubmenu')"
							style="cursor:pointer;" title="Account Management">
							<i class="fas fa-users-cog"></i>
							<span>Account Management</span>
							<i class="fas fa-chevron-down ms-auto" style="font-size: 0.7rem;"></i>
						</a>
						<div class="collapse <?php echo $is_acc_mgmt_section ? 'show' : ''; ?>" id="accMgmtSubmenu">
							<a class="nav-link ps-5 py-2 <?php echo ($admin_req_basename === 'register_account.php') ? 'active' : ''; ?>"
								href="register_account.php" style="font-size: 0.85rem;" title="Register of Account">Register of Account</a>
							<a class="nav-link ps-5 py-2 <?php echo ($admin_req_basename === 'resident_records.php') ? 'active' : ''; ?>"
								href="resident_records.php" style="font-size: 0.85rem;" title="All resident">All
								resident</a>
							<a class="nav-link ps-5 py-2 <?php echo ($admin_req_basename === 'account_management.php') ? 'active' : ''; ?>"
								href="account_management.php" style="font-size: 0.85rem;"
								title="Resident account">Resident account</a>

							<!-- Admin Account Nested Submenu -->
							<a class="nav-link ps-5 py-2 <?php echo $is_admin_acc_section ? 'active' : ''; ?>"
								href="javascript:void(0)" onclick="manualSidebarCollapse('adminAccSubmenu')"
								style="font-size: 0.85rem; cursor:pointer;" title="Admin Account">
								Admin Account <i class="fas fa-chevron-down ms-auto" style="font-size: 0.6rem;"></i>
							</a>
							<div class="collapse <?php echo $is_admin_acc_section ? 'show' : ''; ?>"
								id="adminAccSubmenu">
								<a class="nav-link ps-5 py-2 <?php echo ($admin_req_basename === 'admin_info_view.php' && ($_GET['id'] ?? 0) == 1) ? 'active' : ''; ?>"
									href="admin_info_view.php?id=1" style="padding-left: 3.5rem !important; font-size: 0.8rem;"
									title="Admin">Admin</a>
								<a class="nav-link ps-5 py-2 <?php echo ($admin_req_basename === 'sub_admin_management.php') ? 'active' : ''; ?>"
									href="sub_admin_management.php"
									style="padding-left: 3.5rem !important; font-size: 0.8rem;" title="Sub admin">Sub
									admin</a>
							</div>
						</div>
						<?php endif; ?>
						<a class="nav-link <?php echo $admin_req_in_docreq_section ? 'active' : ''; ?>"
							href="javascript:void(0)" onclick="manualSidebarCollapse('requestsSubmenu')"
							style="cursor:pointer;" title="Document Requests">
							<i class="fas fa-file-alt"></i>
							<span>Document Requests</span>
							<i class="fas fa-chevron-down ms-auto" style="font-size: 0.7rem;"></i>
						</a>
						<div class="collapse <?php echo $admin_req_in_docreq_section ? 'show' : ''; ?>"
							id="requestsSubmenu">
							<a class="nav-link ps-5 py-2 <?php echo ($admin_req_basename === 'requests.php' && $admin_req_status_filter === '') ? 'active' : ''; ?>"
								href="requests.php" style="font-size: 0.85rem;" title="All Requests">All Requests</a>
							<a class="nav-link ps-5 py-2 <?php echo ($admin_req_status_filter === 'pending') ? 'active' : ''; ?>"
								href="requests_pending.php" style="font-size: 0.85rem;" title="Pending">Pending</a>
							<a class="nav-link ps-5 py-2 <?php echo ($admin_req_status_filter === 'approved') ? 'active' : ''; ?>"
								href="requests_approved.php" style="font-size: 0.85rem;" title="Ready to Pick Up">Ready
								to Pick Up</a>
							<a class="nav-link ps-5 py-2 <?php echo ($admin_req_status_filter === 'released') ? 'active' : ''; ?>"
								href="requests_released.php" style="font-size: 0.85rem;" title="Released">Released</a>
							<a class="nav-link ps-5 py-2 <?php echo ($admin_req_status_filter === 'rejected') ? 'active' : ''; ?>"
								href="requests_rejected.php" style="font-size: 0.85rem;" title="Rejected">Rejected</a>
							<a class="nav-link ps-5 py-2 <?php echo ($admin_req_status_filter === 'canceled') ? 'active' : ''; ?>"
								href="requests_cancelled.php" style="font-size: 0.85rem;" title="Cancelled">Cancelled</a>
						</div>
						<a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'document_types.php' ? 'active' : ''; ?>"
							href="document_types.php" title="Document Types">
							<i class="fas fa-cog"></i>
							<span>Document Types</span>
						</a>
						<a class="nav-link <?php echo $admin_inc_in_inc_section ? 'active' : ''; ?>"
							href="javascript:void(0)" onclick="manualSidebarCollapse('incidentsSubmenu')"
							style="cursor:pointer;" title="Incidents">
							<i class="fas fa-exclamation-triangle"></i>
							<span>Incidents</span>
							<i class="fas fa-chevron-down ms-auto" style="font-size: 0.7rem;"></i>
						</a>
						<div class="collapse <?php echo $admin_inc_in_inc_section ? 'show' : ''; ?>"
							id="incidentsSubmenu">
							<a class="nav-link ps-5 py-2 <?php echo ($admin_inc_basename === 'incidents.php' && $admin_inc_status_filter === '') ? 'active' : ''; ?>"
								href="incidents.php" style="font-size: 0.85rem;" title="All">All</a>
							<a class="nav-link ps-5 py-2 <?php echo ($admin_inc_status_filter === 'submitted') ? 'active' : ''; ?>"
								href="incidents_pending.php" style="font-size: 0.85rem;" title="Pending">Pending</a>
							<a class="nav-link ps-5 py-2 <?php echo ($admin_inc_status_filter === 'in_review') ? 'active' : ''; ?>"
								href="incidents_review.php" style="font-size: 0.85rem;" title="Review">Review</a>
							<a class="nav-link ps-5 py-2 <?php echo ($admin_inc_status_filter === 'resolved') ? 'active' : ''; ?>"
								href="incidents_resolved.php" style="font-size: 0.85rem;" title="Resolved">Resolved</a>
							<a class="nav-link ps-5 py-2 <?php echo ($admin_inc_status_filter === 'closed') ? 'active' : ''; ?>"
								href="incidents_rejected.php" style="font-size: 0.85rem;" title="Rejected">Rejected</a>
							<a class="nav-link ps-5 py-2 <?php echo ($admin_inc_status_filter === 'canceled') ? 'active' : ''; ?>"
								href="incidents_cancelled.php" style="font-size: 0.85rem;"
								title="Cancelled by Resident">Cancelled by Resident</a>
						</div>
						<a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'support_chats.php' ? 'active' : ''; ?>"
							href="support_chats.php" title="Support Chats">
							<i class="fas fa-comments"></i>
							<span>Support Chats</span>
							<span class="badge bg-danger ms-2" id="supportChatBadge" style="display: none;">0</span>
						</a>
						<a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'barangay_officials.php' ? 'active' : ''; ?>"
							href="barangay_officials.php" title="Barangay Officials">
							<i class="fas fa-user-shield"></i>
							<span>Barangay Officials</span>
						</a>
						<a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'announcements.php' ? 'active' : ''; ?>"
							href="announcements.php" title="Announcements">
							<i class="fas fa-bullhorn"></i>
							<span>Announcements</span>
						</a>
						<a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>"
							href="reports.php" title="Reports">
							<i class="fas fa-chart-bar"></i>
							<span>Reports</span>
						</a>
					</nav>

					<div class="mt-auto border-top border-light border-opacity-10 w-100">
						<a class="nav-link py-3 w-100" href="../logout.php" title="Logout">
							<i class="fas fa-sign-out-alt"></i>
							<span>Logout</span>
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
								<h4 class="mb-0"><?php echo isset($page_title) ? $page_title : 'Admin Dashboard'; ?>
								</h4>

							</div>
						</div>
						<div class="d-flex align-items-center gap-3">
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
										<a href="index.php" class="text-primary text-decoration-none small fw-bold">View
											Dashboard</a>
									</div>
								</div>
							</div>

							<div class="dropdown">
								<button
									class="btn border-0 p-1 pe-3 text-dark dropdown-toggle d-flex align-items-center gap-2 rounded-pill shadow-sm bg-light"
									type="button" data-bs-toggle="dropdown">
									<?php if ($nav_avatar && file_exists(__DIR__ . '/../' . $nav_avatar)): ?>
										<img src="../<?php echo htmlspecialchars($nav_avatar); ?>" 
											class="rounded-circle shadow-sm"
											style="width: 36px; height: 36px; object-fit: cover; border: 2px solid white;">
									<?php else: ?>
										<div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold"
											style="width: 36px; height: 36px; font-size: 0.9rem;">
											<?php echo strtoupper(substr($_SESSION['full_name'] ?? 'A', 0, 1)); ?>
										</div>
									<?php endif; ?>
								</button>
								<ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2 p-2 rounded-4">
									<li class="px-3 py-2 d-md-none">
										<span class="fw-bold d-block text-dark small"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Administrator'); ?></span>
										<small class="text-muted" style="font-size: 0.7rem;"><?php echo ($_SESSION['user_id'] == 1) ? 'System Admin' : 'Sub-Admin'; ?></small>
										<hr class="my-2">
									</li>
									<li><a class="dropdown-item rounded-3 py-2" href="admin_info_view.php?id=<?php echo $_SESSION['user_id']; ?>"><i
												class="fas fa-user-circle me-2 text-muted"></i> My Profile</a></li>
									<li>
										<hr class="dropdown-divider">
									</li>
									<li><a class="dropdown-item py-2 text-danger rounded-3" href="../logout.php"><i
												class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
								</ul>
							</div>
						</div>
					</div>
				</div>


				<script>
					function manualSidebarCollapse(id) {
						var el = document.getElementById(id);
						if (!el) return;
						if (el.classList.contains('show')) {
							el.classList.remove('show');
						} else {
							el.classList.add('show');
						}
					}
				</script>

				<!-- Content Area -->
				<div class="admin-content">

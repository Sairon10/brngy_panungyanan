<?php
require_once __DIR__ . '/../config.php';
if (!is_admin())
	redirect('../index.php');
$page_title = ($_SESSION['user_id'] == 1) ? 'Admin Dashboard' : 'Sub-Admin Dashboard';

require_once __DIR__ . '/header.php';
?>

<?php
$pdo = get_db_connection();

// --- Date Filter Logic ---
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$date_where = "";
if (!empty($date_from) && !empty($date_to)) {
	$date_where = " AND DATE(created_at) BETWEEN " . $pdo->quote($date_from) . " AND " . $pdo->quote($date_to);
} elseif (!empty($date_from)) {
	$date_where = " AND DATE(created_at) >= " . $pdo->quote($date_from);
} elseif (!empty($date_to)) {
	$date_where = " AND DATE(created_at) <= " . $pdo->quote($date_to);
}

// Special case for residents/users where created_at naming might differ or be absent
// But in this system, both resident_records and family_members have created_at.

// --- Documents Request Stats (Unified) ---
$pending_docs = (int) ($pdo->query("SELECT COUNT(*) as c FROM document_requests WHERE status='pending' $date_where")->fetch()['c'] ?? 0);
$pending_clear = (int) ($pdo->query("SELECT COUNT(*) as c FROM barangay_clearances WHERE status='pending' $date_where")->fetch()['c'] ?? 0);
$total_pending = $pending_docs + $pending_clear;

$approved_docs = (int) ($pdo->query("SELECT COUNT(*) as c FROM document_requests WHERE status='approved' $date_where")->fetch()['c'] ?? 0);
$approved_clear = (int) ($pdo->query("SELECT COUNT(*) as c FROM barangay_clearances WHERE status='approved' $date_where")->fetch()['c'] ?? 0);
$total_approved = $approved_docs + $approved_clear;

$released_docs = (int) ($pdo->query("SELECT COUNT(*) as c FROM document_requests WHERE status='released' $date_where")->fetch()['c'] ?? 0);
$released_clear = (int) ($pdo->query("SELECT COUNT(*) as c FROM barangay_clearances WHERE status='released' $date_where")->fetch()['c'] ?? 0);
$total_released = $released_docs + $released_clear;

$rejected_docs = (int) ($pdo->query("SELECT COUNT(*) as c FROM document_requests WHERE status='rejected' $date_where")->fetch()['c'] ?? 0);
$rejected_clear = (int) ($pdo->query("SELECT COUNT(*) as c FROM barangay_clearances WHERE status='rejected' $date_where")->fetch()['c'] ?? 0);
$total_rejected = $rejected_docs + $rejected_clear;

// --- Incidents Stats ---
$incidents_submitted = (int) ($pdo->query("SELECT COUNT(*) as c FROM incidents i JOIN users u ON i.user_id = u.id WHERE i.status='submitted' " . str_replace('created_at', 'i.created_at', $date_where) . "")->fetch()['c'] ?? 0);
$incidents_review = (int) ($pdo->query("SELECT COUNT(*) as c FROM incidents i JOIN users u ON i.user_id = u.id WHERE i.status='in_review' " . str_replace('created_at', 'i.created_at', $date_where) . "")->fetch()['c'] ?? 0);
$incidents_resolved = (int) ($pdo->query("SELECT COUNT(*) as c FROM incidents i JOIN users u ON i.user_id = u.id WHERE i.status='resolved' " . str_replace('created_at', 'i.created_at', $date_where) . "")->fetch()['c'] ?? 0);
$incidents_rejected = (int) ($pdo->query("SELECT COUNT(*) as c FROM incidents i JOIN users u ON i.user_id = u.id WHERE i.status='closed' " . str_replace('created_at', 'i.created_at', $date_where) . "")->fetch()['c'] ?? 0);
$incidents_canceled = (int) ($pdo->query("SELECT COUNT(*) as c FROM incidents i JOIN users u ON i.user_id = u.id WHERE i.status='canceled' " . str_replace('created_at', 'i.created_at', $date_where) . "")->fetch()['c'] ?? 0);

// --- General Stats (Population Overview) ---
$pop_date_query = !empty($date_where) ? " WHERE " . substr($date_where, 5) : "";

// Count unique residents (Users with role 'resident', 'admin', 'sub_admin')
$total_users = (int) ($pdo->query("SELECT COUNT(*) as c FROM users WHERE role IN ('resident', 'admin', 'sub_admin') " . str_replace("created_at", "created_at", $date_where))->fetch()['c'] ?? 0);

// Count family members
$total_fm = (int) ($pdo->query("SELECT COUNT(*) AS c FROM family_members $pop_date_query")->fetch()['c'] ?? 0);

// Count resident_records that don't have matching user accounts (to avoid double counting)
// We check by email or full_name matching
$total_orphaned_rr = (int) ($pdo->query("
    SELECT COUNT(*) as c FROM resident_records rr 
    WHERE NOT EXISTS (SELECT 1 FROM users u WHERE u.role = 'resident' AND (u.email = rr.email OR u.full_name = rr.full_name))
    " . str_replace('created_at', 'rr.created_at', str_replace('WHERE', 'AND', $pop_date_query)))->fetch()['c'] ?? 0);

$solo_parents_rr = (int) ($pdo->query("SELECT COUNT(*) AS c FROM resident_records WHERE is_solo_parent = 1 $date_where")->fetch()['c'] ?? 0);
$solo_parents_fm = (int) ($pdo->query("SELECT COUNT(*) AS c FROM family_members WHERE is_solo_parent = 1 $date_where")->fetch()['c'] ?? 0);

$pwd_rr = (int) ($pdo->query("SELECT COUNT(*) AS c FROM resident_records WHERE is_pwd = 1 $date_where")->fetch()['c'] ?? 0);
$pwd_fm = (int) ($pdo->query("SELECT COUNT(*) AS c FROM family_members WHERE is_pwd = 1 $date_where")->fetch()['c'] ?? 0);

$senior_rr = (int) ($pdo->query("SELECT COUNT(*) AS c FROM resident_records WHERE is_senior = 1 $date_where")->fetch()['c'] ?? 0);
$senior_fm = (int) ($pdo->query("SELECT COUNT(*) AS c FROM family_members WHERE is_senior = 1 $date_where")->fetch()['c'] ?? 0);

$labor_rr = (int) ($pdo->query("SELECT COUNT(*) AS c FROM resident_records WHERE classification LIKE '%Labor%Employed%' $date_where")->fetch()['c'] ?? 0);
$labor_fm = (int) ($pdo->query("SELECT COUNT(*) AS c FROM family_members WHERE classification LIKE '%Labor%Employed%' $date_where")->fetch()['c'] ?? 0);

$unemployed_rr = (int) ($pdo->query("SELECT COUNT(*) AS c FROM resident_records WHERE classification LIKE '%Unemployed%' $date_where")->fetch()['c'] ?? 0);
$unemployed_fm = (int) ($pdo->query("SELECT COUNT(*) AS c FROM family_members WHERE classification LIKE '%Unemployed%' $date_where")->fetch()['c'] ?? 0);

$ofw_rr = (int) ($pdo->query("SELECT COUNT(*) AS c FROM resident_records WHERE classification LIKE '%OFW%' $date_where")->fetch()['c'] ?? 0);
$ofw_fm = (int) ($pdo->query("SELECT COUNT(*) AS c FROM family_members WHERE classification LIKE '%OFW%' $date_where")->fetch()['c'] ?? 0);

$osy_rr = (int) ($pdo->query("SELECT COUNT(*) AS c FROM resident_records WHERE classification LIKE '%Out of School Youth%' $date_where")->fetch()['c'] ?? 0);
$osy_fm = (int) ($pdo->query("SELECT COUNT(*) AS c FROM family_members WHERE classification LIKE '%Out of School Youth%' $date_where")->fetch()['c'] ?? 0);

$osc_rr = (int) ($pdo->query("SELECT COUNT(*) AS c FROM resident_records WHERE classification LIKE '%Out of School Children%' $date_where")->fetch()['c'] ?? 0);
$osc_fm = (int) ($pdo->query("SELECT COUNT(*) AS c FROM family_members WHERE classification LIKE '%Out of School Children%' $date_where")->fetch()['c'] ?? 0);

$indigenous_rr = (int) ($pdo->query("SELECT COUNT(*) AS c FROM resident_records WHERE classification LIKE '%Indigenous People%' $date_where")->fetch()['c'] ?? 0);
$indigenous_fm = (int) ($pdo->query("SELECT COUNT(*) AS c FROM family_members WHERE classification LIKE '%Indigenous People%' $date_where")->fetch()['c'] ?? 0);

// For residents table (which lacks created_at), we join with users table
$res_date_where = !empty($date_where) ? str_replace('created_at', 'u.created_at', $date_where) : "";

$stats = [
	'total_residents' => $total_users + $total_fm + $total_orphaned_rr,
	'solo_parents' => $solo_parents_rr + $solo_parents_fm,
	'pwd' => $pwd_rr + $pwd_fm,
	'senior_citizens' => $senior_rr + $senior_fm,
	'labor' => $labor_rr + $labor_fm,
	'unemployed' => $unemployed_rr + $unemployed_fm,
	'ofw' => $ofw_rr + $ofw_fm,
	'osy' => $osy_rr + $osy_fm,
	'osc' => $osc_rr + $osc_fm,
	'indigenous' => $indigenous_rr + $indigenous_fm,
	'pending_verifications' => (int) ($pdo->query("SELECT COUNT(*) AS c FROM residents r JOIN users u ON r.user_id = u.id WHERE r.verification_status='pending' $res_date_where")->fetch()['c'] ?? 0),
];

// Overview pie chart data
$overview_pie_labels = ['Total Residents', 'Solo Parent', 'PWD', 'Senior Citizen', 'Labor', 'Unemployed', 'OFW', 'OSY', 'OSC', 'Indigenous', 'Pending Verifications'];
$overview_pie_data = [
	$stats['total_residents'],
	$stats['solo_parents'],
	$stats['pwd'],
	$stats['senior_citizens'],
	$stats['labor'],
	$stats['unemployed'],
	$stats['ofw'],
	$stats['osy'],
	$stats['osc'],
	$stats['indigenous'],
	$stats['pending_verifications'],
];
?>

<!-- Date Filter Section -->
<div class="card shadow-sm border-0 rounded-4 mb-4 overflow-hidden">
	<div class="card-body p-4">
		<form method="GET" class="row g-4 align-items-end">
			<div class="col-md-4">
				<label class="form-label fw-bold text-dark mb-2">Start Date</label>
				<div class="input-group">
					<input type="date" name="date_from"
						class="form-control form-control-lg rounded-3 border-light-subtle shadow-sm"
						style="font-size: 0.95rem;" value="<?php echo htmlspecialchars($date_from); ?>">
				</div>
			</div>
			<div class="col-md-4">
				<label class="form-label fw-bold text-dark mb-2">End Date</label>
				<div class="input-group">
					<input type="date" name="date_to"
						class="form-control form-control-lg rounded-3 border-light-subtle shadow-sm"
						style="font-size: 0.95rem;" value="<?php echo htmlspecialchars($date_to); ?>">
				</div>
			</div>
			<div class="col-md-4">
				<div class="d-flex gap-2">
					<button type="submit"
						class="btn btn-lg w-100 rounded-3 shadow-sm d-flex align-items-center justify-content-center py-2"
						style="background-color: #0d9488; color: white; border: none; font-weight: 600;">
						<i class="fas fa-filter me-2 small"></i> Filter
					</button>
					<?php if (!empty($date_from) || !empty($date_to)): ?>
						<a href="index.php"
							class="btn btn-lg btn-light rounded-3 shadow-sm d-flex align-items-center justify-content-center"
							style="border: 1px solid #e5e7eb;" title="Clear Filters">
							<i class="fas fa-times"></i>
						</a>
					<?php endif; ?>
				</div>
			</div>
		</form>
	</div>
</div>

<!-- Dashboard Layout -->
<div class="row">
	<div class="col-lg-12">
		<div class="card shadow-sm border-0 rounded-3 mb-4">
			<div class="card-header bg-white border-0 pt-4 px-4">
				<h5 class="fw-bold mb-0 text-dark"><i class="fas fa-chart-line me-2 text-primary"></i>General Population
					Overview</h5>
			</div>
			<div class="card-body p-4">
				<div class="row align-items-center">
					<!-- Left Side: Pie Chart -->
					<div class="col-xl-5 col-lg-6 mb-4 mb-lg-0">
						<div class="d-flex justify-content-center align-items-center">
							<div style="width: 100%; max-width: 320px;">
								<canvas id="overviewPieChart"></canvas>
							</div>
						</div>
					</div>
					<!-- Right Side: Statistics Cards -->
					<div class="col-xl-7 col-lg-6">
						<div class="row g-2">
							<?php
							$cards = [
								['url' => 'resident_records.php', 'color1' => '#10b981', 'color2' => '#059669', 'icon' => 'fa-users', 'val' => $stats['total_residents'], 'label' => 'Total Residents'],
								['url' => 'resident_records.php?filter=solo_parent', 'color1' => '#a855f7', 'color2' => '#7c3aed', 'icon' => 'fa-user-friends', 'val' => $stats['solo_parents'], 'label' => 'Solo Parent'],
								['url' => 'resident_records.php?filter=pwd', 'color1' => '#06b6d4', 'color2' => '#0891b2', 'icon' => 'fa-wheelchair', 'val' => $stats['pwd'], 'label' => 'PWD'],
								['url' => 'resident_records.php?filter=senior', 'color1' => '#f59e0b', 'color2' => '#d97706', 'icon' => 'fa-user-clock', 'val' => $stats['senior_citizens'], 'label' => 'Senior Citizen'],
								['url' => 'id_verifications.php', 'color1' => '#f43f5e', 'color2' => '#be123c', 'icon' => 'fa-id-badge', 'val' => $stats['pending_verifications'], 'label' => 'Pending Verifs'],
								['url' => 'resident_records.php?filter=labor', 'color1' => '#3b82f6', 'color2' => '#2563eb', 'icon' => 'fa-hard-hat', 'val' => $stats['labor'], 'label' => 'Employed'],
								['url' => 'resident_records.php?filter=unemployed', 'color1' => '#64748b', 'color2' => '#475569', 'icon' => 'fa-user-slash', 'val' => $stats['unemployed'], 'label' => 'Unemployed'],
								['url' => 'resident_records.php?filter=ofw', 'color1' => '#22c55e', 'color2' => '#16a34a', 'icon' => 'fa-plane', 'val' => $stats['ofw'], 'label' => 'OFW'],
								['url' => 'resident_records.php?filter=osy', 'color1' => '#ec4899', 'color2' => '#be185d', 'icon' => 'fa-graduation-cap', 'val' => $stats['osy'], 'label' => 'OSY'],
								['url' => 'resident_records.php?filter=osc', 'color1' => '#f97316', 'color2' => '#ea580c', 'icon' => 'fa-school', 'val' => $stats['osc'], 'label' => 'OSC'],
								['url' => 'resident_records.php?filter=indigenous', 'color1' => '#0f172a', 'color2' => '#1e293b', 'icon' => 'fa-campground', 'val' => $stats['indigenous'], 'label' => 'Indigenous'],
							];
							?>
							<?php foreach ($cards as $c): ?>
								<div class="col-sm-4 col-6">
									<a href="<?php echo htmlspecialchars($c['url']); ?>" class="admin-stats-link text-decoration-none">
										<div class="admin-stats-card mb-0 shadow-sm" style="background: linear-gradient(135deg, <?php echo $c['color1']; ?>, <?php echo $c['color2']; ?>); transition: transform 0.2s;">
											<div class="d-flex align-items-center p-2 text-white">
												<div class="stats-icon me-2 fs-5 opacity-75"><i class="fas <?php echo $c['icon']; ?>"></i></div>
												<div>
													<div class="stats-number fs-5 fw-bold" style="line-height:1.2;"><?php echo $c['val']; ?></div>
													<div class="stats-label opacity-75 text-truncate" style="font-size:0.7rem; max-width:80px;" title="<?php echo htmlspecialchars($c['label']); ?>"><?php echo htmlspecialchars($c['label']); ?></div>
												</div>
											</div>
										</div>
									</a>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Pie Charts Row -->
<div class="row g-4 mb-4">
	<!-- Documents Request Section -->
	<div class="col-lg-12">
		<div class="card shadow-sm border-0 rounded-3 mb-4">
			<div class="card-header bg-white border-0 pt-4 px-4">
				<h5 class="fw-bold mb-0 text-dark"><i class="fas fa-file-alt me-2 text-primary"></i>Documents Request
					Status Overview</h5>
			</div>
			<div class="card-body p-4">
				<div class="row align-items-center">
					<!-- Left Side: Pie Chart -->
					<div class="col-xl-5 col-lg-6 mb-4 mb-lg-0">
						<div class="d-flex justify-content-center align-items-center">
							<div style="width: 100%; max-width: 280px;">
								<canvas id="requestsPieChart"></canvas>
							</div>
						</div>
					</div>
					<!-- Right Side: Cards Grid -->
					<div class="col-xl-7 col-lg-6">
						<div class="row g-3">
							<!-- Pending -->
							<div class="col-sm-6">
								<a href="requests_pending.php" class="admin-stats-link text-decoration-none">
									<div class="admin-stats-card warning mb-0"
										style="background: linear-gradient(135deg, #f59e0b, #d97706);">
										<div class="d-flex align-items-center p-3 text-white">
											<div class="stats-icon me-3 fs-3 opacity-75"><i class="fas fa-clock"></i></div>
											<div>
												<div class="stats-number fs-4 fw-bold"><?php echo $total_pending; ?></div>
												<div class="stats-label small opacity-75">Pending</div>
											</div>
										</div>
									</div>
								</a>
							</div>
							<!-- Ready to Pickup -->
							<div class="col-sm-6">
								<a href="requests_approved.php" class="admin-stats-link text-decoration-none">
									<div class="admin-stats-card success mb-0"
										style="background: linear-gradient(135deg, #10b981, #059669);">
										<div class="d-flex align-items-center p-3 text-white">
											<div class="stats-icon me-3 fs-3 opacity-75"><i class="fas fa-check-circle"></i>
											</div>
											<div>
												<div class="stats-number fs-4 fw-bold"><?php echo $total_approved; ?></div>
												<div class="stats-label small opacity-75">Ready to Pickup</div>
											</div>
										</div>
									</div>
								</a>
							</div>
							<!-- Released -->
							<div class="col-sm-6">
								<a href="requests_released.php" class="admin-stats-link text-decoration-none">
									<div class="admin-stats-card info mb-0"
										style="background: linear-gradient(135deg, #3b82f6, #2563eb);">
										<div class="d-flex align-items-center p-3 text-white">
											<div class="stats-icon me-3 fs-3 opacity-75"><i class="fas fa-hand-holding"></i>
											</div>
											<div>
												<div class="stats-number fs-4 fw-bold"><?php echo $total_released; ?></div>
												<div class="stats-label small opacity-75">Released</div>
											</div>
										</div>
									</div>
								</a>
							</div>
							<!-- Rejected -->
							<div class="col-sm-6">
								<a href="requests_rejected.php" class="admin-stats-link text-decoration-none">
									<div class="admin-stats-card danger mb-0"
										style="background: linear-gradient(135deg, #ef4444, #dc2626);">
										<div class="d-flex align-items-center p-3 text-white">
											<div class="stats-icon me-3 fs-3 opacity-75"><i class="fas fa-times-circle"></i>
											</div>
											<div>
												<div class="stats-number fs-4 fw-bold"><?php echo $total_rejected; ?></div>
												<div class="stats-label small opacity-75">Rejected</div>
											</div>
										</div>
									</div>
								</a>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Incidents Report Section -->
	<div class="col-lg-12">
		<div class="card shadow-sm border-0 rounded-3 mb-4">
			<div class="card-header bg-white border-0 pt-4 px-4">
				<h5 class="fw-bold mb-0 text-dark"><i class="fas fa-exclamation-triangle me-2 text-danger"></i>Incidents
					Report Status Overview</h5>
			</div>
			<div class="card-body p-4">
				<div class="row align-items-center">
					<!-- Left Side: Pie Chart -->
					<div class="col-xl-5 col-lg-6 mb-4 mb-lg-0">
						<div class="d-flex justify-content-center align-items-center">
							<div style="width: 100%; max-width: 280px;">
								<canvas id="incidentsPieChart"></canvas>
							</div>
						</div>
					</div>
					<!-- Right Side: Cards Grid -->
					<div class="col-xl-7 col-lg-6">
						<div class="row g-3">
							<!-- Submitted -->
							<div class="col-sm-6">
								<a href="incidents_pending.php" class="admin-stats-link text-decoration-none">
									<div class="admin-stats-card warning mb-0"
										style="background: linear-gradient(135deg, #f59e0b, #d97706);">
										<div class="d-flex align-items-center p-3 text-white">
											<div class="stats-icon me-3 fs-3 opacity-75"><i class="fas fa-bullhorn"></i>
											</div>
											<div>
												<div class="stats-number fs-4 fw-bold"><?php echo $incidents_submitted; ?>
												</div>
												<div class="stats-label small opacity-75">Pending</div>
											</div>
										</div>
									</div>
								</a>
							</div>
							<!-- In Review -->
							<div class="col-sm-6">
								<a href="incidents_review.php" class="admin-stats-link text-decoration-none">
									<div class="admin-stats-card info mb-0"
										style="background: linear-gradient(135deg, #0ea5e9, #0284c7);">
										<div class="d-flex align-items-center p-3 text-white">
											<div class="stats-icon me-3 fs-3 opacity-75"><i class="fas fa-search"></i></div>
											<div>
												<div class="stats-number fs-4 fw-bold"><?php echo $incidents_review; ?>
												</div>
												<div class="stats-label small opacity-75">In Review</div>
											</div>
										</div>
									</div>
								</a>
							</div>
							<!-- Resolved -->
							<div class="col-sm-6">
								<a href="incidents_resolved.php" class="admin-stats-link text-decoration-none">
									<div class="admin-stats-card success mb-0"
										style="background: linear-gradient(135deg, #10b981, #059669);">
										<div class="d-flex align-items-center p-3 text-white">
											<div class="stats-icon me-3 fs-3 opacity-75"><i class="fas fa-check-double"></i>
											</div>
											<div>
												<div class="stats-number fs-4 fw-bold"><?php echo $incidents_resolved; ?>
												</div>
												<div class="stats-label small opacity-75">Resolved</div>
											</div>
										</div>
									</div>
								</a>
							</div>
							<!-- Rejected -->
							<div class="col-sm-6">
								<a href="incidents_rejected.php" class="admin-stats-link text-decoration-none">
									<div class="admin-stats-card danger mb-0"
										style="background: linear-gradient(135deg, #ef4444, #dc2626);">
										<div class="d-flex align-items-center p-3 text-white">
											<div class="stats-icon me-3 fs-3 opacity-75"><i class="fas fa-times-circle"></i>
											</div>
											<div>
												<div class="stats-number fs-4 fw-bold"><?php echo $incidents_rejected; ?>
												</div>
												<div class="stats-label small opacity-75">Rejected</div>
											</div>
										</div>
									</div>
								</a>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
	document.addEventListener('DOMContentLoaded', function () {
		// ── Overview Pie Chart ──────────────────────────────────────────
		const ovCtx = document.getElementById('overviewPieChart').getContext('2d');
		new Chart(ovCtx, {
			type: 'pie',
			data: {
				labels: <?php echo json_encode($overview_pie_labels); ?>,
				datasets: [{
					data: <?php echo json_encode($overview_pie_data); ?>,
					backgroundColor: ['#10b981', '#a855f7', '#06b6d4', '#f59e0b', '#3b82f6', '#64748b', '#22c55e', '#ec4899', '#f97316', '#0f172a', '#f43f5e']
				}]
			},
			options: {
				responsive: true,
				plugins: { legend: { position: 'bottom' } }
			}
		});

		// ── Documents Request Pie Chart ─────────────────────────────────
		const reqCtx = document.getElementById('requestsPieChart').getContext('2d');
		const reqEmpty = <?php echo ($total_pending + $total_approved + $total_released + $total_rejected) == 0 ? 'true' : 'false'; ?>;
		new Chart(reqCtx, {
			type: 'pie',
			data: {
				labels: reqEmpty ? ['No Requests'] : ['Pending', 'Ready to Pickup', 'Released', 'Rejected'],
				datasets: [{
					data: reqEmpty ? [1] : [<?php echo $total_pending; ?>, <?php echo $total_approved; ?>, <?php echo $total_released; ?>, <?php echo $total_rejected; ?>],
					backgroundColor: reqEmpty ? ['#e5e7eb'] : ['#f59e0b', '#10b981', '#3b82f6', '#ef4444'],
					borderWidth: 2,
					borderColor: '#ffffff'
				}]
			},
			options: {
				responsive: true,
				plugins: {
					legend: { position: 'bottom', display: !reqEmpty },
					tooltip: { enabled: !reqEmpty }
				}
			}
		});

		// ── Incidents Report Pie Chart ──────────────────────────────────
		const incCtx = document.getElementById('incidentsPieChart').getContext('2d');
		const incEmpty = <?php echo ($incidents_submitted + $incidents_review + $incidents_resolved + $incidents_rejected) == 0 ? 'true' : 'false'; ?>;
		new Chart(incCtx, {
			type: 'pie',
			data: {
				labels: incEmpty ? ['No Incidents'] : ['Pending', 'In Review', 'Resolved', 'Rejected'],
				datasets: [{
					data: incEmpty ? [1] : [<?php echo $incidents_submitted; ?>, <?php echo $incidents_review; ?>, <?php echo $incidents_resolved; ?>, <?php echo $incidents_rejected; ?>],
					backgroundColor: incEmpty ? ['#e5e7eb'] : ['#f59e0b', '#0ea5e9', '#10b981', '#ef4444'],
					borderWidth: 2,
					borderColor: '#ffffff'
				}]
			},
			options: {
				responsive: true,
				plugins: {
					legend: { position: 'bottom', display: !incEmpty },
					tooltip: { enabled: !incEmpty }
				}
			}
		});
	});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
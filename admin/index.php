<?php 
require_once __DIR__ . '/../config.php';
if (!is_admin()) redirect('../index.php');
$page_title = 'Admin Dashboard';

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
$pending_docs = (int)($pdo->query("SELECT COUNT(*) as c FROM document_requests WHERE status='pending' $date_where")->fetch()['c'] ?? 0);
$pending_clear = (int)($pdo->query("SELECT COUNT(*) as c FROM barangay_clearances WHERE status='pending' $date_where")->fetch()['c'] ?? 0);
$total_pending = $pending_docs + $pending_clear;

$approved_docs = (int)($pdo->query("SELECT COUNT(*) as c FROM document_requests WHERE status='approved' $date_where")->fetch()['c'] ?? 0);
$approved_clear = (int)($pdo->query("SELECT COUNT(*) as c FROM barangay_clearances WHERE status='approved' $date_where")->fetch()['c'] ?? 0);
$total_approved = $approved_docs + $approved_clear;

$released_docs = (int)($pdo->query("SELECT COUNT(*) as c FROM document_requests WHERE status='released' $date_where")->fetch()['c'] ?? 0);
$released_clear = (int)($pdo->query("SELECT COUNT(*) as c FROM barangay_clearances WHERE status='released' $date_where")->fetch()['c'] ?? 0);
$total_released = $released_docs + $released_clear;

$rejected_docs = (int)($pdo->query("SELECT COUNT(*) as c FROM document_requests WHERE status='rejected' $date_where")->fetch()['c'] ?? 0);
$rejected_clear = (int)($pdo->query("SELECT COUNT(*) as c FROM barangay_clearances WHERE status='rejected' $date_where")->fetch()['c'] ?? 0);
$total_rejected = $rejected_docs + $rejected_clear;

// --- Incidents Stats ---
$incidents_submitted = (int)($pdo->query("SELECT COUNT(*) as c FROM incidents i JOIN users u ON i.user_id = u.id WHERE i.status='submitted' $date_where")->fetch()['c'] ?? 0);
$incidents_review    = (int)($pdo->query("SELECT COUNT(*) as c FROM incidents i JOIN users u ON i.user_id = u.id WHERE i.status='in_review' $date_where")->fetch()['c'] ?? 0);
$incidents_resolved  = (int)($pdo->query("SELECT COUNT(*) as c FROM incidents i JOIN users u ON i.user_id = u.id WHERE i.status='resolved' $date_where")->fetch()['c'] ?? 0);
$incidents_rejected  = (int)($pdo->query("SELECT COUNT(*) as c FROM incidents i JOIN users u ON i.user_id = u.id WHERE i.status='closed' $date_where")->fetch()['c'] ?? 0);
$incidents_canceled  = (int)($pdo->query("SELECT COUNT(*) as c FROM incidents i JOIN users u ON i.user_id = u.id WHERE i.status='canceled' $date_where")->fetch()['c'] ?? 0);

// --- General Stats (Population Overview) ---
$pop_date_query = !empty($date_where) ? " WHERE " . substr($date_where, 5) : "";

$total_rr = (int)($pdo->query("SELECT COUNT(*) AS c FROM resident_records $pop_date_query")->fetch()['c'] ?? 0);
$total_fm = (int)($pdo->query("SELECT COUNT(*) AS c FROM family_members $pop_date_query")->fetch()['c'] ?? 0);

$solo_parents_rr = (int)($pdo->query("SELECT COUNT(*) AS c FROM resident_records WHERE is_solo_parent = 1 $date_where")->fetch()['c'] ?? 0);
$solo_parents_fm = (int)($pdo->query("SELECT COUNT(*) AS c FROM family_members WHERE is_solo_parent = 1 $date_where")->fetch()['c'] ?? 0);

$pwd_rr = (int)($pdo->query("SELECT COUNT(*) AS c FROM resident_records WHERE is_pwd = 1 $date_where")->fetch()['c'] ?? 0);
$pwd_fm = (int)($pdo->query("SELECT COUNT(*) AS c FROM family_members WHERE is_pwd = 1 $date_where")->fetch()['c'] ?? 0);

$senior_rr = (int)($pdo->query("SELECT COUNT(*) AS c FROM resident_records WHERE is_senior = 1 $date_where")->fetch()['c'] ?? 0);
$senior_fm = (int)($pdo->query("SELECT COUNT(*) AS c FROM family_members WHERE is_senior = 1 $date_where")->fetch()['c'] ?? 0);

// For residents table (which lacks created_at), we join with users table
$res_date_where = !empty($date_where) ? str_replace('created_at', 'u.created_at', $date_where) : "";

$stats = [
	'total_residents'       => $total_rr + $total_fm,
	'solo_parents'          => $solo_parents_rr + $solo_parents_fm,
	'pwd'                   => $pwd_rr + $pwd_fm,
	'senior_citizens'       => $senior_rr + $senior_fm,
	'pending_verifications' => (int)($pdo->query("SELECT COUNT(*) AS c FROM residents r JOIN users u ON r.user_id = u.id WHERE r.verification_status='pending' $res_date_where")->fetch()['c'] ?? 0),
];

// Overview pie chart data
$overview_pie_labels = ['Total Residents', 'Solo Parent', 'PWD', 'Senior Citizen', 'Pending Verifications'];
$overview_pie_data = [
	$stats['total_residents'],
	$stats['solo_parents'],
	$stats['pwd'],
	$stats['senior_citizens'],
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
                    <input type="date" name="date_from" class="form-control form-control-lg rounded-3 border-light-subtle shadow-sm" style="font-size: 0.95rem;" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold text-dark mb-2">End Date</label>
                <div class="input-group">
                    <input type="date" name="date_to" class="form-control form-control-lg rounded-3 border-light-subtle shadow-sm" style="font-size: 0.95rem;" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-lg w-100 rounded-3 shadow-sm d-flex align-items-center justify-content-center py-2" style="background-color: #0d9488; color: white; border: none; font-weight: 600;">
                        <i class="fas fa-filter me-2 small"></i> Filter
                    </button>
                    <?php if (!empty($date_from) || !empty($date_to)): ?>
                        <a href="index.php" class="btn btn-lg btn-light rounded-3 shadow-sm d-flex align-items-center justify-content-center" style="border: 1px solid #e5e7eb;" title="Clear Filters">
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
				<h5 class="fw-bold mb-0 text-dark"><i class="fas fa-chart-line me-2 text-primary"></i>General Population Overview</h5>
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
						<div class="row g-3">
							<!-- Total Residents -->
							<div class="col-sm-4">
								<a href="resident_records.php" class="admin-stats-link text-decoration-none">
									<div class="admin-stats-card success mb-0" style="background: linear-gradient(135deg, #10b981, #059669);">
										<div class="d-flex align-items-center p-3 text-white">
											<div class="stats-icon me-3 fs-3 opacity-75"><i class="fas fa-users"></i></div>
											<div>
												<div class="stats-number fs-4 fw-bold"><?php echo $stats['total_residents']; ?></div>
												<div class="stats-label small opacity-75">Total Residents</div>
											</div>
										</div>
									</div>
								</a>
							</div>
							<!-- Solo Parent -->
							<div class="col-sm-4">
								<a href="reports.php?type=solo_parents" class="admin-stats-link text-decoration-none">
									<div class="admin-stats-card warning mb-0" style="background: linear-gradient(135deg, #a855f7, #7c3aed);">
										<div class="d-flex align-items-center p-3 text-white">
											<div class="stats-icon me-3 fs-3 opacity-75"><i class="fas fa-user-friends"></i></div>
											<div>
												<div class="stats-number fs-4 fw-bold"><?php echo $stats['solo_parents']; ?></div>
												<div class="stats-label small opacity-75">Solo Parent</div>
											</div>
										</div>
									</div>
								</a>
							</div>
							<!-- PWD -->
							<div class="col-sm-4">
								<a href="reports.php?type=pwds" class="admin-stats-link text-decoration-none">
									<div class="admin-stats-card info mb-0" style="background: linear-gradient(135deg, #3b82f6, #2563eb);">
										<div class="d-flex align-items-center p-3 text-white">
											<div class="stats-icon me-3 fs-3 opacity-75"><i class="fas fa-wheelchair"></i></div>
											<div>
												<div class="stats-number fs-4 fw-bold"><?php echo $stats['pwd']; ?></div>
												<div class="stats-label small opacity-75">PWD</div>
											</div>
										</div>
									</div>
								</a>
							</div>
							<!-- Senior Citizen -->
							<div class="col-sm-6">
								<a href="reports.php?type=seniors" class="admin-stats-link text-decoration-none">
									<div class="admin-stats-card mb-0" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
										<div class="d-flex align-items-center p-3 text-white">
											<div class="stats-icon me-3 fs-3 opacity-75"><i class="fas fa-user-clock"></i></div>
											<div>
												<div class="stats-number fs-4 fw-bold"><?php echo $stats['senior_citizens']; ?></div>
												<div class="stats-label small opacity-75">Senior Citizen</div>
											</div>
										</div>
									</div>
								</a>
							</div>
							<!-- Pending Verifications -->
							<div class="col-sm-6">
								<a href="id_verifications.php" class="admin-stats-link text-decoration-none">
									<div class="admin-stats-card mb-0" style="background: linear-gradient(135deg, #6366f1, #4f46e5);">
										<div class="d-flex align-items-center p-3 text-white">
											<div class="stats-icon me-3 fs-3 opacity-75"><i class="fas fa-id-badge"></i></div>
											<div>
												<div class="stats-number fs-4 fw-bold"><?php echo $stats['pending_verifications']; ?></div>
												<div class="stats-label small opacity-75">Pending Verifications</div>
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

<!-- Pie Charts Row -->
<div class="row g-4 mb-4">
	<!-- Documents Request Section -->
	<div class="col-lg-12">
		<div class="card shadow-sm border-0 rounded-3 mb-4">
			<div class="card-header bg-white border-0 pt-4 px-4">
				<h5 class="fw-bold mb-0 text-dark"><i class="fas fa-file-alt me-2 text-primary"></i>Documents Request Status Overview</h5>
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
								<div class="admin-stats-card warning mb-0" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
									<div class="d-flex align-items-center p-3 text-white">
										<div class="stats-icon me-3 fs-3 opacity-75"><i class="fas fa-clock"></i></div>
										<div>
											<div class="stats-number fs-4 fw-bold"><?php echo $total_pending; ?></div>
											<div class="stats-label small opacity-75">Pending</div>
										</div>
									</div>
								</div>
							</div>
							<!-- Ready to Pickup -->
							<div class="col-sm-6">
								<div class="admin-stats-card success mb-0" style="background: linear-gradient(135deg, #10b981, #059669);">
									<div class="d-flex align-items-center p-3 text-white">
										<div class="stats-icon me-3 fs-3 opacity-75"><i class="fas fa-check-circle"></i></div>
										<div>
											<div class="stats-number fs-4 fw-bold"><?php echo $total_approved; ?></div>
											<div class="stats-label small opacity-75">Ready to Pickup</div>
										</div>
									</div>
								</div>
							</div>
							<!-- Released -->
							<div class="col-sm-6">
								<div class="admin-stats-card info mb-0" style="background: linear-gradient(135deg, #3b82f6, #2563eb);">
									<div class="d-flex align-items-center p-3 text-white">
										<div class="stats-icon me-3 fs-3 opacity-75"><i class="fas fa-hand-holding"></i></div>
										<div>
											<div class="stats-number fs-4 fw-bold"><?php echo $total_released; ?></div>
											<div class="stats-label small opacity-75">Released</div>
										</div>
									</div>
								</div>
							</div>
							<!-- Rejected -->
							<div class="col-sm-6">
								<div class="admin-stats-card danger mb-0" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
									<div class="d-flex align-items-center p-3 text-white">
										<div class="stats-icon me-3 fs-3 opacity-75"><i class="fas fa-times-circle"></i></div>
										<div>
											<div class="stats-number fs-4 fw-bold"><?php echo $total_rejected; ?></div>
											<div class="stats-label small opacity-75">Rejected</div>
										</div>
									</div>
								</div>
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
				<h5 class="fw-bold mb-0 text-dark"><i class="fas fa-exclamation-triangle me-2 text-danger"></i>Incidents Report Status Overview</h5>
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
							<div class="col-sm-4">
								<div class="admin-stats-card danger mb-0" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
									<div class="d-flex align-items-center p-3 text-white">
										<div class="stats-icon me-3 fs-3 opacity-75"><i class="fas fa-bullhorn"></i></div>
										<div>
											<div class="stats-number fs-4 fw-bold"><?php echo $incidents_submitted; ?></div>
											<div class="stats-label small opacity-75">Pending</div>
										</div>
									</div>
								</div>
							</div>
							<!-- In Review -->
							<div class="col-sm-4">
								<div class="admin-stats-card info mb-0" style="background: linear-gradient(135deg, #0ea5e9, #0284c7);">
									<div class="d-flex align-items-center p-3 text-white">
										<div class="stats-icon me-3 fs-3 opacity-75"><i class="fas fa-search"></i></div>
										<div>
											<div class="stats-number fs-4 fw-bold"><?php echo $incidents_review; ?></div>
											<div class="stats-label small opacity-75">In Review</div>
										</div>
									</div>
								</div>
							</div>
							<!-- Resolved -->
							<div class="col-sm-4">
								<div class="admin-stats-card success mb-0" style="background: linear-gradient(135deg, #10b981, #059669);">
									<div class="d-flex align-items-center p-3 text-white">
										<div class="stats-icon me-3 fs-3 opacity-75"><i class="fas fa-check-double"></i></div>
										<div>
											<div class="stats-number fs-4 fw-bold"><?php echo $incidents_resolved; ?></div>
											<div class="stats-label small opacity-75">Resolved</div>
										</div>
									</div>
								</div>
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
				backgroundColor: ['#10b981', '#a855f7', '#3b82f6', '#f59e0b', '#6366f1']
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
	const incEmpty = <?php echo ($incidents_submitted + $incidents_review + $incidents_resolved) == 0 ? 'true' : 'false'; ?>;
	new Chart(incCtx, {
		type: 'pie',
		data: {
			labels: incEmpty ? ['No Incidents'] : ['Pending', 'In Review', 'Resolved', 'Rejected', 'Cancelled'],
			datasets: [{
				data: incEmpty ? [1] : [<?php echo $incidents_submitted; ?>, <?php echo $incidents_review; ?>, <?php echo $incidents_resolved; ?>, <?php echo $incidents_rejected; ?>, <?php echo $incidents_canceled; ?>],
				backgroundColor: incEmpty ? ['#e5e7eb'] : ['#ef4444', '#0ea5e9', '#10b981', '#6b7280', '#9ca3af'],
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

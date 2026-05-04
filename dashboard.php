<?php
require_once __DIR__ . '/config.php';
if (!is_logged_in())
    redirect('login.php');

// Force RBI and Verification completion
$pdo = get_db_connection();
$stmt = $pdo->prepare('SELECT verification_status, is_rbi_completed FROM residents WHERE user_id = ? LIMIT 1');
$stmt->execute([$_SESSION['user_id']]);
$resident = $stmt->fetch();

if ($resident) {
    if (!$resident['is_rbi_completed']) {
        redirect('rbi_form.php');
    } elseif ($resident['verification_status'] !== 'verified') {
        redirect('id_verification.php');
    }
}

$page_title = 'User Dashboard';
require_once __DIR__ . '/partials/user_dashboard_header.php';

$pdo = get_db_connection();
$user_id = $_SESSION['user_id'];

// Prevent SQL errors if queries fail
try {
    // Pending
    $pending_docs = (int) ($pdo->query("SELECT COUNT(*) as c FROM document_requests WHERE user_id=$user_id AND status='pending'")->fetch()['c'] ?? 0);
    $pending_clear = (int) ($pdo->query("SELECT COUNT(*) as c FROM barangay_clearances WHERE user_id=$user_id AND status='pending'")->fetch()['c'] ?? 0);
    $total_pending = $pending_docs + $pending_clear;

    // Approved
    $approved_docs = (int) ($pdo->query("SELECT COUNT(*) as c FROM document_requests WHERE user_id=$user_id AND status='approved'")->fetch()['c'] ?? 0);
    $approved_clear = (int) ($pdo->query("SELECT COUNT(*) as c FROM barangay_clearances WHERE user_id=$user_id AND status='approved'")->fetch()['c'] ?? 0);
    $total_approved = $approved_docs + $approved_clear;

    // Released
    $released_docs = (int) ($pdo->query("SELECT COUNT(*) as c FROM document_requests WHERE user_id=$user_id AND status='released'")->fetch()['c'] ?? 0);
    $released_clear = (int) ($pdo->query("SELECT COUNT(*) as c FROM barangay_clearances WHERE user_id=$user_id AND status='released'")->fetch()['c'] ?? 0);
    $total_released = $released_docs + $released_clear;

    // Rejected
    $rejected_docs = (int) ($pdo->query("SELECT COUNT(*) as c FROM document_requests WHERE user_id=$user_id AND status='rejected'")->fetch()['c'] ?? 0);
    $rejected_clear = (int) ($pdo->query("SELECT COUNT(*) as c FROM barangay_clearances WHERE user_id=$user_id AND status='rejected'")->fetch()['c'] ?? 0);
    $total_rejected = $rejected_docs + $rejected_clear;

    // Incidents
    $incidents_submitted = (int) ($pdo->query("SELECT COUNT(*) as c FROM incidents WHERE user_id=$user_id AND status='submitted'")->fetch()['c'] ?? 0);
    $incidents_review = (int) ($pdo->query("SELECT COUNT(*) as c FROM incidents WHERE user_id=$user_id AND status='in_review'")->fetch()['c'] ?? 0);
    $incidents_resolved = (int) ($pdo->query("SELECT COUNT(*) as c FROM incidents WHERE user_id=$user_id AND status='resolved'")->fetch()['c'] ?? 0);
} catch (PDOException $e) {
    $total_pending = 0;
    $total_approved = 0;
    $total_released = 0;
    $total_rejected = 0;
    $incidents_submitted = 0;
    $incidents_review = 0;
    $incidents_resolved = 0;
}
?>

<div class="container py-5 animate__animated animate__fadeInUp">
    <div class="mb-4 pb-3 border-bottom">
        <h2 class="fw-bold mb-1 text-dark">User Dashboard</h2>
    </div>

    <!-- Stats & Graph -->
    <div class="row g-4 mb-5">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-transparent border-0 pt-4 pb-0 px-4">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-chart-pie me-2 text-primary"></i>Documents
                        Request</h5>
                </div>
                <div class="card-body d-flex flex-column justify-content-center align-items-center p-4">
                    <?php
                    if ($total_pending == 0 && $total_approved == 0 && $total_released == 0): ?>
                        <div class="text-center text-muted mb-4">
                            <i class="fas fa-folder-open fa-3x mb-3 opacity-50"></i>
                            <p class="mb-0">You have not made any document requests yet.</p>
                        </div>
                    <?php endif; ?>
                    <div style="width: 100%; max-width: 250px;">
                        <canvas id="requestsPieChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="row g-4">
                <!-- Pending Card -->
                <div class="col-md-6">
                    <a href="requests.php" class="text-decoration-none"
                        style="color: inherit; display: block; border-radius: 12px; overflow: hidden; transition: transform 0.2s;">
                        <div class="admin-stats-card warning mb-0"
                            style="margin: 0; border: none; box-shadow: 0 4px 6px -1px rgba(0,0,0,.1), 0 2px 4px -1px rgba(0,0,0,.06);">
                            <div class="d-flex align-items-center p-4" style="color: white;">
                                <div class="stats-icon me-3" style="font-size: 2rem; opacity: 0.9;"><i
                                        class="fas fa-clock"></i></div>
                                <div>
                                    <div class="stats-number"
                                        style="font-size: 2rem; font-weight: bold; line-height: 1; margin-bottom: 0.25rem;">
                                        <?php echo $total_pending; ?></div>
                                    <div class="stats-label" style="font-size: 0.9rem; opacity: 0.9;">Pending</div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
                <!-- Ready to Pickup Card -->
                <div class="col-md-6">
                    <a href="requests.php" class="text-decoration-none"
                        style="color: inherit; display: block; border-radius: 12px; overflow: hidden; transition: transform 0.2s;">
                        <div class="admin-stats-card success mb-0"
                            style="margin: 0; border: none; box-shadow: 0 4px 6px -1px rgba(0,0,0,.1), 0 2px 4px -1px rgba(0,0,0,.06);">
                            <div class="d-flex align-items-center p-4" style="color: white;">
                                <div class="stats-icon me-3" style="font-size: 2rem; opacity: 0.9;"><i
                                        class="fas fa-check-circle"></i></div>
                                <div>
                                    <div class="stats-number"
                                        style="font-size: 2rem; font-weight: bold; line-height: 1; margin-bottom: 0.25rem;">
                                        <?php echo $total_approved; ?></div>
                                    <div class="stats-label" style="font-size: 0.9rem; opacity: 0.9;">Ready to Pickup
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
                <!-- Released Card -->
                <div class="col-md-6">
                    <a href="requests.php" class="text-decoration-none"
                        style="color: inherit; display: block; border-radius: 12px; overflow: hidden; transition: transform 0.2s;">
                        <div class="admin-stats-card info mb-0"
                            style="margin: 0; border: none; box-shadow: 0 4px 6px -1px rgba(0,0,0,.1), 0 2px 4px -1px rgba(0,0,0,.06);">
                            <div class="d-flex align-items-center p-4" style="color: white;">
                                <div class="stats-icon me-3" style="font-size: 2rem; opacity: 0.9;"><i
                                        class="fas fa-hand-holding"></i></div>
                                <div>
                                    <div class="stats-number"
                                        style="font-size: 2rem; font-weight: bold; line-height: 1; margin-bottom: 0.25rem;">
                                        <?php echo $total_released; ?></div>
                                    <div class="stats-label" style="font-size: 0.9rem; opacity: 0.9;">Released</div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
                <!-- Rejected Card -->
                <div class="col-md-6">
                    <a href="requests.php" class="text-decoration-none"
                        style="color: inherit; display: block; border-radius: 12px; overflow: hidden; transition: transform 0.2s;">
                        <div class="admin-stats-card danger mb-0"
                            style="margin: 0; border: none; box-shadow: 0 4px 6px -1px rgba(0,0,0,.1), 0 2px 4px -1px rgba(0,0,0,.06);">
                            <div class="d-flex align-items-center p-4" style="color: white;">
                                <div class="stats-icon me-3" style="font-size: 2rem; opacity: 0.9;"><i
                                        class="fas fa-times-circle"></i></div>
                                <div>
                                    <div class="stats-number"
                                        style="font-size: 2rem; font-weight: bold; line-height: 1; margin-bottom: 0.25rem;">
                                        <?php echo $total_rejected; ?></div>
                                    <div class="stats-label" style="font-size: 0.9rem; opacity: 0.9;">Rejected</div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Incident Stats & Graph -->
    <div class="row g-4 mb-5">
        <div class="col-lg-5 order-lg-2">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-transparent border-0 pt-4 pb-0 px-4">
                    <h5 class="fw-bold mb-0 text-dark"><i
                            class="fas fa-exclamation-triangle me-2 text-danger"></i>Incidents Report</h5>
                </div>
                <div class="card-body d-flex flex-column justify-content-center align-items-center p-4">
                    <?php if ($incidents_submitted == 0 && $incidents_review == 0 && $incidents_resolved == 0): ?>
                        <div class="text-center text-muted mb-4">
                            <i class="fas fa-shield-alt fa-3x mb-3 opacity-50"></i>
                            <p class="mb-0">You have no incident reports yet.</p>
                        </div>
                    <?php endif; ?>
                    <div style="width: 100%; max-width: 250px;">
                        <canvas id="incidentsPieChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7 order-lg-1">
            <div class="row g-4">
                <!-- Submitted Card -->
                <div class="col-md-12">
                    <a href="incidents.php" class="text-decoration-none"
                        style="color: inherit; display: block; border-radius: 12px; overflow: hidden; transition: transform 0.2s;">
                        <div class="admin-stats-card danger mb-0"
                            style="margin: 0; border: none; box-shadow: 0 4px 6px -1px rgba(0,0,0,.1), 0 2px 4px -1px rgba(0,0,0,.06);">
                            <div class="d-flex align-items-center p-4" style="color: white;">
                                <div class="stats-icon me-4" style="font-size: 2.5rem; opacity: 0.9;"><i
                                        class="fas fa-file-signature"></i></div>
                                <div>
                                    <div class="stats-number"
                                        style="font-size: 2.25rem; font-weight: bold; line-height: 1; margin-bottom: 0.25rem;">
                                        <?php echo $incidents_submitted; ?></div>
                                    <div class="stats-label" style="font-size: 1rem; opacity: 0.9;">Submitted Incidents
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
                <!-- In Review Card -->
                <div class="col-md-6">
                    <a href="incidents.php" class="text-decoration-none"
                        style="color: inherit; display: block; border-radius: 12px; overflow: hidden; transition: transform 0.2s;">
                        <div class="admin-stats-card info mb-0"
                            style="margin: 0; border: none; box-shadow: 0 4px 6px -1px rgba(0,0,0,.1), 0 2px 4px -1px rgba(0,0,0,.06);">
                            <div class="d-flex align-items-center p-4" style="color: white;">
                                <div class="stats-icon me-3" style="font-size: 2rem; opacity: 0.9;"><i
                                        class="fas fa-search"></i></div>
                                <div>
                                    <div class="stats-number"
                                        style="font-size: 2rem; font-weight: bold; line-height: 1; margin-bottom: 0.25rem;">
                                        <?php echo $incidents_review; ?></div>
                                    <div class="stats-label" style="font-size: 0.9rem; opacity: 0.9;">In Review</div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
                <!-- Resolved Card -->
                <div class="col-md-6">
                    <a href="incidents.php" class="text-decoration-none"
                        style="color: inherit; display: block; border-radius: 12px; overflow: hidden; transition: transform 0.2s;">
                        <div class="admin-stats-card success mb-0"
                            style="margin: 0; border: none; box-shadow: 0 4px 6px -1px rgba(0,0,0,.1), 0 2px 4px -1px rgba(0,0,0,.06);">
                            <div class="d-flex align-items-center p-4" style="color: white;">
                                <div class="stats-icon me-3" style="font-size: 2rem; opacity: 0.9;"><i
                                        class="fas fa-check-double"></i></div>
                                <div>
                                    <div class="stats-number"
                                        style="font-size: 2rem; font-weight: bold; line-height: 1; margin-bottom: 0.25rem;">
                                        <?php echo $incidents_resolved; ?></div>
                                    <div class="stats-label" style="font-size: 0.9rem; opacity: 0.9;">Resolved</div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>



    <!-- Quick Access Menu -->
    <h5 class="fw-bold mb-3 text-dark"><i class="fas fa-bars me-2 text-primary"></i>Quick Access Options</h5>
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <a href="requests.php" class="text-decoration-none">
                <div class="card border-0 shadow-sm rounded-4 h-100 btn-hover-effect bg-white">
                    <div class="card-body p-4 text-center">
                        <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-inline-flex align-items-center justify-content-center mb-3"
                            style="width: 65px; height: 65px;">
                            <i class="fas fa-file-alt fa-2x"></i>
                        </div>
                        <h5 class="text-dark fw-bold mb-2">Documents</h5>
                        <p class="text-dark opacity-75 small mb-0 px-2">Request barangay clearance, indigency, and other
                            important certificates easily.</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="incidents.php" class="text-decoration-none">
                <div class="card border-0 shadow-sm rounded-4 h-100 btn-hover-effect bg-white">
                    <div class="card-body p-4 text-center">
                        <div class="rounded-circle bg-danger bg-opacity-10 text-danger d-inline-flex align-items-center justify-content-center mb-3"
                            style="width: 65px; height: 65px;">
                            <i class="fas fa-exclamation-triangle fa-2x"></i>
                        </div>
                        <h5 class="text-dark fw-bold mb-2">Report Incident</h5>
                        <p class="text-dark opacity-75 small mb-0 px-2">Report an emergency, concern, or incident in
                            your area directly to the barangay.</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="profile.php" class="text-decoration-none">
                <div class="card border-0 shadow-sm rounded-4 h-100 btn-hover-effect bg-white">
                    <div class="card-body p-4 text-center">
                        <div class="rounded-circle bg-info bg-opacity-10 text-info d-inline-flex align-items-center justify-content-center mb-3"
                            style="width: 65px; height: 65px;">
                            <i class="fas fa-user-shield fa-2x"></i>
                        </div>
                        <h5 class="text-dark fw-bold mb-2">Profile</h5>
                        <p class="text-dark opacity-75 small mb-0 px-2">Manage your account information and ID
                            verification.</p>
                    </div>
                </div>
            </a>
        </div>
    </div>
</div>

<style>
    .btn-hover-effect {
        transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .btn-hover-effect:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05) !important;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const ctx = document.getElementById('requestsPieChart').getContext('2d');

        const dataEmpty = <?php echo ($total_pending + $total_approved + $total_released + $total_rejected) == 0 ? 'true' : 'false'; ?>;

        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: dataEmpty ? ['No Requests'] : ['Pending', 'Ready to Pickup', 'Released', 'Rejected'],
                datasets: [{
                    data: dataEmpty ? [1] : [<?php echo $total_pending; ?>, <?php echo $total_approved; ?>, <?php echo $total_released; ?>, <?php echo $total_rejected; ?>],
                    backgroundColor: dataEmpty ? ['#e5e7eb'] : ['#f59e0b', '#10b981', '#3b82f6', '#ef4444'], // Amber, Teal/Green, Blue, Red
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        display: !dataEmpty // Hide legend if no data
                    },
                    tooltip: {
                        enabled: !dataEmpty, // Disable tooltips if no data
                        callbacks: {
                            label: function (context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed !== null) {
                                    label += context.parsed;
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });

        // Incidents Graph
        const ctxIncidents = document.getElementById('incidentsPieChart').getContext('2d');
        const dataEmptyIncidents = <?php echo ($incidents_submitted + $incidents_review + $incidents_resolved) == 0 ? 'true' : 'false'; ?>;

        new Chart(ctxIncidents, {
            type: 'pie',
            data: {
                labels: dataEmptyIncidents ? ['No Incidents'] : ['Submitted', 'In Review', 'Resolved'],
                datasets: [{
                    data: dataEmptyIncidents ? [1] : [<?php echo $incidents_submitted; ?>, <?php echo $incidents_review; ?>, <?php echo $incidents_resolved; ?>],
                    backgroundColor: dataEmptyIncidents ? ['#e5e7eb'] : ['#ef4444', '#0ea5e9', '#10b981'], // Red, Blue, Green (Gray for empty)
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        display: !dataEmptyIncidents
                    },
                    tooltip: {
                        enabled: !dataEmptyIncidents,
                        callbacks: {
                            label: function (context) {
                                let label = context.label || '';
                                if (label) label += ': ';
                                if (context.parsed !== null) label += context.parsed;
                                return label;
                            }
                        }
                    }
                }
            }
        });
    });
</script>

<?php require_once __DIR__ . '/partials/user_dashboard_footer.php'; ?>
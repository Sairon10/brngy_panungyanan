<?php 
require_once __DIR__ . '/config.php';
if (!is_logged_in()) redirect('login.php'); 

// Check if user is verified and active (for residents only)
if ($_SESSION['role'] === 'resident') {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare('SELECT verification_status, is_active FROM users u LEFT JOIN residents r ON r.user_id = u.id WHERE u.id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $user_data = $stmt->fetch();
    
    // Check verification
    if ($user_data && $user_data['verification_status'] !== 'verified') {
        redirect('id_verification.php');
    }

    // Check active status
    $is_account_active = (bool)($user_data['is_active'] ?? true);
} else {
    $is_account_active = true;
}

$page_title = 'Report Incident';

$pdo = get_db_connection();

$info = $_SESSION['info'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['info'], $_SESSION['error']);

$was_cancel = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        $error = 'Invalid session. Please reload and try again.';
    } elseif (isset($_POST['cancel_incident_id'])) {
        $cancel_id = (int)$_POST['cancel_incident_id'];
        $cancel_reason = trim($_POST['cancel_reason'] ?? '');
        $stmt = $pdo->prepare('UPDATE incidents SET status = "canceled", notes = ? WHERE id = ? AND user_id = ? AND status = "submitted"');
        $stmt->execute([$cancel_reason, $cancel_id, $_SESSION['user_id']]);
        if ($stmt->rowCount() > 0) {
            $_SESSION['info'] = 'Incident report canceled successfully.';
            $_SESSION['was_cancel'] = true;
            header("Location: incidents.php?status_filter=all");
            exit;
        } else {
            $error = 'Could not cancel report. It may already be in review or resolved.';
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'reply') {
        $incident_id = (int)$_POST['incident_id'];
        $reply_msg = trim($_POST['message'] ?? '');
        
        if ($reply_msg !== '' && $incident_id > 0) {
            // Check if incident is NOT closed
            $status_stmt = $pdo->prepare('SELECT status FROM incidents WHERE id = ? AND user_id = ?');
            $status_stmt->execute([$incident_id, $_SESSION['user_id']]);
            $current_status = $status_stmt->fetchColumn();
            
            if ($current_status === 'closed') {
                $error = 'This conversation is closed and cannot be replied to.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO incident_messages (incident_id, user_id, message) VALUES (?, ?, ?)');
                $stmt->execute([$incident_id, $_SESSION['user_id'], $reply_msg]);
                
                // Set status back to in_review if it was something else? No, keep status.
                $info = 'Reply sent successfully.';
            }
        } else {
            $error = 'Please enter a message.';
        }
    } else {
        $desc = trim($_POST['description'] ?? '');

        $lat = $_POST['latitude'] ?? '';
        $lng = $_POST['longitude'] ?? '';
        
        if ($desc !== '' && is_numeric($lat) && is_numeric($lng)) {
            // Validate coordinates are within Panungayanan, General Trias, Cavite boundaries
            $lat = (float)$lat;
            $lng = (float)$lng;
            
            // Barangay Panungyanan boundaries only (restricted area)
            $minLat = 14.2200;
            $maxLat = 14.2500;
            $minLng = 120.9050;
            $maxLng = 120.9350;
            
            if ($lat >= $minLat && $lat <= $maxLat && $lng >= $minLng && $lng <= $maxLng) {
                // Handle image upload
                $imagePath = null;
                if (isset($_FILES['incident_image']) && $_FILES['incident_image']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['incident_image'];
                    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
                    $maxSize = 5 * 1024 * 1024; // 5MB
                    
                    if (!in_array($file['type'], $allowedTypes)) {
                        $error = 'Only JPG, JPEG, and PNG images are allowed.';
                    } elseif ($file['size'] > $maxSize) {
                        $error = 'Image size must not exceed 5MB.';
                    } else {
                        $uploadDir = __DIR__ . '/uploads/incidents/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }
                        
                        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                        $filename = 'incident_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
                        $uploadPath = $uploadDir . $filename;
                        
                        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                            $imagePath = 'uploads/incidents/' . $filename;
                        } else {
                            $error = 'Failed to upload image. Please try again.';
                        }
                    }
                }
                
                if (!$error) {
                    try {
                        if (!isset($_SESSION['user_id'])) {
                            $error = 'Session expired. Please login again.';
                        } else {
                            $stmt = $pdo->prepare('INSERT INTO incidents (user_id, description, latitude, longitude, image_path) VALUES (?, ?, ?, ?, ?)');
                            $stmt->execute([
                                $_SESSION['user_id'], 
                                $desc, 
                                (string)$lat, 
                                (string)$lng, 
                                $imagePath ?: null
                            ]);
                            
                            $incident_id = $pdo->lastInsertId();
                            
                            try {
                                $pdo->prepare('INSERT INTO incident_messages (incident_id, user_id, message) VALUES (?, ?, ?)')
                                    ->execute([$incident_id, $_SESSION['user_id'], $desc]);
                            } catch (PDOException $e) {
                                error_log('Could not insert into incident_messages: ' . $e->getMessage());
                            }
                            $admin_stmt = $pdo->prepare('SELECT id FROM users WHERE role = "admin"');
                            $admin_stmt->execute();
                            $admins = $admin_stmt->fetchAll();
                            
                            foreach ($admins as $admin) {
                                $notif_stmt = $pdo->prepare('INSERT INTO notifications (user_id, type, title, message, related_incident_id) VALUES (?, "incident_update", "New Incident Reported", ?, ?)');
                                $notif_stmt->execute([
                                    $admin['id'], 
                                    "A new incident has been reported by a resident.", 
                                    $incident_id
                                ]);
                            }
                            
                            $info = 'Incident submitted successfully. Admin has been notified.';
                        }
                    } catch (PDOException $e) {
                        error_log('Incident submission error: ' . $e->getMessage());
                        $error = 'Failed to submit incident: ' . htmlspecialchars($e->getMessage());
                    } catch (Exception $e) {
                        error_log('Incident submission error: ' . $e->getMessage());
                        $error = 'Failed to submit incident: ' . htmlspecialchars($e->getMessage());
                    }
                }
            } else {
                $error = 'Selected location must be within Barangay Panungyanan area only.';
            }
        } else {
            $error = 'Please provide a description and select a location within Barangay Panungyanan area.';
        }
    }
}

require_once __DIR__ . '/partials/user_dashboard_header.php'; 
?>

<style>
    .location-btn {
        transition: all 0.2s ease;
    }
    .location-btn:hover {
        background-color: #f0fdfa !important;
        color: #0d9488 !important;
        border-color: #0d9488 !important;
    }
    .active-location {
        background-color: #0d9488 !important;
        color: white !important;
        border-color: #0d9488 !important;
        box-shadow: 0 0.5rem 1rem rgba(13, 148, 136, 0.15) !important;
    }
    .btn-teal {
        background-color: #0d9488 !important;
        color: white !important;
        border-color: #0d9488 !important;
    }
</style>

<?php
// Pagination settings
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($page < 1) $page = 1;
$limit = 5;
$offset = ($page - 1) * $limit;

// Filter and Pagination Logic
$status_filter = $_GET['status_filter'] ?? 'all';
$query_params = [$_SESSION['user_id']];
$where_clause = "WHERE user_id = ?";

if ($status_filter !== 'all') {
    $where_clause .= " AND status = ?";
    $query_params[] = $status_filter;
}

// Count total incidents for this user with filter
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM incidents $where_clause");
$count_stmt->execute($query_params);
$total_rows = $count_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Main query with filter
$main_query = "SELECT i.*, n.title as notification_title, n.message as notification_message, n.created_at as notification_date 
              FROM incidents i 
              LEFT JOIN notifications n ON n.related_incident_id = i.id AND n.user_id = ? AND n.type = 'incident_response'
              " . str_replace('user_id', 'i.user_id', $where_clause) . "
              GROUP BY i.id
              ORDER BY i.id DESC 
              LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

$stmt = $pdo->prepare($main_query);
// We need to add the user_id for the LEFT JOIN as well (notification user_id)
$full_params = array_merge([$_SESSION['user_id']], $query_params);
$stmt->execute($full_params);
$rows = $stmt->fetchAll();

// Fetch messages for each incident
foreach ($rows as &$row) {
	$msg_stmt = $pdo->prepare('
		SELECT im.*, u.full_name, u.role 
		FROM incident_messages im 
		JOIN users u ON u.id = im.user_id 
		WHERE im.incident_id = ? 
		ORDER BY im.created_at ASC
	');
	$msg_stmt->execute([$row['id']]);
	$row['messages'] = $msg_stmt->fetchAll();
	
	// If no admin message in list, check if there is an admin_response column entry (Fallback for older records)
    $has_admin_msg = false;
    foreach ($row['messages'] as $m) {
        if ($m['role'] === 'admin') {
            $has_admin_msg = true;
            break;
        }
    }
    
	if (!$has_admin_msg && $row['admin_response']) {
        // If the resident's initial message is not in the'messages' table but it's a new report,
        // it was already added at line 121, so we just append the admin response.
		$row['messages'][] = [
            'incident_id' => $row['id'],
            'user_id' => $row['admin_response_by'] ?? 0,
            'role' => 'admin',
            'message' => $row['admin_response'],
            'created_at' => $row['admin_response_at'] ?? $row['created_at'],
            'full_name' => 'Admin'
        ];
	}
}
unset($row);
?>

<div class="row animate__animated animate__fadeInUp">
    <div class="col-12">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-4">
                <!-- Header with Action Button -->
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="width-12 height-12 rounded-3 bg-blue-50 text-blue-600 d-flex align-items-center justify-content-center">
                            <i class="fas fa-clipboard-list fa-lg"></i>
                        </div>
                        <div>
                            <h4 class="fw-bold mb-0 text-dark">My Reports</h4>
                            <p class="text-secondary small mb-0">Track and manage your reported incidents</p>
                        </div>
                    </div>
                    
                    <div class="ms-auto flex-shrink-0 d-flex align-items-center gap-2">
                        <button class="btn btn-rose text-white rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#newIncidentModal">
                            <i class="fas fa-plus me-2"></i>Report Incident
                        </button>
                        <form method="GET" class="m-0">
                            <select name="status_filter" class="form-select border-0 bg-transparent fw-semibold text-primary shadow-none ps-0 font-monospace" style="outline: none; cursor: pointer; text-align: right;" onchange="this.form.submit()">
                                <option value="all" <?php echo empty($_GET['status_filter']) || $_GET['status_filter'] === 'all' ? 'selected' : ''; ?>>All</option>
                                <option value="submitted" <?php echo isset($_GET['status_filter']) && $_GET['status_filter'] === 'submitted' ? 'selected' : ''; ?>>Pending</option>
                                <option value="in_review" <?php echo isset($_GET['status_filter']) && $_GET['status_filter'] === 'in_review' ? 'selected' : ''; ?>>In Review</option>
                                <option value="resolved" <?php echo isset($_GET['status_filter']) && $_GET['status_filter'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                <option value="canceled" <?php echo isset($_GET['status_filter']) && $_GET['status_filter'] === 'canceled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </form>
                    </div>
                </div>

                <?php if ($info): ?>
                    <div class="alert alert-success alert-dismissible fade show border-0 bg-teal-50 text-teal-600 rounded-3 mb-4">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($info); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show border-0 bg-rose-50 text-rose-600 rounded-3 mb-4">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                        <thead class="bg-light">
                            <tr class="text-secondary small text-uppercase">
                                <th class="py-3 border-0 ps-4">#</th>
                                <th class="py-3 border-0">NAME</th>
                                <th class="py-3 border-0" style="min-width: 120px;">Report Date</th>
                                <th class="py-3 border-0 text-center">Status</th>
                                <th class="py-3 border-0 text-center pe-4">Action</th>
                            </tr>
                        </thead>
                        <tbody class="border-top-0">
                            <?php if (empty($rows)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-secondary">
                                        <i class="fas fa-clipboard-list fa-3x mb-3 d-block opacity-25"></i>
                                        <p class="mb-0">No incidents reported yet.</p>
                                        <button class="btn btn-sm btn-link mt-2" data-bs-toggle="modal" data-bs-target="#newIncidentModal">Report your first incident</button>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($rows as $i => $r): ?>
                                    <tr>
                                        <td class="text-secondary ps-4"><?php echo $offset + $i + 1; ?></td>
                                        <td>
                                            <div class="fw-bold text-dark ps-2"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Resident'); ?></div>
                                        </td>
                                        <td class="text-secondary fw-medium">
                                            <?php echo date('M j, Y', strtotime($r['created_at'])); ?>
                                            <div class="small opacity-50"><?php echo date('g:i A', strtotime($r['created_at'])); ?></div>
                                        </td>
                                        <td class="text-center align-middle" style="min-width: 140px;">
                                            <?php
                                            $status = $r['status'] ?? 'submitted';
                                            $statusClass = match($status) {
                                                'submitted' => 'status-pending',
                                                'in_review' => 'status-review',
                                                'resolved' => 'status-resolved',
                                                'closed' => 'status-closed',
                                                'canceled' => 'status-canceled',
                                                default => 'bg-light text-dark'
                                            };
                                            $statusText = $status === 'submitted' ? 'Pending' : ucfirst($status);
                                            ?>
                                            <div class="d-flex justify-content-center">
                                                <span class="badge rounded-pill badge-status <?php echo $statusClass; ?>">
                                                    <i class="fas fa-circle me-1 small"></i><?php echo $statusText; ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="text-center pe-4">
                                            <div class="d-flex justify-content-center gap-2">
                                                <a href="incident_details.php?id=<?php echo (int)$r['id']; ?>" 
                                                   class="btn btn-sm btn-white border shadow-sm rounded-pill px-3"
                                                   title="View Details">
                                                    <i class="fas fa-eye me-1 text-primary"></i>View
                                                </a>
                                                <?php if ($r['status'] === 'submitted'): ?>
                                                    <form method="post" class="d-inline cancel-inc-form">
                                                        <?php echo csrf_field(); ?>
                                                        <input type="hidden" name="cancel_incident_id" value="<?php echo (int)$r['id']; ?>">
                                                        <button type="button" 
                                                                class="btn btn-sm btn-rose-50 text-rose-600 rounded-pill px-3" 
                                                                onclick="showIncidentCancelModal(this.closest('form'))"
                                                                title="Cancel Report">
                                                            <i class="fas fa-times me-1"></i>Cancel
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

<!-- View Incident Modal -->
<div class="modal fade" id="viewIncidentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-bottom p-4 bg-light">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-2 me-3">
                        <i class="fas fa-file-alt text-primary fa-lg"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="viewTitle">Incident #--</h5>
                        <p class="text-secondary small mb-0">Detailed information about the reported incident</p>
                    </div>
                </div>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0 bg-light bg-opacity-10">
                <div id="viewLoading" class="text-center py-5">
                    <div class="spinner-grow text-primary" role="status"></div>
                </div>
                
                <div id="viewContent" class="d-none animate__animated animate__fadeIn">
                    <div class="row g-0">
                        <!-- Left Main Content -->
                        <div class="col-lg-8 p-4 bg-white">
                            <!-- Incident Information -->
                            <div class="mb-4">
                                <h6 class="text-secondary small fw-bold text-uppercase border-bottom pb-2 mb-3">Incident Information</h6>
                                <div class="row g-3">
                                    <div class="col-md-6 text-dark small fw-semibold d-flex">
                                        <span class="text-secondary me-2">Status:</span>
                                        <span id="viewStatus" class="badge rounded-pill px-3 py-1 fw-bold"></span>
                                    </div>
                                    <div class="col-md-6 text-dark small fw-semibold d-flex">
                                        <span class="text-secondary me-2">Reported:</span>
                                        <span id="viewDate" class="fw-bold"></span>
                                    </div>
                                    <div class="col-12">
                                        <label class="text-secondary small fw-bold d-block mb-1">Description:</label>
                                        <div class="bg-light p-3 rounded-3 border-0">
                                            <p id="viewDescription" class="text-dark mb-0" style="white-space: pre-wrap; line-height: 1.6;"></p>
                                        </div>
                                    </div>
                                    <div id="viewImageContainer" class="col-12 d-none">
                                        <div class="rounded-4 overflow-hidden border shadow-sm mt-2" style="max-height: 300px;">
                                            <img id="viewImage" src="" class="w-100 h-100 object-fit-contain bg-dark cursor-pointer" onclick="window.open(this.src, '_blank')">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Resident Information -->
                            <div class="mb-4">
                                <h6 class="text-secondary small fw-bold text-uppercase border-bottom pb-2 mb-3">Resident Information</h6>
                                <div class="row g-3">
                                    <div class="col-md-6 small fw-semibold">
                                        <span class="text-secondary d-block">Name:</span>
                                        <span id="viewResName" class="text-dark"></span>
                                    </div>
                                    <div class="col-md-6 small fw-semibold">
                                        <span class="text-secondary d-block">Email:</span>
                                        <span id="viewResEmail" class="text-dark"></span>
                                    </div>
                                    <div class="col-md-6 small fw-semibold">
                                        <span class="text-secondary d-block">Phone:</span>
                                        <a href="" id="viewResPhoneLink" class="text-primary text-decoration-none d-flex align-items-center">
                                            <i class="fas fa-phone-alt me-1 x-small"></i><span id="viewResPhone"></span>
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Location Information -->
                            <div>
                                <h6 class="text-secondary small fw-bold text-uppercase border-bottom pb-2 mb-3">Location Information</h6>
                                <div class="row g-3">
                                    <div class="col-12 small fw-semibold mb-2">
                                        <span class="text-secondary me-2">Coordinates:</span>
                                        <span id="viewCoords" class="text-dark"></span>
                                    </div>
                                    <div class="col-12">
                                        <div id="viewMap" style="height: 250px;" class="rounded-4 border shadow-sm mb-3"></div>
                                        <div class="d-flex gap-2">
                                            <a id="linkGMap" href="#" target="_blank" class="btn btn-sm btn-teal flex-fill py-2 text-white fw-bold">
                                                <i class="fab fa-google me-1"></i> Google Maps
                                            </a>
                                            <a id="linkWaze" href="#" target="_blank" class="btn btn-sm btn-rose flex-fill py-2 text-white fw-bold">
                                                <i class="fab fa-waze me-1"></i> Waze
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Sidebar Content -->
                        <div class="col-lg-4 border-start bg-light bg-opacity-50">
                            <!-- Quick Actions (Only showing what's relevant for resident) -->
                            <div class="p-4 border-bottom bg-white bg-opacity-75">
                                <h6 class="fw-bold mb-3 d-flex align-items-center small">
                                    <i class="fas fa-info-circle me-2 text-secondary"></i>Quick Actions
                                </h6>
                                <div class="d-grid gap-2">
                                    <a href="incidents.php" class="btn btn-white border shadow-sm btn-sm py-2 text-secondary fw-semibold">
                                        <i class="fas fa-list me-1"></i> Back to Dashboard
                                    </a>
                                    <button class="btn btn-white border shadow-sm btn-sm py-2 text-secondary fw-semibold" onclick="window.print()">
                                        <i class="fas fa-print me-1"></i> Print Report
                                    </button>
                                </div>
                            </div>

                            <!-- Conversation/Timeline -->
                            <div class="p-4">
                                <h6 class="fw-bold mb-4 d-flex align-items-center small">
                                    <i class="fas fa-comments me-2 text-secondary"></i>Conversation
                                </h6>
                                <div id="viewTimeline" class="timeline-container pe-2" style="max-height: 400px; overflow-y: auto;">
                                    <!-- Messages loaded here -->
                                </div>
                                <div class="mt-4 pt-3 border-top d-grid">
                                    <a id="goToDetails" href="#" class="btn btn-primary rounded-pill py-2 shadow-sm fw-bold btn-sm">
                                        Open Full View & Reply <i class="fas fa-external-link-alt ms-1 small"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Status Badge Styles */
.badge-status {
    padding: 0.5rem 1rem;
    font-weight: 600;
    font-size: 0.75rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}
.status-pending { background-color: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
.status-review { background-color: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
.status-resolved { background-color: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.status-canceled { background-color: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
.status-closed { background-color: #f9fafb; color: #4b5563; border: 1px solid #e5e7eb; }

.btn-teal { background-color: #0d9488; border-color: #0d9488; }
.btn-teal:hover { background-color: #0f766e; border-color: #0f766e; }
.btn-rose { background-color: #e11d48; border-color: #e11d48; }
.btn-rose:hover { background-color: #be123c; border-color: #be123c; }

@media (min-width: 768px) {
    .border-end-md { border-right: 1px solid #dee2e6 !important; }
}

/* Timeline/Message Styles */
.timeline-msg {
    position: relative;
    padding-bottom: 20px;
}
.timeline-msg:last-child { padding-bottom: 0; }
.bubble {
    font-size: 0.85rem;
    padding: 12px;
    border-radius: 15px;
}
.bubble-admin { background: #f3f4f6; color: #1f2937; border-top-left-radius: 0; }
.bubble-user { background: #e0f2f1; color: #004d40; border-top-right-radius: 0; }
</style>

<!-- Custom Toast Notification -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="systemToast" class="toast border-0 shadow-lg rounded-3" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header border-0 bg-white">
            <i id="toastIcon" class="fas fa-info-circle me-2"></i>
            <strong class="me-auto" id="toastTitle">Notification</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body text-dark" id="toastMessage"></div>
    </div>
</div>
                    
                    <!-- Pagination UI -->
                    <?php if ($total_pages > 1): ?>
                        <div class="mt-4 d-flex justify-content-center">
                            <nav aria-label="Page navigation">
                                <ul class="pagination pagination-sm gap-1 border-0">
                                    <?php 
                                    $q_status = isset($_GET['status_filter']) ? '&status_filter=' . urlencode($_GET['status_filter']) : '';
                                    ?>
                                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                        <a class="page-link border-0 rounded-3 bg-light text-dark" href="<?php echo ($page <= 1) ? '#' : '?p=' . ($page - 1) . $q_status; ?>">
                                            <i class="fas fa-chevron-left small"></i>
                                        </a>
                                    </li>
                                    
                                    <?php
                                    $start = max(1, $page - 1);
                                    $end = min($total_pages, $start + 2);
                                    if ($end - $start < 2) $start = max(1, $end - 2);
                                    
                                    for ($i = $start; $i <= $end; $i++): 
                                        if ($i > 0): ?>
                                            <li class="page-item">
                                                <a class="page-link border-0 rounded-3 <?php echo ($i === $page) ? 'btn-teal text-white shadow-sm' : 'bg-light text-dark'; ?> px-3" href="?p=<?php echo $i . $q_status; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endif;
                                    endfor; ?>
                                    
                                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                        <a class="page-link border-0 rounded-3 bg-light text-dark" href="<?php echo ($page >= $total_pages) ? '#' : '?p=' . ($page + 1) . $q_status; ?>">
                                            <i class="fas fa-chevron-right small"></i>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- New Incident Report Modal -->
<div class="modal fade" id="newIncidentModal" tabindex="-1" aria-labelledby="newIncidentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="modal-title fw-bold" id="newIncidentModalLabel">
                    <i class="fas fa-exclamation-triangle text-danger me-2"></i>Report New Incident
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form method="post" enctype="multipart/form-data" id="incidentForm">
                    <?php echo csrf_field(); ?>
                    
                    <?php if (!$is_account_active): ?>
                        <div class="alert alert-danger border-0 bg-rose-50 text-rose-600 rounded-3 mb-4">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Account Inactive:</strong> Your account is currently deactivated. You cannot report new incidents at this time.
                        </div>
                    <?php endif; ?>
                    
                    <div class="row g-4">
                        <!-- Left: Instructions & Map -->
                        <div class="col-md-7">
                            <label class="form-label fw-semibold text-secondary small text-uppercase">1. Pin Location</label>
                            <div class="bg-light p-3 rounded-3 mb-3">
                                <div class="input-group mb-3 shadow-none border rounded-3 overflow-hidden">
                                    <span class="input-group-text bg-white border-0"><i class="fas fa-search text-secondary"></i></span>
                                    <input type="text" id="locationSearch" class="form-control border-0 shadow-none px-0" style="font-size: 0.9rem;" placeholder="Search street or landmark...">
                                    <button class="btn btn-primary px-3 fw-semibold btn-sm" type="button" id="searchLocationBtn">Search</button>
                                </div>
                                <div class="d-flex gap-2 mb-2">
                                    <button type="button" class="btn btn-white btn-sm border shadow-sm flex-fill py-2 text-secondary fw-semibold location-btn" id="useCurrentLocation">
                                        <i class="fas fa-location-arrow me-1"></i>Current
                                    </button>
                                    <button type="button" class="btn btn-white btn-sm border shadow-sm flex-fill py-2 text-secondary fw-semibold location-btn" id="selectOnMap">
                                        <i class="fas fa-map-marker-alt me-1"></i>Pick Map
                                    </button>
                                </div>
                                <div id="locationStatus" class="text-secondary" style="font-size: 0.75rem;">
                                    <i class="fas fa-info-circle me-1"></i> Area: Barangay Panungyanan
                                </div>
                            </div>
                            
                            <div id="map" style="height: 250px;" class="rounded-3 border"></div>
                            <input type="hidden" name="latitude" id="latitude">
                            <input type="hidden" name="longitude" id="longitude">
                        </div>
                        
                        <!-- Right: Details & Image -->
                        <div class="col-md-5">
                            <div class="mb-4">
                                <label class="form-label fw-semibold text-secondary small text-uppercase">2. Describe Incident</label>
                                <textarea name="description" class="form-control bg-light border-0" rows="5" style="font-size: 0.9rem;" placeholder="What happened?" required></textarea>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label fw-semibold text-secondary small text-uppercase">3. Upload Proof (Optional)</label>
                                <input type="file" name="incident_image" id="incident_image" class="form-control form-control-sm bg-light border-0" accept="image/*">
                                <div id="imagePreview" class="mt-2 d-none">
                                    <img id="previewImg" src="" alt="Preview" class="img-fluid rounded-2 shadow-sm" style="max-height: 100px;">
                                </div>
                            </div>
                            
                            <div class="d-grid pt-2">
                                <button class="btn btn-danger btn-lg rounded-pill shadow-sm py-3" type="submit" <?php echo !$is_account_active ? 'disabled' : ''; ?>>
                                    <i class="fas fa-paper-plane me-2"></i>Submit Report
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Incident Cancel Confirmation Modal -->
<div class="modal fade" id="incidentCancelConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-body text-center p-5">
                <div class="mb-4">
                    <div class="rounded-circle bg-rose-50 d-inline-flex align-items-center justify-content-center mx-auto" style="width: 80px; height: 80px;">
                        <i class="fas fa-times fa-2x text-rose-600"></i>
                    </div>
                </div>
                <h5 class="fw-bold text-dark mb-2">Cancel this report?</h5>
                <p class="text-secondary mb-3 small">Please provide a reason for cancelling this report.</p>
                <div class="mb-4 text-start">
                    <textarea id="incidentCancelReasonInput" class="form-control bg-light border-0" rows="3" placeholder="State your reason for cancellation..." required></textarea>
                    <div id="incidentCancelReasonError" class="text-danger small mt-1" style="display: none;">Please provide a reason.</div>
                </div>
                <div class="d-flex gap-3 justify-content-center">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">No, Keep</button>
                    <button type="button" class="btn btn-danger rounded-pill px-4" id="confirmIncidentCancelBtn">Yes, Cancel</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var map, marker; // For New Report
    var viewMap, viewMarker; // For Viewing Details
    var newIncidentModal = document.getElementById('newIncidentModal');
    var viewIncidentModal = document.getElementById('viewIncidentModal');

    window.showSystemToast = function(message, type = 'info') {
        var toast = document.getElementById('systemToast');
        var icon = document.getElementById('toastIcon');
        var title = document.getElementById('toastTitle');
        var body = document.getElementById('toastMessage');
        
        body.textContent = message;
        
        if (type === 'error') {
            icon.className = 'fas fa-exclamation-circle text-danger me-2';
            title.textContent = 'Error';
        } else if (type === 'success') {
            icon.className = 'fas fa-check-circle text-success me-2';
            title.textContent = 'Success';
        } else {
            icon.className = 'fas fa-info-circle text-primary me-2';
            title.textContent = 'Notice';
        }
        
        var bsToast = new bootstrap.Toast(toast, { delay: 4000 });
        bsToast.show();
    };

    window.viewIncident = function(id) {
        var modal = new bootstrap.Modal(viewIncidentModal);
        modal.show();
        
        document.getElementById('viewLoading').classList.remove('d-none');
        document.getElementById('viewContent').classList.add('d-none');
        
        fetch(`get_incident_details.php?id=${id}`)
            .then(res => res.json())
            .then(data => {
                document.getElementById('viewLoading').classList.add('d-none');
                if (data.error) {
                    showSystemToast(data.error, 'error');
                    modal.hide();
                    return;
                }
                
                document.getElementById('viewContent').classList.remove('d-none');
                document.getElementById('viewTitle').textContent = `Incident #${data.id}`;
                document.getElementById('viewDescription').textContent = data.description;
                document.getElementById('viewDate').textContent = data.formatted_date;
                
                // Resident Info
                document.getElementById('viewResName').textContent = data.resident_name;
                document.getElementById('viewResEmail').textContent = data.resident_email;
                document.getElementById('viewResPhone').textContent = data.resident_phone;
                document.getElementById('viewResPhoneLink').href = `tel:${data.resident_phone}`;
                
                // Location Info
                document.getElementById('viewCoords').textContent = `${data.latitude}, ${data.longitude}`;
                document.getElementById('linkGMap').href = `https://www.google.com/maps?q=${data.latitude},${data.longitude}`;
                document.getElementById('linkWaze').href = `https://waze.com/ul?ll=${data.latitude},${data.longitude}&navigate=yes`;
                
                var statusEl = document.getElementById('viewStatus');
                statusEl.textContent = data.status_label;
                statusEl.className = `badge rounded-pill px-3 py-1 fw-bold ${data.status_class}`;
                
                // Show "View Details" link if cancelled or rejected with notes
                const notes = (data.status === 'canceled' ? data.notes : (data.status === 'closed' ? data.admin_response : '')) || '';
                const hasNotes = notes.trim() !== '';
                const showReasonLink = (data.status === 'canceled' || data.status === 'closed') && hasNotes;
                
                // Remove existing reason link if any
                const existingLink = statusEl.parentElement.querySelector('.btn-show-reason-inc');
                if (existingLink) existingLink.remove();

                if (showReasonLink) {
                    const link = document.createElement('a');
                    link.href = 'javascript:void(0)';
                    link.className = 'text-primary ms-2 small fw-normal btn-show-reason-inc';
                    link.innerHTML = '<i class="fas fa-eye me-1"></i>View Details';
                    link.onclick = function() { showRequestReason(notes, data.status === 'canceled' ? 'Cancelled' : 'Rejected'); };
                    statusEl.parentElement.appendChild(link);
                }
                
                if (data.image_path) {
                    document.getElementById('viewImage').src = data.image_path;
                    document.getElementById('viewImageContainer').classList.remove('d-none');
                } else {
                    document.getElementById('viewImageContainer').classList.add('d-none');
                }
                
                // Timeline / Conversation
                var timeline = document.getElementById('viewTimeline');
                timeline.innerHTML = '';
                if (data.messages && data.messages.length > 0) {
                    data.messages.forEach(msg => {
                        var isUser = msg.role !== 'admin';
                        var html = `
                            <div class="timeline-msg ${isUser ? 'd-flex flex-column align-items-end' : ''}">
                                <div class="d-flex align-items-center mb-1">
                                    <span class="text-secondary" style="font-size: 0.75rem;">${isUser ? 'You' : msg.full_name} • ${msg.formatted_time}</span>
                                </div>
                                <div class="bubble ${isUser ? 'bubble-user' : 'bubble-admin'} shadow-sm">
                                    ${msg.message.replace(/\n/g, '<br>')}
                                </div>
                            </div>
                        `;
                        timeline.innerHTML += html;
                    });
                    // Scroll to bottom
                    setTimeout(() => { timeline.scrollTop = timeline.scrollHeight; }, 100);
                } else {
                    timeline.innerHTML = '<div class="text-center py-4 text-secondary small">No messages yet.</div>';
                }

                // Action Links
                document.getElementById('goToDetails').href = `incident_details.php?id=${data.id}`;
                
                // Map Location
                var latlng = [parseFloat(data.latitude), parseFloat(data.longitude)];
                setTimeout(() => {
                    if (!viewMap) {
                        viewMap = L.map('viewMap', {
                            zoomControl: false,
                            minZoom: 12,
                            maxZoom: 19
                        }).setView(latlng, 17);
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(viewMap);
                        viewMarker = L.marker(latlng).addTo(viewMap);
                    } else {
                        viewMap.setView(latlng, 17);
                        if (viewMarker) viewMarker.setLatLng(latlng);
                        viewMap.invalidateSize();
                    }
                }, 400);
            });
    };

    function showRequestReason(notes, status) {
        const statusLower = status.toLowerCase();
        const isCancellation = statusLower === 'cancelled' || statusLower === 'canceled';
        const titleColor = isCancellation ? 'text-secondary' : 'text-rose-600';
        const borderColor = isCancellation ? 'border-secondary' : 'border-rose-500';
        const btnColor = isCancellation ? '#6c757d' : '#e11d48';
        const titleText = isCancellation ? 'Reason for Cancellation' : 'Reason for Rejection';

        Swal.fire({
            title: '<div class="' + titleColor + ' fw-bold">' + titleText + '</div>',
            html: '<div class="text-start p-3 bg-light rounded border-start border-4 ' + borderColor + '" style="white-space: pre-wrap; font-size: 0.95rem; line-height: 1.6;">' + notes + '</div>',
            icon: 'info',
            confirmButtonText: 'Understood',
            confirmButtonColor: btnColor,
            width: '600px',
            customClass: {
                title: 'fs-4',
                confirmButton: 'px-4 py-2 rounded-pill fw-bold'
            }
        });
    }

    let _cancelIncForm = null;
    window.showIncidentCancelModal = function(form) {
        _cancelIncForm = form;
        document.getElementById('incidentCancelReasonInput').value = '';
        document.getElementById('incidentCancelReasonError').style.display = 'none';
        var modal = new bootstrap.Modal(document.getElementById('incidentCancelConfirmModal'));
        modal.show();
    };

    document.getElementById('confirmIncidentCancelBtn').addEventListener('click', function() {
        var reason = document.getElementById('incidentCancelReasonInput').value.trim();
        if (!reason) {
            document.getElementById('incidentCancelReasonError').style.display = 'block';
            document.getElementById('incidentCancelReasonInput').focus();
            return;
        }
        if (_cancelIncForm) {
            var reasonInput = _cancelIncForm.querySelector('input[name="cancel_reason"]');
            if (!reasonInput) {
                reasonInput = document.createElement('input');
                reasonInput.type = 'hidden';
                reasonInput.name = 'cancel_reason';
                _cancelIncForm.appendChild(reasonInput);
            }
            reasonInput.value = reason;
            bootstrap.Modal.getInstance(document.getElementById('incidentCancelConfirmModal')).hide();
            _cancelIncForm.submit();
        }
    });

    // Open modal automatically if there's an error from previous submission
    <?php if ($error && !isset($_POST['cancel_incident_id'])): ?>
        var myModal = new bootstrap.Modal(newIncidentModal);
        myModal.show();
    <?php endif; ?>

	// Barangay Panungyanan boundaries only (restricted area)
	var barangayBounds = L.latLngBounds(
		[14.2242, 120.9095], // Southwest corner
		[14.2471, 120.9305]  // Northeast corner
	);
	
	// Center of Panungyanan
	var panungyananCenter = [14.2350, 120.9218];

    // Listen for modal shown to initialize/fix map
    newIncidentModal.addEventListener('shown.bs.modal', function () {
        if (!map) {
            map = L.map('map', {
                maxBounds: barangayBounds,
                maxBoundsViscosity: 1.0
            }).setView(panungyananCenter, 15);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                minZoom: 14,
                maxZoom: 18
            }).addTo(map);

            // Add marker when clicking on the map
            map.on('click', function(e) {
                updateMarker(e.latlng);
            });
        } else {
            // Important: Leaflet maps in modals need to re-calculate size
            map.invalidateSize();
        }
    });

    function updateMarker(latlng) {
        var statusEl = document.getElementById('locationStatus');
        if (marker) {
            marker.setLatLng(latlng);
        } else {
            marker = L.marker(latlng, { draggable: true }).addTo(map);
            marker.on('dragend', function() {
                var pos = marker.getLatLng();
                if (barangayBounds.contains(pos)) {
                    document.getElementById('latitude').value = pos.lat;
                    document.getElementById('longitude').value = pos.lng;
                    statusEl.innerHTML = '<i class="fas fa-check-circle text-success me-1"></i> Location pinned inside Panungyanan';
                    statusEl.className = "text-success small fw-semibold d-flex align-items-center";
                } else {
                    marker.setLatLng(latlng);
                    statusEl.innerHTML = '<i class="fas fa-times-circle text-danger me-1"></i> Outside Barangay Panungyanan boundaries';
                    statusEl.className = "text-danger small fw-semibold d-flex align-items-center";
                }
            });
        }
        
        if (barangayBounds.contains(latlng)) {
            document.getElementById('latitude').value = latlng.lat;
            document.getElementById('longitude').value = latlng.lng;
            statusEl.innerHTML = '<i class="fas fa-check-circle text-success me-1"></i> Location pinned inside Panungyanan';
            statusEl.className = "text-success small fw-semibold d-flex align-items-center";
        } else {
            statusEl.innerHTML = '<i class="fas fa-times-circle text-danger me-1"></i> Outside Barangay Panungyanan boundaries';
            statusEl.className = "text-danger small fw-semibold d-flex align-items-center";
        }
    }

    // Location Mode Selection & Highlighting (Teal System Theme)
    function setActiveMode(btnId) {
        document.querySelectorAll('.location-btn').forEach(btn => {
            btn.style.backgroundColor = '';
            btn.style.color = '';
            btn.style.borderColor = '';
            btn.classList.add('btn-white', 'text-secondary');
        });
        
        var activeBtn = document.getElementById(btnId);
        if (activeBtn) {
            activeBtn.classList.remove('btn-white', 'text-secondary');
            activeBtn.style.backgroundColor = '#0d9488'; // System Teal
            activeBtn.style.color = '#ffffff';
            activeBtn.style.borderColor = '#0d9488';
        }
    }

    // Search Location functionality
    document.getElementById('searchLocationBtn').addEventListener('click', function() {
        var query = document.getElementById('locationSearch').value;
        if (!query) return;
        
        setActiveMode('none'); // Clear other modes
        
        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query + ', Panungyanan, General Trias, Cavite')}`)
            .then(response => response.json())
            .then(data => {
                if (data.length > 0) {
                    var latlng = L.latLng(data[0].lat, data[0].lon);
                    if (barangayBounds.contains(latlng)) {
                        map.setView(latlng, 17);
                        updateMarker(latlng);
                    } else {
                        document.getElementById('locationStatus').innerHTML = '<i class="fas fa-times-circle text-danger me-1"></i> Result is outside Barangay Panungyanan boundaries';
                        document.getElementById('locationStatus').className = "text-danger small fw-semibold d-flex align-items-center";
                    }
                } else {
                    showSystemToast('Location not found. Please be more specific.', 'error');
                }
            })
            .catch(err => showSystemToast('Error searching for location.', 'error'));
    });

    // Use Current Location
    document.getElementById('useCurrentLocation').addEventListener('click', function() {
        if (!navigator.geolocation) {
            showSystemToast('Geolocation is not supported by your browser.', 'error');
            return;
        }
        
        setActiveMode('useCurrentLocation');
        var originalInner = this.innerHTML;
        this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Locating...';
        
        navigator.geolocation.getCurrentPosition((position) => {
            var latlng = L.latLng(position.coords.latitude, position.coords.longitude);
            this.innerHTML = originalInner;
            
            if (barangayBounds.contains(latlng)) {
                map.setView(latlng, 18);
                updateMarker(latlng);
            } else {
                document.getElementById('locationStatus').innerHTML = '<i class="fas fa-times-circle text-danger me-1"></i> Your current location is outside Barangay Panungyanan';
                document.getElementById('locationStatus').className = "text-danger small fw-semibold d-flex align-items-center";
            }
        }, (error) => {
            this.innerHTML = originalInner;
            showSystemToast('Error getting your location: ' + error.message, 'error');
            setActiveMode('none');
        });
    });

    // Pick on Map
    document.getElementById('selectOnMap').addEventListener('click', function() {
        setActiveMode('selectOnMap');
        document.getElementById('locationStatus').innerHTML = '<i class="fas fa-hand-pointer text-primary me-1"></i> Click or tap on the map to pick a location';
        document.getElementById('locationStatus').className = "text-primary small fw-semibold d-flex align-items-center";
        document.getElementById('map').style.cursor = 'crosshair';
    });

    // Image Preview
    var incidentImageInput = document.getElementById('incident_image');
    if (incidentImageInput) {
        incidentImageInput.addEventListener('change', function() {
            var file = this.files[0];
            if (file) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('previewImg').src = e.target.result;
                    document.getElementById('imagePreview').classList.remove('d-none');
                }
                reader.readAsDataURL(file);
            }
        });
    }

	// Show success modal if there's a success message
	<?php 
    $display_info = $info ?: ($_SESSION['info'] ?? '');
    $is_cancel = $was_cancel || ($_SESSION['was_cancel'] ?? false);
    if (isset($_SESSION['was_cancel'])) unset($_SESSION['was_cancel']);
    
    if ($display_info): ?>
		// Create and show success modal
		var successModal = document.createElement('div');
		successModal.className = 'modal fade';
		successModal.id = 'successModal';
		successModal.setAttribute('tabindex', '-1');
		successModal.setAttribute('data-bs-backdrop', 'static');
		successModal.setAttribute('data-bs-keyboard', 'false');
		<?php if ($is_cancel): ?>
		successModal.innerHTML = `
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content border-0 shadow-lg rounded-4">
					<div class="modal-body text-center p-5">
						<div class="mb-4">
							<div class="rounded-circle bg-success bg-opacity-10 d-inline-flex align-items-center justify-content-center mx-auto" style="width: 80px; height: 80px;">
								<i class="fas fa-check-circle fa-3x text-success"></i>
							</div>
						</div>
						<p class="fw-semibold text-dark mb-4"><?php echo htmlspecialchars($display_info); ?></p>
						<button type="button" class="btn btn-primary btn-lg rounded-pill px-5" data-bs-dismiss="modal">
							<i class="fas fa-check me-2"></i>OK
						</button>
					</div>
				</div>
			</div>
		`;
		<?php else: ?>
		successModal.innerHTML = `
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content border-0 shadow-lg rounded-4">
					<div class="modal-body text-center p-5">
						<div class="mb-4">
							<div class="rounded-circle bg-success bg-opacity-10 d-inline-flex align-items-center justify-content-center mx-auto" style="width: 80px; height: 80px;">
								<i class="fas fa-check-circle fa-3x text-success"></i>
							</div>
						</div>
						<h4 class="fw-bold text-dark mb-3">Report Submitted Successfully!</h4>
						<p class="text-secondary mb-4"><?php echo htmlspecialchars($display_info); ?></p>
						<button type="button" class="btn btn-primary btn-lg rounded-pill px-5" data-bs-dismiss="modal">
							<i class="fas fa-check me-2"></i>OK
						</button>
					</div>
				</div>
			</div>
		`;
		<?php endif; ?>
		document.body.appendChild(successModal);
		
		// Show modal after page loads
		setTimeout(function() {
			var modal = new bootstrap.Modal(document.getElementById('successModal'));
			modal.show();
			
			// Remove modal from DOM after it's hidden
			document.getElementById('successModal').addEventListener('hidden.bs.modal', function() {
				successModal.remove();
			});
		}, 300);
	<?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/partials/user_dashboard_footer.php'; ?>

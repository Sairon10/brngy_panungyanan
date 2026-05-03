<?php require_once __DIR__ . '/partials/user_dashboard_header.php'; ?>
<?php if (!is_logged_in()) redirect('login.php'); ?>

<?php
$pdo = get_db_connection();
$info = '';
$error = '';

$incident_id = (int)($_GET['id'] ?? 0);

if (!$incident_id) {
	$error = 'Invalid incident ID.';
} else {
	// Fetch incident details and user status
	// Fetch incident details and user status by joining residents table for phone
	$stmt = $pdo->prepare('SELECT i.*, u.full_name as resident_name, u.email as resident_email, r.phone as resident_phone 
                           FROM incidents i 
                           JOIN users u ON u.id = i.user_id 
                           LEFT JOIN residents r ON u.id = r.user_id
                           WHERE i.id = ? AND i.user_id = ?');
	$stmt->execute([$incident_id, $_SESSION['user_id']]);
	$incident = $stmt->fetch();
    
    $is_account_active = true; // Default to true if column is missing
	
	if (!$incident) {
		$error = 'Incident not found or you do not have permission to view it.';
	} else {
		// Fetch all messages for this incident
		$messages_stmt = $pdo->prepare('
			SELECT im.*, u.full_name, u.role 
			FROM incident_messages im 
			JOIN users u ON u.id = im.user_id 
			WHERE im.incident_id = ? 
			ORDER BY im.created_at ASC
		');
		$messages_stmt->execute([$incident_id]);
		$messages = $messages_stmt->fetchAll();
	}
}

// Handle reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($incident)) {
	if (csrf_validate()) {
		$action = $_POST['action'] ?? '';
		
		if ($action === 'reply') {
            if (!($is_account_active ?? true)) {
                $error = 'Your account is deactivated. You cannot send replies.';
            } else {
    			$message = trim($_POST['message'] ?? '');
                if ($message !== '') {
                    try {
    					$pdo->prepare('INSERT INTO incident_messages (incident_id, user_id, message) VALUES (?, ?, ?)')
    						->execute([$incident_id, $_SESSION['user_id'], $message]);
    					
    					// Create notification for admin
    					$admin_stmt = $pdo->prepare('SELECT id FROM users WHERE role = "admin"');
    					$admin_stmt->execute();
    					$admins = $admin_stmt->fetchAll();
    					
    					foreach ($admins as $admin) {
    						$pdo->prepare('INSERT INTO notifications (user_id, type, title, message, related_incident_id) VALUES (?, "incident_update", "New Reply on Incident", ?, ?)')
    							->execute([$admin['id'], "A resident replied to incident #{$incident_id}.", $incident_id]);
    					}
    					
    					$info = 'Your reply has been sent successfully.';
    					
    					// Refresh messages
    					$messages_stmt->execute([$incident_id]);
    					$messages = $messages_stmt->fetchAll();
    				} catch (PDOException $e) {
    					$error = 'Failed to send reply. Please try again.';
    				}
                } else {
                    $error = 'Please enter a message.';
                }
            }
		}
	} else {
		$error = 'Invalid session. Please reload and try again.';
	}
}
?>

<div class="container-fluid py-4 bg-light min-vh-100">
	<div class="row justify-content-center">
		<div class="col-xl-11">
            <?php if ($info): ?>
                <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($info); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error && !isset($incident)): ?>
                <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($incident) && $incident): ?>
                <div class="row g-4">
                    <!-- Left column: Information -->
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                            <div class="card-header bg-white border-0 p-4 pb-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-danger bg-opacity-10 p-2 rounded-3 me-3">
                                            <i class="fas fa-exclamation-triangle text-danger"></i>
                                        </div>
                                        <div>
                                            <h4 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($incident['resident_name']); ?></h4>
                                            <p class="text-secondary small mb-0">Detailed information about the reported incident</p>
                                        </div>
                                    </div>
                                    <a href="incidents.php" class="btn btn-outline-secondary border-0 btn-sm rounded-3">
                                        <i class="fas fa-arrow-left me-1"></i> Back to List
                                    </a>
                                </div>
                                <hr class="my-4 opacity-50">
                            </div>

                            <div class="card-body p-4 pt-0">
                                <!-- Incident Info -->
                                <div class="mb-5">
                                    <h6 class="text-dark opacity-50 small fw-bold text-uppercase mb-3">Incident Information</h6>
                                    <div class="row g-3">
                                        <div class="col-md-6 small">
                                            <span class="text-dark opacity-75 d-block">Status:</span>
                                            <?php
                                            $status = $incident['status'] ?? 'submitted';
                                            $statusLabel = $status === 'submitted' ? 'Pending' : ($status === 'closed' ? 'Rejected' : ucfirst($status));
                                            $statusClass = match($status) {
                                                'submitted' => 'status-pending',
                                                'in_review' => 'status-review',
                                                'resolved' => 'status-resolved',
                                                'closed' => 'status-closed',
                                                'canceled' => 'status-canceled',
                                                default => 'bg-light text-dark'
                                            };
                                            ?>
                                            <span class="badge rounded-pill badge-status <?php echo $statusClass; ?> mt-1"><?php echo $statusLabel; ?></span>
                                            
                                            <?php 
                                            $reason = ($status === 'canceled' ? $incident['notes'] : ($status === 'closed' ? $incident['admin_response'] : ''));
                                            if ($reason): 
                                            ?>
                                                <div class="mt-2 p-2 px-3 rounded-3 bg-light border-start border-4 <?php echo $status === 'canceled' ? 'border-secondary' : 'border-rose-500'; ?> small animate__animated animate__fadeIn">
                                                    <div class="fw-bold text-dark small mb-1">
                                                        <i class="fas fa-info-circle me-1"></i> <?php echo $status === 'canceled' ? 'Reason for Cancellation:' : 'Reason for Rejection:'; ?>
                                                    </div>
                                                    <div class="text-secondary"><?php echo nl2br(htmlspecialchars($reason)); ?></div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-6 small">
                                            <span class="text-dark opacity-75 d-block">Reported:</span>
                                            <span class="text-dark fw-bold"><?php echo date('M j, Y g:i A', strtotime($incident['created_at'])); ?></span>
                                        </div>
                                        <div class="col-12">
                                            <span class="text-dark opacity-75 small d-block mb-1">Description:</span>
                                            <div class="bg-light p-3 rounded-4 border-0">
                                                <p class="text-dark mb-0" style="white-space: pre-wrap; font-size: 0.95rem; line-height: 1.6;"><?php echo htmlspecialchars($incident['description']); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Resident Information -->
                                <div class="mb-5">
                                    <h6 class="text-dark opacity-50 small fw-bold text-uppercase mb-3">Resident Information</h6>
                                    <div class="row g-3">
                                        <div class="col-md-6 small">
                                            <span class="text-dark opacity-75 d-block">Name:</span>
                                            <span class="text-dark fw-bold"><?php echo htmlspecialchars($incident['resident_name']); ?></span>
                                        </div>
                                        <div class="col-md-6 small">
                                            <span class="text-dark opacity-75 d-block">Email:</span>
                                            <span class="text-dark fw-bold"><?php echo htmlspecialchars($incident['resident_email']); ?></span>
                                        </div>
                                        <div class="col-md-6 small">
                                            <span class="text-dark opacity-75 d-block">Phone:</span>
                                            <a href="tel:<?php echo $incident['resident_phone']; ?>" class="text-primary text-decoration-none fw-bold">
                                                <i class="fas fa-phone-alt me-1 text-primary" style="font-size: 0.75rem;"></i> <?php echo htmlspecialchars($incident['resident_phone']); ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <!-- Location Information -->
                                <div>
                                    <h6 class="text-dark opacity-50 small fw-bold text-uppercase mb-3">Location Information</h6>
                                    <div class="row g-4 align-items-center">
                                        <div class="col-md-6">
                                            <p class="small mb-3">
                                                <span class="text-dark opacity-75">Coordinates:</span>
                                                <span class="text-dark fw-bold ms-2"><?php echo number_format($incident['latitude'], 7) . ', ' . number_format($incident['longitude'], 7); ?></span>
                                            </p>
                                            <div class="d-flex gap-2 mb-3">
                                                <a href="https://www.google.com/maps?q=<?php echo $incident['latitude'] . ',' . $incident['longitude']; ?>" target="_blank" class="btn btn-dark text-white fw-bold btn-sm flex-fill py-2">
                                                    <i class="fab fa-google me-1"></i> Google Maps
                                                </a>
                                                <a href="https://waze.com/ul?ll=<?php echo $incident['latitude'] . ',' . $incident['longitude']; ?>&navigate=yes" target="_blank" class="btn btn-rose text-white fw-bold btn-sm flex-fill py-2">
                                                    <i class="fab fa-waze me-1"></i> Waze
                                                </a>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <?php if ($incident['image_path']): ?>
                                                <div class="rounded-4 overflow-hidden shadow-sm border" style="height: 120px;">
                                                    <img src="<?php echo htmlspecialchars($incident['image_path']); ?>" class="w-100 h-100 object-fit-cover cursor-pointer" onclick="window.open(this.src, '_blank')">
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-12">
                                            <div id="detailMap" style="height: 350px;" class="rounded-4 border shadow-sm"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Sidebar -->
                    <div class="col-lg-4">
                        <!-- Quick Actions -->
                        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                            <div class="card-header bg-white border-0 p-4 pb-0">
                                <h6 class="fw-bold text-dark d-flex align-items-center mb-0">
                                    <i class="fas fa-info-circle text-secondary me-2"></i>Quick Actions
                                </h6>
                            </div>
                            <div class="card-body p-4">
                                <div class="d-grid gap-2">
                                    <a href="incidents.php" class="btn btn-white border shadow-sm py-2 rounded-3 text-secondary fw-semibold">
                                        <i class="fas fa-list me-1"></i> Back to Incidents List
                                    </a>
                                    <a href="https://www.google.com/maps?q=<?php echo $incident['latitude'] . ',' . $incident['longitude']; ?>" target="_blank" class="btn btn-dark text-white py-2 rounded-3 fw-bold">
                                        <i class="fab fa-google me-1"></i> Open in Google Maps
                                    </a>
                                    <a href="https://waze.com/ul?ll=<?php echo $incident['latitude'] . ',' . $incident['longitude']; ?>&navigate=yes" target="_blank" class="btn btn-rose text-white py-2 rounded-3 fw-bold">
                                        <i class="fab fa-waze me-1"></i> Open in Waze
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Conversation -->
                        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                            <div class="card-header bg-white border-0 p-4 pb-0">
                                <h6 class="fw-bold text-dark d-flex align-items-center mb-0">
                                    <i class="fas fa-comments text-secondary me-2"></i>Conversation
                                </h6>
                            </div>
                            <div class="card-body p-4">
                                <div id="conversationContainer" style="max-height: 400px; overflow-y: auto;" class="pe-2 mb-4">
                                    <?php if (!empty($messages)): ?>
                                        <?php foreach ($messages as $msg): ?>
                                            <?php $isUser = ($msg['role'] !== 'admin'); ?>
                                            <div class="mb-4 <?php echo $isUser ? 'ms-4' : 'me-4'; ?>">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <strong class="small <?php echo $isUser ? 'text-secondary' : 'text-primary'; ?>"><?php echo $isUser ? 'You' : htmlspecialchars($msg['full_name']); ?></strong>
                                                    <span class="text-secondary" style="font-size: 0.7rem;"><?php echo date('M j, Y g:i A', strtotime($msg['created_at'])); ?></span>
                                                </div>
                                                <div class="p-3 rounded-4 shadow-sm <?php echo $isUser ? 'bg-dark bg-opacity-10 text-dark' : 'bg-light text-dark'; ?>" 
                                                     style="font-size: 0.9rem; border-top-<?php echo $isUser ? 'right' : 'left'; ?>-radius: 0;">
                                                    <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-center py-5">
                                            <div class="rounded-circle bg-light d-inline-flex p-3 mb-2">
                                                <i class="fas fa-comment-dots text-secondary opacity-50"></i>
                                            </div>
                                            <p class="text-secondary small mb-0">No messages yet</p>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Reply Form -->
                                <?php if ($incident['status'] === 'closed'): ?>
                                    <div class="bg-light p-3 rounded-4 text-center small text-secondary">
                                        <i class="fas fa-lock me-1"></i> This conversation is closed.
                                    </div>
                                <?php elseif (!($is_account_active ?? true)): ?>
                                    <div class="bg-rose-50 p-3 rounded-4 text-center small text-rose-600">
                                        <i class="fas fa-user-lock me-1"></i> Account is inactive.
                                    </div>
                                <?php else: ?>
                                    <form method="post" id="replyForm" class="d-flex gap-2 align-items-end">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="reply">
                                        <div class="flex-grow-1">
                                            <textarea name="message" class="form-control border-0 bg-light rounded-4 shadow-none small" rows="2" placeholder="Send a message..." required style="resize: none;"></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-dark text-white rounded-circle shadow-sm" style="width: 40px; height: 40px; padding: 0;">
                                            <i class="fas fa-paper-plane px-1"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
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
                .status-canceled { background-color: #fff1f2; color: #e11d48; border: 1px solid #fecdd3; }
                .status-closed { background-color: #fef2f2; color: #991b1b; border: 1px solid #fee2e2; }

                .btn-dark { background-color: #1e293b; border-color: #1e293b; }
                .btn-dark:hover { background-color: #0f172a; border-color: #0f172a; }
                .btn-rose { background-color: #e11d48; border-color: #e11d48; }
                .btn-rose:hover { background-color: #be123c; border-color: #be123c; }
                .bg-dark-50 { background-color: #f8fafc; }
                .text-dark-800 { color: #1e293b; }
                </style>

                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Update Page Title
                    document.title = "Incident #<?php echo $incident_id; ?> | Details";

                    // Map setup
                    var barangayBounds = L.latLngBounds([14.2242, 120.9095], [14.2471, 120.9305]);
                    var latlng = [<?php echo $incident['latitude']; ?>, <?php echo $incident['longitude']; ?>];
                    
                    const detailMap = L.map('detailMap', {
                        maxBounds: barangayBounds,
                        maxBoundsViscosity: 1.0,
                        minZoom: 15,
                        maxZoom: 19,
                        zoomControl: false
                    }).setView(latlng, 17);
                    
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(detailMap);
                    L.control.zoom({position: 'bottomright'}).addTo(detailMap);
                    L.marker(latlng).addTo(detailMap);

                    // Scroll to bottom of conversation
                    const container = document.getElementById('conversationContainer');
                    if (container) {
                        container.scrollTop = container.scrollHeight;
                    }
                });
                </script>
            <?php endif; ?>
		</div>
	</div>
</div>

<?php require_once __DIR__ . '/partials/user_dashboard_footer.php'; ?>


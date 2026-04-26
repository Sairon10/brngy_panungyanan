<?php 
require_once __DIR__ . '/../config.php';
if (!is_admin()) redirect('../index.php');
require_once __DIR__ . '/../includes/email_service.php';
require_once __DIR__ . '/../includes/sms_service.php';
require_once __DIR__ . '/header.php'; 
?>

<?php
$page_title = 'Incident Details';


$pdo = get_db_connection();
$info = '';
$error = '';

$incident_id = (int)($_GET['id'] ?? 0);

if (!$incident_id) {
	$error = 'Invalid incident ID.';
} else {
	// Fetch incident details with user and admin response info
	$stmt = $pdo->prepare('
		SELECT 
			i.*,
			u.full_name as resident_name,
			u.email as resident_email,
			r.phone as resident_phone,
			admin_resp.full_name as admin_name
		FROM incidents i
		JOIN users u ON u.id = i.user_id
		LEFT JOIN residents r ON r.user_id = u.id
		LEFT JOIN users admin_resp ON admin_resp.id = i.admin_response_by
		WHERE i.id = ?
	');
	$stmt->execute([$incident_id]);
	$incident = $stmt->fetch();
	
	if (!$incident) {
		$error = 'Incident not found.';
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
		
		// If no messages in new table, check old admin_response field
		if (empty($messages) && $incident['admin_response']) {
			// Create messages from old structure
			$pdo->prepare('INSERT INTO incident_messages (incident_id, user_id, message, created_at) VALUES (?, ?, ?, ?)')
				->execute([$incident_id, $incident['user_id'], $incident['description'], $incident['created_at']]);
			
			if ($incident['admin_response_by']) {
				$pdo->prepare('INSERT INTO incident_messages (incident_id, user_id, message, created_at) VALUES (?, ?, ?, ?)')
					->execute([$incident_id, $incident['admin_response_by'], $incident['admin_response'], $incident['admin_response_at']]);
			}
			
			// Re-fetch messages
			$messages_stmt->execute([$incident_id]);
			$messages = $messages_stmt->fetchAll();
		}
	}
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($incident)) {
	if (csrf_validate()) {
		$action = $_POST['action'] ?? '';
		
		if ($action === 'update_status') {
			$status = $_POST['status'] ?? 'submitted';
            $notes = trim($_POST['rejection_notes'] ?? '');

			$pdo->prepare('UPDATE incidents SET status=? WHERE id=?')->execute([$status, $incident_id]);

            // Re-fetch incident with all contact info
			$res_stmt = $pdo->prepare('
				SELECT i.*, u.full_name as resident_name, u.email as resident_email, r.phone as resident_phone
				FROM incidents i
				JOIN users u ON u.id = i.user_id
				LEFT JOIN residents r ON r.user_id = u.id
				WHERE i.id = ?
			');
			$res_stmt->execute([$incident_id]);
			$incidentData = $res_stmt->fetch();

            if ($incidentData) {
                // Prepare notes for notification functions
                $incidentData['notes'] = $notes;
                $displayStatus = ($status === 'submitted' ? 'Pending' : ($status === 'closed' ? 'Rejected' : ucfirst(str_replace('_', ' ', $status))));

                // 1. In-app notification
                $pdo->prepare('INSERT INTO notifications (user_id, type, title, message, related_incident_id) VALUES (?, "incident_response", "Incident Status Update", ?, ?)')
                    ->execute([
                        $incidentData['user_id'], 
                        "Status: " . $displayStatus . ($notes ? ". Reason: $notes" : ""),
                        $incident_id
                    ]);

                // 2. Email notification
                if (!empty($incidentData['resident_email'])) {
                    send_incident_status_email($incidentData['resident_email'], $status, $incidentData);
                }

                // 3. SMS notification
                if (!empty($incidentData['resident_phone'])) {
                    send_incident_status_sms($incidentData['resident_phone'], $status, $incidentData);
                }

                if ($notes !== '') {
                    // Also save as admin response/message
                    $pdo->prepare('INSERT INTO incident_messages (incident_id, user_id, message) VALUES (?, ?, ?)')
                        ->execute([$incident_id, $_SESSION['user_id'], "Status updated to " . $displayStatus . ". Note: " . $notes]);
                    
                    $pdo->prepare('UPDATE incidents SET admin_response=?, admin_response_by=?, admin_response_at=NOW() WHERE id=?')
                        ->execute([$notes, $_SESSION['user_id'], $incident_id]);
                }
            }

			$info = 'Incident status updated successfully.';
			// Refresh incident data
			$stmt->execute([$incident_id]);
			$incident = $stmt->fetch();
            // Refresh messages
            $messages_stmt->execute([$incident_id]);
            $messages = $messages_stmt->fetchAll();
        } elseif ($action === 'respond') {
			$response = trim($_POST['admin_response'] ?? '');
			if ($response !== '') {
				// Add message to incident_messages table
				$pdo->prepare('INSERT INTO incident_messages (incident_id, user_id, message) VALUES (?, ?, ?)')
					->execute([$incident_id, $_SESSION['user_id'], $response]);
				
				// Also update the old admin_response field for backward compatibility
				$pdo->prepare('UPDATE incidents SET admin_response=?, admin_response_by=?, admin_response_at=NOW() WHERE id=?')
					->execute([$response, $_SESSION['user_id'], $incident_id]);
				
				// Create notification for the resident
				if ($incident['user_id']) {
					$pdo->prepare('INSERT INTO notifications (user_id, type, title, message, related_incident_id) VALUES (?, "incident_response", "Admin Response", ?, ?)')
						->execute([$incident['user_id'], $response, $incident_id]);

                    // Send Email and SMS for direct response too
                    $res_stmt = $pdo->prepare('
                        SELECT i.*, u.full_name as resident_name, u.email as resident_email, r.phone as resident_phone
                        FROM incidents i
                        JOIN users u ON u.id = i.user_id
                        LEFT JOIN residents r ON r.user_id = u.id
                        WHERE i.id = ?
                    ');
                    $res_stmt->execute([$incident_id]);
                    $incidentData = $res_stmt->fetch();
                    
                    if ($incidentData) {
                        $incidentData['notes'] = $response;
                        if (!empty($incidentData['resident_email'])) {
                            send_incident_status_email($incidentData['resident_email'], $incidentData['status'], $incidentData);
                        }
                        if (!empty($incidentData['resident_phone'])) {
                            send_incident_status_sms($incidentData['resident_phone'], $incidentData['status'], $incidentData);
                        }
                    }
				}
				
				$info = 'Response sent to resident successfully.';
				// Refresh incident data
				$stmt->execute([$incident_id]);
				$incident = $stmt->fetch();
				
				// Refresh messages
				$messages_stmt->execute([$incident_id]);
				$messages = $messages_stmt->fetchAll();
			} else {
				$error = 'Please provide a response message.';
			}
		}
	} else {
		$error = 'Invalid session. Please reload and try again.';
	}
}
?>

<div class="container-fluid">
	<?php if (isset($info) && $info): ?>
        <script>
            Swal.fire({
                title: 'Updated Successfully',
                text: 'The incident status has been updated and notifications have been triggered.',
                icon: 'success',
                confirmButtonColor: '#0d9488',
                confirmButtonText: 'OK'
            });
        </script>
    <?php endif; ?>
    <?php if (isset($error) && $error): ?>
        <script>
            Swal.fire({
                title: 'Error',
                text: '<?php echo $error; ?>',
                icon: 'error',
                confirmButtonColor: '#dc2626',
                confirmButtonText: 'OK'
            });
        </script>
    <?php endif; ?>
	
	<?php if (isset($incident) && $incident): ?>
		<div class="row">
			<div class="col-md-8">
				<div class="admin-table">
					<div class="p-3 border-bottom d-flex justify-content-between align-items-center">
						<div>
							<h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Incident #<?php echo (int)$incident['id']; ?></h5>
							<p class="text-muted mb-0">Detailed information about the reported incident</p>
						</div>
						<a href="incidents.php" class="btn btn-outline-secondary btn-sm">
							<i class="fas fa-arrow-left me-2"></i>Back to List
						</a>
					</div>
					
					<div class="p-4">
						<!-- Incident Information -->
						<div class="mb-4">
							<h6 class="text-muted text-uppercase small mb-3">Incident Information</h6>
							<div class="row mb-3">
								<div class="col-md-6">
									<strong>Status:</strong>
									<?php
									$statusClass = match($incident['status']) {
										'submitted' => 'bg-warning',
										'in_review' => 'bg-info',
										'resolved' => 'bg-success',
										'closed' => 'bg-secondary',
										'canceled' => 'bg-danger',
										default => 'bg-dark'
									};
									?>
									<span class="badge <?php echo $statusClass; ?> ms-2"><?php echo $incident['status']==='submitted' ? 'Pending' : ($incident['status']==='closed' ? 'Rejected' : ucfirst(str_replace('_', ' ', $incident['status']))); ?></span>
								</div>
								<div class="col-md-6">
									<strong>Reported:</strong>
									<span class="text-muted ms-2"><?php echo date('M j, Y g:i A', strtotime($incident['created_at'])); ?></span>
								</div>
							</div>

                            <?php 
                            $cancellationReason = trim($incident['notes'] ?? '');
                            $rejectionReason = trim($incident['admin_response'] ?? '');
                            $displayReason = !empty($cancellationReason) ? $cancellationReason : $rejectionReason;
                            
                            if (($incident['status'] === 'canceled' || $incident['status'] === 'closed') && !empty($displayReason)): 
                            ?>
                                <div class="alert alert-warning border-0 rounded-3 mb-4 shadow-sm">
                                    <div class="fw-bold text-dark small mb-1">
                                        <?php if ($incident['status'] === 'canceled'): ?>
                                            <i class="fas fa-ban me-1 text-danger"></i> Reason for Cancellation (Resident):
                                        <?php else: ?>
                                            <i class="fas fa-info-circle me-1 text-warning"></i> Reason for Rejection (Admin):
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-dark small"><?php echo nl2br(htmlspecialchars($displayReason)); ?></div>
                                </div>
                            <?php endif; ?>
							
							<div class="mb-3">
								<strong>Description:</strong>
								<div class="mt-2 p-3 bg-light rounded">
									<?php echo nl2br(htmlspecialchars($incident['description'])); ?>
								</div>
							</div>
							
							<?php if ($incident['image_path']): ?>
								<div class="mb-3">
									<strong>Incident Image:</strong>
									<div class="mt-2">
										<img src="../<?php echo htmlspecialchars($incident['image_path']); ?>" 
											 alt="Incident Image" 
											 class="img-fluid rounded shadow-sm" 
											 style="max-height: 400px; cursor: pointer;"
											 onclick="window.open(this.src, '_blank')">
									</div>
								</div>
							<?php endif; ?>
						</div>
						
						<!-- Resident Information -->
						<div class="mb-4">
							<h6 class="text-muted text-uppercase small mb-3">Resident Information</h6>
							<div class="row">
								<div class="col-md-6 mb-2">
									<strong>Name:</strong>
									<span class="text-muted ms-2"><?php echo htmlspecialchars($incident['resident_name'] ?? ''); ?></span>
								</div>
								<div class="col-md-6 mb-2">
									<strong>Email:</strong>
									<span class="text-muted ms-2"><?php echo !empty($incident['resident_email']) ? htmlspecialchars($incident['resident_email']) : 'N/A'; ?></span>
								</div>
								<?php if (!empty($incident['resident_phone'])): ?>
								<div class="col-md-6 mb-2">
									<strong>Phone:</strong>
									<a href="tel:<?php echo htmlspecialchars($incident['resident_phone']); ?>" class="text-primary ms-2 text-decoration-none">
										<i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($incident['resident_phone']); ?>
									</a>
								</div>
								<?php endif; ?>
							</div>
						</div>
						
						<!-- Location Information -->
						<div class="mb-4">
							<h6 class="text-muted text-uppercase small mb-3">Location Information</h6>
							<div class="row mb-3">
								<div class="col-md-6">
									<strong>Coordinates:</strong>
									<span class="text-muted ms-2"><?php echo htmlspecialchars($incident['latitude']); ?>, <?php echo htmlspecialchars($incident['longitude']); ?></span>
								</div>
								<div class="col-md-6">
									<?php
									$googleMapsUrl = 'https://www.google.com/maps/dir/?api=1&destination=' . $incident['latitude'] . ',' . $incident['longitude'];
									$wazeUrl = 'https://waze.com/ul?ll=' . $incident['latitude'] . ',' . $incident['longitude'] . '&navigate=yes';
									?>
									<a href="<?php echo $googleMapsUrl; ?>" target="_blank" class="btn btn-sm btn-primary me-2">
										<i class="fab fa-google me-1"></i>Google Maps
									</a>
									<a href="<?php echo $wazeUrl; ?>" target="_blank" class="btn btn-sm btn-danger">
										<i class="fas fa-map-marked-alt me-1"></i>Waze
									</a>
								</div>
							</div>
							<div id="detailMap" style="height: 400px; border-radius: 8px; overflow: hidden;"></div>
						</div>
						
						<!-- Actions -->
						<div class="border-top pt-4">
							<h6 class="text-muted text-uppercase small mb-3">Actions</h6>
							
							<!-- Update Status -->
							<form id="detailsStatusForm" method="post" class="mb-3">
								<?php echo csrf_field(); ?>
								<input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="rejection_notes" id="detailsRejectionNotes">
								<div class="row align-items-end">
									<div class="col-md-4">
										<label class="form-label"><strong>Update Status:</strong></label>
										<select name="status" id="detailsStatusSelect" class="form-select shadow-sm border-0 bg-light">
											<?php 
                                            $status_options = [
                                                'submitted' => 'Pending',
                                                'in_review' => 'Under Review',
                                                'resolved'  => 'Resolved',
                                                'closed'    => 'Rejected'
                                            ];
                                            foreach ($status_options as $val => $label): ?>
												<option value="<?php echo $val; ?>" <?php echo $incident['status']===$val?'selected':''; ?>><?php echo $label; ?></option>
											<?php endforeach; ?>
										</select>
									</div>
									<div class="col-md-2">
										<button type="button" class="btn btn-primary w-100" onclick="handleDetailsStatusUpdate()">
											<i class="fas fa-save me-1"></i>Update
										</button>
									</div>
								</div>
							</form>
						</div>
					</div>
				</div>
			</div>
			
			<div class="col-md-4">
				<!-- Quick Actions -->
				<div class="card mb-3">
					<div class="card-header">
						<h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Quick Actions</h6>
					</div>
					<div class="card-body">
						<div class="d-grid gap-2">
							<a href="incidents.php" class="btn btn-outline-secondary">
								<i class="fas fa-list me-2"></i>Back to Incidents List
							</a>

							<?php
							$googleMapsUrl = 'https://www.google.com/maps/dir/?api=1&destination=' . $incident['latitude'] . ',' . $incident['longitude'];
							$wazeUrl = 'https://waze.com/ul?ll=' . $incident['latitude'] . ',' . $incident['longitude'] . '&navigate=yes';
							?>
							<a href="<?php echo $googleMapsUrl; ?>" target="_blank" class="btn btn-primary">
								<i class="fab fa-google me-2"></i>Open in Google Maps
							</a>
							<a href="<?php echo $wazeUrl; ?>" target="_blank" class="btn btn-danger">
								<i class="fas fa-map-marked-alt me-2"></i>Open in Waze
							</a>
						</div>
					</div>
				</div>
				
				<!-- Conversation/Response Section -->
				<div class="card">
					<div class="card-header">
						<h6 class="mb-0"><i class="fas fa-comments me-2"></i>Conversation</h6>
					</div>
					<div class="card-body" style="max-height: 500px; overflow-y: auto;">
						<?php if (!empty($messages)): ?>
							<?php foreach ($messages as $msg): ?>
								<?php if ($msg['role'] === 'admin'): ?>
									<!-- Admin Message (Right) -->
									<div class="d-flex justify-content-end mb-3">
										<div style="max-width: 85%;">
											<div class="d-flex align-items-center justify-content-end mb-1">
												<span class="text-muted small me-2"><?php echo date('M j, Y g:i A', strtotime($msg['created_at'])); ?></span>
												<strong class="text-primary small"><?php echo htmlspecialchars($msg['full_name']); ?></strong>
											</div>
											<div class="p-3 bg-primary text-white rounded-3" style="border-top-right-radius: 0 !important; font-size: 0.875rem;">
												<?php echo nl2br(htmlspecialchars($msg['message'])); ?>
											</div>
										</div>
									</div>
								<?php else: ?>
									<!-- Resident Message (Left) -->
									<div class="d-flex mb-3">
										<div style="max-width: 85%;">
											<div class="d-flex align-items-center mb-1">
												<strong class="text-muted small"><?php echo htmlspecialchars($msg['full_name']); ?></strong>
												<span class="text-muted small ms-2"><?php echo date('M j, Y g:i A', strtotime($msg['created_at'])); ?></span>
											</div>
											<div class="p-3 bg-light rounded-3" style="border-top-left-radius: 0 !important; font-size: 0.875rem;">
												<?php echo nl2br(htmlspecialchars($msg['message'])); ?>
											</div>
										</div>
									</div>
								<?php endif; ?>
							<?php endforeach; ?>
						<?php else: ?>
							<div class="text-center text-muted small py-3">
								<i class="fas fa-comment-dots me-1"></i>No messages yet
							</div>
						<?php endif; ?>
					</div>
						
					<!-- Respond Button -->
					<div class="mt-3 pt-3 border-top">
						<?php if ($incident['status'] === 'closed'): ?>
							<div class="alert alert-secondary text-center small py-2 mb-0">
								<i class="fas fa-lock me-1"></i> This conversation is closed.
							</div>
						<?php else: ?>
							<?php 
							$hasAdminMessage = false;
							if (!empty($messages)) {
								foreach ($messages as $msg) {
									if ($msg['role'] === 'admin') {
										$hasAdminMessage = true;
										break;
									}
								}
							}
							?>
							<button class="btn btn-success w-100 mb-2" data-bs-toggle="modal" data-bs-target="#respondModal">
								<i class="fas fa-reply me-2"></i><?php echo $hasAdminMessage ? 'Reply' : 'Respond to Resident'; ?>
							</button>

							<form method="post" class="d-inline admin-confirm-form" data-action-name="Close Conversation">
								<?php echo csrf_field(); ?>
								<input type="hidden" name="action" value="update_status">
								<input type="hidden" name="status" value="closed">
								<button type="button" class="btn btn-outline-danger btn-sm w-100 btn-confirm-submit" title="Close Conversation">
									<i class="fas fa-times-circle me-2"></i>Close Conversation
								</button>
							</form>
						<?php endif; ?>
					</div>
					</div>
				</div>
			</div>
		</div>
		
		<!-- Respond Modal -->
		<div class="modal fade" id="respondModal" tabindex="-1">
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title"><i class="fas fa-reply me-2"></i>Reply to Incident</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<form method="post">
						<div class="modal-body">
							<?php echo csrf_field(); ?>
							<input type="hidden" name="action" value="respond">
							<div class="mb-3">
								<label class="form-label">Your Response</label>
								<textarea name="admin_response" class="form-control" rows="4" placeholder="Enter your response to the resident..." required></textarea>
							</div>
						</div>
						<div class="modal-footer">
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
							<button type="submit" class="btn btn-primary">
								<i class="fas fa-paper-plane me-2"></i>Send Response
							</button>
						</div>
					</form>
				</div>
			</div>
		</div>
		
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			// Barangay Panungyanan boundaries only (restricted area)
			var barangayBounds = L.latLngBounds(
				[14.2242, 120.9095], // Southwest corner
				[14.2471, 120.9305]  // Northeast corner
			);
			
			// Initialize map
			const detailMap = L.map('detailMap', {
				maxBounds: barangayBounds,
				maxBoundsViscosity: 1.0,
				minZoom: 15,
				maxZoom: 19
			}).setView([<?php echo $incident['latitude']; ?>, <?php echo $incident['longitude']; ?>], 16);
			
			L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(detailMap);
			
			// Add marker
			const marker = L.marker([<?php echo $incident['latitude']; ?>, <?php echo $incident['longitude']; ?>]).addTo(detailMap);
			
			// Add popup
			marker.bindPopup(`
				<div>
					<h6>Incident #<?php echo (int)$incident['id']; ?></h6>
					<p><strong>Description:</strong><br><?php echo htmlspecialchars($incident['description'], ENT_QUOTES); ?></p>
					<p><strong>Location:</strong><br><?php echo htmlspecialchars($incident['latitude']); ?>, <?php echo htmlspecialchars($incident['longitude']); ?></p>
				</div>
			`).openPopup();
			
			// Auto-scroll conversation to bottom
			const conversationBody = document.querySelector('.card-body[style*="overflow-y"]');
			if (conversationBody) {
				conversationBody.scrollTop = conversationBody.scrollHeight;
			}
		});

        function handleDetailsStatusUpdate() {
            const statusSelect = document.getElementById('detailsStatusSelect');
            const selectedStatus = statusSelect.value;
            const selectedLabel = statusSelect.options[statusSelect.selectedIndex].text;
            
            let title = "Update Status?";
            let text = `Are you sure you want to change the status to "${selectedLabel}"?`;
            let icon = "question";
            let confirmBtnText = "Yes, update it";
            let confirmBtnColor = "#3085d6";

            if (selectedStatus === 'closed') {
                title = "Reject Incident?";
                text = "Please provide a reason for rejecting this incident report:";
                icon = "warning";
                confirmBtnColor = "#d33";
                confirmBtnText = "Reject Incident";
                
                Swal.fire({
                    title: title,
                    text: text,
                    icon: icon,
                    input: 'textarea',
                    inputPlaceholder: 'Type the reason here...',
                    showCancelButton: true,
                    confirmButtonColor: confirmBtnColor,
                    cancelButtonColor: '#aaa',
                    confirmButtonText: confirmBtnText,
                    inputValidator: (value) => {
                        if (!value) {
                            return 'You need to provide a reason for rejection!'
                        }
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        document.getElementById('detailsRejectionNotes').value = result.value;
                        document.getElementById('detailsStatusForm').submit();
                    }
                });
                return;
            }

            Swal.fire({
                title: title,
                text: text,
                icon: icon,
                showCancelButton: true,
                confirmButtonColor: confirmBtnColor,
                cancelButtonColor: '#aaa',
                confirmButtonText: confirmBtnText
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('detailsRejectionNotes').value = '';
                    document.getElementById('detailsStatusForm').submit();
                }
            });
        }
		</script>
	<?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>


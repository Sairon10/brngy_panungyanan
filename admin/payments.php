<?php
require_once __DIR__ . '/../config.php';
if (!is_admin()) {
	redirect('../index.php');
}

$pdo = get_db_connection();

$page_title = 'Payments';
$breadcrumb = [['title' => 'Payments']];

$message = '';
$message_type = '';

// ── Handle POST (Confirm / Reject / Refund payment) ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_validate()) {
	$pay_id     = (int) ($_POST['pay_id']   ?? 0);
	$pay_type   = trim($_POST['pay_type']   ?? ''); // 'clearance' or 'document'
	$pay_action = trim($_POST['pay_action'] ?? ''); // 'confirmed', 'rejected', or 'refunded'

	if ($pay_id > 0 && in_array($pay_type, ['clearance', 'document']) && in_array($pay_action, ['confirmed', 'rejected', 'refunded'])) {
		if ($pay_action === 'refunded') {
			$refund_number = trim($_POST['refund_number'] ?? '');
			$refund_notes  = trim($_POST['refund_notes']  ?? '');
			
			// Handle file upload
			$refund_receipt_path = null;
			if (isset($_FILES['refund_receipt']) && $_FILES['refund_receipt']['error'] === UPLOAD_ERR_OK) {
				$file = $_FILES['refund_receipt'];
				$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
				$maxSize = 5 * 1024 * 1024; // 5MB
				
				if (!in_array($file['type'], $allowedTypes)) {
					$message = 'Error: Only JPG, JPEG, PNG, and WEBP images are allowed for refund receipts.';
					$message_type = 'danger';
				} elseif ($file['size'] > $maxSize) {
					$message = 'Error: Refund receipt image size must not exceed 5MB.';
					$message_type = 'danger';
				} else {
					$uploadDir = __DIR__ . '/../uploads/receipts/';
					if (!is_dir($uploadDir)) {
						mkdir($uploadDir, 0755, true);
					}
					
					$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
					$filename = 'refund_receipt_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
					$uploadPath = $uploadDir . $filename;
					
					if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
						$refund_receipt_path = 'uploads/receipts/' . $filename;
					} else {
						$message = 'Error: Failed to upload refund receipt. Please try again.';
						$message_type = 'danger';
					}
				}
			} else {
				$message = 'Error: Refund receipt photo is required.';
				$message_type = 'danger';
			}

			if ($message === '') {
				$table = $pay_type === 'clearance' ? 'barangay_clearances' : 'document_requests';
				$pdo->prepare("UPDATE $table SET payment_status = 'refunded', admin_refund_number = ?, admin_refund_notes = ?, refund_receipt = ? WHERE id = ?")
					->execute([$refund_number, $refund_notes, $refund_receipt_path, $pay_id]);
				
				$message = 'Payment has been marked as <strong>refunded</strong> successfully.';
				$message_type = 'success';
			}
		} else {
			if ($pay_type === 'clearance') {
				$pdo->prepare('UPDATE barangay_clearances SET payment_status = ? WHERE id = ?')
					->execute([$pay_action, $pay_id]);
			} else {
				$pdo->prepare('UPDATE document_requests SET payment_status = ? WHERE id = ?')
					->execute([$pay_action, $pay_id]);
			}
			$message = $pay_action === 'confirmed'
				? 'Payment has been <strong>confirmed</strong> successfully.'
				: 'Payment has been <strong>rejected</strong>.';
			$message_type = $pay_action === 'confirmed' ? 'success' : 'danger';
		}
	}
}

// ── Fetch all payments (rows with a receipt uploaded) ─────────────────────────
$payments = [];

// 1. Barangay Clearances with a receipt
$stmt = $pdo->query("
	SELECT bc.id, 'clearance' AS pay_type, u.full_name, u.email,
	       bc.purpose AS doc_detail, 'Barangay Clearance' AS doc_type,
	       bc.payment_reference_no AS reference_no,
	       bc.payment_receipt, bc.payment_status, bc.created_at,
	       COALESCE(bc.payment_amount_paid, dt.price) AS amount_paid,
           dt.price AS expected_amount, bc.refund_number, bc.refund_notes, bc.refund_receipt, bc.notes,
           bc.admin_refund_number, bc.admin_refund_notes
	FROM barangay_clearances bc
	JOIN users u ON u.id = bc.user_id
	LEFT JOIN document_types dt ON dt.name = 'Barangay Clearance'
	WHERE bc.payment_receipt IS NOT NULL AND bc.payment_receipt != ''
	ORDER BY bc.created_at DESC
");
if ($stmt) {
	foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
		$payments[] = $row;
	}
}

// 2. Document Requests with a receipt
$stmt = $pdo->query("
	SELECT dr.id, 'document' AS pay_type, u.full_name, u.email,
	       dr.purpose AS doc_detail, dr.doc_type,
	       dr.payment_reference_no AS reference_no,
	       dr.payment_receipt, dr.payment_status, dr.created_at,
	       COALESCE(dr.payment_amount_paid, dt.price) AS amount_paid,
           dt.price AS expected_amount, dr.refund_number, dr.refund_notes, dr.refund_receipt, dr.notes,
           dr.admin_refund_number, dr.admin_refund_notes
	FROM document_requests dr
	JOIN users u ON u.id = dr.user_id
	LEFT JOIN document_types dt ON dt.name = dr.doc_type
	WHERE dr.payment_receipt IS NOT NULL AND dr.payment_receipt != ''
	ORDER BY dr.created_at DESC
");
if ($stmt) {
	foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
		$payments[] = $row;
	}
}

// Sort by date DESC (combined)
usort($payments, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));

// Count pending
$pending_count = count(array_filter($payments, fn($p) => ($p['payment_status'] ?? 'pending') === 'pending'));

// Apply filter
$filter_val = trim($_GET['filter'] ?? '');
$payments_display = $filter_val !== ''
	? array_values(array_filter($payments, fn($p) => ($p['payment_status'] ?? 'pending') === $filter_val))
	: $payments;

// Pagination
$page       = max(1, (int) ($_GET['page'] ?? 1));
$limit      = 12;
$total      = count($payments_display);
$total_pages = max(1, (int) ceil($total / $limit));
if ($page > $total_pages) $page = $total_pages;
$offset     = ($page - 1) * $limit;
$display_rows = array_slice($payments_display, $offset, $limit);

require_once __DIR__ . '/header.php';
?>

<style>
	.payment-card {
		background: white;
		border-radius: 16px;
		overflow: hidden;
		box-shadow: 0 4px 15px rgba(0,0,0,.07);
	}
	.pay-badge {
		display: inline-flex;
		align-items: center;
		gap: .35rem;
		font-size: .78rem;
		font-weight: 600;
		padding: .3rem .75rem;
		border-radius: 50px;
	}
	.pay-badge.pending   { background:#fef3c7; color:#92400e; }
	.pay-badge.confirmed { background:#d1fae5; color:#065f46; }
	.pay-badge.rejected  { background:#fee2e2; color:#991b1b; }
	.pay-badge.refunded  { background:#f3e8ff; color:#6b21a8; }
	.pay-badge.refund_pending { background:#e0e7ff; color:#3730a3; }
	.stat-pill {
		display: inline-flex;
		align-items: center;
		gap:.45rem;
		padding:.45rem 1.1rem;
		border-radius:50px;
		font-size:.82rem;
		font-weight:600;
	}
	.filter-tab {
		border: none;
		background: transparent;
		padding: .4rem 1rem;
		border-radius: 50px;
		font-size:.85rem;
		font-weight:600;
		color:#6c757d;
		cursor:pointer;
		text-decoration: none;
		transition: background .15s, color .15s;
		display: inline-block;
	}
	.filter-tab.active, .filter-tab:hover { background: #0d9488; color: #fff; }
	.ref-code {
		font-family: 'Courier New', monospace;
		font-size: .8rem;
		font-weight: 600;
		color: #0369a1;
		background: #e0f2fe;
		padding: .2rem .55rem;
		border-radius: 6px;
		letter-spacing: .03em;
	}
	.btn-view-detail {
		width: 34px;
		height: 34px;
		border-radius: 50%;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		border: none;
		background: #e0f2fe;
		color: #0369a1;
		transition: background .15s, color .15s;
	}
	.btn-view-detail:hover { background: #0369a1; color: #fff; }

	/* Detail modal receipt */
	#detailReceiptImg {
		max-height: 260px;
		width: 100%;
		object-fit: contain;
		border-radius: 10px;
		border: 1px solid #e9ecef;
		cursor: zoom-in;
	}
</style>

<div class="payment-card mb-4">
	<!-- Header -->
	<div class="p-4 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-3">
		<div>
			<h5 class="mb-0 fw-bold"><i class="fas fa-credit-card me-2 text-teal-600"></i>Online Payments</h5>
			<p class="text-muted mb-0 small mt-1">Review and confirm payment receipts submitted by residents</p>
		</div>
		<div class="d-flex align-items-center gap-2">
			<?php if ($pending_count > 0): ?>
				<span class="stat-pill" style="background:#fef3c7;color:#92400e;">
					<i class="fas fa-clock"></i><?= $pending_count ?> Pending
				</span>
			<?php endif; ?>
			<span class="stat-pill" style="background:#e0f2fe;color:#0369a1;">
				<i class="fas fa-receipt"></i><?= count($payments) ?> Total
			</span>
		</div>
	</div>

	<?php if ($message): ?>
		<div class="alert alert-<?= $message_type ?> alert-dismissible fade show m-3 mb-0 rounded-3" role="alert">
			<i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
			<?= $message ?>
			<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
		</div>
	<?php endif; ?>

	<!-- Filter tabs -->
	<div class="px-4 py-3 border-bottom d-flex align-items-center gap-1 flex-wrap">
		<a href="payments.php" class="filter-tab <?= $filter_val === '' ? 'active' : '' ?>">All</a>
		<a href="payments.php?filter=pending"   class="filter-tab <?= $filter_val === 'pending'   ? 'active' : '' ?>">Pending</a>
		<a href="payments.php?filter=confirmed" class="filter-tab <?= $filter_val === 'confirmed' ? 'active' : '' ?>">Confirmed</a>
		<a href="payments.php?filter=rejected"  class="filter-tab <?= $filter_val === 'rejected'  ? 'active' : '' ?>">Rejected</a>
		<a href="payments.php?filter=refunded"  class="filter-tab <?= $filter_val === 'refunded'  ? 'active' : '' ?>">Refunded</a>
	</div>

	<!-- Table -->
	<div class="p-3">
		<div class="table-responsive">
			<table class="table table-hover align-middle" id="paymentsTable">
				<thead class="bg-light text-uppercase" style="font-size:.78rem;">
					<tr>
						<th class="py-3 ps-3" style="width:44px;">#</th>
						<th class="py-3">Name</th>
						<th class="py-3">Amount</th>
						<th class="py-3">Reference No.</th>
						<th class="py-3">Status</th>
						<th class="py-3">Date</th>
						<th class="py-3 pe-3 text-center">Action</th>
					</tr>
				</thead>
				<tbody>
					<?php if (empty($display_rows)): ?>
						<tr>
							<td colspan="7" class="text-center py-5">
								<div class="py-4">
									<i class="fas fa-receipt fa-3x text-muted opacity-25 mb-3 d-block"></i>
									<p class="text-muted mb-0">No payment receipts found.</p>
								</div>
							</td>
						</tr>
					<?php else: ?>
						<?php $row_n = $offset + 1; ?>
						<?php foreach ($display_rows as $pay): ?>
							<?php
							$pay_status  = $pay['payment_status'] ?? 'pending';
							$receipt_url = '../' . $pay['payment_receipt'];
							$amount      = (float)($pay['amount_paid'] ?? 0);
							$ref_no      = htmlspecialchars($pay['reference_no'] ?? '—');
							// JSON-safe data for the detail modal
							$j = [
								'id'              => (int)$pay['id'],
								'pay_type'        => $pay['pay_type'],
								'full_name'       => $pay['full_name'],
								'email'           => $pay['email'] ?? '',
								'doc_type'        => $pay['doc_type'],
								'doc_detail'      => $pay['doc_detail'] ?? '',
								'reference_no'    => $ref_no,
								'amount'          => $amount,
								'expected_amount' => (float)($pay['expected_amount'] ?? 0),
								'date'            => date('M d, Y g:i A', strtotime($pay['created_at'])),
								'pay_status'      => $pay_status,
								'receipt_url'     => $receipt_url,
								'refund_number'        => $pay['refund_number'] ?? '',
								'refund_notes'         => $pay['refund_notes'] ?? '',
								'refund_receipt'       => $pay['refund_receipt'] ?? '',
								'notes'                => $pay['notes'] ?? '',
								'admin_refund_number'  => $pay['admin_refund_number'] ?? '',
								'admin_refund_notes'   => $pay['admin_refund_notes'] ?? '',
							];
							$json_attr = htmlspecialchars(json_encode($j), ENT_QUOTES);
							?>
							<tr>
								<td class="ps-3 text-muted fw-semibold"><?= $row_n++ ?></td>

								<!-- Name -->
								<td>
									<div class="fw-bold text-dark"><?= htmlspecialchars($pay['full_name']) ?></div>
								</td>

								<!-- Amount -->
								<td>
									<span class="fw-bold <?= $amount > 0 ? 'text-teal-600' : 'text-muted' ?>">
										<?= $amount > 0 ? '₱ ' . number_format($amount, 2) : '—' ?>
									</span>
								</td>

								<!-- Reference No. -->
								<td>
									<span class="ref-code"><?= $ref_no ?></span>
								</td>

								<!-- Status -->
								<td>
									<span class="pay-badge <?= htmlspecialchars($pay_status) ?>">
										<?php if ($pay_status === 'confirmed'): ?>
											<i class="fas fa-check-circle"></i> Confirmed
										<?php elseif ($pay_status === 'rejected'): ?>
											<i class="fas fa-times-circle"></i> Rejected
										<?php elseif ($pay_status === 'refunded'): ?>
											<i class="fas fa-undo-alt"></i> Refunded
										<?php elseif ($pay_status === 'refund_pending'): ?>
											<i class="fas fa-hourglass-half"></i> Refund Pending
										<?php else: ?>
											<i class="fas fa-clock"></i> Pending
										<?php endif; ?>
									</span>
								</td>

								<!-- Date -->
								<td class="text-muted small">
									<i class="far fa-calendar-alt me-1 opacity-50"></i>
									<?= date('M d, Y', strtotime($pay['created_at'])) ?>
								</td>

								<!-- Action -->
								<td class="pe-3 text-center">
									<div class="d-flex align-items-center justify-content-center gap-1">
										<button type="button"
											class="btn-view-detail"
											title="View Details"
											onclick="openDetailModal(<?= $json_attr ?>)">
											<i class="fas fa-eye" style="font-size:.85rem;"></i>
										</button>
										<?php if ($pay_status === 'refund_pending'): ?>
											<button type="button"
												class="btn btn-sm btn-outline-purple rounded-circle d-flex align-items-center justify-content-center"
												style="width: 34px; height: 34px; color: #6b21a8; border-color: #d8b4fe !important; background: #faf5ff; border: 1px solid #d8b4fe;"
												title="Submit Refund"
												onclick="openRefundModal(<?= (int)$pay['id'] ?>, '<?= htmlspecialchars($pay['pay_type']) ?>')">
												<i class="fas fa-undo-alt" style="font-size:.8rem;"></i>
											</button>
										<?php endif; ?>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<!-- Pagination -->
		<?php if ($total_pages > 1): ?>
			<nav class="d-flex justify-content-center mt-3">
				<ul class="pagination pagination-sm mb-0 gap-1">
					<li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
						<a class="page-link rounded-3" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
							<i class="fas fa-chevron-left" style="font-size:.7rem;"></i>
						</a>
					</li>
					<?php for ($p = 1; $p <= $total_pages; $p++): ?>
						<li class="page-item <?= $p === $page ? 'active' : '' ?>">
							<a class="page-link rounded-3" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
						</li>
					<?php endfor; ?>
					<li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
						<a class="page-link rounded-3" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
							<i class="fas fa-chevron-right" style="font-size:.7rem;"></i>
						</a>
					</li>
				</ul>
			</nav>
		<?php endif; ?>
	</div>
</div>

<!-- ─── Payment Detail Modal ──────────────────────────────────────────────── -->
<div class="modal fade" id="payDetailModal" tabindex="-1">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content rounded-4 border-0 shadow-lg overflow-hidden">
			<div class="modal-header bg-white border-bottom py-3 px-4">
				<div>
					<h5 class="modal-title fw-bold text-dark mb-0" id="detailModalTitle">Payment Verification</h5>
					<div class="small text-muted mt-1" id="detailModalSub"></div>
				</div>
				<div class="ms-auto me-4" id="detailHeaderStatusBadge"></div>
				<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
			</div>
			
			<div class="modal-body p-0 bg-white">
				<div class="row g-0">
					<!-- Left Column -->
					<div class="col-lg-7 p-4 border-end">
						
						<!-- Submitted Payment Receipt Section -->
						<div class="d-flex align-items-center mb-3">
							<div class="bg-light text-teal-600 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 42px; height: 42px;">
								<i class="fas fa-file-invoice-dollar fs-5"></i>
							</div>
							<div>
								<h6 class="fw-bold mb-0 text-dark">Submitted Payment Receipt</h6>
								<small class="text-muted" id="detailDate"></small>
							</div>
						</div>

						<div class="card border border-light-subtle shadow-sm mb-4 rounded-3">
							<div class="card-body p-3">
								<div class="d-flex justify-content-between mb-2">
									<span class="text-secondary small">Reference no.</span>
									<span class="fw-bold text-dark small" id="detailRefNo" style="font-family: monospace;"></span>
								</div>
								<div class="d-flex justify-content-between mb-3">
									<span class="text-secondary small">Paid Amount</span>
									<span class="fw-bold text-dark small" id="detailAmount"></span>
								</div>
								<button type="button" class="btn btn-light w-100 border text-dark fw-semibold rounded-3 py-2" onclick="zoomReceipt(document.getElementById('detailReceiptImg').src)">
									<i class="far fa-eye me-2"></i>View receipt
								</button>
								<img id="detailReceiptImg" src="" class="d-none">
							</div>
						</div>

						<!-- Admin Refund Details (visible only when refunded) -->
						<div class="card border-0 shadow-sm mb-4 rounded-3 d-none" id="detailRefundCard" style="background: #f0fdfa; border: 1px solid #99f6e4 !important;">
							<div class="card-body p-3">
								<div class="fw-bold mb-2" style="font-size: 0.65rem; letter-spacing: 0.5px; color: #0d9488; text-transform: uppercase;">Admin Refund Details</div>
								
								<div class="d-flex justify-content-between mb-2 pb-2 border-bottom border-light-subtle" style="border-bottom-style: dashed !important;">
									<span class="text-secondary small">Transaction / Ref No.</span>
									<span class="fw-bold text-dark small" id="detailRefundNumber" style="font-family: monospace;"></span>
								</div>

								<div class="d-flex justify-content-between mb-2 pb-2 border-bottom border-light-subtle" style="border-bottom-style: dashed !important;">
									<span class="text-secondary small">Admin Notes</span>
									<span class="fw-bold text-dark small text-end" id="detailRefundNotes" style="font-style: italic; max-width: 60%; word-break: break-word;"></span>
								</div>

								<div>
									<div class="text-secondary small mb-1">Refund Receipt</div>
									<div class="mt-1" id="detailRefundReceiptContainer"></div>
								</div>
							</div>
						</div>

						<!-- Resident-requested refund details (visible when refund requested) -->
						<div class="card border border-light-subtle shadow-sm mb-4 rounded-3 d-none" id="detailResidentRefundCard">
							<div class="card-body p-3">
								<div class="text-muted fw-bold mb-2" style="font-size: 0.65rem; letter-spacing: 0.5px;">RESIDENT REFUND REQUEST DETAILS</div>
								
								<div class="d-flex justify-content-between mb-2 pb-2 border-bottom border-light-subtle">
									<span class="text-secondary small">GCash / Maya / Account No.</span>
									<span class="fw-bold text-dark small" id="detailResidentRefundNumber" style="font-family: monospace;"></span>
								</div>
								
								<div class="d-flex justify-content-between">
									<span class="text-secondary small">Reason for Refund</span>
									<span class="fw-bold text-dark small text-end" id="detailResidentRefundNotes" style="max-width: 60%; word-break: break-word; font-style: italic;"></span>
								</div>
							</div>
						</div>

						<!-- Action Needed Section -->
						<div class="d-flex align-items-start mb-2">
							<div class="text-danger rounded-circle d-flex align-items-center justify-content-center me-3 mt-1" style="width: 42px; height: 42px; background: #fef2f2;">
								<i class="fas fa-exclamation-circle fs-5"></i>
							</div>
							<div>
								<h6 class="fw-bold mb-0 text-dark">Action Needed</h6>
								<small class="text-secondary" style="font-size: 0.8rem;">Please review the details and proceed with the necessary action.</small>
							</div>
						</div>
						
						<div class="d-flex gap-2 mt-3 ms-2" id="detailActionFooter" style="padding-left: 3.5rem;">
							<!-- Populated by JS -->
						</div>

					</div>

					<!-- Right Column (Sidebar details) -->
					<div class="col-lg-5 p-4 bg-light">
						
						<div class="card border-0 shadow-sm mb-3 rounded-3">
							<div class="card-body p-3">
								<div class="text-muted fw-bold mb-2" style="font-size: 0.65rem; letter-spacing: 0.5px;">RESIDENT DETAILS</div>
								<div class="fw-bold text-teal-700 small" id="detailName"></div>
								<div class="text-secondary small" id="detailEmail"></div>
							</div>
						</div>

						<div class="card border-0 shadow-sm mb-3 rounded-3">
							<div class="card-body p-3">
								<div class="text-muted fw-bold mb-2" style="font-size: 0.65rem; letter-spacing: 0.5px;">DOCUMENT DETAILS</div>
								
								<div class="text-secondary" style="font-size: 0.75rem;">Document</div>
								<div class="fw-bold text-dark small mb-2" id="detailDocType"></div>

								<div class="text-secondary" style="font-size: 0.75rem;">Date</div>
								<div class="fw-bold text-dark small mb-2" id="detailDocDate"></div>

								<div class="text-secondary" style="font-size: 0.75rem;">Purpose</div>
								<div class="fw-bold text-dark small" id="detailDocDetail"></div>
							</div>
						</div>

						<div class="card border-0 shadow-sm rounded-3">
							<div class="card-body p-3">
								<div class="text-muted fw-bold mb-2" style="font-size: 0.65rem; letter-spacing: 0.5px;">PAYMENT DETAILS</div>
								
								<div class="d-flex justify-content-between mb-2">
									<span class="text-secondary" style="font-size: 0.75rem;">Expected Amount</span>
									<span class="fw-bold text-teal-600 small" id="detailExpectedAmount"></span>
								</div>
								<div class="d-flex justify-content-between mb-3 pb-3 border-bottom border-light-subtle">
									<span class="text-secondary" style="font-size: 0.75rem;">Paid Amount</span>
									<span class="fw-bold text-teal-600 small" id="detailSidebarAmount"></span>
								</div>
								<div class="text-muted" style="font-size: 0.65rem; font-style: italic; line-height: 1.2;">
									Resident is notified automatically when you confirm or reject.
								</div>
							</div>
						</div>


					</div>
				</div>
			</div>
			
			<div class="modal-footer border-top py-2 px-4 bg-light justify-content-between align-items-center">
				<small class="text-muted" style="font-size: 0.75rem; max-width: 70%;">Review the submitted receipt and confirm if the payment is valid. You can also reject the payment if it seems fraudulent.</small>
				<button type="button" class="btn btn-white border fw-semibold px-4 py-1 rounded-pill" data-bs-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>

<!-- Hidden confirm form (reused by modal) -->
<form method="post" id="payActionForm">
	<?= csrf_field() ?>
	<input type="hidden" name="pay_id"     id="payActionId">
	<input type="hidden" name="pay_type"   id="payActionType">
	<input type="hidden" name="pay_action" id="payActionValue">
</form>

<!-- Reject reason modal -->
<div class="modal fade" id="rejectReasonModal" tabindex="-1">
	<div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
		<div class="modal-content rounded-4 border-0 shadow-lg">
			<div class="modal-header border-0 pb-0">
				<h5 class="modal-title fw-bold text-danger"><i class="fas fa-times-circle me-2"></i>Reject Payment</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
			</div>
			<form method="post" id="rejectReasonForm">
				<?= csrf_field() ?>
				<div class="modal-body pt-2">
					<input type="hidden" name="pay_id"     id="rr_pay_id">
					<input type="hidden" name="pay_type"   id="rr_pay_type">
					<input type="hidden" name="pay_action" value="rejected">
					<p class="text-secondary small mb-3">State your reason for rejecting this payment (optional).</p>
					<textarea name="reject_notes" class="form-control rounded-3" rows="3"
						placeholder="e.g. Unclear image, wrong amount, etc."></textarea>
				</div>
				<div class="modal-footer border-0 pt-0 gap-2">
					<button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-danger rounded-pill px-4">
						<i class="fas fa-times me-2"></i>Reject
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- Refund modal -->
<div class="modal fade" id="refundModal" tabindex="-1">
	<div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
		<div class="modal-content rounded-4 border-0 shadow-lg">
			<div class="modal-header border-0 pb-0">
				<h5 class="modal-title fw-bold text-teal-700"><i class="fas fa-undo-alt me-2"></i>Refund Payment</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
			</div>
			<form method="post" id="refundForm" enctype="multipart/form-data">
				<?= csrf_field() ?>
				<div class="modal-body pt-2">
					<input type="hidden" name="pay_id"     id="refund_pay_id">
					<input type="hidden" name="pay_type"   id="refund_pay_type">
					<input type="hidden" name="pay_action" value="refunded">
					<p class="text-secondary small mb-3">Provide the refund details below to mark this payment as refunded.</p>
					
					<div class="mb-3">
						<label for="refund_number" class="form-label small fw-bold text-dark mb-1">Refund Reference / Transaction No. <span class="text-danger">*</span></label>
						<input type="text" name="refund_number" id="refund_number_input" class="form-control rounded-3" placeholder="e.g. GCash Ref No." required>
					</div>

					<div class="mb-3">
						<label for="refund_receipt" class="form-label small fw-bold text-dark mb-1">Upload Refund Receipt <span class="text-danger">*</span></label>
						<input type="file" name="refund_receipt" id="refund_receipt_input" class="form-control rounded-3" accept="image/*" required>
						<div class="form-text text-muted" style="font-size: 0.72rem;">Supported formats: JPG, JPEG, PNG, WEBP. Max 5MB.</div>
					</div>

					<div class="mb-2">
						<label for="refund_notes" class="form-label small fw-bold text-dark mb-1">Refund Notes / Remarks <span class="text-danger">*</span></label>
						<textarea name="refund_notes" id="refund_notes_input" class="form-control rounded-3" rows="3"
							placeholder="e.g. Returned via GCash to resident request." required></textarea>
					</div>
				</div>
				<div class="modal-footer border-0 pt-0 gap-2">
					<button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-teal rounded-pill px-4" style="background:#0d9488; color:#fff; border:none;">
						<i class="fas fa-check me-2"></i>Refund
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<script>
let _detailModal = null;
let _rejectModal = null;
let _refundModal = null;
function getDetailModal() {
	if (!_detailModal) _detailModal = new bootstrap.Modal(document.getElementById('payDetailModal'));
	return _detailModal;
}
function getRejectModal() {
	if (!_rejectModal) _rejectModal = new bootstrap.Modal(document.getElementById('rejectReasonModal'));
	return _rejectModal;
}
function getRefundModal() {
	if (!_refundModal) _refundModal = new bootstrap.Modal(document.getElementById('refundModal'));
	return _refundModal;
}

function openDetailModal(data) {
	// Header & Main Info
	document.getElementById('detailModalSub').textContent   = data.doc_type + ' — ' + data.full_name;
	document.getElementById('detailDate').textContent       = data.date;
	document.getElementById('detailRefNo').textContent      = data.reference_no;

    // Amounts
    const amountStr = data.amount > 0 ? '₱' + parseFloat(data.amount).toLocaleString('en-PH',{minimumFractionDigits:2}) : 'Free';
    const expectedStr = data.expected_amount > 0 ? '₱' + parseFloat(data.expected_amount).toLocaleString('en-PH',{minimumFractionDigits:2}) : 'Free';
    
    document.getElementById('detailAmount').textContent         = amountStr;
	document.getElementById('detailExpectedAmount').textContent = expectedStr;
    document.getElementById('detailSidebarAmount').textContent  = amountStr;

	// Right Sidebar Details
	document.getElementById('detailName').textContent       = data.full_name;
	document.getElementById('detailEmail').textContent      = data.email || '—';
	document.getElementById('detailDocType').textContent    = data.doc_type;
    document.getElementById('detailDocDate').textContent    = data.date;
	document.getElementById('detailDocDetail').textContent  = data.doc_detail || '—';

	// Header Status badge
	const statusMap = {
		pending:   { cls:'pending',   icon:'fa-clock',        label:'Pending'   },
		confirmed: { cls:'confirmed', icon:'fa-check-circle', label:'Confirmed' },
		rejected:  { cls:'rejected',  icon:'fa-times-circle', label:'Rejected'  },
		refunded:  { cls:'refunded',  icon:'fa-undo-alt',     label:'Refunded'  },
		refund_pending: { cls:'refund_pending', icon:'fa-hourglass-half', label:'Refund Pending' },
	};
	const s = statusMap[data.pay_status] || statusMap.pending;
	document.getElementById('detailHeaderStatusBadge').innerHTML =
		`<span class="pay-badge ${s.cls} py-1 px-3" style="font-size: 0.75rem;"><i class="fas ${s.icon}"></i> ${s.label}</span>`;

	// Receipt image
	document.getElementById('detailReceiptImg').src = data.receipt_url;

	// ── Resident Refund Request Details card (what resident submitted) ────────
	const resRefundCard = document.getElementById('detailResidentRefundCard');
	if (data.pay_status === 'refund_pending' || data.pay_status === 'refunded') {
		const numEl   = document.getElementById('detailResidentRefundNumber');
		const notesEl = document.getElementById('detailResidentRefundNotes');
		// Resident's GCash/account number
		if (data.refund_number) {
			numEl.textContent    = data.refund_number;
			numEl.style.color    = '';
			numEl.style.fontStyle = '';
		} else {
			numEl.textContent    = 'Not provided';
			numEl.style.color    = '#d97706';
			numEl.style.fontStyle = 'italic';
			numEl.style.fontFamily = '';
		}
		// Resident's reason/notes (fallback to notes column for older records)
		notesEl.textContent = data.refund_notes || data.notes || '—';
		resRefundCard.classList.remove('d-none');
	} else {
		resRefundCard.classList.add('d-none');
	}

	// ── Admin Refund Details card (what admin submitted when processing) ───────
	const refundCard = document.getElementById('detailRefundCard');
	if (data.pay_status === 'refunded') {
		const adminNumEl   = document.getElementById('detailRefundNumber');
		const adminNotesEl = document.getElementById('detailRefundNotes');

		if (data.admin_refund_number) {
			adminNumEl.textContent    = data.admin_refund_number;
			adminNumEl.style.color    = '';
			adminNumEl.style.fontStyle = '';
		} else {
			adminNumEl.textContent    = '—';
			adminNumEl.style.color    = '';
			adminNumEl.style.fontStyle = '';
		}
		adminNotesEl.textContent = data.admin_refund_notes || 'No remarks provided.';

		const rcContainer = document.getElementById('detailRefundReceiptContainer');
		if (data.refund_receipt) {
			const rrUrl = '../' + data.refund_receipt;
			rcContainer.innerHTML = `
				<button type="button" class="btn btn-sm w-100 border fw-semibold rounded-3 py-2 mt-1"
					onclick="zoomReceipt('${rrUrl}')" style="font-size: 0.8rem; color: #0d9488; border-color: #99f6e4 !important; background: #f0fdfa;">
					<i class="far fa-eye me-2"></i>View Refund Receipt
				</button>
			`;
		} else {
			rcContainer.innerHTML = '<span class="text-muted small">No receipt uploaded.</span>';
		}

		refundCard.classList.remove('d-none');
	} else {
		refundCard.classList.add('d-none');
	}

	// Action footer buttons
	const footer = document.getElementById('detailActionFooter');
	if (data.pay_status === 'pending') {
		footer.innerHTML = `
			<button type="button" class="btn btn-white border text-danger fw-semibold rounded-3 px-3 py-2 shadow-sm"
				onclick="openRejectReason(${data.id}, '${data.pay_type}')" style="font-size: 0.85rem;">
				<i class="fas fa-times me-1"></i> Reject Payment
			</button>
			<button type="button" class="btn btn-white border text-success fw-semibold rounded-3 px-3 py-2 shadow-sm"
				onclick="confirmPayment(${data.id}, '${data.pay_type}')" style="font-size: 0.85rem;">
				<i class="fas fa-check me-1"></i> Confirm Payment
			</button>`;
	} else if (data.pay_status === 'confirmed') {
		footer.innerHTML = `
			<button type="button" class="btn btn-white border text-purple-700 fw-semibold rounded-3 px-3 py-2 shadow-sm"
				onclick="openRefundModal(${data.id}, '${data.pay_type}')" style="font-size: 0.85rem; color: #6b21a8; border-color: #d8b4fe !important;">
				<i class="fas fa-undo-alt me-1"></i> Refund Payment
			</button>`;
	} else if (data.pay_status === 'refund_pending') {
		footer.innerHTML = `
			<button type="button" class="btn btn-white border text-danger fw-semibold rounded-3 px-3 py-2 shadow-sm"
				onclick="openRejectReason(${data.id}, '${data.pay_type}')" style="font-size: 0.85rem;">
				<i class="fas fa-times me-1"></i> Reject Payment
			</button>
			<button type="button" class="btn btn-white border text-purple-700 fw-semibold rounded-3 px-3 py-2 shadow-sm"
				onclick="openRefundModal(${data.id}, '${data.pay_type}')" style="font-size: 0.85rem; color: #6b21a8; border-color: #d8b4fe !important; background: #faf5ff;">
				<i class="fas fa-undo-alt me-1"></i> Submit Refund
			</button>`;
	} else {
		footer.innerHTML = `<span class="text-secondary" style="font-size: 0.85rem; font-style: italic;"><i class="fas fa-info-circle me-1"></i>Action has already been taken.</span>`;
	}

	getDetailModal().show();
}

function confirmPayment(id, type) {
	Swal.fire({
		title: 'Confirm Payment',
		text: 'Are you sure you want to confirm this payment?',
		icon: 'question',
		showCancelButton: true,
		confirmButtonColor: '#0d9488',
		cancelButtonColor: '#6c757d',
		confirmButtonText: 'Yes, Confirm'
	}).then(r => {
		if (r.isConfirmed) {
			document.getElementById('payActionId').value    = id;
			document.getElementById('payActionType').value  = type;
			document.getElementById('payActionValue').value = 'confirmed';
			document.getElementById('payActionForm').submit();
		}
	});
}

function openRejectReason(id, type) {
	getDetailModal().hide();
	document.getElementById('rr_pay_id').value   = id;
	document.getElementById('rr_pay_type').value = type;
	setTimeout(() => getRejectModal().show(), 300);
}

function openRefundModal(id, type) {
	getDetailModal().hide();
	document.getElementById('refund_pay_id').value   = id;
	document.getElementById('refund_pay_type').value = type;
	document.getElementById('refund_number_input').value = '';
	if (document.getElementById('refund_receipt_input')) {
		document.getElementById('refund_receipt_input').value = '';
	}
	if (document.getElementById('refund_notes_input')) {
		document.getElementById('refund_notes_input').value = '';
	}
	setTimeout(() => getRefundModal().show(), 300);
}

function zoomReceipt(url) {
	Swal.fire({
		title: 'Payment Receipt',
		html: `<div class="text-center"><img src="${url}" alt="Receipt" class="img-fluid rounded-3 shadow" style="max-height:75vh;max-width:100%;object-fit:contain;"></div>`,
		showCloseButton: true,
		confirmButtonText: 'Close',
		confirmButtonColor: '#0d9488',
		customClass: { popup: 'rounded-4 shadow' }
	});
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>

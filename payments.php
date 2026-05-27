<?php
require_once __DIR__ . '/config.php';
if (!is_logged_in()) {
    redirect('login.php');
}

// Check if user is verified and active (for residents only)
if ($_SESSION['role'] === 'resident') {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare('SELECT verification_status, is_active FROM users u LEFT JOIN residents r ON r.user_id = u.id WHERE u.id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $user_data = $stmt->fetch();

    if ($user_data && $user_data['verification_status'] !== 'verified') {
        redirect('id_verification.php');
    }
}

$page_title = 'Payment History';
$pdo = get_db_connection();

$message = $_SESSION['info'] ?? '';
unset($_SESSION['info']);

// Handle Refund submission directly on this page
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        $message = 'Invalid session. Please reload and try again.';
    } else if (isset($_POST['action']) && $_POST['action'] === 'refund') {
        $req_id = (int) ($_POST['req_id'] ?? 0);
        $req_type = $_POST['req_type'] ?? '';

        if ($req_id && $req_type) {
            $refund_number = trim($_POST['refund_number'] ?? '');
            $refund_notes = trim($_POST['refund_notes'] ?? '');
            
            if ($req_type === 'clearance') {
                $pdo->prepare("UPDATE barangay_clearances SET payment_status = 'refund_pending', status = 'canceled', notes = ?, refund_number = ?, refund_notes = ? WHERE id = ? AND user_id = ? AND payment_status IN ('pending', 'confirmed')")
                    ->execute([$refund_notes, $refund_number, $refund_notes, $req_id, $_SESSION['user_id']]);
            } else if ($req_type === 'document') {
                $pdo->prepare("UPDATE document_requests SET payment_status = 'refund_pending', status = 'canceled', notes = ?, refund_number = ?, refund_notes = ? WHERE id = ? AND user_id = ? AND payment_status IN ('pending', 'confirmed')")
                    ->execute([$refund_notes, $refund_number, $refund_notes, $req_id, $_SESSION['user_id']]);
            }
            $_SESSION['info'] = 'Refund request submitted successfully.';
            header("Location: payments.php");
            exit;
        }
    }
}

// Fetch user's GCash paid clearances (matched admin SQL fields for modal support)
$clearances_stmt = $pdo->prepare("
	SELECT bc.*, u.full_name AS user_name, u.email AS user_email,
	       bc.purpose AS doc_detail, 'Barangay Clearance' AS doc_type,
	       bc.payment_reference_no AS reference_no,
	       bc.payment_receipt, bc.payment_status, bc.created_at,
	       COALESCE(bc.payment_amount_paid, dt.price) AS amount_paid,
           dt.price AS expected_amount, fm.full_name AS fm_name
	FROM barangay_clearances bc
	JOIN users u ON u.id = bc.user_id
	LEFT JOIN document_types dt ON dt.name = 'Barangay Clearance'
	LEFT JOIN family_members fm ON bc.family_member_id = fm.id
	WHERE bc.user_id = ? AND bc.payment_receipt IS NOT NULL AND bc.payment_receipt != ''
	ORDER BY bc.created_at DESC
");
$clearances_stmt->execute([$_SESSION['user_id']]);
$clearances = $clearances_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch user's GCash paid document requests (matched admin SQL fields for modal support)
$documents_stmt = $pdo->prepare("
	SELECT dr.*, u.full_name AS user_name, u.email AS user_email,
	       dr.purpose AS doc_detail, dr.doc_type,
	       dr.payment_reference_no AS reference_no,
	       dr.payment_receipt, dr.payment_status, dr.created_at,
	       COALESCE(dr.payment_amount_paid, dt.price) AS amount_paid,
           dt.price AS expected_amount, fm.full_name AS fm_name
	FROM document_requests dr
	JOIN users u ON u.id = dr.user_id
	LEFT JOIN document_types dt ON dt.name = dr.doc_type
	LEFT JOIN family_members fm ON dr.family_member_id = fm.id
	WHERE dr.user_id = ? AND dr.payment_receipt IS NOT NULL AND dr.payment_receipt != ''
	ORDER BY dr.created_at DESC
");
$documents_stmt->execute([$_SESSION['user_id']]);
$documents = $documents_stmt->fetchAll(PDO::FETCH_ASSOC);

// Combine and merge them
$all_payments = [];
foreach ($clearances as $c) {
    $all_payments[] = array_merge($c, [
        'pay_type' => 'clearance',
        'doc_name' => 'Barangay Clearance',
        'date' => $c['created_at'],
        'payment_status' => $c['payment_status'] ?: 'pending',
    ]);
}

foreach ($documents as $d) {
    $all_payments[] = array_merge($d, [
        'pay_type' => 'document',
        'doc_name' => $d['doc_type'],
        'date' => $d['created_at'],
        'payment_status' => $d['payment_status'] ?: 'pending',
    ]);
}

// Sort all payments by date descending
usort($all_payments, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// Calculate metrics
$pending_count = count(array_filter($all_payments, fn($p) => ($p['payment_status'] ?? 'pending') === 'pending'));
$total_count = count($all_payments);

// Get filter
$status_filter = $_GET['payment_status_filter'] ?? 'all';

// Filter list
$filtered_payments = [];
foreach ($all_payments as $p) {
    if ($status_filter === 'all') {
        $filtered_payments[] = $p;
    } else if ($status_filter === 'pending' && $p['payment_status'] === 'pending') {
        $filtered_payments[] = $p;
    } else if ($status_filter === 'confirmed' && $p['payment_status'] === 'confirmed') {
        $filtered_payments[] = $p;
    } else if ($status_filter === 'rejected' && $p['payment_status'] === 'rejected') {
        $filtered_payments[] = $p;
    } else if ($status_filter === 'refunded' && in_array($p['payment_status'], ['refunded', 'refund_pending'])) {
        $filtered_payments[] = $p;
    }
}

// Pagination setup to match admin
$page         = max(1, (int) ($_GET['page'] ?? 1));
$limit        = 10;
$total        = count($filtered_payments);
$total_pages  = max(1, (int) ceil($total / $limit));
if ($page > $total_pages) $page = $total_pages;
$offset       = ($page - 1) * $limit;
$display_rows = array_slice($filtered_payments, $offset, $limit);

require_once __DIR__ . '/partials/user_dashboard_header.php';
?>

<script src="public/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
window.onerror = function(message, source, lineno, colno, error) {
    alert("JS Error: " + message + "\nLine: " + lineno + "\nSource: " + source);
    return false;
};

var _refundModal = null;

function openDetailModal(index) {
	var dataEl = document.getElementById('payment-data-' + index);
	if (!dataEl) {
		console.error("No data element found for index:", index);
		return;
	}
	var data;
	try {
		data = JSON.parse(dataEl.value);
	} catch (e) {
		console.error("Failed to parse payment data for index:", index, e);
		return;
	}

	var modalEl = document.getElementById('payDetailModal');

	try {
		// Header & Main Info
		document.getElementById('detailModalTitle').textContent = 'Payment Details';
		document.getElementById('detailModalSub').textContent   = data.doc_type + ' — ' + data.full_name;
		document.getElementById('detailDate').textContent       = data.date;
		document.getElementById('detailRefNo').textContent      = data.reference_no;

		// Amounts formatting (safe parsing to prevent NaN crashes)
		var amountVal = parseFloat(data.amount);
		var amountStr = !isNaN(amountVal) && amountVal > 0 ? '₱' + amountVal.toLocaleString('en-PH',{minimumFractionDigits:2}) : 'Free';
		
		var expectedVal = parseFloat(data.expected_amount);
		var expectedStr = !isNaN(expectedVal) && expectedVal > 0 ? '₱' + expectedVal.toLocaleString('en-PH',{minimumFractionDigits:2}) : 'Free';
		
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
		var statusMap = {
			pending:   { cls:'pending',   icon:'fa-clock',        label:'Pending'   },
			confirmed: { cls:'confirmed', icon:'fa-check-circle', label:'Confirmed' },
			rejected:  { cls:'rejected',  icon:'fa-times-circle', label:'Rejected'  },
			refunded:  { cls:'refunded',  icon:'fa-undo-alt',     label:'Refunded'  },
			refund_pending: { cls:'refund_pending', icon:'fa-hourglass-half', label:'Refund Pending' }
		};
		var s = statusMap[data.pay_status] || statusMap.pending;
		document.getElementById('detailHeaderStatusBadge').innerHTML =
			'<span class="pay-badge ' + s.cls + ' py-1 px-3" style="font-size: 0.75rem;"><i class="fas ' + s.icon + '"></i> ' + s.label + '</span>';

		// Receipt image
		document.getElementById('detailReceiptImg').src = data.receipt_url;

		// Resident Refund Request Details card (your refund submission)
		var resRefundCard = document.getElementById('detailResidentRefundCard');
		if (data.pay_status === 'refund_pending' || data.pay_status === 'refunded') {
			var numEl   = document.getElementById('detailResidentRefundNumber');
			var notesEl = document.getElementById('detailResidentRefundNotes');
			if (data.refund_number) {
				numEl.textContent    = data.refund_number;
				numEl.style.color    = '';
				numEl.style.fontStyle = '';
			} else {
				numEl.textContent    = 'Not provided';
				numEl.style.color    = '#d97706';
				numEl.style.fontStyle = 'italic';
			}
			notesEl.textContent = data.refund_notes || data.notes || '—';
			resRefundCard.classList.remove('d-none');
		} else {
			resRefundCard.classList.add('d-none');
		}

		// Admin Refund Details card (visible only when refunded by admin)
		var refundCard = document.getElementById('detailRefundCard');
		if (data.pay_status === 'refunded') {
			var adminNumEl    = document.getElementById('detailRefundNumber');
			var adminAmountEl  = document.getElementById('detailRefundAmount');
			var adminNotesEl   = document.getElementById('detailRefundNotes');

			adminNumEl.textContent = data.admin_refund_number || '—';
			
			if (data.admin_refund_amount) {
				adminAmountEl.textContent = '₱' + parseFloat(data.admin_refund_amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
			} else {
				adminAmountEl.textContent = '—';
			}

			adminNotesEl.textContent = data.admin_refund_notes || 'No remarks provided.';

			var rcContainer = document.getElementById('detailRefundReceiptContainer');
			if (data.refund_receipt) {
				var rrUrl = data.refund_receipt;
				rcContainer.innerHTML = 
					'<button type="button" class="btn btn-sm w-100 border fw-semibold rounded-3 py-2 mt-1" ' +
					'onclick="zoomReceipt(\'' + rrUrl + '\')" style="font-size: 0.8rem; color: #0d9488; border-color: #99f6e4 !important; background: #f0fdfa;">' +
					'<i class="far fa-eye me-2"></i>View Refund Receipt' +
					'</button>';
			} else {
				rcContainer.innerHTML = '<span class="text-muted small">No receipt uploaded.</span>';
			}
			refundCard.classList.remove('d-none');
		} else {
			refundCard.classList.add('d-none');
		}

		// Action footer buttons for resident
		var footer = document.getElementById('detailActionFooter');
		if (data.pay_status === 'refund_pending') {
			footer.innerHTML = '<span class="text-info" style="font-size: 0.85rem; font-style: italic;"><i class="fas fa-spinner fa-spin me-1"></i>Refund Request Pending Verification</span>';
		} else if (data.pay_status === 'refunded') {
			footer.innerHTML = '<span class="text-success" style="font-size: 0.85rem; font-style: italic;"><i class="fas fa-check-circle me-1"></i>Refund Processed Successfully</span>';
		} else if (data.pay_status === 'rejected') {
			footer.innerHTML = '<span class="text-danger" style="font-size: 0.85rem; font-style: italic;"><i class="fas fa-times-circle me-1"></i>Payment Rejected by Admin. Reason: ' + (data.notes || '—') + '</span>';
		} else if (data.status !== 'canceled') {
			footer.innerHTML = 
				'<button type="button" class="btn btn-danger rounded-pill px-4" ' +
				'onclick="openRefundModal(' + data.id + ', \'' + data.pay_type + '\')" style="font-size: 0.85rem;">' +
				'<i class="fas fa-undo me-1"></i> Request Refund' +
				'</button>';
		} else {
			footer.innerHTML = '<span class="text-secondary" style="font-size: 0.85rem; font-style: italic;"><i class="fas fa-ban me-1"></i>Document request canceled.</span>';
		}

		// Fail-safe manual modal show trigger
		var modalEl = document.getElementById('payDetailModal');
		if (typeof bootstrap !== 'undefined' && modalEl) {
			var modalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
			modalInstance.show();
		}
	} catch (err) {
		console.error("Error populating modal:", err);
	}
}

function openRefundModal(reqId, reqType) {
    // Close detail modal if open
    var detailModalEl = document.getElementById('payDetailModal');
    var modalInstance = bootstrap.Modal.getInstance(detailModalEl);
    if (modalInstance) {
        modalInstance.hide();
    }

    document.getElementById('refund_req_id').value = reqId;
    document.getElementById('refund_req_type').value = reqType;

    var refundModalEl = document.getElementById('refundModal');
    var refundModalInstance = bootstrap.Modal.getOrCreateInstance(refundModalEl);
    refundModalInstance.show();
}

function zoomReceipt(imgSrc) {
	Swal.fire({
		imageUrl: imgSrc,
		imageAlt: 'Receipt Image',
		confirmButtonColor: '#0d9488',
		confirmButtonText: 'Close',
		customClass: {
			image: 'img-fluid rounded-3'
		}
	});
}

function showRefundDetails(number, notes) {
    Swal.fire({
        title: 'Refund Details',
        html: 
            '<div class="text-start">' +
            '<p><strong>GCash Account:</strong> ' + number + '</p>' +
            '<p><strong>Notes:</strong> ' + notes + '</p>' +
            '</div>',
        icon: 'info',
        confirmButtonColor: '#14b8a6'
    });
}
</script>

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

	/* Detail modal receipt styles matching admin */
	#detailReceiptImg {
		max-height: 260px;
		width: 100%;
		object-fit: contain;
		border-radius: 10px;
		border: 1px solid #e9ecef;
		cursor: zoom-in;
	}
</style>

<!-- ─── Payment Detail Modal (Exact copy from Admin, adjusted for Resident view) ──────────────── -->
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
									<span class="text-secondary small">Amount Refunded</span>
									<span class="fw-bold text-dark small" id="detailRefundAmount"></span>
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
								<div class="text-muted fw-bold mb-2" style="font-size: 0.65rem; letter-spacing: 0.5px;">YOUR REFUND REQUEST DETAILS</div>
								
								<div class="d-flex justify-content-between mb-2 pb-2 border-bottom border-light-subtle">
									<span class="text-secondary small">GCash / Account No.</span>
									<span class="fw-bold text-dark small" id="detailResidentRefundNumber" style="font-family: monospace;"></span>
								</div>
								
								<div class="d-flex justify-content-between">
									<span class="text-secondary small">Reason for Refund</span>
									<span class="fw-bold text-dark small text-end" id="detailResidentRefundNotes" style="max-width: 60%; word-break: break-word; font-style: italic;"></span>
								</div>
							</div>
						</div>

						<!-- Action Needed / Status Updates Section for Resident -->
						<div class="d-flex align-items-start mb-2">
							<div class="text-teal-600 rounded-circle d-flex align-items-center justify-content-center me-3 mt-1" style="width: 42px; height: 42px; background: #f0fdfa;">
								<i class="fas fa-info-circle fs-5"></i>
							</div>
							<div>
								<h6 class="fw-bold mb-0 text-dark">Transaction Status</h6>
								<small class="text-secondary" style="font-size: 0.8rem;">You can request a refund for this e-wallet payment if the document request is not yet canceled.</small>
							</div>
						</div>
						
						<div class="d-flex gap-2 mt-3 ms-2" id="detailActionFooter" style="padding-left: 3.5rem;">
							<!-- Populated dynamically by openDetailModal JS -->
						</div>

					</div>

					<!-- Right Column (Sidebar details matching admin view) -->
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
									Payments are reviewed and verified securely by the barangay administrators.
								</div>
							</div>
						</div>

					</div>
				</div>
			</div>
			
			<div class="modal-footer border-top py-2 px-4 bg-light justify-content-between align-items-center">
				<small class="text-muted" style="font-size: 0.75rem; max-width: 70%;">You can view your uploaded receipt image by clicking on the "View receipt" button above.</small>
				<button type="button" class="btn btn-white border fw-semibold px-4 py-1 rounded-pill" data-bs-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>

<!-- Refund Request Modal -->
<div class="modal fade" id="refundModal" tabindex="-1" aria-labelledby="refundModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form method="POST" id="refundForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="refund">
                <input type="hidden" name="req_id" id="refund_req_id">
                <input type="hidden" name="req_type" id="refund_req_type">
                
                <div class="modal-header border-0 pb-0 bg-light p-4 rounded-top-4">
                    <h5 class="modal-title fw-bold text-danger" id="refundModalLabel">
                        <i class="fas fa-undo text-danger me-2"></i>Request E-Wallet Refund
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-4">
                    <div class="alert alert-warning rounded-3 border-0 small mb-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Please provide your correct GCash mobile number to process your refund request.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small">GCash Mobile Number</label>
                        <input type="text" name="refund_number" class="form-control rounded-3" placeholder="Example: 09123456789" required maxlength="11" pattern="^(09)\d{9}$">
                        <div class="form-text small">Must start with 09 and contain exactly 11 digits.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small">Reason for Refund</label>
                        <select name="refund_notes" class="form-select rounded-3" required>
                            <option value="" disabled selected>-- Select a Reason --</option>
                            <option value="Accidental / Duplicate Payment">Accidental / Duplicate Payment</option>
                            <option value="Incorrect Document Requested">Incorrect Document Requested</option>
                            <option value="Decided to Cancel Request">Decided to Cancel Request</option>
                            <option value="Overpaid / Sent Excess Amount">Overpaid / Sent Excess Amount</option>
                            <option value="Other / Personal Reason">Other / Personal Reason</option>
                        </select>
                    </div>
                </div>
                
                <div class="modal-footer border-0 p-4 pt-0 d-flex gap-2">
                    <button type="button" class="btn btn-light rounded-pill px-4 w-50" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4 w-50">Submit Request</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="payment-card mb-4 animate__animated animate__fadeInUp">
	<!-- Header -->
	<div class="p-4 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-3">
		<div>
			<h5 class="mb-0 fw-bold"><i class="fas fa-credit-card me-2 text-teal-600"></i>Online Payments</h5>
			<p class="text-muted mb-0 small mt-1">Review and track your payment receipts submitted via e-wallet</p>
		</div>
		<div class="d-flex align-items-center gap-2">
			<?php if ($pending_count > 0): ?>
				<span class="stat-pill" style="background:#fef3c7;color:#92400e;">
					<i class="fas fa-clock"></i><?php echo $pending_count; ?> Pending
				</span>
			<?php endif; ?>
			<span class="stat-pill" style="background:#e0f2fe;color:#0369a1;">
				<i class="fas fa-receipt"></i><?php echo $total_count; ?> Total
			</span>
		</div>
	</div>

	<?php if ($message): ?>
		<div class="alert alert-success alert-dismissible fade show m-3 mb-0 rounded-3" role="alert">
			<i class="fas fa-info-circle me-2"></i><?php echo htmlspecialchars($message); ?>
			<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
		</div>
	<?php endif; ?>

	<!-- Filter tabs -->
	<div class="px-4 py-3 border-bottom d-flex align-items-center gap-1 flex-wrap">
		<a href="payments.php?payment_status_filter=all" class="filter-tab <?php echo $status_filter === 'all' ? 'active' : ''; ?>">All</a>
		<a href="payments.php?payment_status_filter=pending"   class="filter-tab <?php echo $status_filter === 'pending'   ? 'active' : ''; ?>">Pending</a>
		<a href="payments.php?payment_status_filter=confirmed" class="filter-tab <?php echo $status_filter === 'confirmed' ? 'active' : ''; ?>">Confirmed</a>
		<a href="payments.php?payment_status_filter=rejected"  class="filter-tab <?php echo $status_filter === 'rejected'  ? 'active' : ''; ?>">Rejected</a>
		<a href="payments.php?payment_status_filter=refunded"  class="filter-tab <?php echo $status_filter === 'refunded'  ? 'active' : ''; ?>">Refunded</a>
	</div>

	<!-- Table -->
	<div class="p-3">
		<div class="table-card">
		<div class="table-responsive">
			<table class="table table-hover align-middle">
				<thead class="bg-light text-uppercase" style="font-size:.78rem;">
					<tr>
						<th class="py-3 ps-3" style="width: 44px;">#</th>
						<th class="py-3">Name</th>
						<th class="py-3">Type</th>
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
							<td colspan="8" class="text-center py-5">
								<div class="py-4">
									<i class="fas fa-receipt fa-3x text-muted opacity-25 mb-3 d-block"></i>
									<p class="text-muted mb-0">Walang nahanap na record ng bayad.</p>
								</div>
							</td>
						</tr>
					<?php else: ?>
						<?php $row_n = $offset + 1; ?>
						<?php foreach ($display_rows as $slice_idx => $p): ?>
							<?php
							$index = $offset + $slice_idx;
							$pay_status = $p['payment_status'] ?? 'pending';
							$amount_paid = (float)($p['amount_paid'] ?? 0);
							$ref_no = htmlspecialchars($p['reference_no'] ?? '—');
							
							// JSON data safe for detail modal population matching admin
							$j = [
								'id'              => (int)$p['id'],
								'pay_type'        => $p['pay_type'],
								'full_name'       => $p['fm_name'] ?? $p['user_name'],
								'email'           => $p['user_email'] ?? '',
								'doc_type'        => $p['doc_name'],
								'doc_detail'      => $p['doc_detail'] ?? '',
								'reference_no'    => $ref_no,
								'amount'          => $amount_paid,
								'expected_amount' => (float)($p['expected_amount'] ?? 0),
								'date'            => date('M d, Y g:i A', strtotime($p['created_at'])),
								'pay_status'      => $pay_status,
								'receipt_url'     => $p['payment_receipt'],
								'refund_number'        => $p['refund_number'] ?? '',
								'refund_notes'         => $p['refund_notes'] ?? '',
								'refund_receipt'       => $p['refund_receipt'] ?? '',
								'notes'                => $p['notes'] ?? '',
								'admin_refund_number'  => $p['admin_refund_number'] ?? '',
								'admin_refund_notes'   => $p['admin_refund_notes'] ?? '',
								'admin_refund_amount'  => $p['admin_refund_amount'] ?? '',
								'status'               => $p['status'] ?? 'pending',
							];
							?>
							<textarea id="payment-data-<?php echo $index; ?>" class="d-none" style="display: none;"><?php echo htmlspecialchars(json_encode($j), ENT_QUOTES, 'UTF-8'); ?></textarea>
							<tr>
								<td class="ps-3 text-muted fw-semibold"><?php echo $row_n++; ?></td>
								<td>
									<div class="text-dark"><?php echo htmlspecialchars($p['fm_name'] ?? $p['user_name']); ?></div>
								</td>
								<td>
									<span class="text-dark"><?php echo htmlspecialchars($p['doc_name']); ?></span>
								</td>
								<td>
									<span class="fw-bold text-teal-600">
										₱<?php echo number_format($amount_paid, 2); ?>
									</span>
								</td>
								<td>
									<span class="ref-code"><?php echo $ref_no; ?></span>
								</td>
								<td>
									<?php
									if ($pay_status === 'confirmed') {
										echo '<span class="pay-badge confirmed"><i class="fas fa-check-circle"></i> Confirmed</span>';
									} else if ($pay_status === 'pending') {
										echo '<span class="pay-badge pending"><i class="fas fa-clock"></i> Pending</span>';
									} else if ($pay_status === 'rejected') {
										echo '<span class="pay-badge rejected"><i class="fas fa-times-circle"></i> Rejected</span>';
									} else if ($pay_status === 'refunded') {
										echo '<span class="pay-badge refunded"><i class="fas fa-undo-alt"></i> Refunded</span>';
									} else if ($pay_status === 'refund_pending') {
										echo '<span class="pay-badge refund_pending"><i class="fas fa-hourglass-half"></i> Refund Pending</span>';
									}
									?>
								</td>
								<td class="text-muted small">
									<i class="far fa-calendar-alt me-1 opacity-50"></i>
									<?php echo date('M d, Y', strtotime($p['created_at'])); ?>
								</td>
								<td class="pe-3 text-center">
									<div class="d-flex align-items-center justify-content-center gap-1">
										<button type="button" class="btn-view-detail" title="Tingnan Detalye" 
												data-bs-toggle="modal" data-bs-target="#payDetailModal"
												onclick="openDetailModal(<?php echo $index; ?>)">
											<i class="fas fa-eye" style="font-size:.85rem;"></i>
										</button>

									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		</div>

		<!-- Pagination -->
		<?php if ($total_pages > 1): ?>
			<nav class="table-pagination">
				<ul class="pagination">
					<!-- Previous Page Link -->
					<li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
						<a class="page-link rounded-3 px-3 py-2 border-light-subtle d-inline-flex align-items-center gap-1 fw-bold" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
							<i class="fas fa-chevron-left" style="font-size:.65rem;"></i> Prev
						</a>
					</li>

					<?php
					$max_visible = 5;
					$start = max(1, $page - floor($max_visible / 2));
					$end = min($total_pages, $start + $max_visible - 1);
					if ($end - $start + 1 < $max_visible) {
						$start = max(1, $end - $max_visible + 1);
					}

					if ($start > 1): ?>
						<li class="page-item">
							<a class="page-link rounded-3 px-3 py-2 border-light-subtle" href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">1</a>
						</li>
						<?php if ($start > 2): ?>
							<li class="page-item disabled"><span class="page-link rounded-3 px-2 py-2 border-light-subtle">...</span></li>
						<?php endif; ?>
					<?php endif; ?>

					<?php for ($p = $start; $p <= $end; $p++): ?>
						<li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
							<a class="page-link rounded-3 px-3 py-2 border-light-subtle" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $p])); ?>"><?php echo $p; ?></a>
						</li>
					<?php endfor; ?>

					<?php if ($end < $total_pages): ?>
						<?php if ($end < $total_pages - 1): ?>
							<li class="page-item disabled"><span class="page-link rounded-3 px-2 py-2 border-light-subtle">...</span></li>
						<?php endif; ?>
						<li class="page-item">
							<a class="page-link rounded-3 px-3 py-2 border-light-subtle" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>"><?php echo $total_pages; ?></a>
						</li>
					<?php endif; ?>

					<!-- Next Page Link -->
					<li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
						<a class="page-link rounded-3 px-3 py-2 border-light-subtle d-inline-flex align-items-center gap-1 fw-bold" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
							Next <i class="fas fa-chevron-right" style="font-size:.65rem;"></i>
						</a>
					</li>
				</ul>
			</nav>
		<?php endif; ?>
	</div>
</div>





<?php
require_once __DIR__ . '/partials/user_dashboard_footer.php';
?>

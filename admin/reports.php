<?php
require_once __DIR__ . '/../config.php';
if (!is_admin())
    redirect('../index.php');

$page_title = 'Generate Reports';
$breadcrumb = [
    ['title' => 'Reports']
];

$pdo = get_db_connection();

// Filter parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-t');     // Last day of current month

$db_start = $start_date . ' 00:00:00';
$db_end = $end_date . ' 23:59:59';

// 1. Residents Demographics
$stmt = $pdo->query("
    SELECT 
        (SELECT COUNT(*) FROM residents WHERE verification_status = 'verified') +
        (SELECT COUNT(*) FROM family_members)
");
$total_residents = $stmt->fetchColumn();

$stmt = $pdo->query("
    SELECT sex, SUM(cnt) as count FROM (
        SELECT sex, COUNT(*) as cnt FROM residents WHERE verification_status = 'verified' GROUP BY sex
        UNION ALL
        SELECT sex, COUNT(*) as cnt FROM family_members GROUP BY sex
    ) t GROUP BY sex
");
$gender_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$male_res = $gender_stats['Male'] ?? 0;
$female_res = $gender_stats['Female'] ?? 0;

// Age and special demographics - Combined from residents and family members
$stmt = $pdo->query("
    SELECT 
        (SELECT COUNT(*) FROM residents WHERE is_senior = 1 AND verification_status = 'verified') + 
        (SELECT COUNT(*) FROM family_members WHERE is_senior = 1) as seniors,
        (SELECT COUNT(*) FROM residents WHERE is_pwd = 1 AND verification_status = 'verified') + 
        (SELECT COUNT(*) FROM family_members WHERE is_pwd = 1) as pwds,
        (SELECT COUNT(*) FROM residents WHERE is_solo_parent = 1 AND verification_status = 'verified') +
        (SELECT COUNT(*) FROM family_members WHERE is_solo_parent = 1) as solo_parents
");
$special_sectors = $stmt->fetch(PDO::FETCH_ASSOC);

// 2. Document Requests (Summary Stats - Keep for the top cards)
$stmt = $pdo->prepare("
    SELECT status, COUNT(*) as count 
    FROM document_requests 
    WHERE created_at BETWEEN ? AND ? 
    GROUP BY status
");
$stmt->execute([$db_start, $db_end]);
$doc_summary_raw = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$total_docs = array_sum($doc_summary_raw);

// 3. Incidents (Summary Stats - Keep for the top cards)
$stmt = $pdo->prepare("
    SELECT status, COUNT(*) as count 
    FROM incidents 
    WHERE created_at BETWEEN ? AND ? 
    GROUP BY status
");
$stmt->execute([$db_start, $db_end]);
$incidents_summary_raw = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$total_incidents = array_sum($incidents_summary_raw);

// 4. Pagination & Detailed Lists Configuration
$items_per_page = 10;

// Document Requests Pagination
$doc_page = isset($_GET['doc_page']) ? (int) $_GET['doc_page'] : 1;
if ($doc_page < 1)
    $doc_page = 1;
$doc_offset = ($doc_page - 1) * $items_per_page;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM document_requests WHERE created_at BETWEEN ? AND ?");
$stmt->execute([$db_start, $db_end]);
$total_doc_records = $stmt->fetchColumn();
$total_doc_pages = ceil($total_doc_records / $items_per_page);

$stmt = $pdo->prepare("
    SELECT dr.*, COALESCE(fm.full_name, u.full_name) as display_name 
    FROM document_requests dr 
    JOIN users u ON dr.user_id = u.id 
    LEFT JOIN family_members fm ON dr.family_member_id = fm.id
    WHERE dr.created_at BETWEEN ? AND ? 
    ORDER BY dr.created_at DESC 
    LIMIT $items_per_page OFFSET $doc_offset
");
$stmt->execute([$db_start, $db_end]);
$detailed_docs = $stmt->fetchAll();

// Incidents Pagination
$inc_page = isset($_GET['inc_page']) ? (int) $_GET['inc_page'] : 1;
if ($inc_page < 1)
    $inc_page = 1;
$inc_offset = ($inc_page - 1) * $items_per_page;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM incidents WHERE created_at BETWEEN ? AND ?");
$stmt->execute([$db_start, $db_end]);
$total_inc_records = $stmt->fetchColumn();
$total_inc_pages = ceil($total_inc_records / $items_per_page);

$stmt = $pdo->prepare("
    SELECT i.*, u.full_name 
    FROM incidents i 
    JOIN users u ON i.user_id = u.id 
    WHERE i.created_at BETWEEN ? AND ? 
    ORDER BY i.created_at DESC 
    LIMIT $items_per_page OFFSET $inc_offset
");
$stmt->execute([$db_start, $db_end]);
$detailed_incidents = $stmt->fetchAll();

require_once __DIR__ . '/header.php';
?>

<style>
    /* Clickable card cursor */
    .report-card-clickable {
        cursor: pointer;
        transition: transform 0.15s, box-shadow 0.15s;
    }

    .report-card-clickable:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15) !important;
    }

    .report-stat-clickable {
        cursor: pointer;
        transition: background 0.15s, transform 0.15s;
    }

    .report-stat-clickable:hover {
        background: #f0f4ff !important;
        transform: scale(1.02);
    }

    /* Custom Teal Styles for RBI */
    .table-teal-light { background-color: rgba(20, 184, 166, 0.05) !important; }
    .bg-teal { background-color: #0f766e !important; color: white !important; }
    .text-teal { color: #0f766e !important; }
    
    /* Z-index fix for modal backdrop */
    .modal-backdrop { z-index: 1040 !important; }
    #reportDetailModal { z-index: 1050 !important; }

    @media print {
        .btn-print-hide { display: none !important; }
    }
</style>

<!-- Filter Section -->
<div class="card border-0 shadow-sm mb-4 filter-section">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Start Date</label>
                <input type="date" name="start_date" class="form-control"
                    value="<?php echo htmlspecialchars($start_date); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">End Date</label>
                <input type="date" name="end_date" class="form-control"
                    value="<?php echo htmlspecialchars($end_date); ?>">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter me-2"></i>Filter
                </button>
            </div>
        </form>
    </div>
</div>

<div id="printableArea">
    <div class="text-center mb-4 d-none d-print-block">
        <img src="../public/img/barangaylogo.png" alt="Logo" style="width: 80px; height: 80px;" class="mb-2">
        <h4 class="mb-0">Barangay Panungyanan</h4>
        <p class="text-muted">General Trias, Cavite</p>
        <h5 class="mt-3 text-uppercase">System Activity Report</h5>
        <small>Period: <?php echo date('F d, Y', strtotime($start_date)); ?> to
            <?php echo date('F d, Y', strtotime($end_date)); ?></small>
        <hr>
    </div>

    <!-- Restore Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="admin-stats-card info m-0 shadow-sm report-card-clickable" onclick="loadReport('total_residents','Total of Resident')" title="Click to view list">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stats-label">Total of Resident</div>
                        <div class="stats-number"><?php echo number_format($total_residents); ?></div>
                    </div>
                    <i class="fas fa-users stats-icon"></i>
                </div>
                <div class="text-end mt-1"><small class="text-white opacity-75"><i class="fas fa-mouse-pointer me-1"></i>Click to view</small></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="admin-stats-card success m-0 shadow-sm report-card-clickable" onclick="loadReport('doc_requests','Document Requests')" title="Click to view list">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stats-label">Doc Requests (Period)</div>
                        <div class="stats-number"><?php echo number_format($total_docs); ?></div>
                    </div>
                    <i class="fas fa-file-alt stats-icon"></i>
                </div>
                <div class="text-end mt-1"><small class="text-white opacity-75"><i class="fas fa-mouse-pointer me-1"></i>Click to view</small></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="admin-stats-card warning m-0 shadow-sm report-card-clickable" onclick="loadReport('incidents','Incident Reports')" title="Click to view list">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stats-label">Incidents (Period)</div>
                        <div class="stats-number"><?php echo number_format($total_incidents); ?></div>
                    </div>
                    <i class="fas fa-exclamation-triangle stats-icon"></i>
                </div>
                <div class="text-end mt-1"><small class="text-white opacity-75"><i class="fas fa-mouse-pointer me-1"></i>Click to view</small></div>
            </div>
        </div>
    </div>

    <!-- RBI FORM REPORTS AS CARDS -->
    <div class="row g-4 mb-4">
        <div class="col-12">
            <h5 class="fw-bold text-teal mb-3"><i class="fas fa-file-invoice me-2"></i>BARANGAY RBI FORM REPORTS</h5>
        </div>
        <!-- RBI FORM A -->
        <div class="col-md-4">
            <div class="admin-stats-card info m-0 shadow-sm report-card-clickable" onclick="loadReport('households','RBI FORM A: Household Record')" title="Click to view Form A">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stats-label">RBI FORM A</div>
                        <div class="h3 fw-bold mb-0">Household</div>
                        <div class="small opacity-75 mt-1">Record of Barangay Informants by Household</div>
                    </div>
                    <i class="fas fa-house-user stats-icon"></i>
                </div>
                <div class="text-end mt-2 small opacity-75"><i class="fas fa-mouse-pointer me-1"></i>Click to view</div>
            </div>
        </div>
        <!-- RBI FORM B -->
        <div class="col-md-4">
            <div class="admin-stats-card success m-0 shadow-sm report-card-clickable" onclick="loadReport('households','RBI FORM B: Individual Record')" title="Click to view Form B">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stats-label">RBI FORM B</div>
                        <div class="h3 fw-bold mb-0">Individual</div>
                        <div class="small opacity-75 mt-1">Individual Records of Barangay Inhabitant</div>
                    </div>
                    <i class="fas fa-user-check stats-icon"></i>
                </div>
                <div class="text-end mt-2 small opacity-75"><i class="fas fa-mouse-pointer me-1"></i>Click to view</div>
            </div>
        </div>
        <!-- RBI FORM C -->
        <div class="col-md-4">
            <div class="admin-stats-card warning m-0 shadow-sm report-card-clickable" onclick="loadReport('summary','RBI FORM C: Monitoring Report')" title="Click to view Form C">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stats-label">RBI FORM C</div>
                        <div class="h3 fw-bold mb-0">Monitoring</div>
                        <div class="small opacity-75 mt-1">Semestral Monitoring Report</div>
                    </div>
                    <i class="fas fa-chart-pie stats-icon"></i>
                </div>
                <div class="text-end mt-2 small opacity-75"><i class="fas fa-mouse-pointer me-1"></i>Click to view</div>
            </div>
        </div>
    </div>

    </div>
</div>

<!-- Report Detail Modal -->
<div class="modal fade" id="reportDetailModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="modal-title fw-bold mb-0" id="reportModalTitle"><i class="fas fa-list me-2"></i>Report
                        Detail</h5>
                    <small class="text-muted" id="reportModalPeriod"></small>
                </div>
                <div class="d-flex gap-2 align-items-center ms-auto">
                    <button class="btn btn-success btn-sm" onclick="printReport()"><i
                            class="fas fa-print me-1"></i>Print</button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body" id="reportModalBody">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="mt-2 text-muted">Loading...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const START_DATE = '<?php echo $start_date; ?>';
    const END_DATE = '<?php echo $end_date; ?>';

    function loadReport(type, label) {
        document.getElementById('reportModalTitle').innerHTML = '<i class="fas fa-list me-2"></i>' + label;
        document.getElementById('reportModalPeriod').textContent =
            'Period: ' + formatDate(START_DATE) + ' to ' + formatDate(END_DATE);
        document.getElementById('reportModalBody').innerHTML =
            '<div class="text-center py-5"><div class="spinner-border text-primary"></div><div class="mt-2 text-muted">Loading...</div></div>';
        if (!window.reportModalObj) {
            window.reportModalObj = new bootstrap.Modal(document.getElementById('reportDetailModal'));
        }
        window.reportModalObj.show();

        fetch('reports_data.php?type=' + type + '&start=' + START_DATE + '&end=' + END_DATE + '&v=' + new Date().getTime())
            .then(r => r.json())
            .then(data => {
                window.currentReportData = data;
                window.currentReportType = type;
                document.getElementById('reportModalBody').innerHTML = buildTable(type, label, data);
            })
            .catch(() => {
                document.getElementById('reportModalBody').innerHTML =
                    '<div class="alert alert-danger">Failed to load data. Please try again.</div>';
            });
    }

    function formatDate(d) {
        const dt = new Date(d + 'T00:00:00');
        return dt.toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' });
    }

    function buildTable(type, label, data) {
        if (!data || data.length === 0) {
            return '<div class="text-center text-muted py-5"><i class="fas fa-inbox fa-2x mb-2 d-block"></i>No records found.</div>';
        }

        let rows = '';
        let headers = '';

        if (type === 'male' || type === 'female' || type === 'total_residents') {
            headers = '<th>#</th><th>Full Name</th><th>Type</th><th>Birthdate</th><th>Sex</th><th>Civil Status</th><th>Address</th><th>Phone</th>';
            data.forEach((r, i) => {
                const isFM = r.source === 'Family Member' && r.owner_name;
                rows += `<tr>
                <td>${i + 1}</td>
                <td class="fw-bold">${esc(r.full_name)}</td>
                <td>${isFM
                        ? `<span class="badge bg-secondary">Family Member</span>`
                        : `<span class="badge bg-primary">Owner</span>`}</td>
                <td>${r.birthdate ? formatDate(r.birthdate) : '—'}</td>
                <td>${esc(r.sex || '—')}</td>
                <td>${esc(r.civil_status || '—')}</td>
                <td>${esc(r.address || '—')}</td>
                <td>${esc(r.phone || '—')}</td>
            </tr>`;
            });
        } else if (type === 'seniors' || type === 'pwds') {
            headers = '<th>#</th><th>Full Name</th><th>Type</th><th>Birthdate</th><th>Sex</th><th>Address</th>';
            data.forEach((r, i) => {
                const isFM = r.source === 'Family Member' && r.owner_name;
                rows += `<tr>
                <td>${i + 1}</td>
                <td class="fw-bold">${esc(r.full_name)}</td>
                <td>${isFM
                        ? `<span class="badge bg-secondary">Family Member</span>`
                        : `<span class="badge bg-primary">Owner</span>`}</td>
                <td>${r.birthdate ? formatDate(r.birthdate) : '—'}</td>
                <td>${esc(r.sex || '—')}</td>
                <td>${esc(r.address || '—')}</td>
            </tr>`;
            });
        } else if (type === 'solo_parents') {
            headers = '<th>#</th><th>Full Name</th><th>Type</th><th>Birthdate</th><th>Sex</th><th>Address</th><th>Phone</th>';
            data.forEach((r, i) => {
                const isFM = r.source === 'Family Member' && r.owner_name;
                rows += `<tr>
                <td>${i + 1}</td>
                <td class="fw-bold">${esc(r.full_name)}</td>
                <td>${isFM
                        ? `<span class="badge bg-secondary">Family Member</span>`
                        : `<span class="badge bg-primary">Owner</span>`}</td>
                <td>${r.birthdate ? formatDate(r.birthdate) : '—'}</td>
                <td>${esc(r.sex || '—')}</td>
                <td>${esc(r.address || '—')}</td>
                <td>${esc(r.phone || '—')}</td>
            </tr>`;
            });
        } else if (type === 'doc_requests') {
            headers = '<th>#</th><th>Date</th><th>Resident Name / Requestor</th><th>Document Type</th><th>Status</th>';
            data.forEach((r, i) => {
                const badgeMap = { pending: 'bg-warning text-dark', approved: 'bg-info', released: 'bg-success', rejected: 'bg-danger', canceled: 'bg-secondary' };
                const badge = badgeMap[r.status] || 'bg-secondary';
                const isFM = r.requestor_type === 'family_member';
                const nameCell = isFM
                    ? `${esc(r.display_name)} <span class="badge bg-light text-dark border">Family</span><br><small class="text-muted fw-normal">${esc(r.requester_name)}</small>`
                    : esc(r.display_name);
                rows += `<tr>
                <td>${i + 1}</td>
                <td class="small">${esc(r.created_at ? new Date(r.created_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '—')}</td>
                <td class="fw-bold">${nameCell}</td>
                <td>${esc(r.doc_type)}</td>
                <td><span class="badge ${badge}">${esc(r.status ? r.status.charAt(0).toUpperCase() + r.status.slice(1) : '')}</span></td>
            </tr>`;
            });
        } else if (type === 'households') {
            const isIndividual = label.includes('Individual');
            headers = `<th>#</th><th>Full Name</th>${isIndividual ? '<th>Role</th>' : ''}<th>Birthdate</th><th>Sex</th><th>Address</th><th class="text-center">Action</th>`;
            
            let displayData = isIndividual ? data : data.filter(r => r.role === 'Head');
            
            displayData.forEach((r, i) => {
                const roleBadge = r.role === 'Head' ? 'bg-teal' : 'bg-secondary';
                const printFunc = isIndividual ? `printRbiFormB([${JSON.stringify(r).replace(/"/g, '&quot;')}], 'RBI FORM B: Individual Record')` : `printSingleHousehold('${esc(r.address)}')`;
                
                rows += `<tr>
                <td>${i + 1}</td>
                <td class="fw-bold">${esc(r.full_name)}</td>
                ${isIndividual ? `<td><span class="badge ${roleBadge}">${esc(r.role)}</span></td>` : ''}
                <td>${r.birthdate ? formatDate(r.birthdate) : '—'}</td>
                <td>${esc(r.sex || '—')}</td>
                <td><strong>${esc(r.address)}</strong></td>
                <td class="text-center">
                    <button class="btn btn-sm btn-outline-success rounded-pill px-3" onclick="${printFunc}">
                        <i class="fas fa-print me-1"></i>Print
                    </button>
                </td>
            </tr>`;
            });
        } else if (type === 'summary') {
            const ageBrackets = Object.keys(data.age_brackets).map(b => `<tr><td>${b} years old</td><td>${data.age_brackets[b].M}</td><td>${data.age_brackets[b].F}</td><td class="fw-bold">${data.age_brackets[b].M + data.age_brackets[b].F}</td></tr>`).join('');
            const sectorRows = Object.keys(data.sectors).map(s => `<tr><td>${s}</td><td>${data.sectors[s].M}</td><td>${data.sectors[s].F}</td><td class="fw-bold">${data.sectors[s].M + data.sectors[s].F}</td></tr>`).join('');
            
            return `
            <div class="row g-3 mb-4 text-center">
                <div class="col-6 col-md-4"><div class="p-3 border rounded bg-white shadow-sm"><div class="h4 mb-0 fw-bold text-teal">${data.total_inhabitants}</div><div class="small text-muted">Total Inhabitants</div></div></div>
                <div class="col-6 col-md-4"><div class="p-3 border rounded bg-white shadow-sm"><div class="h4 mb-0 fw-bold text-teal">${data.total_households}</div><div class="small text-muted">Total Households</div></div></div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle" style="font-size:0.85rem;">
                    <thead class="table-teal-light"><tr><th class="w-50">INDICATORS</th><th>MALE</th><th>FEMALE</th><th>TOTAL</th></tr></thead>
                    <tbody>
                        <tr class="table-light fw-bold"><td colspan="4">Population by Age Bracket</td></tr>
                        ${ageBrackets}
                        <tr class="table-light fw-bold"><td colspan="4">Population by Sector</td></tr>
                        ${sectorRows}
                    </tbody>
                </table>
            </div>`;
        } else if (type === 'incidents') {
            headers = '<th>#</th><th>Date</th><th>Resident Name</th><th>Description</th><th>Status</th>';
            data.forEach((r, i) => {
                const badgeMap = { submitted: 'bg-danger', in_review: 'bg-warning text-dark', resolved: 'bg-success' };
                const badge = badgeMap[r.status] || 'bg-secondary';
                let statusLabel = r.status ? r.status.replace('_', ' ').replace(/^./, c => c.toUpperCase()) : '';
                if (r.status === 'submitted') statusLabel = 'Pending';
                
                let displayName = r.full_name;
                let desc = r.description || '';
                if (desc.startsWith('[Walk-in Reporter: ')) {
                    let endBracket = desc.indexOf(']');
                    if (endBracket !== -1) {
                        displayName = desc.substring(19, endBracket).trim();
                        desc = desc.substring(endBracket + 1).trim();
                    }
                }

                rows += `<tr>
                <td>${i + 1}</td>
                <td class="small">${esc(r.created_at ? new Date(r.created_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '—')}</td>
                <td class="fw-bold">${esc(displayName)}</td>
                <td class="small" style="max-width:280px;word-break:break-word;">${esc(desc.substring(0, 120))}${desc.length > 120 ? '…' : ''}</td>
                <td><span class="badge ${badge}">${statusLabel}</span></td>
            </tr>`;
            });
        }

        return `
        <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="text-muted small">Showing <strong>${data.length}</strong> record(s)</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-bordered align-middle mb-0" style="font-size:0.9rem;">
                <thead class="table-dark"><tr>${headers}</tr></thead>
                <tbody>${rows}</tbody>
            </table>
        </div>`;
    }

    function esc(s) {
        if (s === null || s === undefined) return '—';
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function printSingleHousehold(address) {
        if (!window.currentReportData) return;
        // Filter the full data for all members living in this address
        const filteredData = window.currentReportData.filter(r => r.address === address);
        if (filteredData.length > 0) {
            printRbiFormA(filteredData, 'RBI FORM A: Household Record');
        } else {
            alert('No records found for this address.');
        }
    }

    function printReport() {
        const title = document.getElementById('reportModalTitle').innerText.trim();
        const period = document.getElementById('reportModalPeriod').textContent;

        if (title.includes('RBI FORM A')) {
            printRbiFormA(window.currentReportData, title);
            return;
        }
        if (title.includes('RBI FORM B')) {
            printRbiFormB(window.currentReportData, title);
            return;
        }
        if (title.includes('RBI FORM C')) {
            printRbiFormC(window.currentReportData, title);
            return;
        }

        const bodyHTML = document.getElementById('reportModalBody').innerHTML;

        const win = window.open('', '_blank', 'width=1000,height=700');
        win.document.write(`<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>${title} - Barangay Panungyanan</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #000;
            padding: 30px;
        }
        .header {
            text-align: center;
            margin-bottom: 18px;
            border-bottom: 2px solid #000;
            padding-bottom: 14px;
        }
        .header img { width: 70px; height: 70px; margin-bottom: 6px; display: block; margin-left: auto; margin-right: auto; }
        .header h2 { font-size: 16px; margin: 4px 0 2px; }
        .header p  { font-size: 12px; margin: 2px 0; color: #444; }
        .header .report-title { font-size: 14px; font-weight: bold; text-transform: uppercase; margin-top: 8px; }
        .header .period { font-size: 11px; color: #555; margin-top: 2px; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
        }
        thead {
            background-color: #1a1a2e !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            color: #fff !important;
        }
        thead th {
            padding: 8px 10px;
            font-size: 11px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #000;
            color: #fff !important;
            background-color: #1a1a2e !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        tbody td {
            padding: 7px 10px;
            border: 1px solid #ccc;
            font-size: 11px;
            vertical-align: top;
        }
        tbody tr:nth-child(even) { background: #f5f5f5; }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border: 1px solid #000;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
        }
        .record-count {
            font-size: 11px;
            color: #555;
            margin-top: 10px;
            margin-bottom: 4px;
        }
        small { font-size: 10px; color: #555; }
        strong { font-weight: bold; }
        .footer {
            margin-top: 30px;
            border-top: 1px solid #ccc;
            padding-top: 10px;
            font-size: 10px;
            color: #888;
            text-align: center;
        }
        @media print {
            body { padding: 15px; }
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="../public/img/barangaylogo.png" alt="Logo">
        <h2>Barangay Panungyanan</h2>
        <p>General Trias, Cavite</p>
        <div class="report-title">${title}</div>
        <div class="period">${period}</div>
    </div>
    ${bodyHTML}
    <div class="footer">
        Printed on: ${new Date().toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })}
    </div>
    <script>
        // strip icon elements from FontAwesome (they don't load in popup)
        document.querySelectorAll('i.fas, i.far, i.fab').forEach(el => el.remove());
        window.onload = function() { window.print(); };
    <\/script>
</body>
</html>`);
        win.document.close();
    }

    function printRbiFormA(data, label) {
    if (!data || data.length === 0) return;
    
    const win = window.open('', '_blank', 'width=1100,height=850');
    
    let html = `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>${label} - Print</title>
    <style>
        @page { size: landscape; margin: 0.5in; }
        body { font-family: "Arial Narrow", Arial, sans-serif; font-size: 10pt; line-height: 1.2; padding: 0; margin: 0; }
        .form-header { font-size: 9pt; font-weight: bold; margin-bottom: 5px; }
        .main-title { text-align: center; font-size: 12pt; font-weight: bold; text-decoration: underline; margin-bottom: 15px; }
        .info-grid { display: grid; grid-template-columns: 180px 1fr; margin-bottom: 15px; row-gap: 2px; }
        .info-label { font-weight: bold; font-size: 9pt; }
        .info-value { border-bottom: 1px solid #000; padding-left: 10px; font-weight: normal; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #000; padding: 4px 6px; text-align: center; vertical-align: middle; }
        th { background: #f8f9fa; font-size: 8pt; font-weight: bold; }
        td { font-size: 8.5pt; height: 25px; }
        
        .name-cols { width: 80px; }
        .indicators-col { width: 120px; font-size: 7.5pt; text-align: left; }
        
        .footer-sig { display: flex; justify-content: space-between; margin-top: 30px; text-align: center; }
        .sig-block { width: 30%; }
        .sig-line { border-top: 1px solid #000; margin-top: 40px; padding-top: 2px; font-size: 8pt; }
        .sig-sub { font-size: 7pt; font-style: italic; }
        
        .notice { font-size: 6.5pt; color: #444; margin-top: 20px; line-height: 1.3; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>`;

    const households = {};
    data.forEach(r => {
        const key = r.address || 'Unknown';
        if (!households[key]) households[key] = [];
        households[key].push(r);
    });

    Object.keys(households).forEach((addr, idx) => {
        const members = households[addr];
        const head = members.find(m => m.role === 'Head') || members[0];
        
        if (idx > 0) html += `<div style="page-break-before: always;"></div>`;
        
        html += `
        <div class="form-header">RBI FORM A (Revised 2024)</div>
        <div class="main-title">RECORD OF BARANGAY INFORMANTS BY HOUSEHOLD</div>
        
        <div class="info-grid">
            <div class="info-label">REGION:</div><div class="info-value">CALABARZON (Region IV-A)</div>
            <div class="info-label">PROVINCE:</div><div class="info-value">Cavite</div>
            <div class="info-label">BARANGAY:</div><div class="info-value">Panungyanan</div>
            <div class="info-label">CITY/MUNICIPALITY:</div><div class="info-value">General Trias</div>
            <div class="info-label">HOUSEHOLD ADDRESS:</div><div class="info-value">${esc(addr)} ${head.purok ? '('+esc(head.purok)+')' : ''}</div>
            <div class="info-label">NO. OF HOUSEHOLD MEMBERS:</div><div class="info-value">${members.length}</div>
        </div>

        <table>
            <thead>
                <tr>
                    <th colspan="3">NAME</th>
                    <th rowspan="2">EXT</th>
                    <th rowspan="2">PLACE OF BIRTH</th>
                    <th rowspan="2">DATE OF BIRTH</th>
                    <th rowspan="2">AGE</th>
                    <th rowspan="2">SEX</th>
                    <th rowspan="2">CIVIL STATUS</th>
                    <th rowspan="2">CITIZENSHIP</th>
                    <th rowspan="2">OCCUPATION</th>
                    <th rowspan="2" class="indicators-col">Indicators (Disability, Unemployed, PWD, OSY, Child Laborer, etc.)</th>
                </tr>
                <tr>
                    <th>LAST NAME</th>
                    <th>FIRST NAME</th>
                    <th>MIDDLE NAME</th>
                </tr>
            </thead>
            <tbody>`;
            
            members.forEach(m => {
                let last = m.last_name || '', 
                    first = m.first_name || '', 
                    middle = m.middle_name || '',
                    ext = m.suffix || '';

                // Fallback splitting only if database columns are missing for some reason
                if (!first && !last && m.full_name) {
                    const parts = m.full_name.split(' ');
                    if (parts.length >= 3) {
                        last = parts[parts.length - 1];
                        first = parts[0];
                        middle = parts.slice(1, -1).join(' ');
                    } else if (parts.length === 2) {
                        last = parts[1];
                        first = parts[0];
                    } else {
                        first = parts[0];
                    }
                }

                let age = '—';
                if (m.birthdate) {
                    const diff = Date.now() - new Date(m.birthdate).getTime();
                    age = Math.floor(diff / (1000 * 60 * 60 * 24 * 365.25));
                }

                html += `<tr>
                    <td>${esc(last)}</td>
                    <td>${esc(first)}</td>
                    <td>${esc(middle)}</td>
                    <td>${esc(ext)}</td>
                    <td>${esc(m.birth_place || '—')}</td>
                    <td>${m.birthdate || '—'}</td>
                    <td>${age}</td>
                    <td>${esc(m.sex ? m.sex.charAt(0).toUpperCase() : '—')}</td>
                    <td>${esc(m.civil_status || 'Single')}</td>
                    <td>FILIPINO</td>
                    <td>${esc(m.occupation || 'N/A')}</td>
                    <td class="indicators-col">${esc(m.classification || '')}</td>
                </tr>`;
            });

            for (let i = members.length; i < 5; i++) {
                html += `<tr><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>`;
            }

        html += `</tbody>
        </table>

        <div class="footer-sig">
            <div class="sig-block">
                <div class="sig-line">Prepared by:</div>
                <div class="sig-sub">Name of Household Head/Member<br>(Signature over Printed Name)</div>
            </div>
            <div class="sig-block">
                <div class="sig-line">Certified Correct:</div>
                <div class="sig-sub">Barangay Secretary<br>(Signature over Printed Name)</div>
            </div>
            <div class="sig-block">
                <div class="sig-line">Validated by:</div>
                <div class="sig-sub">Punong Barangay<br>(Signature over Printed Name)</div>
            </div>
        </div>

        <div class="notice">
            **Notice:** I/We certify that the above information are true and correct to the best of my/our knowledge and understand that they are subject to verification and investigation by the authorities... 
            (This form is governed by relevant laws and regulations of the Philippines. By choosing to provide my personal information, I/We agree to the provisions of the Philippine Data Privacy Act of 2012.)
        </div>
        `;
    });

    html += `</body></html>`;
    win.document.write(html);
    win.document.close();
    win.onload = function() { win.print(); };
}

function printRbiFormB(data, label) {
    if (!data || data.length === 0) return;
    const win = window.open('', '_blank', 'width=900,height=1000');
    if (!win) {
        alert('Please allow popups for this site to print the report.');
        return;
    }
    let html = `<!DOCTYPE html><html><head><meta charset="UTF-8"><title>RBI Form B - Print</title>
    <style>
        @page { size: portrait; margin: 0.5in; }
        body { font-family: Arial, sans-serif; font-size: 10pt; line-height: 1.4; padding: 0; margin: 0; }
        .form-header { font-size: 9pt; font-weight: bold; }
        .main-title { text-align: center; font-size: 13pt; font-weight: bold; margin: 15px 0; }
        .head-info { display: flex; justify-content: space-between; font-size: 9pt; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 15px; }
        .head-info div { width: 48%; }
        .head-line { display: flex; border-bottom: 1px solid #000; margin-bottom: 4px; }
        .head-label { width: 100px; font-weight: bold; }
        
        .box-section { border: 2px solid #000; padding: 10px; margin-bottom: 20px; min-height: 850px; position: relative; }
        .section-title { text-align: center; font-weight: bold; border-bottom: 1px solid #000; margin: -10px -10px 10px -10px; padding: 5px; }
        
        .field-group { margin-bottom: 15px; }
        .field-box { border: 1px solid #000; min-height: 22px; padding: 2px 8px; margin-top: 2px; background: #fff; font-weight: bold; }
        .field-sub { font-size: 7.5pt; color: #333; margin-top: 1px; }
        
        .row { display: flex; gap: 10px; margin-bottom: 10px; }
        .col { flex: 1; }
        
        .checkbox-group { display: flex; flex-wrap: wrap; gap: 15px; margin-top: 8px; font-weight: bold; }
        .checkbox { border: 1px solid #000; width: 15px; height: 15px; display: inline-block; vertical-align: middle; margin-right: 5px; text-align: center; line-height: 14px; }
        
        .footer-sig { display: flex; justify-content: space-between; margin-top: 30px; }
        .sig-block { width: 45%; text-align: center; }
        .sig-line { border-bottom: 1px solid #000; margin-top: 35px; min-height: 20px; }
        .sig-label { font-size: 8pt; margin-top: 4px; }
        
        .thumb-section { display: flex; gap: 20px; margin-top: 20px; }
        .thumb-box { width: 80px; height: 100px; border: 1px solid #000; display: flex; align-items: flex-end; justify-content: center; padding-bottom: 5px; font-size: 8pt; text-align: center; }
        
        @media print { .page-break { page-break-after: always; } }
    </style></head><body>`;

    data.forEach((r, idx) => {
        // Use separate name columns if available
        let last = r.last_name || '', 
            suffix = r.suffix || '', 
            first = r.first_name || '', 
            middle = r.middle_name || '';

        // Fallback splitting only if database columns are missing
        if (!first && !last && r.full_name) {
            const parts = r.full_name.split(' ');
            if (parts.length >= 4) {
                last = parts[parts.length - 1];
                middle = parts[parts.length-2];
                first = parts.slice(0, parts.length - 2).join(' ');
            } else if (parts.length === 3) {
                last = parts[2];
                middle = parts[1];
                first = parts[0];
            } else if (parts.length === 2) {
                last = parts[1];
                first = parts[0];
            } else {
                first = parts[0];
            }
        }

        const eduAttainment = String(r.educational_attainment || '');
        const isUnder = eduAttainment.toLowerCase().includes('under graduate') || eduAttainment.toLowerCase().includes('undergraduate');
        const isGrad = eduAttainment.toLowerCase().includes('graduate') && !isUnder;
        const baseEdu = eduAttainment.split(' (')[0].trim().toUpperCase();

        html += `
        <div class="${idx < data.length - 1 ? 'page-break' : ''}">
            <div class="form-header">RBI Form B (Revised 2024)</div>
            <div class="main-title">INDIVIDUAL RECORDS OF BARANGAY INHABITANT</div>
            
            <div class="head-info">
                <div>
                    <div class="head-line"><span class="head-label">REGION:</span><span>CALABARZON (Region IV-A)</span></div>
                    <div class="head-line"><span class="head-label">PROVINCE:</span><span>Cavite</span></div>
                </div>
                <div>
                    <div class="head-line"><span class="head-label">CITY/MUN:</span><span>General Trias</span></div>
                    <div class="head-line"><span class="head-label">BARANGAY:</span><span>Panungyanan</span></div>
                </div>
            </div>

            <div class="box-section">
                <div class="section-title">PERSONAL INFORMATION</div>
                
                <div class="field-group">
                    <div class="field-box">${esc(r.philsys_card_no || '')}</div>
                    <div class="field-sub">(PhilSys Card No.)</div>
                </div>

                <div class="row">
                    <div class="col" style="flex: 2;">
                        <div class="field-box">${esc(last)}</div>
                        <div class="field-sub">(Last Name)</div>
                    </div>
                    <div class="col">
                        <div class="field-box">${esc(suffix)}</div>
                        <div class="field-sub">(Suffix)</div>
                    </div>
                    <div class="col" style="flex: 2;">
                        <div class="field-box">${esc(first)}</div>
                        <div class="field-sub">(First Name)</div>
                    </div>
                    <div class="col" style="flex: 2;">
                        <div class="field-box">${esc(middle)}</div>
                        <div class="field-sub">(Middle Name)</div>
                    </div>
                </div>

                <div class="row" style="margin-top: 10px;">
                    <div class="col">
                        <div class="field-box">${r.birthdate ? formatDate(r.birthdate) : ''}</div>
                        <div class="field-sub">(Birth Date)</div>
                    </div>
                    <div class="col">
                        <div class="field-box">${esc(r.birth_place || '')}</div>
                        <div class="field-sub">(Place of Birth)</div>
                    </div>
                    <div class="col" style="flex: 0.5;">
                        <div class="field-box">${esc(r.sex || '')}</div>
                        <div class="field-sub">(Sex)</div>
                    </div>
                    <div class="col">
                        <div class="field-box">${esc(r.civil_status || '')}</div>
                        <div class="field-sub">(Civil Status)</div>
                    </div>
                    <div class="col">
                        <div class="field-box">${esc(r.religion || '')}</div>
                        <div class="field-sub">(Religion)</div>
                    </div>
                </div>

                <div class="row" style="margin-top: 10px;">
                    <div class="col" style="flex: 2;">
                        <div class="field-box">${esc(r.address || '')}</div>
                        <div class="field-sub">(Residence Address)</div>
                    </div>
                    <div class="col">
                        <div class="field-box">${esc(r.citizenship || '')}</div>
                        <div class="field-sub">(Citizenship)</div>
                    </div>
                </div>

                <div class="row" style="margin-top: 10px;">
                    <div class="col">
                        <div class="field-box">${esc(r.occupation || 'N/A')}</div>
                        <div class="field-sub">(Profession / Occupation)</div>
                    </div>
                    <div class="col">
                        <div class="field-box">${esc(r.phone || '')}</div>
                        <div class="field-sub">(Contact Number)</div>
                    </div>
                    <div class="col">
                        <div class="field-box">${esc(r.email || '')}</div>
                        <div class="field-sub">(E-mail Address)</div>
                    </div>
                </div>

                <div style="font-weight: bold; margin-top: 25px; border-top: 1px solid #000; padding-top: 10px;">HIGHEST EDUCATIONAL ATTAINMENT</div>
                <div class="checkbox-group">
                    ${['Elementary', 'High School', 'College', 'Post Grad', 'Vocational'].map(opt => {
                        const isMatch = baseEdu.includes(opt.toUpperCase());
                        return `<span><div class="checkbox">${isMatch ? '✓' : ''}</div>${opt.toUpperCase()}</span>`;
                    }).join('')}
                </div>
                
                <div class="row" style="margin-top: 10px;">
                    <div class="col" style="flex: 0.5;">Please specify:</div>
                    <div class="col"><div class="checkbox">${isUnder ? '✓' : ''}</div> Under Graduate</div>
                    <div class="col"><div class="checkbox">${isGrad ? '✓' : ''}</div> Graduate</div>
                </div>

                <div style="font-size: 8.5pt; margin-top: 40px; font-style: italic; color: #444; border-top: 1px solid #eee; padding-top: 10px;">
                    I hereby certify that the above information is true and correct to the best of my knowledge. I understand that for the Barangay to carry out its mandate pursuant to Section 394 (d)(6) of the Local Goverment Code of 1991, they must necessarily process my personal information for easy identification of inhabitants, as a tool in planning, and as an updated reference in the number of inhabitants of the Barangay. Therefore, I grant my consent and recognize the authority of the Barangay to process my personal information, subject to the provision of the Philippine Data Privacy Act of 2012.
                </div>

                <div class="footer-sig">
                    <div class="sig-block">
                        <div class="sig-line"></div>
                        <div class="sig-label">Date Accomplished</div>
                    </div>
                    <div class="sig-block">
                        <div class="sig-line"></div>
                        <div class="sig-label">Name/Signature of Person Accomplishing the Form</div>
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: flex-end; position: absolute; bottom: 20px; left: 10px; right: 10px;">
                    <div style="width: 55%;">
                        <div style="font-size: 8pt; margin-bottom: 5px;">Attested By:</div>
                        <div style="border-bottom: 1px solid #000; width: 250px; min-height: 20px;"></div>
                        <div style="font-size: 8pt; font-weight: bold; margin-top: 5px;">Barangay Secretary</div>
                        
                        <div style="margin-top: 25px; display: flex; align-items: center; gap: 10px;">
                            <span style="font-weight: bold;">Household Number:</span>
                            <div style="border: 1px solid #000; width: 100px; height: 25px;"></div>
                        </div>
                        <div style="font-size: 7.5pt; font-style: italic; margin-top: 4px;">**Note:** The household number shall be filled up by the Barangay Secretary.</div>
                    </div>
                    
                    <div class="thumb-section">
                        <div class="thumb-box">Left Thumbmark</div>
                        <div class="thumb-box">Right Thumbmark</div>
                    </div>
                </div>
            </div>
            <div style="text-align: right; font-size: 7pt; font-style: italic; color: #888;">Computer Generated</div>
        </div>`;
    });

    html += `</body></html>`;
    win.document.write(html);
    win.document.close();
    // Use a small timeout to ensure document is ready for print in all browsers
    setTimeout(() => { win.print(); }, 250);
}

function printRbiFormC(data, label) {
    if (!data) return;
    const win = window.open('', '_blank', 'width=1000,height=900');
    
    let ageRows = '', sectorRows = '';
    Object.keys(data.age_brackets).forEach(b => {
        const m = data.age_brackets[b].M;
        const f = data.age_brackets[b].F;
        ageRows += `<tr><td>${b} years old</td><td>${m}</td><td>${f}</td><td>${m+f}</td><td></td></tr>`;
    });
    Object.keys(data.sectors).forEach(s => {
        const m = data.sectors[s].M;
        const f = data.sectors[s].F;
        sectorRows += `<tr><td>${s}</td><td>${m}</td><td>${f}</td><td>${m+f}</td><td></td></tr>`;
    });

    win.document.write(`<!DOCTYPE html><html><head><meta charset="UTF-8"><title>RBI Form C - Print</title>
    <style>
        @page { size: portrait; margin: 0.25in; }
        body { font-family: Arial, sans-serif; font-size: 8.5pt; line-height: 1.0; padding: 0; margin: 0; color: #000; }
        .header { text-align: center; margin-bottom: 8px; position: relative; }
        .form-id { text-align: left; font-size: 7pt; font-weight: bold; position: absolute; top: -15px; left: 0; }
        .main-title { font-size: 11pt; font-weight: bold; text-decoration: underline; margin-top: 0; }
        .period-sub { font-size: 8.5pt; margin-top: 1px; }
        
        .info-grid { margin: 10px 0; font-weight: bold; font-size: 8.5pt; text-transform: uppercase; display: grid; grid-template-columns: 1fr 1fr; gap: 2px 20px; border-bottom: 1px solid #000; padding-bottom: 8px; }
        .info-grid div { margin-bottom: 1px; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #000; padding: 1.5px 4px; text-align: center; }
        th { background: #e2efda; font-size: 8pt; font-weight: bold; text-transform: uppercase; }
        td { font-size: 8pt; }
        td:first-child { text-align: left; padding-left: 5px; font-weight: normal; }
        .section-header { background: #f2f2f2; font-weight: bold !important; text-align: left; font-size: 8pt; }
        
        .footer-sigs { display: flex; justify-content: space-between; margin-top: 25px; page-break-inside: avoid; }
        .sig-block { width: 45%; }
        .sig-line { border-bottom: 1.2px solid #000; margin-top: 20px; width: 90%; display: inline-block; min-height: 12px; }
        .sig-label { font-weight: bold; font-size: 8.5pt; margin-top: 2px; }
        .sig-sub { font-size: 7.5pt; color: #333; margin-top: 1px; }
        
        .accomplished { margin-top: 15px; font-weight: bold; font-size: 8.5pt; }
        .footer-note { font-size: 7pt; color: #444; border-top: 1px solid #ccc; padding-top: 5px; margin-top: 15px; font-style: italic; }
        
        @media print {
            body { padding: 0; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
    </style></head><body>
        <div class="form-id">RBI FORM C (Revised 2024)</div>
        <div class="header">
            <div class="main-title">MONITORING REPORT</div>
            <div class="period-sub">For ${new Date().getMonth() < 6 ? '1st' : '2nd'} Semester of CY ${new Date().getFullYear()}</div>
        </div>

        <div class="info-grid">
            <div>REGION: IV-A CALABARZON</div>
            <div>BARANGAY: PANUNGYANAN</div>
            <div>PROVINCE: CAVITE</div>
            <div>Total Inhabitants: ${data.total_inhabitants}</div>
            <div>CITY/MUN: GENERAL TRIAS</div>
            <div>Total Households/Families: ${data.total_households}</div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 45%;">INDICATORS</th>
                    <th style="width: 12%;">MALE</th>
                    <th style="width: 12%;">FEMALE</th>
                    <th style="width: 12%;">TOTAL</th>
                    <th>REMARKS</th>
                </tr>
            </thead>
            <tbody>
                <tr class="section-header"><td colspan="5">Population by Age Bracket:</td></tr>
                ${ageRows}
                <tr class="section-header"><td colspan="5">Population by Sector:</td></tr>
                ${sectorRows}
            </tbody>
        </table>

        <div class="footer-sigs">
            <div class="sig-block">
                <div>Prepared by:</div>
                <div class="sig-line"></div>
                <div class="sig-label">Barangay Secretary</div>
                <div class="sig-sub">Signature over Printed Name</div>
            </div>
            <div class="sig-block">
                <div>Submitted by:</div>
                <div class="sig-line"></div>
                <div class="sig-label">Punong Barangay</div>
                <div class="sig-sub">Signature over Printed Name</div>
            </div>
        </div>
        
        <div class="accomplished">
            Date Accomplished: <span style="border-bottom: 1px solid #000; padding: 0 40px;">${new Date().toLocaleDateString('en-PH', { month: 'long', day: 'numeric', year: 'numeric' })}</span>
        </div>

        <div class="footer-note">
            Note: This RBI Form C (Semestral Monitoring Report) is to be submitted to DILG C/MLGOO as reference for encoding to BIS-BPS
        </div>
    </body></html>`);
    win.document.close();
    win.onload = function() { win.print(); };
}

    async function printAllReport() {
        const btn = document.querySelector('[onclick="printAllReport()"]');
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Preparing...'; }

        const periodLabel = `${formatDate(START_DATE)} to ${formatDate(END_DATE)}`;
        const types = [
            { key: 'total_residents', label: 'Total of Resident' },
            { key: 'male', label: 'Male Residents' },
            { key: 'female', label: 'Female Residents' },
            { key: 'seniors', label: 'Senior Citizens' },
            { key: 'pwds', label: 'Persons with Disability (PWD)' },
            { key: 'solo_parents', label: 'Solo Parents' },
            { key: 'doc_requests', label: 'Document Requests' },
            { key: 'incidents', label: 'Incident Reports' },
        ];

        // Fetch all in parallel
        const results = await Promise.all(types.map(t =>
            fetch(`reports_data.php?type=${t.key}&start=${START_DATE}&end=${END_DATE}`)
                .then(r => r.json()).catch(() => [])
        ));

        // Build sections HTML
        let sectionsHTML = '';
        types.forEach((t, idx) => {
            const data = results[idx];
            sectionsHTML += `
            <div class="section" style="margin-top:28px;">
                <div class="section-title">${t.label} <span class="count">(${data.length})</span></div>
                ${buildSectionTable(t.key, data)}
            </div>`;
        });

        const win = window.open('', '_blank', 'width=1100,height=800');
        win.document.write(`<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Barangay Panungyanan - Full Report</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 12px; color: #000; padding: 30px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 14px; }
        .header img { width: 70px; height: 70px; display: block; margin: 0 auto 6px; }
        .header h2 { font-size: 16px; margin: 4px 0; }
        .header p { font-size: 12px; color: #444; margin: 2px 0; }
        .header .report-title { font-size: 15px; font-weight: bold; text-transform: uppercase; margin-top: 8px; }
        .header .period { font-size: 11px; color: #555; margin-top: 3px; }
        .section { page-break-inside: avoid; }
        .section-title { font-size: 12px; font-weight: bold; text-transform: uppercase; background: #f0f0f0; padding: 6px 10px; border-left: 4px solid #1a1a2e; margin-bottom: 6px; }
        .count { font-weight: normal; color: #555; text-transform: none; }
        table { width: 100%; border-collapse: collapse; }
        thead { background-color: #1a1a2e !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        thead th { padding: 6px 8px; font-size: 10px; text-align: left; font-weight: bold; border: 1px solid #000; color: #fff !important; background-color: #1a1a2e !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        tbody td { padding: 5px 8px; border: 1px solid #ccc; font-size: 10px; vertical-align: top; }
        tbody tr:nth-child(even) { background: #f9f9f9; }
        .no-data { color: #888; font-style: italic; font-size: 10px; padding: 6px 0; }
        .badge { display: inline-block; padding: 1px 5px; border: 1px solid #000; border-radius: 3px; font-size: 9px; font-weight: bold; }
        small { font-size: 9px; color: #555; }
        .footer { margin-top: 30px; border-top: 1px solid #ccc; padding-top: 10px; font-size: 10px; color: #888; text-align: center; }
        @media print {
            body { padding: 15px; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .section { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="../public/img/barangaylogo.png" alt="Logo">
        <h2>Barangay Panungyanan</h2>
        <p>General Trias, Cavite</p>
        <div class="report-title">General System Report</div>
        <div class="period">Period: ${periodLabel}</div>
    </div>
    ${sectionsHTML}
    <div class="footer">
        Printed on: ${new Date().toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })}
    </div>
    <script>window.onload = function() { window.print(); };<\/script>
</body>
</html>`);
        win.document.close();

        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-print me-2"></i>Print'; }
    }

    function buildSectionTable(type, data) {
        if (!data || data.length === 0) return '<div class="no-data">No records found for this period.</div>';
        let headers = '', rows = '';

        if (type === 'total_residents' || type === 'male' || type === 'female') {
            headers = '<th>#</th><th>Full Name</th><th>Source</th><th>Birthdate</th><th>Sex</th><th>Civil Status</th><th>Address</th><th>Phone</th>';
            data.forEach((r, i) => {
                const isFM = r.source === 'Family Member' && r.owner_name;
                rows += `<tr><td>${i + 1}</td><td>${esc(r.full_name)}</td><td>${isFM ? `Family Member<br><small>${esc(r.owner_name)}</small>` : 'Resident'}</td><td>${r.birthdate ? formatDate(r.birthdate) : '—'}</td><td>${esc(r.sex || '—')}</td><td>${esc(r.civil_status || '—')}</td><td>${esc(r.address || '—')}</td><td>${esc(r.phone || '—')}</td></tr>`;
            });
        } else if (type === 'seniors' || type === 'pwds') {
            headers = '<th>#</th><th>Full Name</th><th>Source</th><th>Birthdate</th><th>Sex</th><th>Address</th>';
            data.forEach((r, i) => {
                const isFM = r.source === 'Family Member' && r.owner_name;
                rows += `<tr><td>${i + 1}</td><td>${esc(r.full_name)}</td><td>${isFM ? `Family Member<br><small>${esc(r.owner_name)}</small>` : 'Resident'}</td><td>${r.birthdate ? formatDate(r.birthdate) : '—'}</td><td>${esc(r.sex || '—')}</td><td>${esc(r.address || '—')}</td></tr>`;
            });
        } else if (type === 'solo_parents') {
            headers = '<th>#</th><th>Full Name</th><th>Birthdate</th><th>Sex</th><th>Address</th><th>Phone</th>';
            data.forEach((r, i) => {
                rows += `<tr><td>${i + 1}</td><td>${esc(r.full_name)}</td><td>${r.birthdate ? formatDate(r.birthdate) : '—'}</td><td>${esc(r.sex || '—')}</td><td>${esc(r.address || '—')}</td><td>${esc(r.phone || '—')}</td></tr>`;
            });
        } else if (type === 'doc_requests') {
            headers = '<th>#</th><th>Date</th><th>Name</th><th>Requestor</th><th>Document Type</th><th>Status</th>';
            data.forEach((r, i) => {
                const isFM = r.requestor_type === 'family_member';
                rows += `<tr><td>${i + 1}</td><td>${r.created_at ? new Date(r.created_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'}</td><td>${esc(r.display_name)}</td><td>${isFM ? esc(r.requester_name) : '—'}</td><td>${esc(r.doc_type)}</td><td>${esc(r.status ? r.status.charAt(0).toUpperCase() + r.status.slice(1) : '')}</td></tr>`;
            });
        } else if (type === 'incidents') {
            headers = '<th>#</th><th>Date</th><th>Resident Name</th><th>Description</th><th>Status</th>';
            data.forEach((r, i) => {
                const statusLabel = r.status ? r.status.replace('_', ' ').replace(/^./, c => c.toUpperCase()) : '';
                rows += `<tr><td>${i + 1}</td><td>${r.created_at ? new Date(r.created_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'}</td><td>${esc(r.full_name)}</td><td>${esc((r.description || '').substring(0, 100))}${(r.description || '').length > 100 ? '…' : ''}</td><td>${statusLabel}</td></tr>`;
            });
        }
        return `<table><thead><tr>${headers}</tr></thead><tbody>${rows}</tbody></table>`;
    }
</script>

<?php require_once __DIR__ . '/footer.php'; ?>

<?php
require_once __DIR__ . '/config.php';

if (!is_logged_in()) {
    redirect('login.php');
}

// Only admins can generate PDFs
if (!is_admin()) {
    die('Access denied. Only administrators can generate clearance PDFs.');
}

$pdo = get_db_connection();
$clearance_id = (int)($_GET['id'] ?? 0);

if ($clearance_id <= 0) {
    die('Invalid clearance ID');
}

// Get clearance data (admin can access any clearance)
$stmt = $pdo->prepare('
    SELECT bc.*, u.full_name, u.email, r.address, r.phone, r.birthdate, r.sex, r.civil_status, r.barangay_id, r.purok,
           fm.full_name as fm_name, fm.sex as fm_sex, fm.birthdate as fm_birthdate
    FROM barangay_clearances bc
    JOIN users u ON u.id = bc.user_id
    LEFT JOIN residents r ON r.user_id = u.id
    LEFT JOIN family_members fm ON fm.id = bc.family_member_id
    WHERE bc.id = ?
');
$stmt->execute([$clearance_id]);
$clearance = $stmt->fetch();

if (!$clearance) {
    die('Clearance not found.');
}

if ($clearance['status'] !== 'approved' && $clearance['status'] !== 'released') {
    die('Clearance not yet approved');
}

// Override details if requested for a family member
if (!empty($clearance['family_member_id']) && !empty($clearance['fm_name'])) {
    $clearance['full_name'] = $clearance['fm_name'];
    $clearance['sex'] = $clearance['fm_sex'];
    $clearance['birthdate'] = $clearance['fm_birthdate'];
    $clearance['civil_status'] = ''; // Family members do not have civil status recorded
}

// Calculate age from birthdate
$age = 'of legal age';
if ($clearance['birthdate']) {
    $birthDate = new DateTime($clearance['birthdate']);
    $today = new DateTime();
    $age = $today->diff($birthDate)->y . ' years old';
}

// Determine marital status
$is_single = false;
$is_married = false;
$is_widower = false;

if ($clearance['civil_status']) {
    switch(strtolower($clearance['civil_status'])) {
        case 'single':
            $is_single = true;
            break;
        case 'married':
            $is_married = true;
            break;
        case 'widowed':
            $is_widower = true;
            break;
    }
}

// Get purok from database field, fallback to extracting from address if not available
$purok = '';
if (!empty($clearance['purok'])) {
    $purok = $clearance['purok'];
} elseif ($clearance['address']) {
    // Fallback: Try to extract purok number from address
    if (preg_match('/purok\s*(\d+)/i', $clearance['address'], $matches)) {
        $purok = $matches[1];
    } elseif (preg_match('/purok\s*([a-z]+)/i', $clearance['address'], $matches)) {
        $purok = strtoupper($matches[1]);
    }
}

// Define available purposes for Clearance certificates (must match admin/requests.php)
$clearance_purposes_list = [
    'Local Employment',
    'Postal ID Application',
    'Medical/Financial Assistance',
    'Bank Requirements',
    'Scholarship Program',
    'Water/Electric Connection',
    'Educational Assistance',
    'Other\'s'
];

// Map purposes to checkbox keys
$purpose_to_checkbox = [
    'Local Employment' => 'local_employment',
    'Postal ID Application' => 'postal_id',
    'Medical/Financial Assistance' => 'medical_financial',
    'Bank Requirements' => 'bank',
    'Scholarship Program' => 'scholarship',
    'Water/Electric Connection' => 'water_electric',
    'Educational Assistance' => 'educational',
    'Other\'s' => 'others'
];

// Initialize all checkboxes to false
$purpose_checks = [
    'local_employment' => false,
    'medical_financial' => false,
    'scholarship' => false,
    'educational' => false,
    'postal_id' => false,
    'bank' => false,
    'water_electric' => false,
    'others' => false
];

// Check if the stored purpose matches one of the predefined options
$stored_purpose = trim($clearance['purpose']);
if (isset($purpose_to_checkbox[$stored_purpose])) {
    // Direct match - use the stored purpose
    $purpose_checks[$purpose_to_checkbox[$stored_purpose]] = true;
} else {
    // Fallback: Parse purpose text for backward compatibility with old data
    $purpose_lower = strtolower($stored_purpose);
    
    if (stripos($purpose_lower, 'employment') !== false || stripos($purpose_lower, 'job') !== false) {
        $purpose_checks['local_employment'] = true;
    }
    if (stripos($purpose_lower, 'medical') !== false || stripos($purpose_lower, 'financial') !== false || stripos($purpose_lower, 'assistance') !== false) {
        $purpose_checks['medical_financial'] = true;
    }
    if (stripos($purpose_lower, 'scholarship') !== false) {
        $purpose_checks['scholarship'] = true;
    }
    if (stripos($purpose_lower, 'educational') !== false || stripos($purpose_lower, 'education') !== false) {
        $purpose_checks['educational'] = true;
    }
    if (stripos($purpose_lower, 'postal') !== false || stripos($purpose_lower, 'id') !== false) {
        $purpose_checks['postal_id'] = true;
    }
    if (stripos($purpose_lower, 'bank') !== false) {
        $purpose_checks['bank'] = true;
    }
    if (stripos($purpose_lower, 'water') !== false || stripos($purpose_lower, 'electric') !== false || stripos($purpose_lower, 'connection') !== false) {
        $purpose_checks['water_electric'] = true;
    }
    if (!$purpose_checks['local_employment'] && !$purpose_checks['medical_financial'] && !$purpose_checks['scholarship'] && 
        !$purpose_checks['educational'] && !$purpose_checks['postal_id'] && !$purpose_checks['bank'] && !$purpose_checks['water_electric']) {
        $purpose_checks['others'] = true;
    }
}

// Format dates
$issue_day = date('jS', strtotime($clearance['created_at']));
$issue_month = date('F', strtotime($clearance['created_at']));
$issue_year = date('Y', strtotime($clearance['created_at']));
$issue_date_formatted = date('F d, Y', strtotime($clearance['created_at']));

$full_name = strtoupper($clearance['full_name']);

// Update database to track PDF generation
try {
    // Check if columns exist (for backward compatibility)
    $check_stmt = $pdo->query("SHOW COLUMNS FROM barangay_clearances LIKE 'pdf_generated_at'");
    $has_pdf_tracking = $check_stmt->rowCount() > 0;
    
    if ($has_pdf_tracking) {
        // Update PDF generation tracking
        $pdo->prepare('UPDATE barangay_clearances SET pdf_generated_at = NOW(), pdf_generated_by = ? WHERE id = ?')
            ->execute([$_SESSION['user_id'], $clearance_id]);
    }
} catch (Exception $e) {
    // PDF tracking update failed, but continue with PDF generation
}

// Create notification for user
try {
    $pdo->prepare('INSERT INTO notifications (user_id, type, title, message) VALUES (?, "general", "Barangay Clearance PDF Generated", ?)')
        ->execute([
            $clearance['user_id'],
            "Your Barangay Clearance (No. {$clearance['clearance_number']}) PDF has been generated and is ready for pickup at the barangay office."
        ]);
} catch (Exception $e) {
    // Notification creation failed, but continue with PDF generation
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Clearance - <?php echo htmlspecialchars($clearance['clearance_number']); ?></title>
    <style>
        @media print {
            body {
                margin: 0;
            }

            .no-print {
                display: none;
            }

            @page {
                margin: 0.5in;
            }
        }

        body {
            font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
            margin: 0;
            padding: 40px;
            color: #000;
            background: white;
            line-height: 1.5;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            position: relative;
        }

        /* Header Logos */
        .header-logos {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            position: relative;
        }

        .logo {
            width: 100px;
            height: auto;
        }

        .header-text {
            text-align: center;
            flex-grow: 1;
            padding: 0 10px;
        }

        .header-text h3 {
            margin: 0;
            font-size: 14px;
            font-weight: normal;
        }

        .header-text h2 {
            margin: 5px 0;
            font-size: 16px;
            font-weight: bold;
        }

        .header-text h1 {
            margin: 5px 0;
            font-size: 18px;
            font-weight: bold;
            color: #000;
        }

        .office-title {
            color: #ff0000;
            font-weight: bold;
            font-size: 18px;
            margin-top: 10px;
            text-transform: uppercase;
        }

        .doc-title {
            color: #0099cc;
            font-weight: bold;
            font-size: 24px;
            margin-top: 10px;
            text-transform: uppercase;
            margin-bottom: 40px;
        }

        /* Watermark Background */
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 80%;
            opacity: 0.1;
            z-index: -1;
            pointer-events: none;
        }

        /* Body Content */
        .content {
            font-size: 14px;
            text-align: justify;
        }

        .to-whom {
            font-weight: bold;
            margin-bottom: 20px;
            text-transform: uppercase;
        }

        .indent {
            text-indent: 40px;
        }

        .field-underline {
            border-bottom: 1px solid black;
            display: inline-block;
            min-width: 200px;
            text-align: center;
            font-weight: bold;
        }

        .small-underline {
            border-bottom: 1px solid black;
            display: inline-block;
            min-width: 50px;
            text-align: center;
        }

        /* Checkboxes */
        .purpose-grid {
            margin: 20px 0 20px 40px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .checkbox-item {
            display: flex;
            align-items: flex-start;
        }

        .checkbox-box {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 1px solid #000;
            margin-right: 8px;
            margin-top: 3px;
            position: relative;
            vertical-align: middle;
        }

        .checkbox-box.checked {
            background-color: #000;
        }

        .checkbox-box.checked::after {
            content: '✓';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 10px;
            font-weight: bold;
            line-height: 1;
        }

        /* Signatories */
        .signatories {
            margin-top: 50px;
            display: flex;
            justify-content: flex-end;
            flex-direction: column;
        }

        .attested {
            margin-bottom: 40px;
        }

        .sign-block {
            margin-bottom: 20px;
        }

        .sign-name {
            font-weight: bold;
            text-decoration: underline;
            text-transform: uppercase;
        }

        .sign-title {
            font-weight: bold;
        }

        .approved-by {
            text-align: right;
            margin-top: 20px;
        }

        /* Footer */
        .footer-note {
            margin-top: 50px;
            font-style: italic;
            font-size: 12px;
            color: #555;
            border-top: 1px solid #ccc;
            padding-top: 10px;
        }

        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        .print-btn:hover {
            background: #0056b3;
        }

        .corner-marks {
            position: absolute;
            width: 20px;
            height: 20px;
            border-color: #ccc;
            border-style: solid;
        }

        .top-left {
            top: 0;
            left: 0;
            border-width: 1px 0 0 1px;
        }

        .top-right {
            top: 0;
            right: 0;
            border-width: 1px 1px 0 0;
        }

        .bottom-left {
            bottom: 0;
            left: 0;
            border-width: 0 0 1px 1px;
        }

        .bottom-right {
            bottom: 0;
            right: 0;
            border-width: 0 1px 1px 0;
        }

        /* Footer section for clearance */
        .footer-section {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .footer-left {
            width: 45%;
        }

        .footer-right {
            width: 45%;
            text-align: right;
        }

        .signature-field {
            border-bottom: 1px solid black;
            min-width: 200px;
            margin: 8px 0;
            padding: 3px 0;
        }

        .thumbmark-box {
            width: 120px;
            height: 80px;
            border: 2px solid #000;
            margin: 10px 0;
            display: inline-block;
        }

        .thumbmark-label {
            font-size: 11px;
            font-weight: bold;
            margin-top: 5px;
        }
    </style>
</head>

<body>

    <button class="print-btn no-print" onclick="window.print()">Print / Save as PDF</button>

    <div class="container">
        <!-- Corner crops simulation -->
        <div class="corner-marks top-left"></div>
        <div class="corner-marks top-right"></div>
        <div class="corner-marks bottom-left"></div>
        <div class="corner-marks bottom-right"></div>

        <div class="header-logos">
            <!-- Left Logo -->
            <img src="public/img/panungyanan logo.jpg" alt="Barangay Logo" class="logo">

            <div class="header-text">
                <!-- Center Logo (Bagong Pilipinas) -->
                <div style="text-align:center; margin-bottom:5px;">
                    <img src="public/img/bagongpilipinas.jpg" alt="Logo" style="height: 60px; object-fit: contain;">
                </div>
                <h3>Province of Cavite</h3>
                <h3>Region IV-A CALABARZON</h3>
                <h3>City of General Trias</h3>
                <h1>BARANGAY PANUNGYANAN</h1>

                <div class="office-title">OFFICE OF THE PUNONG BARANGAY</div>
                <div class="doc-title">BARANGAY CLEARANCE</div>
            </div>

            <!-- Right Logo -->
            <img src="public/img/gentri.jpg" alt="City Logo" class="logo">
        </div>

        <!-- Watermark -->
        <img src="public/img/bagongpilipinas.jpg" class="watermark" alt="Watermark">

        <div class="content">
            <div class="to-whom">TO WHOM IT MAY CONCERN:</div>

            <p class="indent">
                This is to certify that <span class="field-underline">
                    <?php echo htmlspecialchars($full_name); ?>
                </span>
                of legal age single (<?php echo $is_single ? '✓' : ''; ?>) married (<?php echo $is_married ? '✓' : ''; ?>), widow/er (<?php echo $is_widower ? '✓' : ''; ?>), Filipino citizen, resident of this Barangay and presently residing at, <strong>PUROK <span class="small-underline"><?php echo htmlspecialchars($purok ?: ''); ?></span></strong> Barangay Panungyanan, General Trias City of Cavite.
            </p>

            <p class="indent">
                It further certifies that he/she has no derogatory record filed in the office as of this date.
            </p>

            <p class="indent">
                And that some indexes such as specimen signature and right thumb mark are belonged to him/her.
            </p>

            <p class="indent" style="margin-top: 20px;">
                This clearance is issued for the following purpose(s):
            </p>

            <div class="purpose-grid">
                <div class="checkbox-item">
                    <span class="checkbox-box <?php echo $purpose_checks['local_employment'] ? 'checked' : ''; ?>"></span> Local Employment
                </div>
                <div class="checkbox-item">
                    <span class="checkbox-box <?php echo $purpose_checks['postal_id'] ? 'checked' : ''; ?>"></span> Postal ID Application
                </div>
                <div class="checkbox-item">
                    <span class="checkbox-box <?php echo $purpose_checks['medical_financial'] ? 'checked' : ''; ?>"></span> Medical/Financial Assistance
                </div>
                <div class="checkbox-item">
                    <span class="checkbox-box <?php echo $purpose_checks['bank'] ? 'checked' : ''; ?>"></span> Bank Requirements
                </div>
                <div class="checkbox-item">
                    <span class="checkbox-box <?php echo $purpose_checks['scholarship'] ? 'checked' : ''; ?>"></span> Scholarship Program
                </div>
                <div class="checkbox-item">
                    <span class="checkbox-box <?php echo $purpose_checks['water_electric'] ? 'checked' : ''; ?>"></span> Water/Electric Connection
                </div>
                <div class="checkbox-item">
                    <span class="checkbox-box <?php echo $purpose_checks['educational'] ? 'checked' : ''; ?>"></span> Educational Assistance
                </div>
                <div class="checkbox-item">
                    <span class="checkbox-box <?php echo $purpose_checks['others'] ? 'checked' : ''; ?>"></span> Other's
                </div>
            </div>

            <p class="indent" style="margin-top: 30px;">
                Issued this <span class="small-underline">
                    <?php echo $issue_day; ?>
                </span> day of
                <span class="small-underline">
                    <?php echo $issue_month; ?>
                </span> <?php echo $issue_year; ?>, at Barangay Panungyanan, City of General Trias, Cavite.
            </p>
        </div>

        <div class="footer-section">
            <div class="footer-left">
                <div style="font-size: 11px; margin-bottom: 5px;"><strong><?php echo htmlspecialchars($full_name); ?></strong></div>
                <div class="signature-field"></div>
                <div style="font-size: 11px; margin-top: 5px;">Requesters Signature</div>
                
                <div style="margin-top: 10px;">
                    <div style="font-size: 11px;">Issued on: <span class="small-underline" style="min-width: 120px;"><?php echo $issue_date_formatted; ?></span></div>
                </div>
                
                <div style="margin-top: 5px; font-size: 11px;">General Trias City, Cavite.</div>
                
                <div class="signatories" style="margin-top: 30px;">
                    <div class="attested">
                        Attested by:<br><br>
                        <div class="sign-block">
                            <div class="sign-name">JEREMIAH-ANGELICA B. CAHINHINAN</div>
                            <div class="sign-title">Barangay Secretary</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="footer-right">
                <div class="thumbmark-box"></div>
                <div class="thumbmark-label">RIGHT THUMBMARK</div>
                
                <div class="approved-by">
                    Approved by:<br><br>
                    <div class="sign-block">
                        <div class="sign-name">RENATO S. ALMANZOR</div>
                        <div class="sign-title">Punong Barangay</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="footer-note">
            <p><strong>Note:</strong> Valid for 6 months from date of issue.</p>
            <p><strong>Note:</strong> Not valid without official seal/erasure.</p>
        </div>
    </div>

</body>

</html>

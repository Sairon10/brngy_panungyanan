<?php
require_once __DIR__ . '/config.php';

if (!is_logged_in()) {
    redirect('login.php');
}

// Only admins can generate certificates
if (!is_admin()) {
    die('Access denied. Only administrators can generate certificates.');
}

$pdo = get_db_connection();
$request_id = (int) ($_GET['id'] ?? 0);

if ($request_id <= 0) {
    die('Invalid request ID');
}

// Fetch request and resident details
$stmt = $pdo->prepare('
    SELECT dr.*, u.full_name, r.address, r.sex, r.birthdate, r.purok, r.civil_status,
           fm.full_name as fm_name, fm.sex as fm_sex, fm.birthdate as fm_birthdate
    FROM document_requests dr
    JOIN users u ON u.id = dr.user_id
    LEFT JOIN residents r ON r.user_id = u.id
    LEFT JOIN family_members fm ON fm.id = dr.family_member_id
    WHERE dr.id = ?
');
$stmt->execute([$request_id]);
$request = $stmt->fetch();

// Parse stored indigency purpose (single selection)
$selected_purpose = '';
if (!empty($request['indigency_purposes'])) {
    // Check if it's JSON (old format) or plain string (new format)
    $decoded = json_decode($request['indigency_purposes'], true);
    if (is_array($decoded) && !empty($decoded)) {
        // Old format: JSON array, take first item
        $selected_purpose = $decoded[0];
    } else if (is_string($decoded)) {
        $selected_purpose = $decoded;
    } else {
        // New format: plain string
        $selected_purpose = $request['indigency_purposes'];
    }
}

if (!$request) {
    die('Request not found.');
}

// Override details if requested for a family member
if (!empty($request['family_member_id']) && !empty($request['fm_name'])) {
    $request['full_name'] = $request['fm_name'];
    $request['sex'] = $request['fm_sex'];
    $request['birthdate'] = $request['fm_birthdate'];
    $request['civil_status'] = ''; // Family members do not have civil status recorded
}

// Check if document type contains "Indigency" (case-insensitive, handles variations like "Barangay Indigency")
if (stripos($request['doc_type'], 'Indigency') === false) {
    die('Invalid document type requested. This certificate generator is only for Indigency documents.');
}

// Allow both 'approved' and 'released' statuses (released means it's already approved)
if ($request['status'] !== 'approved' && $request['status'] !== 'released') {
    die('Document not yet approved. Status must be "approved" or "released" to generate certificate.');
}

$day = date('jS', strtotime($request['created_at']));
$month_year = date('F Y', strtotime($request['created_at']));
$full_name = strtoupper($request['full_name']);

// Determine salutation based on sex (simple fallback)
$salutation = 'MR./MS./MRS.';
if ($request['sex'] === 'Male')
    $salutation = 'MR.';
if ($request['sex'] === 'Female')
    $salutation = 'MS./MRS.';

// Determine civil status checkboxes
$is_single = false;
$is_married = false;
$is_widowed = false;
if ($request['civil_status']) {
    switch(strtolower($request['civil_status'])) {
        case 'single':
            $is_single = true;
            break;
        case 'married':
            $is_married = true;
            break;
        case 'widowed':
            $is_widowed = true;
            break;
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate of Indigency -
        <?php echo htmlspecialchars($full_name); ?>
    </title>
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

        /* Attempt to center the header exactly like the image */
        .header-center {
            text-align: center;
        }

        .header-center img.logo-center {
            width: 80px;
            margin-bottom: 5px;
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
                <!-- Center Logo (Bagong Pilipinas) - Placeholder if not exists or use text if simple -->
                <div style="text-align:center; margin-bottom:5px;">
                    <!-- Assuming we don't have the exact vector, we just format the text nicely -->
                    <img src="public/img/bagongpilipinas.jpg" alt="Logo" style="height: 60px; object-fit: contain;">
                </div>
                <h3>Province of Cavite</h3>
                <h3>Region IV-A CALABARZON</h3>
                <h3>City of General Trias</h3>
                <h1>BARANGAY PANUNGYANAN</h1>

                <div class="office-title">OFFICE OF THE PUNONG BARANGAY</div>
                <div class="doc-title">CERTIFICATE OF INDIGENCY</div>
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
                of legal age single (<?php echo $is_single ? '✓' : ''; ?>) married (<?php echo $is_married ? '✓' : ''; ?>), widow/er (<?php echo $is_widowed ? '✓' : ''; ?>), Filipino is a bonafide resident of
                <strong>PUROK <span class="small-underline"><?php echo htmlspecialchars($request['purok'] ?? ''); ?></span></strong>,
                <strong>Barangay Panungyanan City of General Trias, Cavite.</strong>
            </p>

            <p class="indent">
                Further certify that he/she is one among our residents that can be considered as
                "<u><strong>indigent</strong></u>".
                The collective family income is not even sufficient to support all their needs.
            </p>

            <p class="indent">
                This certification is being issued upon request of <strong>
                    <?php echo $salutation; ?>
                    <?php echo htmlspecialchars($full_name); ?>
                </strong>
                for the following purposes.
            </p>

            <div class="purpose-grid">
                <?php 
                $purposes = [
                    'Financial/Medical Assistance',
                    'Burial Assistance',
                    'Senior Citizen Social Pension',
                    'Vaccination Requirements',
                    'Educational Assistance',
                    'Other\'s'
                ];
                foreach ($purposes as $purpose): 
                    $is_checked = ($purpose === $selected_purpose);
                ?>
                    <div class="checkbox-item">
                        <span class="checkbox-box <?php echo $is_checked ? 'checked' : ''; ?>"></span> <?php echo htmlspecialchars($purpose); ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <p class="indent" style="margin-top: 30px;">
                Issued this <span class="small-underline">
                    <?php echo date('jS'); ?>
                </span> day of
                <span class="small-underline">
                    <?php echo date('F'); ?>
                </span> 2025 , at Barangay Panungyanan, City of General Trias, Cavite.
            </p>
        </div>

        <div class="signatories">
            <div class="attested">
                Attested by:<br><br>
                <div class="sign-block">
                    <div class="sign-name">JEREMIAH-ANGELICA B. CAHINHINAN</div>
                    <div class="sign-title">Barangay Secretary</div>
                </div>
            </div>

            <div class="approved-by">
                Approved by:<br><br>
                <div class="sign-block">
                    <div class="sign-name">RENATO S. ALMANZOR</div>
                    <div class="sign-title">Punong Barangay</div>
                </div>
            </div>
        </div>

        <div class="footer-note">
            Note: Not valid without official seal/erasure.
        </div>
    </div>

</body>

</html>

<?php
require_once __DIR__ . '/config.php';

if (!is_logged_in()) {
    redirect('login.php');
}

// Only admins can generate PDFs
if (!is_admin()) {
    die('Access denied. Only administrators can generate generic PDFs.');
}

$pdo = get_db_connection();
$request_id = (int)($_GET['id'] ?? 0);

if ($request_id <= 0) {
    die('Invalid request ID');
}

// Get the document request and resident data
$stmt = $pdo->prepare('
    SELECT dr.*, u.full_name, u.email, r.address, r.phone, r.birthdate, r.sex, r.civil_status, r.barangay_id, r.purok, dt.name as document_name,
           fm.full_name as fm_name, fm.sex as fm_sex, fm.birthdate as fm_birthdate
    FROM document_requests dr
    JOIN users u ON u.id = dr.user_id
    LEFT JOIN residents r ON r.user_id = u.id
    LEFT JOIN document_types dt ON dt.name = dr.doc_type
    LEFT JOIN family_members fm ON fm.id = dr.family_member_id
    WHERE dr.id = ?
');
$stmt->execute([$request_id]);
$request = $stmt->fetch();

if (!$request) {
    die('Document request not found.');
}

if ($request['status'] !== 'approved' && $request['status'] !== 'released') {
    die('Document request not yet approved.');
}

// Override details if requested for a family member
if (!empty($request['family_member_id']) && !empty($request['fm_name'])) {
    $request['full_name'] = $request['fm_name'];
    $request['sex'] = $request['fm_sex'];
    $request['birthdate'] = $request['fm_birthdate'];
    $request['civil_status'] = ''; // Family members do not have civil status recorded
}

// Prefix for name based on sex
$prefix = '';
if (strtolower($request['sex']) === 'male') {
    $prefix = 'MR. ';
} else if (strtolower($request['sex']) === 'female') {
    if (strtolower($request['civil_status']) === 'single') {
        $prefix = 'MS. ';
    } else {
        $prefix = 'MRS. ';
    }
}

$formatted_name = strtoupper($request['full_name']);

// Format dates
$issue_day = date('jS');
$issue_month_year = strtoupper(date('F, Y'));

$purpose = htmlspecialchars(strtoupper(trim($request['purpose'] ?: 'REFERENCE PURPOSES')));
$address = htmlspecialchars($request['address'] ?: 'Barangay Panungyanan');
if (!empty($request['purok'])) {
    // If address doesn't contain purok already, prepend it just to mimic the structure if desired
    // For now we just use the raw address + purok if needed.
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate of Cohabitation - <?php echo htmlspecialchars($request['full_name']); ?></title>
    <style>
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
            @page { margin: 0.5in; }
            .print-container { padding: 0 !important; }
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #000;
            background: white;
            line-height: 1.5;
        }

        .container {
            max-width: 850px;
            margin: 0 auto;
            position: relative;
        }

        /* Header Logos */
        .header-logos {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .header-text {
            text-align: center;
            flex-grow: 1;
            font-family: Arial, sans-serif;
        }
        .header-text h3 { margin: 0; font-size: 13px; font-weight: bold; color: #0f3d7b; }
        .header-text .highlight { color: #cc0000; }

        .wrapper {
            display: flex;
            gap: 10px;
        }

        /* Left Sidebar */
        .sidebar {
            width: 32%;
            border: 3px solid #0070c0;
            padding: 20px 10px;
            text-align: center;
        }

        .sidebar h4 {
            margin: 0;
            font-size: 14px;
            font-weight: bold;
            color: #000;
        }

        .sidebar p.title {
            margin: 2px 0 25px 0;
            font-size: 11px;
            font-weight: bold;
            color: #0070c0;
            text-transform: uppercase;
        }

        .sidebar .sangguniang {
            color: #00b050;
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 25px;
            padding: 0 10px;
        }

        .official-block { margin-bottom: 22px; }
        .official-block h4 { font-size: 12px; }
        .official-block p.title { margin: 2px 0 0 0; font-size: 10px; }


        /* Main Content Box */
        .main-content {
            width: 68%;
            border: 3px solid #f79646;
            padding: 30px 40px;
        }

        .doc-title {
            color: #0070c0;
            font-weight: 900;
            font-size: 22px;
            text-transform: uppercase;
            text-align: center;
            letter-spacing: 4px;
            margin-bottom: 40px;
            font-family: "Arial Black", Arial, sans-serif;
        }

        .content-text {
            font-size: 14px;
            text-align: justify;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .to-whom {
            font-weight: bold;
            margin-bottom: 30px;
        }

        .indent { text-indent: 40px; }
        
        .name-highlight {
            font-weight: bold;
            text-decoration: underline;
        }

        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            font-size: 13px;
        }
        
        .sign-left { width: 50%; }
        .sign-right { width: 45%; text-align: center; align-self: flex-end; }

        .ctc-lines {
            margin-top: 15px;
            line-height: 1.8;
        }

        .sign-name-bold { font-weight: bold; text-decoration: underline; }
        
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
        .print-btn:hover { background: #0056b3; }

    </style>
</head>

<body>
    <button class="print-btn no-print" onclick="window.print()">Print / Save as PDF</button>

    <div class="container print-container">
        <!-- Header -->
        <div class="header-logos">
            <div style="width: 20%; text-align: center;">
                <img src="public/img/panungyanan logo.jpg" alt="Barangay Logo" style="width: 90px; height: auto;">
            </div>

            <div class="header-text" style="width: 50%;">
                <h3>Republic of the Philippines</h3>
                <h3>Region IV-A Calabarzon</h3>
                <h3>Province of Cavite</h3>
                <h3>City of General Trias</h3>
                <h3 style="font-size: 15px; margin-top: 2px;">BARANGAY PANUNGYANAN</h3>
                <h3 class="highlight" style="margin-top: 2px;">OFFICE OF THE PUNONG BARANGAY</h3>
            </div>

            <div style="width: 30%; display: flex; justify-content: flex-end; align-items: center; gap: 10px;">
                <img src="public/img/gentri.jpg" alt="City Logo" style="width: 80px; height: auto;">
                <img src="public/img/bagongpilipinas.jpg" alt="Bagong Pilipinas Logo" style="height: 70px; object-fit: contain;">
            </div>
        </div>

        <div class="wrapper">
            <!-- Left Sidebar -->
            <div class="sidebar">
                <h4>HON. RENATO S. ALMANZOR</h4>
                <p class="title">PUNONG BARANGAY</p>

                <div class="sangguniang">MEMBERS OF THE SANGGUNIANG</div>

                <div class="official-block">
                    <h4>HON. DANILO D. ALMANZOR</h4>
                    <p class="title">PEACE AND ORDER</p>
                </div>
                
                <div class="official-block">
                    <h4>HON. ERICSON B. SALANDANAN</h4>
                    <p class="title">CHAIRMAN OF APPROPRIATION</p>
                </div>
                
                <div class="official-block">
                    <h4>HON. DELIA G. ANACAN</h4>
                    <p class="title">INFRASTRUCTURE</p>
                </div>
                
                <div class="official-block">
                    <h4>HON. JANETTE B. BAUTISTA</h4>
                    <p class="title">AGRICULTURE</p>
                </div>
                
                <div class="official-block">
                    <h4>HON. MICHEL G. ANACAN</h4>
                    <p class="title">ENVIRONMENT</p>
                </div>
                
                <div class="official-block">
                    <h4>HON. LUCITA A. LAYSA</h4>
                    <p class="title">HEALTH</p>
                </div>
                
                <div class="official-block">
                    <h4>HON. ROCHELLE S. BAUTISTA</h4>
                    <p class="title">EDUCATION</p>
                </div>
                
                <div class="official-block">
                    <h4>HON. LANCE ANDRIE L. HERNANDEZ</h4>
                    <p class="title">YOUTH AND SPORT</p>
                </div>
            </div>

            <!-- Main Content -->
            <div class="main-content">
                <div class="doc-title">CERTIFICATE OF COHABITATION</div>

                <div class="content-text">
                    <div class="to-whom">TO WHOM IT MAY CONCERN,</div>
                    
                    <p class="indent">
                        This is to certify that <span class="name-highlight"><?php echo $formatted_name; ?></span> resident of <?php echo $address; ?> Barangay Panungyanan, General Trias City, Cavite. Further certify that the name mention above is a law abiding citizen of this Barangay.
                    </p>
                    
                    <p class="indent" style="margin-top: 20px;">
                        Given this certification to <span class="name-highlight"><?php echo ($prefix . $formatted_name); ?></span> for <span style="font-weight: bold; text-decoration: underline;"><?php echo $purpose; ?></span> purposes.
                    </p>
                    
                    <p class="indent" style="margin-top: 20px;">
                        Issued this <span class="name-highlight"><?php echo $issue_day; ?> day</span> of <span class="name-highlight"><?php echo $issue_month_year; ?></span> at Barangay Hall of Panungyanan General Trias City, Cavite.
                    </p>
                </div>

                <div style="font-weight: bold; margin-bottom: 20px; font-size: 14px;">Requester's Signature</div>

                <div class="signature-section">
                    <div class="sign-left">
                        <div class="sign-name-bold"><?php echo $formatted_name; ?></div>
                        <div class="ctc-lines">
                            CTC No. : __________________<br>
                            Issued on : _________________<br>
                            At General Trias City, Cavite<br>
                            Note : Not valid without official seal/erasure.
                        </div>
                        
                        <div style="margin-top: 40px; text-align: center; width: 80%;">
                            <div style="font-weight: bold; text-decoration: underline;">JEREMIAH-ANGELICA B. CAHINHINAN</div>
                            <div style="color: #0070c0; font-weight: bold; font-size: 10px;">BARANGAY SECRETARY</div>
                        </div>
                    </div>
                    
                    <div class="sign-right">
                        <div style="font-weight: 900; font-size: 16px; font-family: 'Arial Black', Arial, sans-serif; text-decoration: underline;">RENATO S. ALMANZOR</div>
                        <div style="color: #0070c0; font-weight: bold; font-size: 12px;">Punong Barangay</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

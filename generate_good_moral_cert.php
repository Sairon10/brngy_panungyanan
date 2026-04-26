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

$formatted_name = $prefix . strtoupper($request['full_name']);

// Format dates
$issue_day = date('jS');
$issue_month = date('F');
$issue_year = date('Y');
$issue_date_formatted = date('F d, Y');

$purpose = htmlspecialchars(trim($request['purpose'] ?: 'whatever legal purpose this may served'));
$address = htmlspecialchars($request['address'] ?: 'N/A');

// Determine pronoun
$pronoun = (strtolower($request['sex']) === 'female') ? 'She' : 'He';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Good Moral Character - <?php echo htmlspecialchars($request['full_name']); ?></title>
    <style>
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
            @page { margin: 0.5in; }
        }

        body {
            font-family: "Times New Roman", Times, serif;
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
            align-items: center;
            margin-bottom: 10px;
        }

        .header-text {
            text-align: center;
            flex-grow: 1;
        }
        .header-text h3 { margin: 0; font-size: 14px; font-weight: normal; }

        .doc-title {
            color: #000;
            font-weight: bold;
            font-size: 24px;
            margin-top: 20px;
            text-transform: uppercase;
            margin-bottom: 40px;
            text-align: center;
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
            font-size: 16px;
            text-align: justify;
            margin-bottom: 40px;
            line-height: 1.8;
        }

        .to-whom {
            font-weight: normal;
            margin-bottom: 30px;
        }

        .indent { text-indent: 40px; }
        
        .name-highlight {
            font-weight: bold;
            text-decoration: underline;
        }

        /* Signatories */
        .signatories {
            margin-top: 50px;
            display: flex;
            justify-content: flex-end;
            flex-direction: column;
        }

        .sign-block { margin-bottom: 20px; text-align: center;}
        .sign-name { font-weight: bold; text-decoration: none; text-transform: uppercase; }
        .sign-title { font-weight: normal; }

        /* Footer section */
        .footer-section {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .footer-left { width: 45%; }
        .footer-right { width: 45%; display: flex; flex-direction: column; align-items: center; }

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

    <div class="container">
        <div class="header-logos">
            <!-- Left Logo -->
            <div style="width: 25%; text-align: center;">
                <img src="public/img/panungyanan logo.jpg" alt="Barangay Logo" style="width: 110px; height: auto;">
            </div>

            <!-- Top Center Text -->
            <div class="header-text" style="width: 45%;">
                <h3>Republic of the Philippines</h3>
                <h3>Region IV-A (CALABARZON)</h3>
                <h3>Province of Cavite</h3>
                <h3>City of General Trias</h3>
            </div>

            <!-- Right Logos -->
            <div style="width: 30%; display: flex; justify-content: flex-start; align-items: center; gap: 15px;">
                <img src="public/img/gentri.jpg" alt="City Logo" style="width: 100px; height: auto;">
                <img src="public/img/bagongpilipinas.jpg" alt="Bagong Pilipinas Logo" style="height: 85px; object-fit: contain;">
            </div>
        </div>

        <!-- Blue & Green Heading -->
        <div style="text-align: center;">
            <h1 style="color: #0070c0; font-size: 26px; margin: 10px 0 5px 0; font-family: 'Times New Roman', Times, serif;">BARANGAY PANUNGYANAN</h1>
            <div style="color: #00b050; font-size: 22px; font-weight: bold; text-transform: uppercase; font-family: 'Times New Roman', Times, serif;">
                OFFICE OF THE PUNONGBARANGAY
            </div>
            <hr style="border: 0; border-bottom: 2px solid black; margin-top: 5px; margin-bottom: 20px;">
        </div>

        <img src="public/img/panungyanan logo.jpg" class="watermark" alt="Watermark" style="opacity: 0.15; width: 60%;">

        <div class="doc-title">BARANGAY CERTIFICATE</div>

        <div class="content">
            <div class="to-whom">To Whom It May Concern:</div>
            
            <p class="indent">
                <span style="text-decoration: underline;">This is</span> to certify that <span class="name-highlight"><?php echo $formatted_name; ?></span> of legal age and residing at <?php echo $address; ?> is known to be the law abiding citizen with good moral character and not connected to any subversive organization of this Barangay. <?php echo $pronoun; ?> is a certified accredited member of our Barangay.
            </p>
            
            <p class="indent">
                This certification is being issued for <?php echo $purpose; ?> <?php echo (strpos(strtolower($purpose), 'purpose') === false) ? 'for whatever legal purpose this may served.' : ''; ?>
            </p>
            
            <p class="indent" style="margin-top: 30px;">
                Given this <span style="text-decoration: underline;"><?php echo $issue_day; ?></span> day of <span style="text-decoration: underline;"><?php echo $issue_month; ?></span> <?php echo $issue_year; ?>, at the Barangay Hall of Panungyanan, City of General Trias, Cavite.
            </p>
        </div>

        <div class="footer-section">
            <div class="footer-left">
            </div>
            
            <div class="footer-right">
                <div style="margin-top: 0px;">
                    Signed by:<br><br><br>
                    <div class="sign-block">
                        <div class="sign-name">Hon. RENATO S. ALMANZOR</div>
                        <div class="sign-title">Punong Barangay</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

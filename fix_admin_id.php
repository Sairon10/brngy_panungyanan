<?php
$html = file_get_contents('id_print.php');

// Extract the <style> to </html> part
$parts = explode('<style>', $html);
$body_html = '<style>' . $parts[1];

// We need to remove the top nav / layout HTML and replace it with a clean standalone HTML structure.
// id_print.php includes user_dashboard_header.php which has the nav.
// The standalone print should just be a clean HTML page.
$standalone_html = <<<EOT
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident ID Card</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    @media print {
        @page { size: landscape; margin: 0; }
        body { margin: 0; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        .no-print { display: none !important; }
    }
    body { background: #f0f2f5; display: flex; flex-direction: column; align-items: center; min-height: 100vh; padding-top: 40px; }
    .print-btn { padding: 12px 24px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; font-size: 16px; margin-bottom: 30px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    .print-btn:hover { background: #0056b3; }
EOT;

// Extract just the CSS rules from the `<style>` block
preg_match('/<style>(.*?)<\/style>/is', $html, $matches);
if (isset($matches[1])) {
    // Remove the @media print from the extracted CSS to avoid conflicts with ours, or just keep it
    $css = $matches[1];
    $standalone_html .= $css . "\n</style>\n</head>\n<body>\n";
} else {
    $standalone_html .= "\n</style>\n</head>\n<body>\n";
}

$standalone_html .= <<<EOT
    <button class="print-btn no-print" onclick="window.print()"><i class="fas fa-print me-2"></i>Print ID Card</button>
EOT;

// Extract the id-card-container
preg_match('/<div class="id-card-container">(.*?)<\/div>\s*<!--/is', $html, $container_match); // This regex might fail, let's just use string functions

$start_pos = strpos($html, '<div class="id-card-container">');
$end_pos = strpos($html, '<script>', $start_pos);

if ($start_pos !== false && $end_pos !== false) {
    $card_html = substr($html, $start_pos, $end_pos - $start_pos);
    $standalone_html .= "\n" . $card_html . "\n";
}

$standalone_html .= <<<EOT
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const idCard = document.getElementById('idCard');
            if(idCard) {
                idCard.addEventListener('click', function () {
                    this.classList.toggle('flipped');
                });
            }
        });
    </script>
</body>
</html>
EOT;

$php_logic = <<<EOT
<?php
require_once __DIR__ . '/config.php';

if (!is_logged_in() || !is_admin()) {
    die('Access denied. Only administrators can generate generic PDFs.');
}

\$pdo = get_db_connection();
\$request_id = (int)(\$_GET['id'] ?? 0);

if (\$request_id <= 0) {
    die('Invalid request ID');
}

// Fetch the document request
\$stmt = \$pdo->prepare("SELECT * FROM document_requests WHERE id = ? AND doc_type = 'Resident ID'");
\$stmt->execute([\$request_id]);
\$req = \$stmt->fetch();

if (!\$req) {
    die('Document request not found or not a Resident ID request.');
}

if (\$req['status'] !== 'approved' && \$req['status'] !== 'released') {
    die('Document request not yet approved.');
}

\$resident = [];
\$is_family_member = (\$req['requestor_type'] === 'family_member' && !empty(\$req['family_member_id']));
\$user_id = \$req['user_id'];

// Get Head address and phone regardless (fallback)
\$head_stmt = \$pdo->prepare('SELECT r.address, r.phone FROM residents r WHERE user_id = ?');
\$head_stmt->execute([\$user_id]);
\$head_data = \$head_stmt->fetch();

if (\$is_family_member) {
    \$fm_stmt = \$pdo->prepare('SELECT * FROM family_members WHERE id = ?');
    \$fm_stmt->execute([\$req['family_member_id']]);
    \$resident = \$fm_stmt->fetch();
    if (!\$resident) die('Family member not found.');
    
    // Fallbacks
    \$resident['address'] = !empty(\$resident['address']) ? \$resident['address'] : (\$head_data['address'] ?? '');
    \$resident['phone'] = !empty(\$resident['phone']) ? \$resident['phone'] : (\$head_data['phone'] ?? '');
    \$resident['birth_place'] = ''; // fm doesn't have birthplace usually
    
    // Construct name
    \$resident['first_name'] = \$resident['first_name'] ?? '';
    \$resident['middle_name'] = \$resident['middle_name'] ?? '';
    \$resident['last_name'] = \$resident['last_name'] ?? '';
    
    \$uid_prefix = date('Y', strtotime(\$resident['created_at'] ?? 'now')) . '-F' . str_pad((string)\$req['family_member_id'], 4, '0', STR_PAD_LEFT);
    \$qr_id_param = 'fm_id=' . \$req['family_member_id'];
} else {
    // Self
    \$self_stmt = \$pdo->prepare('
        SELECT u.first_name, u.last_name, u.middle_name,
               r.address, r.birthdate, r.avatar, r.phone, r.birth_place, r.created_at
        FROM users u 
        LEFT JOIN residents r ON r.user_id = u.id 
        WHERE u.id = ?
    ');
    \$self_stmt->execute([\$user_id]);
    \$resident = \$self_stmt->fetch();
    if (!\$resident) die('Resident data not found.');
    
    \$uid_prefix = date('Y', strtotime(\$resident['created_at'] ?? 'now')) . '-' . str_pad((string)\$user_id, 4, '0', STR_PAD_LEFT);
    \$qr_id_param = 'id=' . \$user_id;
}

\$uid = \$uid_prefix;

// Get base URL for QR code
\$protocol = isset(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
\$host = \$_SERVER['HTTP_HOST'];
\$base_url = \$protocol . '://' . \$host . dirname(\$_SERVER['PHP_SELF']);
\$qr_verify_url = rtrim(\$base_url, '/') . '/qr_verify.php?' . \$qr_id_param;

// Generate QR code URL
\$qr_code_url = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode(\$qr_verify_url);

\$valid_until = date('F d, Y', strtotime(\$req['created_at'] . ' +1 year'));
\$req_date = \$req['created_at'];

?>
EOT;

$final_content = $php_logic . "\n" . $standalone_html;
file_put_contents('generate_resident_id_card.php', $final_content);
echo "Successfully updated generate_resident_id_card.php";
?>

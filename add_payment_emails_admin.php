<?php
$file = 'admin/payments.php';
$c = file_get_contents($file);

// 1. Add require statements for services
if (strpos($c, "require_once __DIR__ . '/../includes/email_service.php';") === false) {
    $c = str_replace(
        "\$pdo = get_db_connection();",
        "\$pdo = get_db_connection();\nrequire_once __DIR__ . '/../includes/email_service.php';\nrequire_once __DIR__ . '/../includes/sms_service.php';",
        $c
    );
}

// 2. Fetch resident info and send email/sms
// We will look for: $message = $pay_action === 'confirmed'
// and the refunded block: $message = 'Payment has been marked as <strong>refunded</strong> successfully.';
// Actually, it's easier to put the email logic right after the DB updates.

// Let's create a helper block to inject.
$injection = <<<'EOD'
            
            // --- Send Notification ---
            // Fetch user info and payment details
            $table = $pay_type === 'clearance' ? 'barangay_clearances' : 'document_requests';
            $doc_type_name = $pay_type === 'clearance' ? 'Barangay Clearance' : 'Document Request';
            
            // For document requests, we can fetch the actual doc_type
            $docTypeCol = $pay_type === 'clearance' ? "'Barangay Clearance' AS doc_type" : "p.doc_type";
            
            $stmt = $pdo->prepare("
                SELECT u.email, u.full_name, u.first_name, r.phone,
                       p.payment_reference_no, p.payment_amount_paid,
                       $docTypeCol
                FROM $table p
                JOIN users u ON u.id = p.user_id
                LEFT JOIN residents r ON r.user_id = u.id
                WHERE p.id = ? LIMIT 1
            ");
            $stmt->execute([$pay_id]);
            $paymentInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($paymentInfo) {
                $paymentData = [
                    'resident_name' => $paymentInfo['first_name'] ?: ($paymentInfo['full_name'] ?: 'Resident'),
                    'reference_no'  => $paymentInfo['payment_reference_no'],
                    'amount'        => $paymentInfo['payment_amount_paid'],
                    'doc_type'      => $paymentInfo['doc_type'],
                    'notes'         => $pay_action === 'refunded' ? ($refund_notes ?? '') : '',
                    'admin_refund_amount' => $pay_action === 'refunded' ? ($refund_amount ?? null) : null
                ];
                
                if (!empty($paymentInfo['email'])) {
                    send_payment_status_email($paymentInfo['email'], $pay_action, $paymentData);
                }
                if (!empty($paymentInfo['phone'])) {
                    send_payment_status_sms($paymentInfo['phone'], $pay_action, $paymentData);
                }
            }
            // --- End Notification ---
EOD;

// We need to inject this when refunded is successful:
// $message = 'Payment has been marked as <strong>refunded</strong> successfully.';
if (strpos($c, "// --- Send Notification ---") === false) {
    $c = str_replace(
        "\$message = 'Payment has been marked as <strong>refunded</strong> successfully.';",
        "\$message = 'Payment has been marked as <strong>refunded</strong> successfully.';\n" . $injection,
        $c
    );
    
    // And when confirmed/rejected:
    // $message_type = $pay_action === 'confirmed' ? 'success' : 'danger';
    $c = str_replace(
        "\$message_type = \$pay_action === 'confirmed' ? 'success' : 'danger';",
        "\$message_type = \$pay_action === 'confirmed' ? 'success' : 'danger';\n" . $injection,
        $c
    );
}

file_put_contents($file, $c);
echo "Updated admin/payments.php\n";

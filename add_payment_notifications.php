<?php
// includes/email_service.php
$email_file = __DIR__ . '/includes/email_service.php';
$email_content = file_get_contents($email_file);

if (strpos($email_content, 'function send_payment_status_email') === false) {
    $new_email_func = <<<'EOD'

/**
 * Send payment status update email
 */
function send_payment_status_email($email, $status, $paymentData) {
    $subject = "Payment Status Update: " . ucfirst($status);
    
    // Status specific styling and messaging
    $statusColor = '#1e40af'; // default blue
    $statusIcon = '💳';
    $statusMessage = '';
    
    switch(strtolower($status)) {
        case 'confirmed':
            $statusColor = '#15803d'; // green
            $statusIcon = '✅';
            $statusMessage = 'We are pleased to inform you that your payment has been confirmed.';
            break;
        case 'rejected':
            $statusColor = '#b91c1c'; // red
            $statusIcon = '❌';
            $statusMessage = 'Unfortunately, your payment has been rejected. Please review the details below or contact the barangay office.';
            break;
        case 'refunded':
            $statusColor = '#475569'; // slate
            $statusIcon = '🔄';
            $statusMessage = 'Your payment has been successfully refunded.';
            break;
    }
    
    $amount = isset($paymentData['amount']) ? '₱' . number_format((float)$paymentData['amount'], 2) : 'N/A';
    $refNo = htmlspecialchars($paymentData['reference_no'] ?? 'N/A');
    $docType = htmlspecialchars($paymentData['doc_type'] ?? 'Document');
    $notes = isset($paymentData['notes']) ? htmlspecialchars($paymentData['notes']) : '';
    
    $htmlContent = "
    <div style='text-align: center; margin-bottom: 30px;'>
        <div style='font-size: 48px; margin-bottom: 15px;'>{$statusIcon}</div>
        <h2 style='color: {$statusColor}; margin: 0; font-size: 24px;'>Payment {$status}</h2>
    </div>
    
    <p style='color: #475569; font-size: 16px; line-height: 1.6;'>
        Hello <strong>" . htmlspecialchars($paymentData['resident_name'] ?? 'Resident') . "</strong>,<br><br>
        {$statusMessage}
    </p>
    
    <div style='background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin: 25px 0;'>
        <h3 style='margin-top: 0; color: #1e293b; font-size: 16px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;'>Payment Details</h3>
        
        <table style='width: 100%; border-collapse: collapse;'>
            <tr>
                <td style='padding: 8px 0; color: #64748b; font-weight: 500; width: 40%;'>Document:</td>
                <td style='padding: 8px 0; color: #0f172a; font-weight: 600;'>{$docType}</td>
            </tr>
            <tr>
                <td style='padding: 8px 0; color: #64748b; font-weight: 500;'>Reference No:</td>
                <td style='padding: 8px 0; color: #0f172a; font-weight: 600;'>{$refNo}</td>
            </tr>
            <tr>
                <td style='padding: 8px 0; color: #64748b; font-weight: 500;'>Amount:</td>
                <td style='padding: 8px 0; color: #0f172a; font-weight: 600;'>{$amount}</td>
            </tr>";
            
    if ($status === 'refunded' && !empty($paymentData['admin_refund_amount'])) {
        $refundAmount = '₱' . number_format((float)$paymentData['admin_refund_amount'], 2);
        $htmlContent .= "
            <tr>
                <td style='padding: 8px 0; color: #64748b; font-weight: 500;'>Refund Amount:</td>
                <td style='padding: 8px 0; color: #0f172a; font-weight: 600; color: #15803d;'>{$refundAmount}</td>
            </tr>";
    }
            
    if (!empty($notes)) {
        $htmlContent .= "
            <tr>
                <td style='padding: 8px 0; color: #64748b; font-weight: 500; vertical-align: top;'>Notes:</td>
                <td style='padding: 8px 0; color: #0f172a; font-weight: 600;'><em>{$notes}</em></td>
            </tr>";
    }

    $htmlContent .= "
        </table>
    </div>
    
    <div style='text-align: center; margin-top: 30px;'>
        <a href='http://localhost/payments.php' style='display: inline-block; background-color: #0d9488; color: white; text-decoration: none; padding: 12px 25px; border-radius: 6px; font-weight: 600;'>View Payment History</a>
    </div>";

    $textContent = "Payment Status Update: " . ucfirst($status) . "\n\n";
    $textContent .= "Hello " . ($paymentData['resident_name'] ?? 'Resident') . ",\n\n";
    $textContent .= $statusMessage . "\n\n";
    $textContent .= "Payment Details:\n";
    $textContent .= "- Document: {$docType}\n";
    $textContent .= "- Reference No: {$refNo}\n";
    $textContent .= "- Amount: {$amount}\n";
    if ($status === 'refunded' && !empty($paymentData['admin_refund_amount'])) {
        $textContent .= "- Refund Amount: ₱" . number_format((float)$paymentData['admin_refund_amount'], 2) . "\n";
    }
    if (!empty($notes)) {
        $textContent .= "- Notes: {$notes}\n";
    }
    
    return send_email($email, $subject, $htmlContent, $textContent);
}
EOD;

    // Insert before the last closing brace or at the end
    $email_content .= $new_email_func;
    file_put_contents($email_file, $email_content);
    echo "Added send_payment_status_email\n";
}

// includes/sms_service.php
$sms_file = __DIR__ . '/includes/sms_service.php';
$sms_content = file_get_contents($sms_file);

if (strpos($sms_content, 'function send_payment_status_sms') === false) {
    $new_sms_func = <<<'EOD'

/**
 * Send payment status update SMS
 */
function send_payment_status_sms($phoneNumber, $status, $paymentData) {
    if (empty($phoneNumber)) {
        return ['success' => false, 'error' => 'No phone number provided'];
    }

    $refNo = $paymentData['reference_no'] ?? 'N/A';
    $docType = $paymentData['doc_type'] ?? 'Document';
    
    $message = "Brgy Panungyanan\n";
    
    switch(strtolower($status)) {
        case 'confirmed':
            $message .= "Your payment for {$docType} (Ref: {$refNo}) has been CONFIRMED. Thank you!";
            break;
        case 'rejected':
            $message .= "Your payment for {$docType} (Ref: {$refNo}) was REJECTED. Please check your account for details.";
            break;
        case 'refunded':
            $message .= "Your payment for {$docType} (Ref: {$refNo}) has been REFUNDED.";
            if (!empty($paymentData['admin_refund_amount'])) {
                $message .= " Amount: PHP " . number_format((float)$paymentData['admin_refund_amount'], 2);
            }
            break;
        default:
            $message .= "Your payment status for {$docType} is now: " . strtoupper($status) . ".";
    }
    
    if (!empty($paymentData['notes'])) {
        $notes = $paymentData['notes'];
        if (strlen($notes) > 30) {
            $notes = substr($notes, 0, 27) . '...';
        }
        $message .= "\nNote: " . $notes;
    }
    
    return send_sms($phoneNumber, $message);
}
EOD;

    $sms_content .= $new_sms_func;
    file_put_contents($sms_file, $sms_content);
    echo "Added send_payment_status_sms\n";
}

echo "Done adding functions\n";

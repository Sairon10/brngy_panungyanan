<?php
$email_file = 'includes/email_service.php';
$email_c = file_get_contents($email_file);

// Replace email html amount part
$old_email_html = <<<EOD
            <tr>
                <td style='padding: 8px 0; color: #64748b; font-weight: 500;'>Amount:</td>
                <td style='padding: 8px 0; color: #0f172a; font-weight: 600;'>{\$amount}</td>
            </tr>";
EOD;

$new_email_html = <<<EOD
            <tr>
                <td style='padding: 8px 0; color: #64748b; font-weight: 500;'>Amount Due:</td>
                <td style='padding: 8px 0; color: #0f172a; font-weight: 600;'>{\$amountDue}</td>
            </tr>
            <tr>
                <td style='padding: 8px 0; color: #64748b; font-weight: 500;'>Amount Paid:</td>
                <td style='padding: 8px 0; color: #0f172a; font-weight: 600;'>{\$amountPaid}</td>
            </tr>";
EOD;
$email_c = str_replace($old_email_html, $new_email_html, $email_c);

// Replace email variables
$old_email_vars = <<<EOD
    \$amount = isset(\$paymentData['amount']) ? '₱' . number_format((float)\$paymentData['amount'], 2) : 'N/A';
EOD;
$new_email_vars = <<<EOD
    \$amountDue = isset(\$paymentData['amount_due']) ? '₱' . number_format((float)\$paymentData['amount_due'], 2) : 'N/A';
    \$amountPaid = isset(\$paymentData['amount_paid']) ? '₱' . number_format((float)\$paymentData['amount_paid'], 2) : 'N/A';
EOD;
$email_c = str_replace($old_email_vars, $new_email_vars, $email_c);

// Replace email text content
$old_email_text = <<<EOD
    \$textContent .= "- Amount: {\$amount}\\n";
EOD;
$new_email_text = <<<EOD
    \$textContent .= "- Amount Due: {\$amountDue}\\n";
    \$textContent .= "- Amount Paid: {\$amountPaid}\\n";
EOD;
$email_c = str_replace($old_email_text, $new_email_text, $email_c);

file_put_contents($email_file, $email_c);


// Update SMS Service
$sms_file = 'includes/sms_service.php';
$sms_c = file_get_contents($sms_file);

// Find the base message construction in send_payment_status_sms
// We will replace "Amount: PHP ..." if it existed, but we didn't have amount in SMS by default, only in refund.
// Let's just append Amount Due and Amount Paid if they exist.
$old_sms_ref = <<<EOD
    \$refNo = \$paymentData['reference_no'] ?? 'N/A';
    \$docType = \$paymentData['doc_type'] ?? 'Document';
EOD;
$new_sms_ref = <<<EOD
    \$refNo = \$paymentData['reference_no'] ?? 'N/A';
    \$docType = \$paymentData['doc_type'] ?? 'Document';
    \$amountDue = isset(\$paymentData['amount_due']) ? number_format((float)\$paymentData['amount_due'], 2) : 'N/A';
    \$amountPaid = isset(\$paymentData['amount_paid']) ? number_format((float)\$paymentData['amount_paid'], 2) : 'N/A';
EOD;
$sms_c = str_replace($old_sms_ref, $new_sms_ref, $sms_c);

// For Confirmed:
$old_sms_confirmed = <<<EOD
        case 'confirmed':
            \$message .= "Your payment for {\$docType} (Ref: {\$refNo}) has been CONFIRMED. Thank you!";
            break;
EOD;
$new_sms_confirmed = <<<EOD
        case 'confirmed':
            \$message .= "Your payment for {\$docType} (Ref: {\$refNo}) has been CONFIRMED. Amount Due: P{\$amountDue}, Paid: P{\$amountPaid}. Thank you!";
            break;
EOD;
$sms_c = str_replace($old_sms_confirmed, $new_sms_confirmed, $sms_c);

// For Rejected:
$old_sms_rejected = <<<EOD
        case 'rejected':
            \$message .= "Your payment for {\$docType} (Ref: {\$refNo}) was REJECTED. Please check your account for details.";
            break;
EOD;
$new_sms_rejected = <<<EOD
        case 'rejected':
            \$message .= "Your payment for {\$docType} (Ref: {\$refNo}) was REJECTED. Paid: P{\$amountPaid}. Please check your account.";
            break;
EOD;
$sms_c = str_replace($old_sms_rejected, $new_sms_rejected, $sms_c);

file_put_contents($sms_file, $sms_c);

echo "Updated email and sms services.\n";

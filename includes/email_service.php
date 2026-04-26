<?php
/**
 * Email Service using Resend API
 * 
 * This service handles sending emails via Resend when document request statuses are updated.
 */

// Load environment variables from .env file if it exists
function load_env_file($path) {
    if (!file_exists($path)) {
        return;
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue; // Skip comments
        }
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Load .env file
load_env_file(__DIR__ . '/../.env');

/**
 * Send email using Resend API
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $htmlContent HTML email content
 * @param string|null $textContent Plain text email content (optional)
 * @return array Result with 'success' and 'message' keys
 */
function send_resend_email($to, $subject, $htmlContent, $textContent = null) {
    $apiKey = getenv('RESEND_API_KEY') ?: $_ENV['RESEND_API_KEY'] ?? '';
    $fromEmail = getenv('RESEND_FROM_EMAIL') ?: $_ENV['RESEND_FROM_EMAIL'] ?? 'noreply@barangay.local';
    $fromName = getenv('RESEND_FROM_NAME') ?: $_ENV['RESEND_FROM_NAME'] ?? 'Barangay Panungyanan';
    
    if (empty($apiKey)) {
        return [
            'success' => false,
            'message' => 'Resend API key is not configured. Please set RESEND_API_KEY in your .env file.'
        ];
    }
    
    if (empty($to)) {
        return [
            'success' => false,
            'message' => 'Recipient email address is required.'
        ];
    }
    
    // Prepare email payload
    $payload = [
        'from' => $fromName . ' <' . $fromEmail . '>',
        'to' => [$to],
        'subject' => $subject,
        'html' => $htmlContent
    ];
    
    if ($textContent) {
        $payload['text'] = $textContent;
    }
    
    // Send via Resend API
    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return [
            'success' => false,
            'message' => 'CURL Error: ' . $error
        ];
    }
    
    $responseData = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return [
            'success' => true,
            'message' => 'Email sent successfully',
            'data' => $responseData
        ];
    } else {
        $errorMsg = $responseData['message'] ?? 'Unknown error occurred';
        return [
            'success' => false,
            'message' => 'Resend API Error: ' . $errorMsg,
            'data' => $responseData
        ];
    }
}

/**
 * Generate email HTML template for request status updates
 * 
 * @param string $status Request status (pending, approved, rejected, released)
 * @param array $requestData Request information
 * @return string HTML email content
 */
function generate_status_email_html($status, $requestData) {
    $barangayName = getenv('BARANGAY_NAME') ?: $_ENV['BARANGAY_NAME'] ?? 'Barangay Panungyanan';
    $barangayAddress = getenv('BARANGAY_ADDRESS') ?: $_ENV['BARANGAY_ADDRESS'] ?? '';
    $barangayPhone = getenv('BARANGAY_PHONE') ?: $_ENV['BARANGAY_PHONE'] ?? '';
    
    $requestType = $requestData['type'] === 'clearance' ? 'Barangay Clearance' : htmlspecialchars($requestData['doc_type'] ?? 'Document');
    $requestNumber = htmlspecialchars($requestData['number'] ?? 'N/A');
    $purpose = htmlspecialchars($requestData['purpose'] ?? '');
    $notes = !empty($requestData['notes']) ? htmlspecialchars($requestData['notes']) : null;
    $residentName = htmlspecialchars($requestData['resident_name'] ?? 'Resident');
    $price = isset($requestData['price']) ? (float)$requestData['price'] : 0.00;
    
    // Status-specific content
    $statusConfig = [
        'pending' => [
            'title' => 'Request Received',
            'icon' => '⏳',
            'color' => '#f59e0b',
            'message' => 'Your request has been received and is currently being reviewed by our office.',
            'action' => 'We will notify you once your request has been processed.'
        ],
        'approved' => [
            'title' => 'Document Ready for Pickup',
            'icon' => '✅',
            'color' => '#10b981',
            'message' => 'Great news! Your request has been approved and your document is ready for pickup.',
            'action' => 'Please visit the barangay office to claim your document. Don\'t forget to bring a valid ID for verification.'
        ],
        'rejected' => [
            'title' => 'Request Rejected',
            'icon' => '❌',
            'color' => '#ef4444',
            'message' => 'We regret to inform you that your request has been rejected.',
            'action' => 'Please contact the barangay office for more information or to submit a new request.'
        ],
        'released' => [
            'title' => 'Document Successfully Released',
            'icon' => '📄',
            'color' => '#3b82f6',
            'message' => 'Your document has been successfully released and picked up.',
            'action' => 'Thank you for using our barangay services. If you have any further questions, feel free to contact us.'
        ]
    ];
    
    $config = $statusConfig[$status] ?? $statusConfig['pending'];
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . htmlspecialchars($config['title']) . '</title>
    </head>
    <body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #f3f4f6;">
        <table role="presentation" style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 40px 20px;">
                    <table role="presentation" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <!-- Header -->
                        <tr>
                            <td style="background: linear-gradient(135deg, #0f766e 0%, #14b8a6 100%); padding: 30px; text-align: center;">
                                <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 600;">' . htmlspecialchars($barangayName) . '</h1>
                            </td>
                        </tr>
                        
                        <!-- Content -->
                        <tr>
                            <td style="padding: 40px 30px;">
                                <div style="text-align: center; margin-bottom: 30px;">
                                    <div style="font-size: 48px; margin-bottom: 15px;">' . $config['icon'] . '</div>
                                    <h2 style="margin: 0; color: ' . $config['color'] . '; font-size: 28px; font-weight: 600;">' . htmlspecialchars($config['title']) . '</h2>
                                </div>
                                
                                <p style="margin: 0 0 20px; color: #374151; font-size: 16px; line-height: 1.6;">
                                    Dear <strong>' . $residentName . '</strong>,
                                </p>
                                
                                <p style="margin: 0 0 20px; color: #374151; font-size: 16px; line-height: 1.6;">
                                    ' . $config['message'] . '
                                </p>
                                
                                <!-- Request Details -->
                                <div style="background-color: #f9fafb; border-left: 4px solid ' . $config['color'] . '; padding: 20px; margin: 25px 0; border-radius: 4px;">
                                    <table role="presentation" style="width: 100%; border-collapse: collapse;">
                                        <tr>
                                            <td style="padding: 5px 0; color: #6b7280; font-size: 14px; width: 120px;"><strong>Request Type:</strong></td>
                                            <td style="padding: 5px 0; color: #111827; font-size: 14px;">' . $requestType . '</td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 5px 0; color: #6b7280; font-size: 14px;"><strong>Request Number:</strong></td>
                                            <td style="padding: 5px 0; color: #111827; font-size: 14px;">' . $requestNumber . '</td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 5px 0; color: #6b7280; font-size: 14px;"><strong>Purpose:</strong></td>
                                            <td style="padding: 5px 0; color: #111827; font-size: 14px;">' . $purpose . '</td>
                                        </tr>
                                        ' . ($price > 0 ? '
                                        <tr>
                                            <td style="padding: 5px 0; color: #6b7280; font-size: 14px;"><strong>Price:</strong></td>
                                            <td style="padding: 5px 0; color: #111827; font-size: 14px; font-weight: 600;">
                                                ₱' . number_format($price, 2) . '
                                            </td>
                                        </tr>
                                        ' : '') . '
                                        <tr>
                                            <td style="padding: 5px 0; color: #6b7280; font-size: 14px;"><strong>Status:</strong></td>
                                            <td style="padding: 5px 0;">
                                                <span style="display: inline-block; background-color: ' . $config['color'] . '; color: #ffffff; padding: 4px 12px; border-radius: 4px; font-size: 12px; font-weight: 600; text-transform: uppercase;">
                                                    ' . htmlspecialchars(ucfirst($status)) . '
                                                </span>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                
                                ' . ($notes ? '
                                <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; border-radius: 4px;">
                                    <p style="margin: 0; color: #92400e; font-size: 14px; line-height: 1.6;">
                                        <strong>Note:</strong> ' . $notes . '
                                    </p>
                                </div>
                                ' : '') . '
                                
                                <p style="margin: 20px 0 0; color: #374151; font-size: 16px; line-height: 1.6;">
                                    ' . $config['action'] . '
                                </p>
                                
                                ' . ($status === 'approved' ? '
                                <div style="margin-top: 30px; padding: 20px; background-color: #eff6ff; border-radius: 4px; border: 1px solid #bfdbfe;">
                                    ' . ($price > 0 ? '
                                    <div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #bfdbfe;">
                                        <p style="margin: 0 0 5px; color: #1e40af; font-size: 14px; font-weight: 600;">💰 Payment Required:</p>
                                        <p style="margin: 0; color: #1e40af; font-size: 16px; font-weight: 700;">
                                            ₱' . number_format($price, 2) . '
                                        </p>
                                        <p style="margin: 5px 0 0; color: #64748b; font-size: 12px;">
                                            Please prepare the exact amount when claiming your document.
                                        </p>
                                    </div>
                                    ' : '') . '
                                    <p style="margin: 0 0 10px; color: #1e40af; font-size: 14px; font-weight: 600;">📋 What to bring:</p>
                                    <ul style="margin: 0; padding-left: 20px; color: #1e40af; font-size: 14px; line-height: 1.8;">
                                        <li>Valid government-issued ID</li>
                                        ' . ($price > 0 ? '<li>Payment of ₱' . number_format($price, 2) . '</li>' : '') . '
                                        <li>Proof of residency (if required)</li>
                                    </ul>
                                    <div style="margin-top: 15px; padding: 12px; background-color: #fef3c7; border-left: 3px solid #f59e0b; border-radius: 4px;">
                                        <p style="margin: 0; color: #92400e; font-size: 13px; line-height: 1.6;">
                                            <strong>⚠️ Important:</strong> If someone else will claim the document on your behalf, they must bring an <strong>authorization letter with your signature</strong>. The authorized person must also present a valid ID.
                                        </p>
                                    </div>
                                </div>
                                ' : '') . '
                            </td>
                        </tr>
                        
                        <!-- Footer -->
                        <tr>
                            <td style="background-color: #f9fafb; padding: 30px; text-align: center; border-top: 1px solid #e5e7eb;">
                                <p style="margin: 0 0 10px; color: #6b7280; font-size: 14px;">
                                    <strong>' . htmlspecialchars($barangayName) . '</strong>
                                </p>
                                ' . ($barangayAddress ? '<p style="margin: 0 0 5px; color: #9ca3af; font-size: 12px;">' . htmlspecialchars($barangayAddress) . '</p>' : '') . '
                                ' . ($barangayPhone ? '<p style="margin: 0; color: #9ca3af; font-size: 12px;">Phone: ' . htmlspecialchars($barangayPhone) . '</p>' : '') . '
                                <p style="margin: 15px 0 0; color: #9ca3af; font-size: 12px;">
                                    This is an automated email. Please do not reply to this message.
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>';
    
    return $html;
}

/**
 * Send status update email to resident
 * 
 * @param string $email Recipient email
 * @param string $status Request status
 * @param array $requestData Request information
 * @return array Result with 'success' and 'message' keys
 */
function send_request_status_email($email, $status, $requestData) {
    if (empty($email)) {
        return [
            'success' => false,
            'message' => 'Recipient email is required'
        ];
    }
    
    $statusLabels = [
        'pending' => 'Request Received',
        'approved' => 'Document Ready for Pickup',
        'rejected' => 'Request Rejected',
        'released' => 'Document Successfully Released'
    ];
    
    $subject = $statusLabels[$status] ?? 'Request Status Update';
    $htmlContent = generate_status_email_html($status, $requestData);
    
    return send_resend_email($email, $subject, $htmlContent);
}

/**
 * Generate password reset email HTML template
 * 
 * @param string $resetLink Full URL to reset password page with token
 * @param string $userName User's name (optional)
 * @return string HTML email content
 */
function generate_password_reset_email_html($resetLink, $userName = 'User') {
    $barangayName = getenv('BARANGAY_NAME') ?: $_ENV['BARANGAY_NAME'] ?? 'Barangay Panungyanan';
    $barangayAddress = getenv('BARANGAY_ADDRESS') ?: $_ENV['BARANGAY_ADDRESS'] ?? '';
    $barangayPhone = getenv('BARANGAY_PHONE') ?: $_ENV['BARANGAY_PHONE'] ?? '';
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Reset Your Password</title>
    </head>
    <body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #f3f4f6;">
        <table role="presentation" style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 40px 20px;">
                    <table role="presentation" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <!-- Header -->
                        <tr>
                            <td style="background: linear-gradient(135deg, #0f766e 0%, #14b8a6 100%); padding: 30px; text-align: center;">
                                <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 600;">' . htmlspecialchars($barangayName) . '</h1>
                            </td>
                        </tr>
                        
                        <!-- Content -->
                        <tr>
                            <td style="padding: 40px 30px;">
                                <div style="text-align: center; margin-bottom: 30px;">
                                    <div style="font-size: 48px; margin-bottom: 15px;">🔐</div>
                                    <h2 style="margin: 0; color: #0f766e; font-size: 28px; font-weight: 600;">Reset Your Password</h2>
                                </div>
                                
                                <p style="margin: 0 0 20px; color: #374151; font-size: 16px; line-height: 1.6;">
                                    Dear <strong>' . htmlspecialchars($userName) . '</strong>,
                                </p>
                                
                                <p style="margin: 0 0 20px; color: #374151; font-size: 16px; line-height: 1.6;">
                                    We received a request to reset your password for your account. Click the button below to create a new password:
                                </p>
                                
                                <!-- Reset Button -->
                                <div style="text-align: center; margin: 30px 0;">
                                    <a href="' . htmlspecialchars($resetLink) . '" style="display: inline-block; background: linear-gradient(135deg, #0f766e 0%, #14b8a6 100%); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 6px; font-weight: 600; font-size: 16px; box-shadow: 0 4px 6px rgba(15, 118, 110, 0.3);">
                                        Reset Password
                                    </a>
                                </div>
                                
                                <!-- Alternative Link -->
                                <div style="background-color: #f9fafb; padding: 20px; margin: 25px 0; border-radius: 4px; border-left: 4px solid #0f766e;">
                                    <p style="margin: 0 0 10px; color: #6b7280; font-size: 14px; font-weight: 600;">Or copy and paste this link:</p>
                                    <p style="margin: 0; color: #111827; font-size: 12px; word-break: break-all; font-family: monospace;">
                                        ' . htmlspecialchars($resetLink) . '
                                    </p>
                                </div>
                                
                                <p style="margin: 20px 0 0; color: #374151; font-size: 16px; line-height: 1.6;">
                                    This link will expire in <strong>1 hour</strong> for security reasons.
                                </p>
                                
                                <div style="margin-top: 30px; padding: 20px; background-color: #fef3c7; border-radius: 4px; border-left: 4px solid #f59e0b;">
                                    <p style="margin: 0; color: #92400e; font-size: 14px; line-height: 1.6;">
                                        <strong>⚠️ Security Notice:</strong> If you did not request a password reset, please ignore this email. Your password will remain unchanged.
                                    </p>
                                </div>
                            </td>
                        </tr>
                        
                        <!-- Footer -->
                        <tr>
                            <td style="background-color: #f9fafb; padding: 30px; text-align: center; border-top: 1px solid #e5e7eb;">
                                <p style="margin: 0 0 10px; color: #6b7280; font-size: 14px;">
                                    <strong>' . htmlspecialchars($barangayName) . '</strong>
                                </p>
                                ' . ($barangayAddress ? '<p style="margin: 0 0 5px; color: #9ca3af; font-size: 12px;">' . htmlspecialchars($barangayAddress) . '</p>' : '') . '
                                ' . ($barangayPhone ? '<p style="margin: 0; color: #9ca3af; font-size: 12px;">Phone: ' . htmlspecialchars($barangayPhone) . '</p>' : '') . '
                                <p style="margin: 15px 0 0; color: #9ca3af; font-size: 12px;">
                                    This is an automated email. Please do not reply to this message.
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>';
    
    return $html;
}

/**
 * Send password reset email
 * 
 * @param string $email Recipient email
 * @param string $resetLink Full URL to reset password page with token
 * @param string $userName User's name (optional)
 * @return array Result with 'success' and 'message' keys
 */
function send_password_reset_email($email, $resetLink, $userName = 'User') {
    if (empty($email)) {
        return [
            'success' => false,
            'message' => 'Recipient email is required'
        ];
    }
    
    $subject = 'Reset Your Password - Barangay Panungyanan';
    $htmlContent = generate_password_reset_email_html($resetLink, $userName);
    $textContent = "Dear {$userName},\n\nWe received a request to reset your password. Click the link below to create a new password:\n\n{$resetLink}\n\nThis link will expire in 1 hour.\n\nIf you did not request this, please ignore this email.";
    
    return send_resend_email($email, $subject, $htmlContent, $textContent);
}

/**
 * Generate ID verification email HTML template
 * 
 * @param string $status Verification status (verified, rejected)
 * @param array $residentData Resident information
 * @return string HTML email content
 */
function generate_id_verification_email_html($status, $residentData) {
    $barangayName = getenv('BARANGAY_NAME') ?: $_ENV['BARANGAY_NAME'] ?? 'Barangay Panungyanan';
    $barangayAddress = getenv('BARANGAY_ADDRESS') ?: $_ENV['BARANGAY_ADDRESS'] ?? '';
    $barangayPhone = getenv('BARANGAY_PHONE') ?: $_ENV['BARANGAY_PHONE'] ?? '';
    
    $residentName = htmlspecialchars($residentData['full_name'] ?? 'Resident');
    $notes = !empty($residentData['verification_notes']) ? htmlspecialchars($residentData['verification_notes']) : null;
    
    // Status-specific content
    $statusConfig = [
        'verified' => [
            'title' => 'ID Verification Approved',
            'icon' => '✅',
            'color' => '#10b981',
            'message' => 'Great news! Your ID verification has been approved.',
            'action' => 'You can now access all features of the barangay system, including requesting documents and services.'
        ],
        'rejected' => [
            'title' => 'ID Verification Rejected',
            'icon' => '❌',
            'color' => '#ef4444',
            'message' => 'We regret to inform you that your ID verification has been rejected.',
            'action' => 'Please review the rejection reason below and submit a new ID document if needed.'
        ]
    ];
    
    $config = $statusConfig[$status] ?? $statusConfig['verified'];
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . htmlspecialchars($config['title']) . '</title>
    </head>
    <body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #f3f4f6;">
        <table role="presentation" style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 40px 20px;">
                    <table role="presentation" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <!-- Header -->
                        <tr>
                            <td style="background: linear-gradient(135deg, #0f766e 0%, #14b8a6 100%); padding: 30px; text-align: center;">
                                <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 600;">' . htmlspecialchars($barangayName) . '</h1>
                            </td>
                        </tr>
                        
                        <!-- Content -->
                        <tr>
                            <td style="padding: 40px 30px;">
                                <div style="text-align: center; margin-bottom: 30px;">
                                    <div style="font-size: 48px; margin-bottom: 15px;">' . $config['icon'] . '</div>
                                    <h2 style="margin: 0; color: ' . $config['color'] . '; font-size: 28px; font-weight: 600;">' . htmlspecialchars($config['title']) . '</h2>
                                </div>
                                
                                <p style="margin: 0 0 20px; color: #374151; font-size: 16px; line-height: 1.6;">
                                    Dear <strong>' . $residentName . '</strong>,
                                </p>
                                
                                <p style="margin: 0 0 20px; color: #374151; font-size: 16px; line-height: 1.6;">
                                    ' . $config['message'] . '
                                </p>
                                
                                <!-- Verification Details -->
                                <div style="background-color: #f9fafb; border-left: 4px solid ' . $config['color'] . '; padding: 20px; margin: 25px 0; border-radius: 4px;">
                                    <table role="presentation" style="width: 100%; border-collapse: collapse;">
                                        <tr>
                                            <td style="padding: 5px 0; color: #6b7280; font-size: 14px; width: 120px;"><strong>Status:</strong></td>
                                            <td style="padding: 5px 0;">
                                                <span style="display: inline-block; background-color: ' . $config['color'] . '; color: #ffffff; padding: 4px 12px; border-radius: 4px; font-size: 12px; font-weight: 600; text-transform: uppercase;">
                                                    ' . htmlspecialchars(ucfirst($status)) . '
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 5px 0; color: #6b7280; font-size: 14px;"><strong>Date:</strong></td>
                                            <td style="padding: 5px 0; color: #111827; font-size: 14px;">' . date('F j, Y g:i A') . '</td>
                                        </tr>
                                    </table>
                                </div>
                                
                                ' . ($notes ? '
                                <div style="background-color: ' . ($status === 'rejected' ? '#fee2e2' : '#d1fae5') . '; border-left: 4px solid ' . $config['color'] . '; padding: 15px; margin: 20px 0; border-radius: 4px;">
                                    <p style="margin: 0 0 10px; color: ' . ($status === 'rejected' ? '#991b1b' : '#065f46') . '; font-size: 14px; font-weight: 600;">' . ($status === 'rejected' ? 'Rejection Reason:' : 'Note:') . '</p>
                                    <p style="margin: 0; color: ' . ($status === 'rejected' ? '#991b1b' : '#065f46') . '; font-size: 14px; line-height: 1.6;">
                                        ' . $notes . '
                                    </p>
                                </div>
                                ' : '') . '
                                
                                <p style="margin: 20px 0 0; color: #374151; font-size: 16px; line-height: 1.6;">
                                    ' . $config['action'] . '
                                </p>
                                
                                ' . ($status === 'verified' ? '
                                <div style="margin-top: 30px; padding: 20px; background-color: #eff6ff; border-radius: 4px; border: 1px solid #bfdbfe;">
                                    <p style="margin: 0 0 10px; color: #1e40af; font-size: 14px; font-weight: 600;">🎉 What you can do now:</p>
                                    <ul style="margin: 0; padding-left: 20px; color: #1e40af; font-size: 14px; line-height: 1.8;">
                                        <li>Request barangay documents (clearances, certificates, etc.)</li>
                                        <li>Report incidents to the barangay</li>
                                        <li>Access all resident services</li>
                                        <li>View your resident profile</li>
                                    </ul>
                                </div>
                                ' : ($status === 'rejected' ? '
                                <div style="margin-top: 30px; padding: 20px; background-color: #fef3c7; border-radius: 4px; border: 1px solid #f59e0b;">
                                    <p style="margin: 0 0 10px; color: #92400e; font-size: 14px; font-weight: 600;">📋 Next Steps:</p>
                                    <ul style="margin: 0; padding-left: 20px; color: #92400e; font-size: 14px; line-height: 1.8;">
                                        <li>Review the rejection reason above</li>
                                        <li>Upload a new, valid ID document</li>
                                        <li>Ensure the document is clear and readable</li>
                                        <li>Contact the barangay office if you have questions</li>
                                    </ul>
                                </div>
                                ' : '')) . '
                            </td>
                        </tr>
                        
                        <!-- Footer -->
                        <tr>
                            <td style="background-color: #f9fafb; padding: 30px; text-align: center; border-top: 1px solid #e5e7eb;">
                                <p style="margin: 0 0 10px; color: #6b7280; font-size: 14px;">
                                    <strong>' . htmlspecialchars($barangayName) . '</strong>
                                </p>
                                ' . ($barangayAddress ? '<p style="margin: 0 0 5px; color: #9ca3af; font-size: 12px;">' . htmlspecialchars($barangayAddress) . '</p>' : '') . '
                                ' . ($barangayPhone ? '<p style="margin: 0; color: #9ca3af; font-size: 12px;">Phone: ' . htmlspecialchars($barangayPhone) . '</p>' : '') . '
                                <p style="margin: 15px 0 0; color: #9ca3af; font-size: 12px;">
                                    This is an automated email. Please do not reply to this message.
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>';
    
    return $html;
}

/**
 * Send ID verification status email to resident
 * 
 * @param string $email Recipient email
 * @param string $status Verification status (verified, rejected)
 * @param array $residentData Resident information
 * @return array Result with 'success' and 'message' keys
 */
function send_id_verification_email($email, $status, $residentData) {
    if (empty($email)) {
        return [
            'success' => false,
            'message' => 'Recipient email is required'
        ];
    }
    
    $statusLabels = [
        'verified' => 'ID Verification Approved',
        'rejected' => 'ID Verification Rejected'
    ];
    
    $subject = ($statusLabels[$status] ?? 'ID Verification Status Update') . ' - Barangay Panungyanan';
    $htmlContent = generate_id_verification_email_html($status, $residentData);
    
    return send_resend_email($email, $subject, $htmlContent);
}


/**
 * Generate email HTML template for incident status updates
 * 
 * @param string $status Incident status (submitted, in_review, resolved, closed)
 * @param array $incidentData Incident information
 * @return string HTML email content
 */
function generate_incident_email_html($status, $incidentData) {
    $barangayName = getenv('BARANGAY_NAME') ?: $_ENV['BARANGAY_NAME'] ?? 'Barangay Panungyanan';
    $barangayAddress = getenv('BARANGAY_ADDRESS') ?: $_ENV['BARANGAY_ADDRESS'] ?? '';
    $barangayPhone = getenv('BARANGAY_PHONE') ?: $_ENV['BARANGAY_PHONE'] ?? '';
    
    $incidentId = htmlspecialchars($incidentData['id'] ?? 'N/A');
    $description = htmlspecialchars($incidentData['description'] ?? '');
    $notes = !empty($incidentData['notes']) ? htmlspecialchars($incidentData['notes']) : null;
    $residentName = htmlspecialchars($incidentData['resident_name'] ?? 'Resident');
    
    // Status-specific content
    $statusConfig = [
        'submitted' => [
            'title' => 'Incident Report Received',
            'icon' => '📝',
            'color' => '#f59e0b',
            'message' => 'Your incident report has been received and is currently Pending review.',
            'action' => 'We will notify you once an officer begins reviewing your report.'
        ],
        'in_review' => [
            'title' => 'Incident Under Review',
            'icon' => '🔍',
            'color' => '#0284c7',
            'message' => 'Your incident report is now Under Review by our office.',
            'action' => 'Our team is currently investigating the reported incident. We may contact you for further information if needed.'
        ],
        'resolved' => [
            'title' => 'Incident Resolved',
            'icon' => '✅',
            'color' => '#16a34a',
            'message' => 'We are pleased to inform you that your reported incident has been marked as Resolved.',
            'action' => 'Thank you for your report and for helping maintain the safety and order of our barangay.'
        ],
        'closed' => [
            'title' => 'Incident Report Rejected',
            'icon' => '❌',
            'color' => '#dc2626',
            'message' => 'Your incident report has been Rejected/Closed.',
            'action' => 'Please review the reason provided below. If you have questions, feel free to contact the barangay office.'
        ]
    ];
    
    $config = $statusConfig[$status] ?? $statusConfig['submitted'];
    $displayStatus = $status === 'submitted' ? 'Pending' : ($status === 'closed' ? 'Rejected' : ucfirst(str_replace('_', ' ', $status)));
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . htmlspecialchars($config['title']) . '</title>
    </head>
    <body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #f3f4f6;">
        <table role="presentation" style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 40px 20px;">
                    <table role="presentation" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <!-- Header -->
                        <tr>
                            <td style="background: linear-gradient(135deg, #0f766e 0%, #14b8a6 100%); padding: 30px; text-align: center;">
                                <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 600;">' . htmlspecialchars($barangayName) . '</h1>
                            </td>
                        </tr>
                        
                        <!-- Content -->
                        <tr>
                            <td style="padding: 40px 30px;">
                                <div style="text-align: center; margin-bottom: 30px;">
                                    <div style="font-size: 48px; margin-bottom: 15px;">' . $config['icon'] . '</div>
                                    <h2 style="margin: 0; color: ' . $config['color'] . '; font-size: 28px; font-weight: 600;">' . htmlspecialchars($config['title']) . '</h2>
                                </div>
                                
                                <p style="margin: 0 0 20px; color: #374151; font-size: 16px; line-height: 1.6;">
                                    Dear <strong>' . $residentName . '</strong>,
                                </p>
                                
                                <p style="margin: 0 0 20px; color: #374151; font-size: 16px; line-height: 1.6;">
                                    ' . $config['message'] . '
                                </p>
                                
                                <!-- Incident Details -->
                                <div style="background-color: #f9fafb; border-left: 4px solid ' . $config['color'] . '; padding: 20px; margin: 25px 0; border-radius: 4px;">
                                    <table role="presentation" style="width: 100%; border-collapse: collapse;">
                                        <tr>
                                            <td style="padding: 5px 0; color: #6b7280; font-size: 14px; width: 120px;"><strong>Incident ID:</strong></td>
                                            <td style="padding: 5px 0; color: #111827; font-size: 14px;">#' . $incidentId . '</td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 5px 0; color: #6b7280; font-size: 14px;"><strong>Status:</strong></td>
                                            <td style="padding: 5px 0;">
                                                <span style="display: inline-block; background-color: ' . $config['color'] . '; color: #ffffff; padding: 4px 12px; border-radius: 4px; font-size: 12px; font-weight: 600; text-transform: uppercase;">
                                                    ' . htmlspecialchars($displayStatus) . '
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 5px 0; color: #6b7280; font-size: 14px; vertical-align: top;"><strong>Description:</strong></td>
                                            <td style="padding: 5px 0; color: #111827; font-size: 14px;">' . $description . '</td>
                                        </tr>
                                    </table>
                                </div>
                                
                                ' . ($notes ? '
                                <div style="background-color: ' . ($status === 'closed' ? '#fee2e2' : '#fef3c7') . '; border-left: 4px solid ' . $config['color'] . '; padding: 15px; margin: 20px 0; border-radius: 4px;">
                                    <p style="margin: 0; color: ' . ($status === 'closed' ? '#991b1b' : '#92400e') . '; font-size: 14px; line-height: 1.6;">
                                        <strong>' . ($status === 'closed' ? 'Reason for Rejection:' : 'Admin Note:') . '</strong> ' . $notes . '
                                    </p>
                                </div>
                                ' : '') . '
                                
                                <p style="margin: 20px 0 0; color: #374151; font-size: 16px; line-height: 1.6;">
                                    ' . $config['action'] . '
                                </p>
                            </td>
                        </tr>
                        
                        <!-- Footer -->
                        <tr>
                            <td style="background-color: #f9fafb; padding: 30px; text-align: center; border-top: 1px solid #e5e7eb;">
                                <p style="margin: 0 0 10px; color: #6b7280; font-size: 14px;">
                                    <strong>' . htmlspecialchars($barangayName) . '</strong>
                                </p>
                                ' . ($barangayAddress ? '<p style="margin: 0 0 5px; color: #9ca3af; font-size: 12px;">' . htmlspecialchars($barangayAddress) . '</p>' : '') . '
                                ' . ($barangayPhone ? '<p style="margin: 0; color: #9ca3af; font-size: 12px;">Phone: ' . htmlspecialchars($barangayPhone) . '</p>' : '') . '
                                <p style="margin: 15px 0 0; color: #9ca3af; font-size: 12px;">
                                    This is an automated email. Please do not reply to this message.
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>';
    
    return $html;
}

/**
 * Send incident status email to resident
 * 
 * @param string $email Recipient email
 * @param string $status Incident status
 * @param array $incidentData Incident information
 * @return array Result with 'success' and 'message' keys
 */
function send_incident_status_email($email, $status, $incidentData) {
    if (empty($email)) {
        return [
            'success' => false,
            'message' => 'Recipient email is required'
        ];
    }
    
    $displayStatus = $status === 'submitted' ? 'Pending' : ($status === 'closed' ? 'Rejected' : ucfirst(str_replace('_', ' ', $status)));
    $subject = "Incident Report Status: {$displayStatus} - Barangay Panungyanan";
    $htmlContent = generate_incident_email_html($status, $incidentData);
    
    return send_resend_email($email, $subject, $htmlContent);
}

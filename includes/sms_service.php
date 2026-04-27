<?php
/**
 * SMS Service using local device API
 * 
 * This service handles sending SMS messages via a local device API endpoint.
 * 
 * Note: Environment variables are loaded by email_service.php which is included first.
 */

/**
 * Send SMS using local device API
 * 
 * @param string|array $phoneNumbers Phone number(s) - can be a single number or array of numbers
 * @param string $message SMS message text
 * @return array Result with 'success' and 'message' keys
 */
/**
 * Send SMS using sms-gate.app API (Compatible with Local & Cloud)
 */
function send_sms($phoneNumbers, $message) {
    $username = getenv('SMS_USERNAME') ?: $_ENV['SMS_USERNAME'] ?? '';
    $password = getenv('SMS_PASSWORD') ?: $_ENV['SMS_PASSWORD'] ?? '';
    // For cloud, this should be set to: https://api.sms-gate.app
    $deviceIp = getenv('SMS_DEVICE_IP') ?: $_ENV['SMS_DEVICE_IP'] ?? ''; 
    
    if (empty($username) || empty($password)) {
        return ['success' => false, 'message' => 'SMS credentials are not configured.'];
    }

    if (empty($deviceIp)) {
        return ['success' => false, 'message' => 'SMS API address is not configured.'];
    }
    
    // Normalize phone numbers to array
    if (!is_array($phoneNumbers)) {
        $phoneNumbers = [$phoneNumbers];
    }

    $formattedNumbers = [];
    foreach ($phoneNumbers as $number) {
        // 1. Remove all spaces, dashes, parentheses, and letters
        $cleanNumber = preg_replace('/[^0-9+]/', '', $number);
        
        // 2. Convert standard Philippine format (09xx) to International (+639xx)
        if (preg_match('/^0(9\d{9})$/', $cleanNumber, $matches)) {
            $cleanNumber = '+63' . $matches[1];
        } 
        // 3. If it already starts with 639 but is missing the '+', add it
        else if (preg_match('/^63(9\d{9})$/', $cleanNumber, $matches)) {
            $cleanNumber = '+' . $cleanNumber;
        }

        if (!empty($cleanNumber)) {
            $formattedNumbers[] = $cleanNumber;
        }
    }
    
    $phoneNumbers = $formattedNumbers;
    
    if (empty($phoneNumbers)) {
        return ['success' => false, 'message' => 'At least one phone number is required.'];
    }
    
    // sms-gate.app standard payload (Same for Local and Cloud)
    $payload = [
        'textMessage' => [
            'text' => $message
        ],
        'phoneNumbers' => array_values($phoneNumbers)
    ];
    
    // Build API URL
    $apiUrl = $deviceIp;
    if (strpos($apiUrl, 'http://') !== 0 && strpos($apiUrl, 'https://') !== 0) {
        $apiUrl = 'http://' . $apiUrl;
    }
    
    // Add default port for local IPs if missing
    if (strpos($apiUrl, ':') === false && (preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $apiUrl) || $apiUrl === 'localhost' || $apiUrl === '127.0.0.1')) {
        $apiUrl .= ':8080';
    }
    
    // Fix: Use the correct sms-gate.app endpoint
    $apiUrl = rtrim($apiUrl, '/');
    if (strpos($apiUrl, '/3rdparty/v1/messages') === false) {
        $apiUrl .= '/3rdparty/v1/messages';
    }
    
    // Send via cURL
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
    // FIX: ALWAYS use Basic Auth for sms-gate.app, regardless of cloud/local
    curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
    
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'message' => 'CURL Error: ' . $error];
    }
    
    $responseData = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return [
            'success' => true,
            'message' => 'SMS sent successfully',
            'data' => $responseData
        ];
    } else {
        $errorMsg = $responseData['message'] ?? $responseData['error'] ?? 'Server Response: ' . substr(strip_tags($response), 0, 100);
        return [
            'success' => false,
            'message' => 'SMS API Error (HTTP ' . $httpCode . '): ' . $errorMsg,
            'raw_response' => $response
        ];
    }
}

/**
 * Generate SMS text for request status updates
 * 
 * @param string $status Request status (pending, approved, rejected, released)
 * @param array $requestData Request information
 * @return string SMS message text
 */
function generate_status_sms_text($status, $requestData) {
    $barangayName = getenv('BARANGAY_NAME') ?: $_ENV['BARANGAY_NAME'] ?? 'Barangay Panungyanan';
    
    $requestType = $requestData['type'] === 'clearance' ? 'Barangay Clearance' : ($requestData['doc_type'] ?? 'Document');
    $requestNumber = $requestData['number'] ?? 'N/A';
    $purpose = $requestData['purpose'] ?? '';
    $notes = !empty($requestData['notes']) ? $requestData['notes'] : null;
    $residentName = $requestData['resident_name'] ?? 'Resident';
    $price = isset($requestData['price']) ? (float)$requestData['price'] : 0.00;
    
    // Status-specific content
    $statusConfig = [
        'pending' => [
            'title' => 'Request Received',
            'message' => 'Your request has been received and is currently being reviewed.',
        ],
        'approved' => [
            'title' => 'Request Approved',
            'message' => 'Great news! Your request has been approved. Please visit the barangay office to claim your document. Bring a valid ID.',
        ],
        'rejected' => [
            'title' => 'Request Rejected',
            'message' => 'We regret to inform you that your request has been rejected. Please contact the barangay office for more information.',
        ],
        'released' => [
            'title' => 'Document Ready',
            'message' => 'Your document is ready for pickup! Please visit the barangay office to claim it. Bring a valid ID.',
        ]
    ];
    
    $config = $statusConfig[$status] ?? $statusConfig['pending'];
    
    $sms = $barangayName . "\n\n";
    $sms .= $config['title'] . "\n\n";
    $sms .= "Dear " . $residentName . ",\n\n";
    $sms .= $config['message'] . "\n\n";
    $sms .= "Request: " . $requestType . "\n";
    $sms .= "Number: " . $requestNumber . "\n";
    if ($purpose) {
        $sms .= "Purpose: " . $purpose . "\n";
    }
    if ($price > 0) {
        $sms .= "Price: ₱" . number_format($price, 2) . "\n";
    }
    if ($notes) {
        $sms .= "\nNote: " . $notes . "\n";
    }
    
    if ($status === 'approved' || $status === 'released') {
        $sms .= "\nWhat to bring:\n";
        $sms .= "- Valid government-issued ID\n";
        if ($price > 0) {
            $sms .= "- Payment of ₱" . number_format($price, 2) . "\n";
        }
        $sms .= "\nImportant: If someone else will claim the document, they must bring an authorization letter with your signature. The authorized person must also present a valid ID.\n";
    }
    
    return $sms;
}

/**
 * Send request status SMS to resident
 * 
 * @param string $phoneNumber Recipient phone number
 * @param string $status Request status
 * @param array $requestData Request information
 * @return array Result with 'success' and 'message' keys
 */
function send_request_status_sms($phoneNumber, $status, $requestData) {
    if (empty($phoneNumber)) {
        return [
            'success' => false,
            'message' => 'Recipient phone number is required'
        ];
    }
    
    $smsText = generate_status_sms_text($status, $requestData);
    
    return send_sms($phoneNumber, $smsText);
}

/**
 * Generate password reset SMS text
 * 
 * @param string $resetLink Full URL to reset password page with token
 * @param string $userName User's name (optional)
 * @return string SMS message text
 */
function generate_password_reset_sms_text($resetLink, $userName = 'User') {
    $barangayName = getenv('BARANGAY_NAME') ?: $_ENV['BARANGAY_NAME'] ?? 'Barangay Panungyanan';
    
    $sms = $barangayName . "\n\n";
    $sms .= "Password Reset Request\n\n";
    $sms .= "Dear " . $userName . ",\n\n";
    $sms .= "We received a request to reset the password associated with your account. To proceed, please click the link below to securely create a new password:\n\n";
    $sms .= $resetLink . "\n\n";
    $sms .= "This link will expire in 1 hour.\n\n";
    $sms .= "If you did not request this, please ignore this message.";
    
    return $sms;
}

/**
 * Send password reset SMS
 * 
 * @param string $phoneNumber Recipient phone number
 * @param string $resetLink Full URL to reset password page with token
 * @param string $userName User's name (optional)
 * @return array Result with 'success' and 'message' keys
 */
function send_password_reset_sms($phoneNumber, $resetLink, $userName = 'User') {
    if (empty($phoneNumber)) {
        return [
            'success' => false,
            'message' => 'Recipient phone number is required'
        ];
    }
    
    $smsText = generate_password_reset_sms_text($resetLink, $userName);
    
    return send_sms($phoneNumber, $smsText);
}

/**
 * Generate ID verification SMS text
 * 
 * @param string $status Verification status (verified, rejected)
 * @param array $residentData Resident information
 * @return string SMS message text
 */
function generate_id_verification_sms_text($status, $residentData) {
    $barangayName = getenv('BARANGAY_NAME') ?: $_ENV['BARANGAY_NAME'] ?? 'Barangay Panungyanan';
    
    $residentName = $residentData['full_name'] ?? 'Resident';
    $notes = !empty($residentData['verification_notes']) ? $residentData['verification_notes'] : null;
    
    // Status-specific content
    $statusConfig = [
        'verified' => [
            'title' => 'ID Verification Approved',
            'message' => 'Great news! Your ID verification has been approved. You can now access all features of the barangay system.',
        ],
        'rejected' => [
            'title' => 'ID Verification Rejected',
            'message' => 'We regret to inform you that your ID verification has been rejected. Please review the reason below and submit a new ID document if needed.',
        ]
    ];
    
    $config = $statusConfig[$status] ?? $statusConfig['verified'];
    
    $sms = $barangayName . "\n\n";
    $sms .= $config['title'] . "\n\n";
    $sms .= "Dear " . $residentName . ",\n\n";
    $sms .= $config['message'] . "\n\n";
    
    if ($notes) {
        $sms .= ($status === 'rejected' ? 'Rejection Reason: ' : 'Note: ') . $notes . "\n\n";
    }
    
    if ($status === 'verified') {
        $sms .= "You can now:\n";
        $sms .= "- Request barangay documents\n";
        $sms .= "- Report incidents\n";
        $sms .= "- Access all resident services\n";
    } elseif ($status === 'rejected') {
        $sms .= "Next steps:\n";
        $sms .= "- Review the rejection reason\n";
        $sms .= "- Upload a new, valid ID document\n";
        $sms .= "- Ensure the document is clear and readable\n";
    }
    
    return $sms;
}

/**
 * Send ID verification status SMS to resident
 * 
 * @param string $phoneNumber Recipient phone number
 * @param string $status Verification status (verified, rejected)
 * @param array $residentData Resident information
 * @return array Result with 'success' and 'message' keys
 */
function send_id_verification_sms($phoneNumber, $status, $residentData) {
    if (empty($phoneNumber)) {
        return [
            'success' => false,
            'message' => 'Recipient phone number is required'
        ];
    }
    
    $smsText = generate_id_verification_sms_text($status, $residentData);
    
    return send_sms($phoneNumber, $smsText);
}


/**
 * Generate SMS text for incident status updates
 * 
 * @param string $status Incident status (submitted, in_review, resolved, closed)
 * @param array $incidentData Incident information
 * @return string SMS message text
 */
function generate_incident_sms_text($status, $incidentData) {
    $barangayName = getenv('BARANGAY_NAME') ?: $_ENV['BARANGAY_NAME'] ?? 'Barangay Panungyanan';
    
    $residentName = $incidentData['resident_name'] ?? 'Resident';
    $notes = !empty($incidentData['notes']) ? $incidentData['notes'] : null;
    $incidentId = $incidentData['id'] ?? 'N/A';
    
    // Status-specific content
    $statusConfig = [
        'submitted' => [
            'title' => 'Incident Report Received',
            'message' => 'Your incident report has been received and is currently Pending review.',
        ],
        'in_review' => [
            'title' => 'Incident Under Review',
            'message' => 'Your incident report is now Under Review. Our team is currently investigating the reported incident.',
        ],
        'resolved' => [
            'title' => 'Incident Resolved',
            'message' => 'Your reported incident has been marked as Resolved. Thank you for your cooperation.',
        ],
        'closed' => [
            'title' => 'Incident Report Rejected',
            'message' => 'Your incident report has been Rejected/Closed. Please review the reason below.',
        ]
    ];
    
    $config = $statusConfig[$status] ?? $statusConfig['submitted'];
    $displayStatus = $status === 'submitted' ? 'Pending' : ($status === 'closed' ? 'Rejected' : ucfirst(str_replace('_', ' ', $status)));
    
    $sms = $barangayName . "\n\n";
    $sms .= $config['title'] . "\n\n";
    $sms .= "Dear " . $residentName . ",\n\n";
    $sms .= $config['message'] . "\n\n";
    $sms .= "Incident ID: #" . $incidentId . "\n";
    $sms .= "Status: " . $displayStatus . "\n\n";
    
    if ($notes) {
        $sms .= ($status === 'closed' ? 'Reason: ' : 'Note: ') . $notes . "\n\n";
    }
    
    $sms .= "Thank you.";
    
    return $sms;
}

/**
 * Send incident status SMS to resident
 * 
 * @param string $phoneNumber Recipient phone number
 * @param string $status Incident status
 * @param array $incidentData Incident information
 * @return array Result with 'success' and 'message' keys
 */
function send_incident_status_sms($phoneNumber, $status, $incidentData) {
    if (empty($phoneNumber)) {
        return [
            'success' => false,
            'message' => 'Recipient phone number is required'
        ];
    }
    
    $smsText = generate_incident_sms_text($status, $incidentData);
    
    return send_sms($phoneNumber, $smsText);
}

<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: text/plain');

echo "Checking SMS Service...\n";

if (function_exists('send_sms')) {
    echo "SUCCESS: send_sms function exists.\n";
} else {
    echo "ERROR: send_sms function NOT found.\n";
}

if (function_exists('send_id_verification_sms')) {
    echo "SUCCESS: send_id_verification_sms function exists.\n";
} else {
    echo "ERROR: send_id_verification_sms function NOT found.\n";
}

// Test with a hardcoded number (use your number here if you want to test)
$testPhone = '09261640911'; // Sairon's number from DB
echo "Attempting to send test SMS to $testPhone...\n";

$result = send_sms($testPhone, "Test SMS from Barangay System Debugger");

echo "Result: " . ($result['success'] ? "SUCCESS" : "FAILED") . "\n";
echo "Message: " . $result['message'] . "\n";
if (isset($result['raw_response'])) {
    echo "Raw Response: " . $result['raw_response'] . "\n";
}

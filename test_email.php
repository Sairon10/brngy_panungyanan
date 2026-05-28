<?php
require 'includes/email_service.php';

$res = send_resend_email('saironmarkmanalad43@gmail.com', 'Test Email', '<p>Test</p>');
print_r($res);

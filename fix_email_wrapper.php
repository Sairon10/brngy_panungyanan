<?php
$file = 'includes/email_service.php';
$c = file_get_contents($file);

$old_html_start = <<<EOD
    \$htmlContent = "
    <div style='text-align: center; margin-bottom: 30px;'>
        <div style='font-size: 48px; margin-bottom: 15px;'>{\$statusIcon}</div>
        <h2 style='color: {\$statusColor}; margin: 0; font-size: 24px;'>Payment {\$status}</h2>
    </div>
EOD;

$new_html_start = <<<EOD
    \$barangayName = getenv('BARANGAY_NAME') ?: \$_ENV['BARANGAY_NAME'] ?? 'Barangay Panungyanan';
    \$barangayAddress = getenv('BARANGAY_ADDRESS') ?: \$_ENV['BARANGAY_ADDRESS'] ?? '';
    \$barangayPhone = getenv('BARANGAY_PHONE') ?: \$_ENV['BARANGAY_PHONE'] ?? '';

    \$htmlContent = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Payment Status Update</title>
    </head>
    <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; background-color: #f3f4f6;'>
        <table role='presentation' style='width: 100%; border-collapse: collapse;'>
            <tr>
                <td style='padding: 40px 20px;'>
                    <table role='presentation' style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
                        <!-- Header -->
                        <tr>
                            <td style='background: linear-gradient(135deg, #0f766e 0%, #14b8a6 100%); padding: 30px; text-align: center;'>
                                <h1 style='margin: 0; color: #ffffff; font-size: 24px; font-weight: 600;'>{\$barangayName}</h1>
                            </td>
                        </tr>
                        <!-- Content -->
                        <tr>
                            <td style='padding: 40px 30px;'>
                                <div style='text-align: center; margin-bottom: 30px;'>
                                    <div style='font-size: 48px; margin-bottom: 15px;'>{\$statusIcon}</div>
                                    <h2 style='color: {\$statusColor}; margin: 0; font-size: 24px;'>Payment " . ucfirst(\$status) . "</h2>
                                </div>";
EOD;

$old_html_end = <<<EOD
    <div style='text-align: center; margin-top: 30px;'>
        <a href='http://localhost/payments.php' style='display: inline-block; background-color: #0d9488; color: white; text-decoration: none; padding: 12px 25px; border-radius: 6px; font-weight: 600;'>View Payment History</a>
    </div>";
EOD;

$new_html_end = <<<EOD
    <div style='text-align: center; margin-top: 30px;'>
        <a href='https://brgypanungyanan.site/payments.php' style='display: inline-block; background-color: #0d9488; color: white; text-decoration: none; padding: 12px 25px; border-radius: 6px; font-weight: 600;'>View Payment History</a>
    </div>
                            </td>
                        </tr>
                        <!-- Footer -->
                        <tr>
                            <td style='background-color: #f9fafb; padding: 30px; text-align: center; border-top: 1px solid #e5e7eb;'>
                                <p style='margin: 0 0 10px; color: #6b7280; font-size: 14px;'>
                                    <strong>{\$barangayName}</strong>
                                </p>
                                " . (\$barangayAddress ? "<p style='margin: 0 0 5px; color: #9ca3af; font-size: 12px;'>{\$barangayAddress}</p>" : '') . "
                                " . (\$barangayPhone ? "<p style='margin: 0; color: #9ca3af; font-size: 12px;'>Phone: {\$barangayPhone}</p>" : '') . "
                                <p style='margin: 15px 0 0; color: #9ca3af; font-size: 12px;'>
                                    This is an automated email. Please do not reply to this message.
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>";
EOD;

$c = str_replace($old_html_start, $new_html_start, $c);
$c = str_replace($old_html_end, $new_html_end, $c);

file_put_contents($file, $c);
echo "Updated email_service.php\n";

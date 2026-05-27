<?php
$file = 'admin/payments.php';
$c = file_get_contents($file);

$old_query = <<<EOD
            \$stmt = \$pdo->prepare("
                SELECT u.email, u.full_name, u.first_name, r.phone,
                       p.payment_reference_no, p.payment_amount_paid,
                       \$docTypeCol
                FROM \$table p
                JOIN users u ON u.id = p.user_id
                LEFT JOIN residents r ON r.user_id = u.id
                WHERE p.id = ? LIMIT 1
            ");
EOD;

$new_query = <<<EOD
            \$dtJoin = \$pay_type === 'clearance' ? "ON dt.name = 'Barangay Clearance'" : "ON dt.name = p.doc_type";
            
            \$stmt = \$pdo->prepare("
                SELECT u.email, u.full_name, u.first_name, r.phone,
                       p.payment_reference_no, p.payment_amount_paid,
                       \$docTypeCol, dt.price as amount_due
                FROM \$table p
                JOIN users u ON u.id = p.user_id
                LEFT JOIN residents r ON r.user_id = u.id
                LEFT JOIN document_types dt \$dtJoin
                WHERE p.id = ? LIMIT 1
            ");
EOD;

$c = str_replace($old_query, $new_query, $c);

$old_data = <<<EOD
                \$paymentData = [
                    'resident_name' => \$paymentInfo['first_name'] ?: (\$paymentInfo['full_name'] ?: 'Resident'),
                    'reference_no'  => \$paymentInfo['payment_reference_no'],
                    'amount'        => \$paymentInfo['payment_amount_paid'],
                    'doc_type'      => \$paymentInfo['doc_type'],
                    'notes'         => \$pay_action === 'refunded' ? (\$refund_notes ?? '') : '',
                    'admin_refund_amount' => \$pay_action === 'refunded' ? (\$refund_amount ?? null) : null
                ];
EOD;

$new_data = <<<EOD
                \$paymentData = [
                    'resident_name' => \$paymentInfo['first_name'] ?: (\$paymentInfo['full_name'] ?: 'Resident'),
                    'reference_no'  => \$paymentInfo['payment_reference_no'],
                    'amount_paid'   => \$paymentInfo['payment_amount_paid'],
                    'amount_due'    => \$paymentInfo['amount_due'],
                    'doc_type'      => \$paymentInfo['doc_type'],
                    'notes'         => \$pay_action === 'refunded' ? (\$refund_notes ?? '') : '',
                    'admin_refund_amount' => \$pay_action === 'refunded' ? (\$refund_amount ?? null) : null
                ];
EOD;

$c = str_replace($old_data, $new_data, $c);
file_put_contents($file, $c);
echo "Updated admin/payments.php\n";

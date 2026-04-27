<?php
$hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
echo "Admin@1234: " . (password_verify('Admin@1234', $hash) ? 'YES' : 'NO') . "\n";
echo "password: " . (password_verify('password', $hash) ? 'YES' : 'NO') . "\n";

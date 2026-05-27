<?php
require_once 'config.php';
$_GET['date_from'] = '2026-05-01';
$_GET['date_to'] = '2026-05-28';
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

ob_start();
require_once 'admin/index.php';
$output = ob_get_clean();
echo "Success, length: " . strlen($output);

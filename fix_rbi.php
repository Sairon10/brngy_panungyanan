<?php
$c = file_get_contents('admin/reports_data.php');
$c = str_replace("WHERE u.role = 'resident'", "WHERE 1=1", $c);
$c = str_replace("WHERE u2.role = 'resident'", "WHERE 1=1", $c);
$c = str_replace("WHERE 1=1 AND (u.email = rr.email OR u.full_name = rr.full_name)", "WHERE (u.email = rr.email OR u.full_name = rr.full_name)", $c);
file_put_contents('admin/reports_data.php', $c);
echo "fixed\n";

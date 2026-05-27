<?php
$c = file_get_contents('payments.php');

$c = str_replace(
    "\$p['fm_name'] ?? \$_SESSION['full_name']",
    "\$p['fm_name'] ?? \$p['user_name']",
    $c
);

file_put_contents('payments.php', $c);

// Also let's update profile.php to update session variables
$p = file_get_contents('profile.php');
$p = str_replace(
    "\$msg = 'Profile saved successfully.';",
    "\$_SESSION['full_name'] = \$full_name;\n                \$msg = 'Profile saved successfully.';",
    $p
);
file_put_contents('profile.php', $p);

echo "fixed payments and profile session update\n";

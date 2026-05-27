<?php
$file = 'includes/email_service.php';
$c = file_get_contents($file);

$c = str_replace(
    "<h2 style='color: {\$statusColor}; margin: 0; font-size: 24px;'>Payment \" . ucfirst(\$status) . \"</h2>\n                                </div>\";",
    "<h2 style='color: {\$statusColor}; margin: 0; font-size: 24px;'>Payment {\$status}</h2>\n                                </div>",
    $c
);

file_put_contents($file, $c);
echo "Fixed syntax\n";

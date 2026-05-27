<?php
$file = 'admin/index.php';
$c = file_get_contents($file);

// Replace $date_where in Incidents queries
$c = str_replace(
    "i.status='submitted' \$date_where",
    "i.status='submitted' \" . str_replace('created_at', 'i.created_at', \$date_where) . \"",
    $c
);
$c = str_replace(
    "i.status='in_review' \$date_where",
    "i.status='in_review' \" . str_replace('created_at', 'i.created_at', \$date_where) . \"",
    $c
);
$c = str_replace(
    "i.status='resolved' \$date_where",
    "i.status='resolved' \" . str_replace('created_at', 'i.created_at', \$date_where) . \"",
    $c
);
$c = str_replace(
    "i.status='closed' \$date_where",
    "i.status='closed' \" . str_replace('created_at', 'i.created_at', \$date_where) . \"",
    $c
);
$c = str_replace(
    "i.status='canceled' \$date_where",
    "i.status='canceled' \" . str_replace('created_at', 'i.created_at', \$date_where) . \"",
    $c
);

// Fix orphaned resident records query
$c = str_replace(
    ". str_replace(\"WHERE\", \"AND\", \$pop_date_query)",
    ". str_replace('created_at', 'rr.created_at', str_replace('WHERE', 'AND', \$pop_date_query))",
    $c
);

file_put_contents($file, $c);
echo "Fixed index.php date queries\n";

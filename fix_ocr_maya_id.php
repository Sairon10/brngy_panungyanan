<?php
$files = [
    'admin/payments.php',
    'admin/requests.php',
    'requests.php'
];

$oldRegex = '/(?:ref(?:erence)?\.\?\s\*(?:no\.\?|num(?:ber)\?)?\s*|\s*trans(?:action)?\.\?\s\*(?:no\.\?|id)\?)\s*\*\[:\.-\]\?\s*\*\(\[\\\\d\\\\sA-Za-z\]\+\)\/i';
// Wait, doing simple str_replace is safer for exact string.
$oldStr = 'const refLineMatch = line.match(/(?:ref(?:erence)?\.?\s*(?:no\.?|num(?:ber)?)?|trans(?:action)?\.?\s*(?:no\.?|id)?)\s*[:.-]?\s*([\d\sA-Za-z]+)/i);';
$newStr = 'const refLineMatch = line.match(/(?:ref(?:erence)?\.?\s*(?:no\.?|num(?:ber)?|id)?|trans(?:action)?\.?\s*(?:no\.?|id)?)\s*[:.-]?\s*([\d\sA-Za-z]+)/i);';

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    
    $c = file_get_contents($file);
    if (strpos($c, $oldStr) !== false) {
        $c = str_replace($oldStr, $newStr, $c);
        file_put_contents($file, $c);
        echo "Updated $file\n";
    } else {
        echo "No match found in $file\n";
    }
}

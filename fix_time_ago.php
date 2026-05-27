<?php
$files = [
    'api/admin_notifications.php',
    'api/notifications.php'
];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $c = file_get_contents($file);

    // Update query to include diff_seconds
    $c = preg_replace(
        '/SELECT \* FROM notifications/i',
        'SELECT *, TIMESTAMPDIFF(SECOND, created_at, NOW()) AS diff_seconds FROM notifications',
        $c
    );

    // Update time_ago calculation
    $c = preg_replace(
        '/\$n\[\'time_ago\'\] = time_ago\(strtotime\(\$n\[\'created_at\'\]\)\);/',
        '$n[\'time_ago\'] = time_ago((int)($n[\'diff_seconds\'] ?? 0), strtotime($n[\'created_at\']));',
        $c
    );

    // Update time_ago function definition
    $old_func = <<<EOD
function time_ago(int \$timestamp): string {
    \$diff = time() - \$timestamp;
    if (\$diff < 60) return 'just now';
    if (\$diff < 3600) return floor(\$diff / 60) . 'm ago';
    if (\$diff < 86400) return floor(\$diff / 3600) . 'h ago';
    if (\$diff < 604800) return floor(\$diff / 86400) . 'd ago';
    return date('M j', \$timestamp);
}
EOD;

    $new_func = <<<EOD
function time_ago(int \$diff_seconds, int \$timestamp = 0): string {
    // Fallback to PHP time if diff_seconds is not available
    if (\$diff_seconds === 0 && \$timestamp > 0) {
        \$diff_seconds = time() - \$timestamp;
    }
    
    // Prevent negative time due to slight sync issues
    if (\$diff_seconds < 0) \$diff_seconds = 0;
    
    if (\$diff_seconds < 60) return 'just now';
    if (\$diff_seconds < 3600) return floor(\$diff_seconds / 60) . 'm ago';
    if (\$diff_seconds < 86400) return floor(\$diff_seconds / 3600) . 'h ago';
    if (\$diff_seconds < 604800) return floor(\$diff_seconds / 86400) . 'd ago';
    
    // If we only have diff_seconds, we can compute the date relative to PHP time
    return date('M j', time() - \$diff_seconds);
}
EOD;

    $c = str_replace($old_func, $new_func, $c);

    file_put_contents($file, $c);
    echo "Updated $file\n";
}

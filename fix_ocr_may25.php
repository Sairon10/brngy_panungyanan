<?php
$files = [
    'admin/payments.php',
    'admin/requests.php',
    'requests.php'
];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    
    $c = file_get_contents($file);

    // I need to replace the entire alphaNumMatch block with the new gcashMatch / mayaMatch block.
    
    // Previous block:
    $oldStr = <<<EOD
const noSpaces = refLineMatch[1].replace(/\s+/g, '');
                        // Accept alphanumeric for Maya (9-20 chars) or digits for GCash
                        const alphaNumMatch = noSpaces.match(/[A-Za-z0-9]{9,20}/);
                        if (alphaNumMatch) {
                            foundRef = alphaNumMatch[0].toUpperCase();
                            break;
                        }
EOD;

    $newStr = <<<EOD
const noSpaces = refLineMatch[1].replace(/\s+/g, '');
                        // Check for GCash (10-15 digits)
                        const gcashMatch = noSpaces.match(/\d{10,15}/);
                        if (gcashMatch) {
                            foundRef = gcashMatch[0];
                            break;
                        }
                        
                        // Check for Maya (strictly 12 alphanumeric chars)
                        const mayaMatch = noSpaces.match(/[A-Za-z0-9]{12}/);
                        if (mayaMatch && /[A-Z]/i.test(mayaMatch[0]) && /[0-9]/.test(mayaMatch[0])) {
                            foundRef = mayaMatch[0].toUpperCase();
                            break;
                        }
EOD;

    if (strpos($c, $oldStr) !== false) {
        $c = str_replace($oldStr, $newStr, $c);
        file_put_contents($file, $c);
        echo "Updated $file\n";
    } else {
        // Fallback: If spaces or indentation differs, try preg_replace
        $pattern = '/const noSpaces = refLineMatch\[1\]\.replace\(\/\\\\s\+\/g, \'\'\);\s*\/\/ Accept alphanumeric.*?break;\s*\}/s';
        $c = preg_replace($pattern, $newStr, $c);
        file_put_contents($file, $c);
        echo "Regex Updated $file\n";
    }
}

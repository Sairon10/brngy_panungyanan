<?php
$files = [
    'admin/payments.php',
    'admin/requests.php',
    'requests.php'
];

$oldStr = <<<EOD
                        const digitsMatch = line.match(/\\b(\d[\d\s]{9,16}\d)\\b/);
                        if (digitsMatch) {
                            const clean = digitsMatch[1].replace(/\s+/g, '');
                            if (clean.length >= 10 && clean.length <= 15) {
                                foundRef = clean;
                                break;
                            }
                        }
EOD;

$newStr = <<<EOD
                        const digitsMatch = line.match(/\b(\d[\d\s]{9,16}\d)\b/);
                        if (digitsMatch) {
                            const clean = digitsMatch[1].replace(/\s+/g, '');
                            if (clean.length >= 10 && clean.length <= 15) {
                                // Skip if it looks like a PH phone number (09... or 639...)
                                if ((clean.startsWith('09') && clean.length === 11) || 
                                    (clean.startsWith('639') && clean.length === 12)) {
                                    continue; // Skip phone number, keep looking for reference
                                }
                                foundRef = clean;
                                break;
                            }
                        }
EOD;

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $c = file_get_contents($file);

    // To be safe with regex replacement since spaces might vary
    $pattern = '/const digitsMatch = line\.match\(\/\\\\b\(\\\\d\[\\\\d\\\\s\]\{9,16\}\\\\d\)\\\\b\/\);[\s\n]*if\s*\(digitsMatch\)\s*\{[\s\n]*const clean = digitsMatch\[1\]\.replace\(\/\\\\s\+\/g,\s*\'\'\);[\s\n]*if\s*\(clean\.length >= 10 && clean\.length <= 15\)\s*\{[\s\n]*foundRef = clean;[\s\n]*break;[\s\n]*\}[\s\n]*\}/i';
    
    $c = preg_replace($pattern, $newStr, $c);
    file_put_contents($file, $c);
    echo "Updated $file\n";
}

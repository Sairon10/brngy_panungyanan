<?php
$files = [
    'admin/payments.php',
    'admin/requests.php',
    'requests.php'
];

foreach ($files as $file) {
    if (!file_exists($file)) {
        continue;
    }
    
    $c = file_get_contents($file);

    // Replace the part inside refLineMatch
    $pattern1 = '/const noSpaces\s*=\s*refLineMatch\[1\]\.replace\(\/\\\\s\+\/g,\s*\'\'\);[\s\n]*const digitsOnly\s*=\s*noSpaces\.match\(\/\\\\d\{10,15\}\/\);[\s\n]*if\s*\(digitsOnly\)\s*\{[\s\n]*foundRef\s*=\s*digitsOnly\[0\];[\s\n]*break;[\s\n]*\}/';
    
    $repl1 = <<<EOD
const noSpaces = refLineMatch[1].replace(/\s+/g, '');
                        // Accept alphanumeric for Maya (9-20 chars) or digits for GCash
                        const alphaNumMatch = noSpaces.match(/[A-Za-z0-9]{9,20}/);
                        if (alphaNumMatch) {
                            foundRef = alphaNumMatch[0].toUpperCase();
                            break;
                        }
EOD;

    // Replace the standalone digits check
    $pattern2 = '/\bconst digitsMatch\s*=\s*line\.match\(\/\\\\b\(\\\\d\[\\\\d\\\\s\]\{9,16\}\\\\d\)\\\\b\/\);[\s\n]*if\s*\(digitsMatch\)\s*\{[\s\n]*const clean\s*=\s*digitsMatch\[1\]\.replace\(\/\\\\s\+\/g,\s*\'\'\);[\s\n]*if\s*\(clean\.length\s*>=\s*10\s*&&\s*clean\.length\s*<=\s*15\)\s*\{[\s\n]*foundRef\s*=\s*clean;[\s\n]*break;[\s\n]*\}[\s\n]*\}/';

    $repl2 = <<<EOD
// Maya standalone check (12 alphanumeric)
                        const mayaMatch = line.match(/\b([A-Z0-9]{12})\b/i);
                        if (mayaMatch && /[A-Z]/i.test(mayaMatch[1]) && /[0-9]/.test(mayaMatch[1])) {
                            foundRef = mayaMatch[1].toUpperCase();
                            break;
                        }
                        
                        const digitsMatch = line.match(/\b(\d[\d\s]{9,16}\d)\b/);
                        if (digitsMatch) {
                            const clean = digitsMatch[1].replace(/\s+/g, '');
                            if (clean.length >= 10 && clean.length <= 15) {
                                foundRef = clean;
                                break;
                            }
                        }
EOD;

    $c = preg_replace($pattern1, $repl1, $c);
    $c = preg_replace($pattern2, $repl2, $c);
    
    file_put_contents($file, $c);
    echo "Processed $file\n";
}

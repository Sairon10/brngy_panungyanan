<?php
$html = file_get_contents('admin/requests.php');
$lines = explode("\n", $html);
$form_open = false;
$open_line = 0;
foreach ($lines as $i => $line) {
    if (stripos($line, '<form') !== false) {
        if ($form_open) {
            echo "NESTED FORM FOUND! Line " . ($i+1) . " is inside form from line $open_line\n";
        }
        $form_open = true;
        $open_line = $i + 1;
    }
    if (stripos($line, '</form>') !== false) {
        $form_open = false;
    }
}
echo "Form nesting check complete.\n";

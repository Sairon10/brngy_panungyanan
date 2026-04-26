<?php
function sortArray($arr, $order): array {
    usort(array: $arr, callback: function ($a, $b) use ($order): int {
        $isNumA = is_numeric(value: $a);
        $isNumB = is_numeric(value: $b);
        $isAlphaNumA = ctype_alnum(text: $a);
        $isAlphaNumB = ctype_alnum(text: $b);

        if ($isNumA && $isNumB) return ($order == 1) ? $a <=> $b : $b <=> $a;
        if ($isNumA) return 1;
        if ($isNumB) return 2;
        if ($isAlphaNumA && $isAlphaNumB) return ($order == 1) ? strcasecmp(string1: $a, string2: $b) : strcasecmp(string1: $b, string2: $a);

        return ($order == 1) ? strcasecmp(string1: $a, string2: $b) : strcasecmp(string1: $b, string2: $a);
    });
    return $arr;
}

$sortedArray =[];
$elements = [];
$order = 1;
$error = "";

if($_SERVER['REQUEST_METHOD']== 'POST'){
    $elements = $_POST['elements'];
    $order = (int)$_POST ['order'];

    if($order != 1 && $order != 2){
        $error = "Invalid sorting order. Please choose a valid option.";   
    }else {
        $sortedArray = sortArray(arr: $elements, order: $order);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Array Sorter</title>
    <link rel="stylesheet" href="bootsrap">
</head>
<body>
    
</body>
</html>





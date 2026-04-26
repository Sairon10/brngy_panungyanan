<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <?php

    function Even($array)
    {
        if($array%2==0)
        return TRUE;
        else
        return FALSE;
    }

    $array = array(12,0,0,18,27,0,46);
    print_r(array_filter($array, "Even"));

    ?>
</body>
</html>
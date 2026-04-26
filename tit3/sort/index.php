<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sort Array</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2>Sort Array</h2>
        <form method="post">
            <?php
            for ($i = 1; $i <= 5; $i++) {
                echo "<input type='number' name='numbers[]' placeholder='Enter number $i' required min='0'>";
            }
            ?>
            <select name="sort_order" required>
                <option value="">Select sorting order</option>
                <option value="1">Acsending</option>
                <option value="2">Descending</option>
            </select>
                <button type="submit">Sort</button>
        </form>

        <?php
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $numbers = $_POST["numbers"];
            $sort_order = $_POST["sort_order"];

            $has_negatives = false;
            foreach ($numbers as $number) {
                if ($number < 0) {
                    $has_negatives = true;
                    break;
                }
            }

            if ($has_negatives) {
                echo "<div class ='result'>Error: No negative numbers allowed.</div>";
            } else {
                if ($sort_order == 1) {
                    sort($numbers);
                } elseif ($sort_order == 2) {
                    rsort($numbers);
                }
                echo "<div class='result'>Sorted Numbers: ". implode(", ", $numbers) . "</div>";    
            }
        }
        ?>
    </div>
    
</body>
</html>
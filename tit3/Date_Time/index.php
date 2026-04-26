<?php
// Initialize variables to avoid undefined variable warnings
$formatted_datetime = $formatted_current = $formatted_future = $formatted_past = '';
$timezone_name = $timezone_offset = '';
$interval = null;
$datetime = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get user input
    $date = $_POST['date'] ?? '';
    $time = $_POST['time'] ?? '';
    $timezone = $_POST['timezone'] ?? 'UTC';
    
    // Create DateTime object with user input and timezone
    date_default_timezone_set($timezone);
    $datetime = new DateTime("$date $time");
    
    // Current time in selected timezone
    $current_datetime = new DateTime('now', new DateTimeZone($timezone));
    
    // Calculations
    $interval = $current_datetime->diff($datetime);
    $future_date = clone $datetime;
    $future_date->add(new DateInterval('P1M')); // Add 1 month
    $past_date = clone $datetime;
    $past_date->sub(new DateInterval('P2W')); // Subtract 2 weeks
    
    // Formatting
    $formatted_datetime = $datetime->format('l, F j, Y \a\t g:i A');
    $formatted_current = $current_datetime->format('l, F j, Y \a\t g:i A');
    $formatted_future = $future_date->format('l, F j, Y');
    $formatted_past = $past_date->format('l, F j, Y');
    
    // Timezone info
    $timezone_info = $datetime->getTimezone();
    $timezone_name = $timezone_info->getName();
    $timezone_offset = $datetime->format('P');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Date and Time Manipulation</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Date and Time Manipulation</h1>
        
        <form method="post">
            <div class="form-group">
                <label for="date">Select a Date:</label>
                <input type="date" id="date" name="date" required>
            </div>
            
            <div class="form-group">
                <label for="time">Select a Time:</label>
                <input type="time" id="time" name="time" required>
            </div>
            
            <div class="form-group">
                <label for="timezone">Select Timezone:</label>
                <select id="timezone" name="timezone">
                    <option value="Asia/Manila">Philippines (PHT)</option>
                    <option value="America/New_York">New York (EST/EDT)</option>
                    <option value="Europe/London">London (GMT/BST)</option>
                    <option value="Asia/Tokyo">Tokyo (JST)</option>
                    <option value="Australia/Sydney">Sydney (AEST/AEDT)</option>
                    <option value="UTC">UTC</option>
                </select>
            </div>
            
            <button type="submit">Calculate</button>
        </form>
        
        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <div class="results">
            <?php 
                echo "<div class='result-item'>
                <div class='result-title'>Selected Date and Time:</div>
                <div>{$formatted_datetime}</div>
                <div class='timezone-info'>Timezone: {$timezone_name} (UTC{$timezone_offset})</div>
              </div>";
        
        echo "<div class='result-item'>
                <div class='result-title'>Current Date and Time:</div>
                <div>{$formatted_current}</div>
              </div>";
        
        echo "<div class='result-item'>
                <div class='result-title'>Time Difference:</div>
                <div>";
        if ($interval->invert) {
            echo "The selected time was {$interval->format('%a days, %h hours, and %i minutes')} ago";
        } else {
            echo "The selected time is in {$interval->format('%a days, %h hours, and %i minutes')}";
        }
        echo "</div>
              </div>";
        
        echo "<div class='result-item'>
                <div class='result-title'>One Month Later:</div>
                <div>{$formatted_future}</div>
              </div>";
        
        echo "<div class='result-item'>
                <div class='result-title'>Two Weeks Earlier:</div>
                <div>{$formatted_past}</div>
              </div>";
        
        echo "<div class='result-item'>
                <div class='result-title'>Unix Timestamp:</div>
                <div>{$datetime->format('U')} seconds since January 1, 1970</div>
              </div>";
        
        $daysInYear = date('L') ? 366 : 365;
        echo "<div class='result-item'>
                <div class='result-title'>Day of Year:</div>
                <div>{$datetime->format('z')} (out of {$daysInYear} days)</div>
              </div>";
        
        echo "<div class='result-item'>
                <div class='result-title'>Week Number:</div>
                <div>Week {$datetime->format('W')} of the year</div>
              </div>";
            ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>Document</title>
</head>
<body>
    <header class="welcome-header">
        <ul>
            <li>Home</li>
            <li><a href="index.php">Logout</a></li>
        </ul>
    </header>
   <div class="nanay-sheesh">
        <div class="welcome-container">
            <h2 class="title">Welcome</h2>
            <p class="subtitle">You have successfully logged in!</p>
            <p  class="subtitle"><?php
            include 'db.php';
            if(isset($_SESSION['username'])){
                echo $_SESSION['username'];
            }else{
                echo 'wala laman';
            }
            ?></p>
        </div>
        <div class="sir-container">
            <h2 class="sir-title">Hello, sir</h2>
            <p class="sir-subtitle">check this out</p>
            <p class="sir-subtitle">Here we go</p>
        </div>
   </div>
   </div>
</body>
</html>
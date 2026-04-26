<?php
include('db.php');

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $sql = "INSERT INTO user(username, email, password) VALUES ('$username', '$email', '$password')";

    if ($conn->query($sql) === TRUE) {
        $message = "Registration successful!";
    } else {
        $message = "Error: " . $sql . "<br>" . $conn->error;
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
    <link rel="stylesheet" type="text/css" href="style.css">
</head>
<body>
    <div class="container">
        <h2>Register</h2>
        <?php if ($message): ?>
            <div style="text-align: center; color: #000000; font-family:Verdana, Geneva, Tahoma, sans-serif;" class="<?php echo strpos($message, 'successful') !== false ? 'success' : 'error'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        <form method="post" action="">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" placeholder="Enter username" required>
            <label for="email">Email</label>
            <input type="email" id="email" name="email" placeholder="Enter email" required >
            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="Enter password"  required>
            <button type="submit">Register</button>
        </form>
        <p>Already have an account?<a href="index.php" class="redirect">Login here</a></p>
    </div>
</body>
</html>
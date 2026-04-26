<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ToDo List</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>To-Do List</h1>
            <form action="index.php" method="POST">
                <input type="text" name="task" placeholder="Add a new task" required>
                <button type="submit" name="addTask">Add Task</button>
            </form>

            <ul>
                <?php 
                    session_start();

                    if (!isset($_SESSION['tasks'])) {
                        $_SESSION['tasks'] = [];
                    }

                    if (isset($_POST['addTask'])) {
                        $task = trim($_POST['task']);
                        if (!empty($task)) {
                            array_push($_SESSION['tasks'], ['task' => $task, 'completed' => false]);
                        }
                    }
                    
                    if (isset($_GET['delete'])) {
                        $index = $_GET['delete'];
                        if (isset($_SESSION['tasks'][$index])) {
                            unset($_SESSION['tasks'][$index]);
                            $_SESSION['tasks'] = array_values($_SESSION['tasks']);
                        }
                    }

                    if (isset($_GET['toggle'])) {
                        $index = $_GET['toggle'];
                        if (isset($_SESSION['tasks'][$index])) {
                            $_SESSION['tasks'][$index]['completed'] = !$_SESSION['tasks'][$index]['completed'];
                        }
                    }

                    foreach ($_SESSION['tasks'] as $index => $task) {
                        echo '<li class ="' . ($task['completed'] ? 'completed' : '') . '">';
                        echo htmlspecialchars($task['task']);
                        echo ' <a href="index.php?toggle=' . $index .'">[' . ($task['completed'] ? 'Undo' : 'Complete') . ']</a>'; 
                        echo ' <a href="index.php?delete=' . $index . '">[Delete]</a>';
                        echo '</li>';
                        
                    }
                
                ?>
            </ul>
    </div>    
    <script src="script.js"></script>
</body>
</html>
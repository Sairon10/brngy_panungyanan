function deleteTask(index) {
    const taskElement = document.getElementById(`task-${index}`);
    taskElement.classList.add('fade-out');

    taskElement.addEventListener('animationend', () => {
        window.location.href = `index.php?delete=${index}`;
    });
}
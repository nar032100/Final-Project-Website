<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['user_role'] === 'teacher') {
    header("Location: teacher_dashboard.php");
} else {
    header("Location: student_dashboard.php");
}
exit();
?>
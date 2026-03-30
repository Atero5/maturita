<?php
session_start();

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    // Redirect based on role
    if ($_SESSION['role'] === 'admin') {
        header("Location: Admin.html");
    } elseif ($_SESSION['role'] === 'teacher') {
        header("Location: Teacher.html");
    } else {
        header("Location: User.html");
    }
    exit();
} else {
    // Not logged in, redirect to login page
    header("Location: Login.html");
    exit();
}
?>
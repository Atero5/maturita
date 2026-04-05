<?php
session_start();

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    // Redirect based on role
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin.html");
    } elseif ($_SESSION['role'] === 'teacher') {
        header("Location: teacher.html");
    } else {
        header("Location: user.html");
    }
    exit();
} else {
    // Not logged in, redirect to login page
    header("Location: login.html");
    exit();
}
?>
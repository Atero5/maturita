<?php
session_start();

// jen admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

$conn = new mysqli("localhost", "root", "", "vyletos");

if ($conn->connect_error) {
    die("Chyba připojení: " . $conn->connect_error);
}

$id = $_GET['id'];
$role = $_GET['role'];

// update role
$stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
$stmt->bind_param("si", $role, $id);
$stmt->execute();

$stmt->close();
$conn->close();

// zpět do admin panelu
header("Location: Admin.html");
exit();
?>
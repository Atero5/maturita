<?php
session_start();

// Load environment variables
$env = parse_ini_file(__DIR__ . '/.env');

// jen admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

$conn = new mysqli($env['DB_HOSTNAME'], $env['DB_USERNAME'], $env['DB_PASSWORD'], $env['DB_NAME']);

if ($conn->connect_error) {
    die("Chyba připojení: " . $conn->connect_error);
}

$id = $_GET['id'];
$role = $_GET['role'];

// update role
$stmt = $conn->prepare("UPDATE " . $env['USER_TABLE'] . " SET role = ? WHERE userId = ?");
$stmt->bind_param("si", $role, $id);
$stmt->execute();

$stmt->close();
$conn->close();

// zpět do admin panelu
header("Location: admin.html");
exit();
?>
<?php
session_start();
$env = parse_ini_file(__DIR__ . '/.env');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}
$conn = new mysqli($env['DB_HOSTNAME'], $env['DB_USERNAME'], $env['DB_PASSWORD'], $env['DB_NAME']);
if ($conn->connect_error) {
    die("Chyba připojení: " . $conn->connect_error);
}
$id = $_POST['id'] ?? null;
$class = $_POST['class'] ?? null;
if ($id && $class !== null) {
    $stmt = $conn->prepare("UPDATE " . $env['USER_TABLE'] . " SET class = ? WHERE userId = ?");
    $stmt->bind_param("si", $class, $id);
    $stmt->execute();
    $stmt->close();
}
$conn->close();
header("Location: admin.html");
exit();
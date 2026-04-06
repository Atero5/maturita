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

// zabránění smazání sebe sama
if ($id == $_SESSION['user_id']) {
    die("Nemůžeš smazat sám sebe!");
}

// smazání uživatele
$stmt = $conn->prepare("DELETE FROM " . $env['USER_TABLE'] . " WHERE userId = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

$stmt->close();
$conn->close();

// zpět do admin panelu
header("Location: admin.html");
exit();
?>
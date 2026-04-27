<?php
session_start();

// Načte hesla a údaje z .env souboru
$env = parse_ini_file(__DIR__ . '/.env');

// Zkontroluje, že je přihlášen admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

$conn = new mysqli($env['DB_HOSTNAME'], $env['DB_USERNAME'], $env['DB_PASSWORD'], $env['DB_NAME']);
if ($conn->connect_error) {
    die("Chyba připojení: " . $conn->connect_error);
}

// Ověří vstupy
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$class = isset($_POST['class']) ? trim($_POST['class']) : '';

if ($id <= 0) {
    header("Location: admin.html");
    exit();
}

// Aktualizuje třídu uživatele
$stmt = $conn->prepare("UPDATE " . $env['USER_TABLE'] . " SET class = ? WHERE userId = ?");
$stmt->bind_param("si", $class, $id);
$stmt->execute();
$stmt->close();
$conn->close();

// Zpět do admin panelu
header("Location: admin.html");
exit();

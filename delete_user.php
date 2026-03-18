<?php
session_start();

// 🔒 jen admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

$conn = new mysqli("localhost", "root", "", "vyletos");

if ($conn->connect_error) {
    die("Chyba připojení: " . $conn->connect_error);
}

$id = $_GET['id'];

// ❗ zabránění smazání sebe sama
if ($id == $_SESSION['user_id']) {
    die("Nemůžeš smazat sám sebe!");
}

// smazání uživatele
$stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

$stmt->close();
$conn->close();

// zpět do admin panelu
header("Location: Admin.php");
exit();
?>
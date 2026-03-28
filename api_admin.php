<?php
session_start();
header('Content-Type: application/json');

// Load environment variables
$env = parse_ini_file(__DIR__ . '/.env');

// 1. Ochrana – jen přihlášený admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403); // Zakázaný přístup
    echo json_encode(['error' => 'Nepovolený přístup']);
    exit();
}

$conn = new mysqli($env['DB_HOSTNAME'], $env['DB_USERNAME'], $env['DB_PASSWORD'], $env['DB_NAME']);
if ($conn->connect_error) {
    echo json_encode(['error' => 'Chyba připojení k DB']);
    exit();
}

// 2. Načtení dat o adminovi a seznamu uživatelů
$admin_email = $_SESSION['email'];
$users = [];

$result = $conn->query("SELECT id, email, role FROM " . $env['USER_TABLE'] . " WHERE role != 'admin'");
while($row = $result->fetch_assoc()) {
    $users[] = $row;
}

// 3. Odeslání dat do HTML
echo json_encode([
    'admin_email' => $admin_email,
    'users' => $users
]);

$conn->close();
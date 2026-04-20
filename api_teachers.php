<?php
session_start();
header('Content-Type: application/json');

$env = parse_ini_file(__DIR__ . '/.env');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Nepovolený přístup']);
    exit();
}

$conn = new mysqli($env['DB_HOSTNAME'], $env['DB_USERNAME'], $env['DB_PASSWORD'], $env['DB_NAME']);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    echo json_encode(['error' => 'Chyba připojení k DB']);
    exit();
}

$stmt = $conn->prepare("SELECT userId, email FROM " . $env['USER_TABLE'] . " WHERE role = 'teacher'");
$stmt->execute();
$result = $stmt->get_result();

$teachers = [];
while ($row = $result->fetch_assoc()) {
    $teachers[] = $row;
}

echo json_encode(['teachers' => $teachers]);

$stmt->close();
$conn->close();

<?php
session_start();
$env = parse_ini_file(__DIR__ . '/.env');

$conn = new mysqli($env['DB_HOSTNAME'], $env['DB_USERNAME'], $env['DB_PASSWORD'], $env['DB_NAME']);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Chyba připojení k databázi']);
    exit();
}

// Vrátit všechny unikátní třídy od studentů
$stmt = $conn->prepare("SELECT DISTINCT class FROM " . $env['USER_TABLE'] . " WHERE role = 'student' AND class IS NOT NULL AND class != '' ORDER BY class");
$stmt->execute();
$result = $stmt->get_result();

$classes = [];
while ($row = $result->fetch_assoc()) {
    $classes[] = $row['class'];
}

header('Content-Type: application/json');
echo json_encode($classes);

$stmt->close();
$conn->close();
?>

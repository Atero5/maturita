<?php
session_start();
header('Content-Type: application/json');

// Načte hesla a údaje z .env souboru
$env = parse_ini_file(__DIR__ . '/.env');

// Ochrana – přístup mají pouze přihlášení admini
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Nepovolený přístup']);
    exit();
}

// Připojí se k databázi
$conn = new mysqli($env['DB_HOSTNAME'], $env['DB_USERNAME'], $env['DB_PASSWORD'], $env['DB_NAME']);
if ($conn->connect_error) {
    echo json_encode(['error' => 'Chyba připojení k DB']);
    exit();
}

// Načte e-mail přihlášeného admina a připraví pole uživatelů
$admin_email = $_SESSION['email'];
$users = [];

// Parametry stránkování
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Vyhledávání podle e-mailu (volitelné)
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchParam = '%' . $search . '%';

// Celkový počet (pro stránkování)
$stmtTotal = $conn->prepare("SELECT COUNT(*) as total FROM " . $env['USER_TABLE'] . " WHERE role != 'admin' AND email LIKE ?");
$stmtTotal->bind_param('s', $searchParam);
$stmtTotal->execute();
$total_users = $stmtTotal->get_result()->fetch_assoc()['total'];
$stmtTotal->close();

// Načte uživatele pro aktuální stránku
$stmtUsers = $conn->prepare("SELECT userId, email, class, role FROM " . $env['USER_TABLE'] . " WHERE role != 'admin' AND email LIKE ? LIMIT ? OFFSET ?");
$stmtUsers->bind_param('sii', $searchParam, $limit, $offset);
$stmtUsers->execute();
$result = $stmtUsers->get_result();
while($row = $result->fetch_assoc()) {
    $users[] = $row;
}
$stmtUsers->close();


// Pošle seznam uživatelů a data pro stránkování jako JSON
echo json_encode([
    'admin_email' => $admin_email,
    'users' => $users,
    'total' => $total_users,
    'page' => $page,
    'limit' => $limit
]);

$conn->close();
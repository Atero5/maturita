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

// Pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Get paginated users
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where = "role != 'admin'";
if ($search !== '') {
    $where .= " AND email LIKE '%" . $conn->real_escape_string($search) . "%'";
}

// Total with filters
$total_result = $conn->query("SELECT COUNT(*) as total FROM " . $env['USER_TABLE'] . " WHERE $where");
$total_users = $total_result->fetch_assoc()['total'];

// Paginated with filters
$result = $conn->query("SELECT userId, email, class, role FROM " . $env['USER_TABLE'] . " WHERE $where LIMIT $limit OFFSET $offset");
while($row = $result->fetch_assoc()) {
    $users[] = $row;
}


// 3. Odeslání dat do HTML
echo json_encode([
    'admin_email' => $admin_email,
    'users' => $users,
    'total' => $total_users,
    'page' => $page,
    'limit' => $limit
]);

$conn->close();
<?php
session_start();
header('Content-Type: application/json');

// Load environment variables
$env = parse_ini_file(__DIR__ . '/.env');

// Kontrola: Uživatel musí být přihlášen a učitel/admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['teacher', 'admin'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Nepovolený přístup']);
    exit();
}

// Připojení k databázi
$conn = new mysqli($env['DB_HOSTNAME'], $env['DB_USERNAME'], $env['DB_PASSWORD'], $env['DB_NAME']);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Chyba připojení k DB']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

// ========== GET seznam studentů na výletu s jejich statusem platby ==========
if ($method === 'GET' && isset($_GET['tripId'])) {
    $tripId = (int)$_GET['tripId'];
    
    // Ověření, že učitel může vidět tento výlet
    $checkTrip = $conn->prepare("SELECT vyletId FROM " . $env['TRIPS_TABLE'] . " WHERE vyletId = ? AND (userId = ? OR CONCAT(', ', uciitele, ', ') LIKE CONCAT('%, ', ?, ', %'))");
    $teacher_email = $_SESSION['email'] ?? '';
    $checkTrip->bind_param("iss", $tripId, $_SESSION['user_id'], $teacher_email);
    $checkTrip->execute();
    
    if ($checkTrip->get_result()->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Nemáte oprávnění']);
        exit();
    }
    $checkTrip->close();
    
    // Načtení studentů na výletu s jejich statusem platby a odhlášení
    $query = "SELECT 
        u.userId,
        u.email,
        vt.tridy,
        CASE WHEN to_odh.id IS NOT NULL THEN 1 ELSE 0 END as odhlasen,
        COALESCE(tp.zaplaceno, 0) as zaplaceno,
        tp.id as platby_id
    FROM users u
    INNER JOIN vylety_tridy vt ON u.class = vt.tridy
    LEFT JOIN trip_odhlaseni to_odh ON to_odh.userId = u.userId AND to_odh.vyletId = ?
    LEFT JOIN trip_platby tp ON tp.userId = u.userId AND tp.vyletId = ?
    WHERE vt.vyletId = ? AND u.role = 'student'
    ORDER BY u.email";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['error' => 'Prepare error: ' . $conn->error]);
        exit();
    }
    
    $stmt->bind_param("iii", $tripId, $tripId, $tripId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = [
            'userId' => $row['userId'],
            'email' => $row['email'],
            'class' => $row['tridy'],
            'odhlasen' => (int)$row['odhlasen'],
            'zaplaceno' => (int)$row['zaplaceno'],
            'platby_id' => $row['platby_id']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'tripId' => $tripId,
        'students' => $students,
        'count' => count($students)
    ]);
    
    $stmt->close();
    $conn->close();
    exit();
}

// ========== PUT – aktualizace statusu platby ==========
if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $tripId = isset($input['tripId']) ? (int)$input['tripId'] : 0;
    $userId = isset($input['userId']) ? (int)$input['userId'] : 0;
    $zaplaceno = isset($input['zaplaceno']) ? (int)$input['zaplaceno'] : 0;
    
    if ($tripId <= 0 || $userId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Neplatné ID']);
        exit();
    }
    
    // Ověření, že učitel může upravit tento výlet
    $checkTrip = $conn->prepare("SELECT vyletId FROM " . $env['TRIPS_TABLE'] . " WHERE vyletId = ? AND (userId = ? OR CONCAT(', ', uciitele, ', ') LIKE CONCAT('%, ', ?, ', %'))");
    $teacher_email = $_SESSION['email'] ?? '';
    $checkTrip->bind_param("iss", $tripId, $_SESSION['user_id'], $teacher_email);
    $checkTrip->execute();
    
    if ($checkTrip->get_result()->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Nemáte oprávnění']);
        exit();
    }
    $checkTrip->close();
    
    // Aktualizace statusu platby v trip_platby
    // Používáme INSERT ... ON DUPLICATE KEY UPDATE aby se záznam vytvořil, pokud neexistuje
    $stmt = $conn->prepare("INSERT INTO trip_platby (vyletId, userId, zaplaceno) 
                            VALUES (?, ?, ?) 
                            ON DUPLICATE KEY UPDATE zaplaceno = ?");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Prepare error: ' . $conn->error]);
        exit();
    }
    
    $stmt->bind_param("iiii", $tripId, $userId, $zaplaceno, $zaplaceno);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Stav platby aktualizován']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Chyba: ' . $stmt->error]);
    }
    
    $stmt->close();
    $conn->close();
    exit();
}

// Ostatní metody nejsou podporovány
http_response_code(405);
echo json_encode(['error' => 'Metoda není podporována']);
$conn->close();
?>

<?php
session_start();
header('Content-Type: application/json');

$env = parse_ini_file(__DIR__ . '/.env');
$conn = new mysqli($env['DB_HOSTNAME'], $env['DB_USERNAME'], $env['DB_PASSWORD'], $env['DB_NAME']);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Chyba připojení k DB']);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nepřihlášen']);
    $conn->close();
    exit();
}

$userId = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// ========== GET – stav odhlášení + seznam žáků (pro učitele) ==========
if ($method === 'GET') {
    $vyletId = isset($_GET['vyletId']) ? (int)$_GET['vyletId'] : 0;

    if ($vyletId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Neplatné ID výletu']);
        $conn->close();
        exit();
    }

    if ($role === 'student') {
        // Zjistit, jestli je student odhlášen
        $stmt = $conn->prepare("SELECT id FROM " . $env['ODHLASENI_TABLE'] . " WHERE vyletId = ? AND userId = ?");
        $stmt->bind_param("ii", $vyletId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $odhlasен = $result->num_rows > 0;
        $stmt->close();

        echo json_encode(['success' => true, 'odhlasen' => $odhlasен]);

    } elseif ($role === 'teacher' || $role === 'admin') {
        // Načíst třídy výletu
        $stmt = $conn->prepare("SELECT tridy FROM " . $env['TRIPS_CLASSES_TABLE'] . " WHERE vyletId = ?");
        $stmt->bind_param("i", $vyletId);
        $stmt->execute();
        $result = $stmt->get_result();
        $tridy = [];
        while ($row = $result->fetch_assoc()) {
            $tridy[] = $row['tridy'];
        }
        $stmt->close();

        if (empty($tridy)) {
            echo json_encode(['success' => true, 'ucastnici' => [], 'odhlaseni' => []]);
            $conn->close();
            exit();
        }

        // Načíst všechny studenty z těchto tříd
        $placeholders = implode(',', array_fill(0, count($tridy), '?'));
        $types = str_repeat('s', count($tridy));
        $stmt = $conn->prepare(
            "SELECT u.userId, u.email, u.class FROM " . $env['USER_TABLE'] . " u
             WHERE u.role = 'student' AND u.class IN ($placeholders)
             ORDER BY u.class, u.email"
        );
        $stmt->bind_param($types, ...$tridy);
        $stmt->execute();
        $result = $stmt->get_result();
        $vsichni = [];
        while ($row = $result->fetch_assoc()) {
            $vsichni[] = $row;
        }
        $stmt->close();

        // Načíst odhlášené
        $stmt = $conn->prepare("SELECT userId FROM " . $env['ODHLASENI_TABLE'] . " WHERE vyletId = ?");
        $stmt->bind_param("i", $vyletId);
        $stmt->execute();
        $result = $stmt->get_result();
        $odhlaseniIds = [];
        while ($row = $result->fetch_assoc()) {
            $odhlaseniIds[] = (int)$row['userId'];
        }
        $stmt->close();

        $ucastnici = [];
        $odhlaseni = [];
        foreach ($vsichni as $student) {
            $entry = [
                'userId' => (int)$student['userId'],
                'email'  => $student['email'],
                'class'  => $student['class'],
            ];
            if (in_array((int)$student['userId'], $odhlaseniIds)) {
                $odhlaseni[] = $entry;
            } else {
                $ucastnici[] = $entry;
            }
        }

        echo json_encode(['success' => true, 'ucastnici' => $ucastnici, 'odhlaseni' => $odhlaseni]);

    } else {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Nemáte oprávnění']);
    }

    $conn->close();
    exit();
}

// ========== POST – odhlásit se (student) ==========
if ($method === 'POST') {
    if ($role !== 'student') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Pouze studenti se mohou odhlásit']);
        $conn->close();
        exit();
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $vyletId = isset($input['vyletId']) ? (int)$input['vyletId'] : 0;

    if ($vyletId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Neplatné ID výletu']);
        $conn->close();
        exit();
    }

    // Ověřit, že student patří do třídy tohoto výletu
    $class = $_SESSION['class'] ?? '';
    $stmt = $conn->prepare("SELECT vyletId FROM " . $env['TRIPS_CLASSES_TABLE'] . " WHERE vyletId = ? AND tridy = ?");
    $stmt->bind_param("is", $vyletId, $class);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Nemáte přístup k tomuto výletu']);
        $stmt->close();
        $conn->close();
        exit();
    }
    $stmt->close();

    $stmt = $conn->prepare("INSERT IGNORE INTO " . $env['ODHLASENI_TABLE'] . " (vyletId, userId) VALUES (?, ?)");
    $stmt->bind_param("ii", $vyletId, $userId);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Byl/a jsi odhlášen/a z výletu']);
    $conn->close();
    exit();
}

// ========== DELETE – přihlásit se zpět (student) ==========
if ($method === 'DELETE') {
    if ($role !== 'student') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Nemáte oprávnění']);
        $conn->close();
        exit();
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $vyletId = isset($input['vyletId']) ? (int)$input['vyletId'] : 0;

    if ($vyletId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Neplatné ID výletu']);
        $conn->close();
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM " . $env['ODHLASENI_TABLE'] . " WHERE vyletId = ? AND userId = ?");
    $stmt->bind_param("ii", $vyletId, $userId);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Byl/a jsi přihlášen/a zpět na výlet']);
    $conn->close();
    exit();
}

echo json_encode(['success' => false, 'message' => 'Neplatný požadavek']);
$conn->close();

<?php
session_start();
header('Content-Type: application/json');

// Load environment variables
$env = parse_ini_file(__DIR__ . '/.env');

// 1. Ochrana – jen přihlášený admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Nepovolený přístup']);
    exit();
}

// 2. Připojení k databázi
$conn = new mysqli($env['DB_HOSTNAME'], $env['DB_USERNAME'], $env['DB_PASSWORD'], $env['DB_NAME']);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Chyba připojení k DB']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

// ========== GET – načtení výletů ==========
if ($method === 'GET') {

    // Detail jednoho výletu
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $stmt = $conn->prepare("SELECT vyletId, nazev_vyletu, adresa_ubytovani, delka_pobytu, celkova_cena, misto_odjezdu_tam, cas_odjezdu_tam, dopravni_prostredek_tam FROM " . $env['TRIPS_TABLE'] . " WHERE vyletId = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            echo json_encode([
                'success' => true,
                'trip' => [
                    'id' => $row['vyletId'],
                    'nazev' => $row['nazev_vyletu'],
                    'adresa' => $row['adresa_ubytovani'],
                    'delka_pobytu' => $row['delka_pobytu'],
                    'cena' => $row['celkova_cena'],
                    'misto' => $row['misto_odjezdu_tam'],
                    'cas' => $row['cas_odjezdu_tam'],
                    'doprava' => $row['dopravni_prostredek_tam']
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Výlet nenalezen']);
        }
        $stmt->close();
        $conn->close();
        exit();
    }

    // Seznam všech výletů s paginací
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 15;
    $offset = ($page - 1) * $limit;

    // Celkový počet
    $total_result = $conn->query("SELECT COUNT(*) as total FROM " . $env['TRIPS_TABLE']);
    if (!$total_result) {
        echo json_encode(['success' => false, 'message' => 'Chyba DB: ' . $conn->error]);
        $conn->close();
        exit();
    }
    $total = $total_result->fetch_assoc()['total'];

    // Výlety s třídami
    $query = "SELECT v.vyletId, v.nazev_vyletu, v.delka_pobytu, v.celkova_cena, v.misto_odjezdu_tam,
              GROUP_CONCAT(vt.tridy ORDER BY vt.tridy SEPARATOR ', ') as tridy
              FROM " . $env['TRIPS_TABLE'] . " v
              LEFT JOIN vylety_tridy vt ON v.vyletId = vt.vyletId
              GROUP BY v.vyletId
              ORDER BY v.vyletId DESC
              LIMIT ? OFFSET ?";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        // Fallback bez tříd pokud tabulka vylety_tridy neexistuje
        $query = "SELECT vyletId, nazev_vyletu, delka_pobytu, celkova_cena, misto_odjezdu_tam
                  FROM " . $env['TRIPS_TABLE'] . "
                  ORDER BY vyletId DESC
                  LIMIT ? OFFSET ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();

        $trips = [];
        while ($row = $result->fetch_assoc()) {
            $trips[] = [
                'id' => $row['vyletId'],
                'nazev' => $row['nazev_vyletu'],
                'delka_pobytu' => $row['delka_pobytu'],
                'cena' => $row['celkova_cena'] ?? '0',
                'misto' => $row['misto_odjezdu_tam'],
                'tridy' => ''
            ];
        }
    } else {
        $stmt->bind_param("ii", $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();

        $trips = [];
        while ($row = $result->fetch_assoc()) {
            $trips[] = [
                'id' => $row['vyletId'],
                'nazev' => $row['nazev_vyletu'],
                'delka_pobytu' => $row['delka_pobytu'],
                'cena' => $row['celkova_cena'] ?? '0',
                'misto' => $row['misto_odjezdu_tam'],
                'tridy' => $row['tridy'] ?? ''
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'trips' => $trips,
        'total' => (int)$total,
        'page' => $page,
        'limit' => $limit
    ]);

    $stmt->close();
}

// ========== DELETE – smazání výletu ==========
elseif ($method === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = isset($input['id']) ? (int)$input['id'] : 0;

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Neplatné ID']);
        $conn->close();
        exit();
    }

    // Smažání výletu (CASCADE smaže i výlety_tridy a trip_photos)
    $stmt = $conn->prepare("DELETE FROM " . $env['TRIPS_TABLE'] . " WHERE vyletId = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Výlet smazán']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Chyba při mazání']);
    }
    $stmt->close();
}

// ========== PUT – úprava výletu ==========
elseif ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = isset($input['id']) ? (int)$input['id'] : 0;

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Neplatné ID']);
        $conn->close();
        exit();
    }

    $nazev = $input['nazev'] ?? '';
    $adresa = $input['adresa'] ?? '';
    $delka = $input['delka_pobytu'] ?? '';
    $misto = $input['misto'] ?? '';
    $cas = $input['cas'] ?? '';
    $doprava = $input['doprava'] ?? '';
    $cena = !empty($input['cena']) ? $input['cena'] : 0;

    $stmt = $conn->prepare("UPDATE " . $env['TRIPS_TABLE'] . " SET nazev_vyletu = ?, adresa_ubytovani = ?, delka_pobytu = ?, misto_odjezdu_tam = ?, cas_odjezdu_tam = ?, dopravni_prostredek_tam = ?, celkova_cena = ? WHERE vyletId = ?");
    $stmt->bind_param("ssssssdi", $nazev, $adresa, $delka, $misto, $cas, $doprava, $cena, $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Výlet upraven']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Chyba při úpravě']);
    }
    $stmt->close();
}

$conn->close();
?>

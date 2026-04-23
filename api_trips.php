<?php
session_start();
header('Content-Type: application/json');

// Load environment variables
$env = parse_ini_file(__DIR__ . '/.env');

// Kontrola: Uživatel musí být přihlášen
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
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

// ========== GET detail – načtení jednoho výletu ==========
if ($method === 'GET' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    // Učitel/admin vidí vlastní výlety, student vidí výlety přiřazené jeho třídě
    if ($_SESSION['role'] === 'student' && isset($_SESSION['class'])) {
        $stmt = $conn->prepare("SELECT v.*, u.email AS creator_email FROM " . $env['TRIPS_TABLE'] . " v LEFT JOIN " . $env['USER_TABLE'] . " u ON v.userId = u.userId INNER JOIN " . $env['TRIPS_CLASSES_TABLE'] . " vt ON v.vyletId = vt.vyletId WHERE v.vyletId = ? AND vt.tridy = ?");
        $stmt->bind_param("is", $id, $_SESSION['class']);
    } elseif ($_SESSION['role'] === 'admin') {
        $stmt = $conn->prepare("SELECT v.*, u.email AS creator_email FROM " . $env['TRIPS_TABLE'] . " v LEFT JOIN " . $env['USER_TABLE'] . " u ON v.userId = u.userId WHERE v.vyletId = ?");
        $stmt->bind_param("i", $id);
    } else {
        $stmt = $conn->prepare("SELECT v.*, u.email AS creator_email FROM " . $env['TRIPS_TABLE'] . " v LEFT JOIN " . $env['USER_TABLE'] . " u ON v.userId = u.userId WHERE v.vyletId = ? AND v.userId = ?");
        $stmt->bind_param("ii", $id, $_SESSION['user_id']);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Načtení tříd
        $tridyStmt = $conn->prepare("SELECT tridy FROM " . $env['TRIPS_CLASSES_TABLE'] . " WHERE vyletId = ?");
        $tridyStmt->bind_param("i", $id);
        $tridyStmt->execute();
        $tridyResult = $tridyStmt->get_result();
        $tridy = [];
        while ($t = $tridyResult->fetch_assoc()) {
            $tridy[] = $t['tridy'];
        }
        $tridyStmt->close();

        // Načtení stravy z vylety_strava
        $stravaStmt = $conn->prepare("SELECT den, typ_jidla, typ, nazev_restaurace, adresa_restaurace, kontakt_restaurace, cas, vlastni_text FROM " . $env['TRIPS_MEALS_TABLE'] . " WHERE vyletId = ? ORDER BY den, FIELD(typ_jidla, 'snidane', 'obed', 'vecere')");
        $stravaStmt->bind_param("i", $id);
        $stravaStmt->execute();
        $stravaResult = $stravaStmt->get_result();
        $strava = [];
        while ($s = $stravaResult->fetch_assoc()) {
            $strava[] = $s;
        }
        $stravaStmt->close();

        echo json_encode([
            'success' => true,
            'trip' => [
                'id' => $row['vyletId'],
                'nazev_vyletu' => $row['nazev_vyletu'],
                'adresa_ubytovani' => $row['adresa_ubytovani'],
                'delka_pobytu' => $row['delka_pobytu'],
                'misto_odjezdu_tam' => $row['misto_odjezdu_tam'],
                'cas_odjezdu_tam' => $row['cas_odjezdu_tam'],
                'dopravni_prostredek_tam' => $row['dopravni_prostredek_tam'],
                'misto_odjezdu_zpet' => $row['misto_odjezdu_zpet'],
                'cas_odjezdu_zpet' => $row['cas_odjezdu_zpet'],
                'dopravni_prostredek_zpet' => $row['dopravni_prostredek_zpet'],
                'harmonogram' => $row['harmonogram'],
                'uciitele' => $row['uciitele'],
                'celkova_cena' => $row['celkova_cena'],
                'cislo_uctu' => $row['cislo_uctu'],
                'datum_vytvoreni' => $row['datum_vytvoreni'],
                'creator_email' => $row['creator_email'] ?? null,
                'strava' => $strava,
                'tridy' => $tridy
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Výlet nenalezen nebo nemáte oprávnění']);
    }
    $stmt->close();
    $conn->close();
    exit();
}

// ========== PUT – úprava výletu (učitel může upravit jen své) ==========
if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = isset($input['id']) ? (int)$input['id'] : 0;

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Neplatné ID']);
        $conn->close();
        exit();
    }

    // Ověření vlastnictví
    $check = $conn->prepare("SELECT vyletId FROM " . $env['TRIPS_TABLE'] . " WHERE vyletId = ? AND userId = ?");
    $check->bind_param("ii", $id, $_SESSION['user_id']);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Nemáte oprávnění upravit tento výlet']);
        $check->close();
        $conn->close();
        exit();
    }
    $check->close();

    $nazev = $input['nazev_vyletu'] ?? '';
    $adresa = $input['adresa_ubytovani'] ?? '';
    $delka = $input['delka_pobytu'] ?? '';
    $misto_tam = $input['misto_odjezdu_tam'] ?? '';
    $cas_tam = $input['cas_odjezdu_tam'] ?? '';
    $doprava_tam = $input['dopravni_prostredek_tam'] ?? '';
    $misto_zpet = $input['misto_odjezdu_zpet'] ?? '';
    $cas_zpet = $input['cas_odjezdu_zpet'] ?? '';
    $doprava_zpet = $input['dopravni_prostredek_zpet'] ?? '';
    $harmonogram = $input['harmonogram'] ?? null;
    $ucitele = $input['uciitele'] ?? null;
    $cena = !empty($input['celkova_cena']) ? $input['celkova_cena'] : 0;
    $cislo_uctu = $input['cislo_uctu'] ?? null;

    $stmt = $conn->prepare("UPDATE " . $env['TRIPS_TABLE'] . " SET 
        nazev_vyletu = ?, adresa_ubytovani = ?, delka_pobytu = ?,
        misto_odjezdu_tam = ?, cas_odjezdu_tam = ?, dopravni_prostredek_tam = ?,
        misto_odjezdu_zpet = ?, cas_odjezdu_zpet = ?, dopravni_prostredek_zpet = ?,
        harmonogram = ?, uciitele = ?,
        celkova_cena = ?, cislo_uctu = ?
        WHERE vyletId = ? AND userId = ?");
    $stmt->bind_param(
        "sssssssssssdsii",
        $nazev, $adresa, $delka,
        $misto_tam, $cas_tam, $doprava_tam,
        $misto_zpet, $cas_zpet, $doprava_zpet,
        $harmonogram, $ucitele,
        $cena, $cislo_uctu,
        $id, $_SESSION['user_id']
    );

    if ($stmt->execute()) {
        // Aktualizace tříd
        $delTridy = $conn->prepare("DELETE FROM " . $env['TRIPS_CLASSES_TABLE'] . " WHERE vyletId = ?");
        $delTridy->bind_param("i", $id);
        $delTridy->execute();
        $delTridy->close();

        $tridy = $input['tridy'] ?? [];
        foreach ($tridy as $trida) {
            $ins = $conn->prepare("INSERT INTO " . $env['TRIPS_CLASSES_TABLE'] . " (vyletId, tridy) VALUES (?, ?)");
            $ins->bind_param("is", $id, $trida);
            $ins->execute();
            $ins->close();
        }

        // Aktualizace stravy
        $delStrava = $conn->prepare("DELETE FROM " . $env['TRIPS_MEALS_TABLE'] . " WHERE vyletId = ?");
        $delStrava->bind_param("i", $id);
        $delStrava->execute();
        $delStrava->close();

        $strava = $input['strava'] ?? [];
        $insStrava = $conn->prepare("INSERT INTO " . $env['TRIPS_MEALS_TABLE'] . " 
            (vyletId, den, typ_jidla, typ, nazev_restaurace, adresa_restaurace, kontakt_restaurace, cas, vlastni_text) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($strava as $den => $meals) {
            foreach ($meals as $typ_jidla => $meal) {
                $typ = $meal['typ'] ?? 'vlastni';
                $nazev_rest = $meal['nazev_restaurace'] ?? null;
                $adresa_rest = $meal['adresa_restaurace'] ?? null;
                $kontakt_rest = $meal['kontakt_restaurace'] ?? null;
                $cas = $meal['cas'] ?? null;
                $vlastni_text = $meal['vlastni_text'] ?? null;
                
                $insStrava->bind_param(
                    "iisssssss",
                    $id, $den, $typ_jidla, $typ,
                    $nazev_rest, $adresa_rest, $kontakt_rest, $cas, $vlastni_text
                );
                $insStrava->execute();
            }
        }
        $insStrava->close();

        echo json_encode(['success' => true, 'message' => 'Výlet byl úspěšně upraven']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Chyba při úpravě výletu']);
    }
    $stmt->close();
    $conn->close();
    exit();
}

// ========== DELETE – smazání výletu (učitel může smazat jen své) ==========
if ($method === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = isset($input['id']) ? (int)$input['id'] : 0;

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Neplatné ID']);
        $conn->close();
        exit();
    }

    // Ověření, že výlet patří přihlášenému učiteli
    $check = $conn->prepare("SELECT vyletId FROM " . $env['TRIPS_TABLE'] . " WHERE vyletId = ? AND userId = ?");
    $check->bind_param("ii", $id, $_SESSION['user_id']);
    $check->execute();
    $checkResult = $check->get_result();

    if ($checkResult->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Nemáte oprávnění smazat tento výlet']);
        $check->close();
        $conn->close();
        exit();
    }
    $check->close();

    // Smažání výletu (CASCADE smaže i výlety_tridy a trip_photos)
    $stmt = $conn->prepare("DELETE FROM " . $env['TRIPS_TABLE'] . " WHERE vyletId = ? AND userId = ?");
    $stmt->bind_param("ii", $id, $_SESSION['user_id']);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Výlet byl úspěšně smazán']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Chyba při mazání výletu']);
    }
    $stmt->close();
    $conn->close();
    exit();
}

$trips = [];

// 3. Načtení výletů
if ($_SESSION['role'] === 'student' && isset($_SESSION['class'])) {
    // Pro studenty: pouze výlety přiřazené k jejich třídě
    $query = "SELECT 
        v.vyletId, 
        v.userId, 
        v.nazev_vyletu, 
        v.adresa_ubytovani, 
        v.delka_pobytu, 
        v.celkova_cena,
        v.misto_odjezdu_tam,
        v.cas_odjezdu_tam,
        v.dopravni_prostredek_tam,
        v.cas_odjezdu_zpet,
        (SELECT COUNT(*) FROM " . $env['PHOTOS_TABLE'] . " tp WHERE tp.vyletId = v.vyletId) AS photo_count
    FROM " . $env['TRIPS_TABLE'] . " v
    INNER JOIN " . $env['TRIPS_CLASSES_TABLE'] . " vt ON v.vyletId = vt.vyletId
    WHERE vt.tridy = ?
    ORDER BY v.vyletId DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $_SESSION['class']);
    $stmt->execute();
    $result = $stmt->get_result();
} elseif ($_SESSION['role'] === 'teacher') {
    // Pro učitele: pouze výlety, které sám vytvořil
    $query = "SELECT 
        vyletId, 
        userId, 
        nazev_vyletu, 
        adresa_ubytovani, 
        delka_pobytu, 
        celkova_cena,
        misto_odjezdu_tam,
        cas_odjezdu_tam,
        dopravni_prostredek_tam,
        cas_odjezdu_zpet,
        (SELECT COUNT(*) FROM " . $env['PHOTOS_TABLE'] . " tp WHERE tp.vyletId = " . $env['TRIPS_TABLE'] . ".vyletId) AS photo_count
    FROM " . $env['TRIPS_TABLE'] . " 
    WHERE userId = ?
    ORDER BY vyletId DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Pro adminy: všechny výlety
    $query = "SELECT 
        vyletId, 
        userId, 
        nazev_vyletu, 
        adresa_ubytovani, 
        delka_pobytu, 
        celkova_cena,
        misto_odjezdu_tam,
        cas_odjezdu_tam,
        dopravni_prostredek_tam,
        cas_odjezdu_zpet,
        (SELECT COUNT(*) FROM " . $env['PHOTOS_TABLE'] . " tp WHERE tp.vyletId = " . $env['TRIPS_TABLE'] . ".vyletId) AS photo_count
    FROM " . $env['TRIPS_TABLE'] . " 
    ORDER BY vyletId DESC";

    $result = $conn->query($query);
}

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $trips[] = [
            'id' => $row['vyletId'],
            'nazev' => $row['nazev_vyletu'],
            'delka_pobytu' => $row['delka_pobytu'],
            'cena' => $row['celkova_cena'] ?? '0',
            'adresa' => $row['adresa_ubytovani'],
            'misto' => $row['misto_odjezdu_tam'],
            'cas' => $row['cas_odjezdu_tam'],
            'doprava' => $row['dopravni_prostredek_tam'],
            'cas_odjezdu_zpet' => $row['cas_odjezdu_zpet'],
            'photo_count' => (int)($row['photo_count'] ?? 0)
        ];
    }
}

// 4. Odeslání dat
echo json_encode([
    'success' => true,
    'trips' => $trips,
    'count' => count($trips)
]);

$conn->close();
?>

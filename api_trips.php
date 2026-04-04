<?php
session_start();
header('Content-Type: application/json');

// Load environment variables
$env = parse_ini_file(__DIR__ . '/.env');

// 1. Kontrola: Uživatel musí být přihlášen
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
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
        v.dopravni_prostredek_tam
    FROM " . $env['TRIPS_TABLE'] . " v
    INNER JOIN vylety_tridy vt ON v.vyletId = vt.vyletId
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
        dopravni_prostredek_tam
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
        dopravni_prostredek_tam
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
            'doprava' => $row['dopravni_prostredek_tam']
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

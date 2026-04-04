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
// Pro všechny přihlášené uživatele: všechny výlety
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

<?php
session_start();
header('Content-Type: application/json');

// Load environment variables
$env = parse_ini_file(__DIR__ . '/.env');

// 1. Ochrana – jen přihlášený uživatel může ukládat
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Chyba: Nejste přihlášen.']);
    exit;
}

// 2. Připojení k databázi
$conn = new mysqli($env['DB_HOSTNAME'], $env['DB_USERNAME'], $env['DB_PASSWORD'], $env['DB_NAME']);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Chyba připojení k DB.']);
    exit;
}

// 3. Načtení dat z POST (používáme ternární operátor, aby v DB nebyly chyby, když je pole prázdné)
$nazev_vyletu = $_POST['nazev_vyletu'] ?? '';
$adresa_ubytovani = $_POST['adresa_ubytovani'] ?? null;
$delka_pobytu = $_POST['delka_pobytu'] ?? null;

$misto_tam = $_POST['misto_odjezdu_tam'] ?? null;
$cas_tam = $_POST['cas_odjezdu_tam'] ?? null;
$doprava_tam = $_POST['dopravni_prostredek_tam'] ?? null;

$misto_zpet = $_POST['misto_odjezdu_zpet'] ?? null;
$cas_zpet = $_POST['cas_odjezdu_zpet'] ?? null;
$doprava_zpet = $_POST['dopravni_prostredek_zpet'] ?? null;

// Snídaně
$typ_snidane = $_POST['typ_snidane'] ?? 'vlastni';
$nazev_rest_snid = $_POST['nazev_restaurace_snidane'] ?? null;
$adr_rest_snid = $_POST['adresa_restaurace_snidane'] ?? null;
$cas_snidane = $_POST['cas_snidane'] ?? null;

// Oběd
$typ_obeda = $_POST['typ_obeda'] ?? 'vlastni';
$nazev_rest_obed = $_POST['nazev_restaurace_obed'] ?? null;
$adr_rest_obed = $_POST['adresa_restaurace_obed'] ?? null;
$cas_obeda = $_POST['cas_obeda'] ?? null;

// Večeře
// $var ?? "fallback_value" znamená: pokud $var existuje a není null,
// použijeme jeho hodnotu, jinak použijeme "fallback_value"
// if ($var is set and not null) { use $var } else { use "fallback_value" }
$typ_vecere = $_POST['typ_vecere'] ?? 'vlastni';
$nazev_rest_vece = $_POST['nazev_restaurace_vecere'] ?? null;
$adr_rest_vece = $_POST['adresa_restaurace_vecere'] ?? null;
$cas_vecere = $_POST['cas_vecere'] ?? null;

// Finance
$cena = !empty($_POST['celkova_cena']) ? $_POST['celkova_cena'] : 0;
$cislo_uctu = $_POST['cislo_uctu'] ?? null;

// 4. Příprava SQL dotazu (Prepared Statement)
$sql = "INSERT INTO " . $env['TRIPS_TABLE'] . " (
    nazev_vyletu, adresa_ubytovani, delka_pobytu, 
    misto_odjezdu_tam, cas_odjezdu_tam, dopravni_prostredek_tam, 
    misto_odjezdu_zpet, cas_odjezdu_zpet, dopravni_prostredek_zpet, 
    typ_snidane, nazev_restaurace_snidane, adresa_restaurace_snidane, cas_snidane, 
    typ_obeda, nazev_restaurace_obed, adresa_restaurace_obed, cas_obeda, 
    typ_vecere, nazev_restaurace_vecere, adresa_restaurace_vecere, cas_vecere, 
    celkova_cena, cislo_uctu
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

// "sssssssssssssssssssssds" znamená: 21x string (s), 1x double/decimal (d), 1x string (s)
$stmt->bind_param(
    "sssssssssssssssssssssds",
    $nazev_vyletu, $adresa_ubytovani, $delka_pobytu,
    $misto_tam, $cas_tam, $doprava_tam,
    $misto_zpet, $cas_zpet, $doprava_zpet,
    $typ_snidane, $nazev_rest_snid, $adr_rest_snid, $cas_snidane,
    $typ_obeda, $nazev_rest_obed, $adr_rest_obed, $cas_obeda,
    $typ_vecere, $nazev_rest_vece, $adr_rest_vece, $cas_vecere,
    $cena, $cislo_uctu
);

// 5. Provedení a odeslání výsledku zpět do HTML
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Výlet byl úspěšně naplánován a uložen!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Chyba při ukládání: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>


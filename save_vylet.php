<?php
session_start();
header('Content-Type: application/json');

// Load environment variables
$env = parse_ini_file(__DIR__ . '/.env');

// Ochrana – jen přihlášený uživatel může ukládat
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Chyba: Nejste přihlášen.']);
    exit;
}

// Připojení k databázi
$conn = new mysqli($env['DB_HOSTNAME'], $env['DB_USERNAME'], $env['DB_PASSWORD'], $env['DB_NAME']);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Chyba připojení k DB.']);
    exit;
}

// Načtení dat z POST
$nazev_vyletu = $_POST['nazev_vyletu'] ?? '';
$adresa_ubytovani = $_POST['adresa_ubytovani'] ?? null;
$delka_pobytu = $_POST['delka_pobytu'] ?? null;
if ($delka_pobytu === 'jine') {
    $delka_pobytu = $_POST['delka_pobytu_custom'] ?? null;
}

$misto_tam = $_POST['misto_odjezdu_tam'] ?? null;
$cas_tam = $_POST['cas_odjezdu_tam'] ?? null;
$doprava_tam = $_POST['dopravni_prostredek_tam'] ?? null;
if ($doprava_tam === 'jine') {
    $doprava_tam = $_POST['dopravni_prostredek_tam_custom'] ?? null;
}

$misto_zpet = $_POST['misto_odjezdu_zpet'] ?? null;
$cas_zpet = $_POST['cas_odjezdu_zpet'] ?? null;
$doprava_zpet = $_POST['dopravni_prostredek_zpet'] ?? null;
if ($doprava_zpet === 'jine') {
    $doprava_zpet = $_POST['dopravni_prostredek_zpet_custom'] ?? null;
}

$harmonogram = $_POST['harmonogram'] ?? null;

// Uložit učitele: vždy přidat zakládajícího učitele jako prvního
$creator_email = $_SESSION['email'] ?? '';
$ostatni = $_POST['uciitele'] ?? '';
if (!empty($ostatni)) {
    $ucitele = $creator_email . ', ' . $ostatni;
} else {
    $ucitele = $creator_email;
}

// Finance
$cena = !empty($_POST['celkova_cena']) ? $_POST['celkova_cena'] : 0;
$cislo_uctu = $_POST['cislo_uctu'] ?? null;

// Příprava SQL dotazu
$sql = "INSERT INTO " . $env['TRIPS_TABLE'] . " (
    userId, nazev_vyletu, adresa_ubytovani, delka_pobytu, 
    misto_odjezdu_tam, cas_odjezdu_tam, dopravni_prostredek_tam, 
    misto_odjezdu_zpet, cas_odjezdu_zpet, dopravni_prostredek_zpet, 
    harmonogram, uciitele,
    celkova_cena, cislo_uctu
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

$user_id = $_SESSION['user_id'];
$stmt->bind_param(
    "isssssssssssds",
    $user_id, $nazev_vyletu, $adresa_ubytovani, $delka_pobytu,
    $misto_tam, $cas_tam, $doprava_tam,
    $misto_zpet, $cas_zpet, $doprava_zpet,
    $harmonogram, $ucitele,
    $cena, $cislo_uctu
);

// Provedení a odeslání výsledku zpět do HTML
if ($stmt->execute()) {
    // Uložení tříd do tabulky vylety_tridy
    $vylet_id = $conn->insert_id;
    $tridy = $_POST['tridy'] ?? [];

    foreach ($tridy as $trida) {
        $stmt2 = $conn->prepare("INSERT INTO vylety_tridy (vyletId, tridy) VALUES (?, ?)");
        $stmt2->bind_param("is", $vylet_id, $trida);
        $stmt2->execute();
        $stmt2->close();
    }

    // Uložení stravy do tabulky vylety_strava
    $strava = $_POST['strava'] ?? [];
    foreach ($strava as $den => $meals) {
        foreach ($meals as $typ_jidla => $data) {
            $typ = $data['typ'] ?? 'vlastni';
            $nazev_rest = $data['nazev_restaurace'] ?? null;
            $adresa_rest = $data['adresa_restaurace'] ?? null;
            $kontakt_rest = $data['kontakt_restaurace'] ?? null;
            $cas = $data['cas'] ?? null;
            $vlastni_text = $data['vlastni_text'] ?? null;

            $stmtS = $conn->prepare("INSERT INTO vylety_strava (vyletId, den, typ_jidla, typ, nazev_restaurace, adresa_restaurace, kontakt_restaurace, cas, vlastni_text) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtS->bind_param("iisssssss", $vylet_id, $den, $typ_jidla, $typ, $nazev_rest, $adresa_rest, $kontakt_rest, $cas, $vlastni_text);
            $stmtS->execute();
            $stmtS->close();
        }
    }

    echo json_encode(['success' => true, 'message' => 'Výlet byl úspěšně naplánován a uložen!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Chyba při ukládání: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>


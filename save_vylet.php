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
    echo json_encode(['success' => false, 'message' => 'Chyba připojení k DB: ' . $conn->connect_error]);
    exit;
}

// Přidáme debug záznam
error_log("DEBUG: POST data - nazev: " . ($_POST['nazev_vyletu'] ?? 'CHYBÍ') . ", delka: " . ($_POST['delka_pobytu'] ?? 'CHYBÍ') . ", cena: " . ($_POST['celkova_cena'] ?? 'CHYBÍ'));
error_log("DEBUG: Session - user_id: " . ($_SESSION['user_id'] ?? 'CHYBÍ') . ", email: " . ($_SESSION['email'] ?? 'CHYBÍ'));
error_log("DEBUG: Počet tříd: " . count($_POST['tridy'] ?? []) . ", Strava set: " . (isset($_POST['strava']) ? 'ano' : 'ne'));

// Načtení dat z POST
$nazev_vyletu = $_POST['nazev_vyletu'] ?? '';
$adresa_ubytovani = $_POST['adresa_ubytovani'] ?? null;
$delka_pobytu = $_POST['delka_pobytu'] ?? null;
if ($delka_pobytu === 'jine') {
    $delka_pobytu = $_POST['delka_pobytu_custom'] ?? null;
}

// Zpracování náhledového obrázku
$nahledovy_obrazek = null;
if (isset($_FILES['nahledovy_obrazek']) && $_FILES['nahledovy_obrazek']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['nahledovy_obrazek'];
    
    // Kontrola typu souboru
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'Neplatný typ souboru. Povolené formáty: JPEG, PNG, GIF, WebP.']);
        exit;
    }
    
    // Kontrola velikosti (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Obrázek je příliš velký. Maximální velikost je 5MB.']);
        exit;
    }
    
    // Vytvoření složky pro obrázky, pokud neexistuje
    $uploadDir = __DIR__ . '/pictures/trips/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generování unikátního názvu souboru
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('trip_preview_', true) . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // Přesun souboru
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        $nahledovy_obrazek = 'pictures/trips/' . $filename;
    } else {
        echo json_encode(['success' => false, 'message' => 'Chyba při ukládání obrázku.']);
        exit;
    }
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
$cena = !empty($_POST['celkova_cena']) ? (float)$_POST['celkova_cena'] : 0;
$cislo_uctu = $_POST['cislo_uctu'] ?? null;

// Příprava SQL dotazu
$sql = "INSERT INTO " . $env['TRIPS_TABLE'] . " (
    userId, nazev_vyletu, nahledovy_obrazek, adresa_ubytovani, delka_pobytu, 
    misto_odjezdu_tam, cas_odjezdu_tam, dopravni_prostredek_tam, 
    misto_odjezdu_zpet, cas_odjezdu_zpet, dopravni_prostredek_zpet, 
    harmonogram, uciitele,
    celkova_cena, cislo_uctu
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Chyba přípravy SQL: ' . $conn->error]);
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt->bind_param(
    "issssssssssssds",
    $user_id, $nazev_vyletu, $nahledovy_obrazek, $adresa_ubytovani, $delka_pobytu,
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
        $stmt2 = $conn->prepare("INSERT INTO " . $env['TRIPS_CLASSES_TABLE'] . " (vyletId, tridy) VALUES (?, ?)");
        if (!$stmt2) {
            echo json_encode(['success' => false, 'message' => 'Chyba přípravy dotazu pro třídy: ' . $conn->error]);
            exit;
        }
        $stmt2->bind_param("is", $vylet_id, $trida);
        if (!$stmt2->execute()) {
            echo json_encode(['success' => false, 'message' => 'Chyba při ukládání třídy: ' . $stmt2->error]);
            $stmt2->close();
            exit;
        }
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

            $stmtS = $conn->prepare("INSERT INTO " . $env['TRIPS_MEALS_TABLE'] . " (vyletId, den, typ_jidla, typ, nazev_restaurace, adresa_restaurace, kontakt_restaurace, cas, vlastni_text) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$stmtS) {
                echo json_encode(['success' => false, 'message' => 'Chyba přípravy dotazu pro stravu: ' . $conn->error]);
                exit;
            }
            $stmtS->bind_param("iisssssss", $vylet_id, $den, $typ_jidla, $typ, $nazev_rest, $adresa_rest, $kontakt_rest, $cas, $vlastni_text);
            if (!$stmtS->execute()) {
                echo json_encode(['success' => false, 'message' => 'Chyba při ukládání stravy den ' . $den . ': ' . $stmtS->error]);
                $stmtS->close();
                exit;
            }
            $stmtS->close();
        }
    }

    echo json_encode(['success' => true, 'message' => 'Výlet byl úspěšně naplánován a uložen!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Chyba při ukládání výletu: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>


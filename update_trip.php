<?php
session_start();
header('Content-Type: application/json');

// Load environment variables
$env = parse_ini_file(__DIR__ . '/.env');

// Ochrana – jen přihlášený uživatel může upravovat
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

// Načtení ID výletu
$vylet_id = isset($_POST['vylet_id']) ? (int)$_POST['vylet_id'] : 0;

if ($vylet_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Neplatné ID výletu.']);
    exit;
}

// Ověření vlastnictví - učitel může upravit výlet pokud ho vytvořil NEBO se na něm podílí
$checkStmt = $conn->prepare("SELECT vyletId, nahledovy_obrazek FROM " . $env['TRIPS_TABLE'] . " WHERE vyletId = ? AND (userId = ? OR CONCAT(', ', uciitele, ', ') LIKE CONCAT('%, ', ?, ', %'))");
$teacher_email = $_SESSION['email'] ?? '';
$checkStmt->bind_param("iss", $vylet_id, $_SESSION['user_id'], $teacher_email);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows === 0) {
    echo json_encode([
        'success' => false, 
        'message' => 'Nemáte oprávnění upravit tento výlet.',
        'debug' => [
            'vylet_id' => $vylet_id,
            'user_id' => $_SESSION['user_id'],
            'teacher_email' => $teacher_email,
            'email_empty' => empty($teacher_email)
        ]
    ]);
    exit;
}

$currentTrip = $checkResult->fetch_assoc();
$checkStmt->close();

// Načtení dat z POST
$nazev_vyletu = $_POST['nazev_vyletu'] ?? '';
$adresa_ubytovani = $_POST['adresa_ubytovani'] ?? null;
$delka_pobytu = $_POST['delka_pobytu'] ?? null;
if ($delka_pobytu === 'jine') {
    $delka_pobytu = $_POST['delka_pobytu_custom'] ?? null;
}

// Zpracování náhledového obrázku
$nahledovy_obrazek = $currentTrip['nahledovy_obrazek']; // Zachovat existující, pokud se nový nenahrajuje

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
        // Smazat starý obrázek, pokud existuje
        if ($currentTrip['nahledovy_obrazek'] && file_exists(__DIR__ . '/' . $currentTrip['nahledovy_obrazek'])) {
            unlink(__DIR__ . '/' . $currentTrip['nahledovy_obrazek']);
        }
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

// Příprava SQL dotazu pro UPDATE
$sql = "UPDATE " . $env['TRIPS_TABLE'] . " SET 
    nazev_vyletu = ?, 
    nahledovy_obrazek = ?, 
    adresa_ubytovani = ?, 
    delka_pobytu = ?,
    misto_odjezdu_tam = ?, 
    cas_odjezdu_tam = ?, 
    dopravni_prostredek_tam = ?,
    misto_odjezdu_zpet = ?, 
    cas_odjezdu_zpet = ?, 
    dopravni_prostredek_zpet = ?,
    harmonogram = ?, 
    uciitele = ?,
    celkova_cena = ?, 
    cislo_uctu = ?
    WHERE vyletId = ? AND (userId = ? OR CONCAT(', ', uciitele, ', ') LIKE CONCAT('%, ', ?, ', %'))";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Chyba přípravy SQL: ' . $conn->error]);
    exit;
}

$stmt->bind_param(
    "ssssssssssssdsiis",
    $nazev_vyletu, $nahledovy_obrazek, $adresa_ubytovani, $delka_pobytu,
    $misto_tam, $cas_tam, $doprava_tam,
    $misto_zpet, $cas_zpet, $doprava_zpet,
    $harmonogram, $ucitele,
    $cena, $cislo_uctu,
    $vylet_id, $_SESSION['user_id'], $teacher_email
);

if (!$stmt->execute()) {
    echo json_encode([
        'success' => false, 
        'message' => 'Chyba při UPDATE: ' . $stmt->error,
        'debug' => [
            'vylet_id' => $vylet_id,
            'user_id' => $_SESSION['user_id'],
            'teacher_email' => $teacher_email,
            'affected_rows' => $stmt->affected_rows
        ]
    ]);
    exit;
}

// Aktualizace tříd
// Nejdřív si zapamatuj, kdo už zaplatil
$paidStudents = [];
$getPaidStmt = $conn->prepare("SELECT userId FROM trip_platby WHERE vyletId = ? AND zaplaceno = 1");
$getPaidStmt->bind_param("i", $vylet_id);
$getPaidStmt->execute();
$paidResult = $getPaidStmt->get_result();
while ($row = $paidResult->fetch_assoc()) {
    $paidStudents[] = (int)$row['userId'];
}
$getPaidStmt->close();

// Smaž staré třídy
$delTridy = $conn->prepare("DELETE FROM " . $env['TRIPS_CLASSES_TABLE'] . " WHERE vyletId = ?");
$delTridy->bind_param("i", $vylet_id);
$delTridy->execute();
$delTridy->close();

// Smaž staré záznamy o platbách
$delPayments = $conn->prepare("DELETE FROM trip_platby WHERE vyletId = ?");
$delPayments->bind_param("i", $vylet_id);
$delPayments->execute();
$delPayments->close();

// Vlož nové třídy
$tridy = $_POST['tridy'] ?? [];
foreach ($tridy as $trida) {
    $stmtTrida = $conn->prepare("INSERT INTO " . $env['TRIPS_CLASSES_TABLE'] . " (vyletId, tridy) VALUES (?, ?)");
    $stmtTrida->bind_param("is", $vylet_id, $trida);
    $stmtTrida->execute();
    $stmtTrida->close();
}

// Vytvoř nové záznamy o platbách pro studenty z nových tříd
if (!empty($tridy)) {
    $placeholders = implode(',', array_fill(0, count($tridy), '?'));
    $types = str_repeat('s', count($tridy));
    
    // Načti všechny studenty z nových tříd
    $stmtStudents = $conn->prepare(
        "SELECT userId FROM " . $env['USER_TABLE'] . " 
         WHERE role = 'student' AND class IN ($placeholders)"
    );
    if ($stmtStudents) {
        $stmtStudents->bind_param($types, ...$tridy);
        $stmtStudents->execute();
        $resultStudents = $stmtStudents->get_result();
        
        // Vytvoř záznam o platbě pro každého studenta
        while ($studentRow = $resultStudents->fetch_assoc()) {
            $student_id = (int)$studentRow['userId'];
            // Pokud student zaplatil, nastav zaplaceno = 1, jinak 0
            $zaplaceno = in_array($student_id, $paidStudents) ? 1 : 0;
            
            $stmtPayment = $conn->prepare(
                "INSERT INTO trip_platby (vyletId, userId, zaplaceno) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE zaplaceno = zaplaceno"
            );
            if ($stmtPayment) {
                $stmtPayment->bind_param("iii", $vylet_id, $student_id, $zaplaceno);
                $stmtPayment->execute();
                $stmtPayment->close();
            }
        }
        $stmtStudents->close();
    }
}

// Aktualizace stravy
$delStrava = $conn->prepare("DELETE FROM " . $env['TRIPS_MEALS_TABLE'] . " WHERE vyletId = ?");
$delStrava->bind_param("i", $vylet_id);
$delStrava->execute();
$delStrava->close();

$strava = $_POST['strava'] ?? [];
foreach ($strava as $den => $meals) {
    foreach ($meals as $typ_jidla => $data) {
        $typ = $data['typ'] ?? 'vlastni';
        $nazev_rest = $data['nazev_restaurace'] ?? null;
        $adresa_rest = $data['adresa_restaurace'] ?? null;
        $kontakt_rest = $data['kontakt_restaurace'] ?? null;
        $cas = $data['cas'] ?? null;
        $vlastni_text = $data['vlastni_text'] ?? null;

        $stmtStrava = $conn->prepare("INSERT INTO " . $env['TRIPS_MEALS_TABLE'] . " (vyletId, den, typ_jidla, typ, nazev_restaurace, adresa_restaurace, kontakt_restaurace, cas, vlastni_text) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtStrava->bind_param("iisssssss", $vylet_id, $den, $typ_jidla, $typ, $nazev_rest, $adresa_rest, $kontakt_rest, $cas, $vlastni_text);
        $stmtStrava->execute();
        $stmtStrava->close();
    }
}

echo json_encode(['success' => true, 'message' => 'Výlet byl úspěšně upraven!']);

$stmt->close();
$conn->close();
?>

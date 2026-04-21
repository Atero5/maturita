<?php
session_start();
header('Content-Type: application/json');

$env = parse_ini_file(__DIR__ . '/.env');

// Kontrola přihlášení
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nepovolený přístup']);
    exit();
}

$conn = new mysqli($env['DB_HOSTNAME'], $env['DB_USERNAME'], $env['DB_PASSWORD'], $env['DB_NAME']);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Chyba připojení k DB']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$userId = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];

// ========== GET – seznam fotek pro daný výlet ==========
if ($method === 'GET' && isset($_GET['vyletId'])) {
    $vyletId = (int)$_GET['vyletId'];

    // Ověření přístupu k výletu
    if (!hasAccessToTrip($conn, $env, $vyletId, $userId, $role)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Nemáte přístup k tomuto výletu']);
        exit();
    }

    $stmt = $conn->prepare("
        SELECT p.photoId, p.filename, p.uploaded_at, p.userId, u.email 
        FROM " . $env['PHOTOS_TABLE'] . " p 
        JOIN " . $env['USER_TABLE'] . " u ON p.userId = u.userId 
        WHERE p.vyletId = ? 
        ORDER BY p.uploaded_at DESC
    ");
    $stmt->bind_param("i", $vyletId);
    $stmt->execute();
    $result = $stmt->get_result();

    $photos = [];
    while ($row = $result->fetch_assoc()) {
        $photos[] = $row;
    }

    // Zjistit, zda je výlet už po datu návratu
    $canUpload = canUploadPhotos($conn, $env, $vyletId);

    // Zjistit, zda je uživatel vlastník výletu (učitel)
    $isOwner = isTripOwner($conn, $env, $vyletId, $userId);

    echo json_encode([
        'success' => true,
        'photos' => $photos,
        'canUpload' => $canUpload,
        'isOwner' => $isOwner,
        'currentUserId' => $userId
    ]);
    exit();
}

// ========== POST – nahrání fotky ==========
if ($method === 'POST') {
    $vyletId = isset($_POST['vyletId']) ? (int)$_POST['vyletId'] : 0;

    if ($vyletId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Neplatný výlet']);
        exit();
    }

    // Ověření přístupu
    if (!hasAccessToTrip($conn, $env, $vyletId, $userId, $role)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Nemáte přístup k tomuto výletu']);
        exit();
    }

    // Ověření, že výlet už proběhl
    if (!canUploadPhotos($conn, $env, $vyletId)) {
        echo json_encode(['success' => false, 'message' => 'Fotky lze nahrávat až po návratu z výletu']);
        exit();
    }

    // Kontrola souboru
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Chyba při nahrávání souboru']);
        exit();
    }

    $file = $_FILES['photo'];
    $maxSize = 10 * 1024 * 1024; // 10 MB

    if ($file['size'] > $maxSize) {
        echo json_encode(['success' => false, 'message' => 'Soubor je příliš velký (max 10 MB)']);
        exit();
    }

    // Kontrola typu souboru
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!in_array($mimeType, $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'Povolené formáty: JPG, PNG, WEBP']);
        exit();
    }

    // Vytvoření složky pro výlet
    $uploadDir = __DIR__ . '/uploads/trips/' . $vyletId;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Unikátní název souboru
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = bin2hex(random_bytes(16)) . '.' . strtolower($ext);
    $filepath = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        echo json_encode(['success' => false, 'message' => 'Chyba při ukládání souboru']);
        exit();
    }

    // Uložení do DB
    $stmt = $conn->prepare("INSERT INTO " . $env['PHOTOS_TABLE'] . " (vyletId, userId, filename) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $vyletId, $userId, $filename);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Fotka nahrána']);
    exit();
}

// ========== DELETE – smazání fotky ==========
if ($method === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    $photoId = isset($data['photoId']) ? (int)$data['photoId'] : 0;

    if ($photoId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Neplatná fotka']);
        exit();
    }

    // Načíst fotku
    $stmt = $conn->prepare("SELECT p.photoId, p.vyletId, p.userId, p.filename, v.userId AS ownerId FROM " . $env['PHOTOS_TABLE'] . " p JOIN " . $env['TRIPS_TABLE'] . " v ON p.vyletId = v.vyletId WHERE p.photoId = ?");
    $stmt->bind_param("i", $photoId);
    $stmt->execute();
    $result = $stmt->get_result();
    $photo = $result->fetch_assoc();

    if (!$photo) {
        echo json_encode(['success' => false, 'message' => 'Fotka nenalezena']);
        exit();
    }

    // Smazat může: ten kdo nahrál NEBO jakýkoliv učitel NEBO admin
    if ($userId !== (int)$photo['userId'] && $role !== 'teacher' && $role !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Nemáte oprávnění smazat tuto fotku']);
        exit();
    }

    // Smazat soubor z disku
    $filepath = __DIR__ . '/uploads/trips/' . $photo['vyletId'] . '/' . $photo['filename'];
    if (file_exists($filepath)) {
        unlink($filepath);
    }

    // Smazat z DB
    $stmt = $conn->prepare("DELETE FROM " . $env['PHOTOS_TABLE'] . " WHERE photoId = ?");
    $stmt->bind_param("i", $photoId);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Fotka smazána']);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Neplatný požadavek']);

// ========== Pomocné funkce ==========

function hasAccessToTrip($conn, $env, $vyletId, $userId, $role) {
    if ($role === 'admin') return true;

    if ($role === 'teacher') {
        $stmt = $conn->prepare("SELECT vyletId FROM " . $env['TRIPS_TABLE'] . " WHERE vyletId = ? AND userId = ?");
        $stmt->bind_param("ii", $vyletId, $userId);
    } else {
        $class = $_SESSION['class'] ?? '';
        $stmt = $conn->prepare("SELECT vt.vyletId FROM " . $env['TRIPS_CLASSES_TABLE'] . " vt WHERE vt.vyletId = ? AND vt.tridy = ?");
        $stmt->bind_param("is", $vyletId, $class);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

function canUploadPhotos($conn, $env, $vyletId) {
    $stmt = $conn->prepare("SELECT cas_odjezdu_zpet FROM " . $env['TRIPS_TABLE'] . " WHERE vyletId = ?");
    $stmt->bind_param("i", $vyletId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if (!$row || !$row['cas_odjezdu_zpet']) return false;

    return strtotime($row['cas_odjezdu_zpet']) <= time();
}

function isTripOwner($conn, $env, $vyletId, $userId) {
    $stmt = $conn->prepare("SELECT vyletId FROM " . $env['TRIPS_TABLE'] . " WHERE vyletId = ? AND userId = ?");
    $stmt->bind_param("ii", $vyletId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$env = parse_ini_file(__DIR__ . '/.env');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    exit();
}

$conn = new mysqli($env['DB_HOSTNAME'], $env['DB_USERNAME'], $env['DB_PASSWORD'], $env['DB_NAME']);
$conn->set_charset("utf8mb4");

// Načíst výlet (učitel/admin vidí vše, student jen svůj)
$role = $_SESSION['role'];
if ($role === 'student' && isset($_SESSION['class'])) {
    $stmt = $conn->prepare("SELECT v.* FROM " . $env['TRIPS_TABLE'] . " v INNER JOIN vylety_tridy vt ON v.vyletId = vt.vyletId WHERE v.vyletId = ? AND vt.tridy = ?");
    $stmt->bind_param("is", $id, $_SESSION['class']);
} elseif ($role === 'admin') {
    $stmt = $conn->prepare("SELECT * FROM " . $env['TRIPS_TABLE'] . " WHERE vyletId = ?");
    $stmt->bind_param("i", $id);
} else {
    // Učitel vidí výlet pokud ho vytvořil NEBO je uveden v uciitele
    $stmt = $conn->prepare("SELECT * FROM " . $env['TRIPS_TABLE'] . " WHERE vyletId = ? AND (userId = ? OR CONCAT(', ', uciitele, ', ') LIKE CONCAT('%, ', ?, ', %'))");
    $teacher_email = $_SESSION['email'] ?? '';
    $stmt->bind_param("iss", $id, $_SESSION['user_id'], $teacher_email);
}
$stmt->execute();
$trip = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$trip) {
    $conn->close();
    exit();
}

// Načíst třídy
$stmt = $conn->prepare("SELECT tridy FROM vylety_tridy WHERE vyletId = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$tridyResult = $stmt->get_result();
$tridy = [];
while ($row = $tridyResult->fetch_assoc()) $tridy[] = $row['tridy'];
$stmt->close();
$conn->close();

function h($text) { return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Harmonogram – <?= h($trip['nazev_vyletu']) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 13px; color: #222; padding: 30px 40px; }
        h1 { font-size: 22px; margin-bottom: 4px; }
        .subtitle { color: #666; font-size: 13px; margin-bottom: 24px; }
        .harmonogram { white-space: pre-wrap; background: #f9f9f9; border: 1px solid #e0e0e0; border-radius: 4px; padding: 12px; line-height: 1.6; font-size: 13px; }
        .btn-print { display: inline-block; padding: 8px 20px; background: #007bff; color: white; border: none; border-radius: 6px; font-size: 13px; cursor: pointer; margin-bottom: 24px; }
        @media print {
            .btn-print { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>


    <h1><?= h($trip['nazev_vyletu']) ?></h1>
    <div class="subtitle">Harmonogram výletu &bull; Třídy: <?= h(implode(', ', $tridy)) ?></div>

    <?php if ($trip['harmonogram']): ?>
    <div class="harmonogram"><?= h($trip['harmonogram']) ?></div>
    <?php endif; ?>

</body>
</html>

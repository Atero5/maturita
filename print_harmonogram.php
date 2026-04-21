<?php
session_start();
$env = parse_ini_file(__DIR__ . '/.env');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo 'Neplatné ID výletu.';
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
    $stmt = $conn->prepare("SELECT * FROM " . $env['TRIPS_TABLE'] . " WHERE vyletId = ? AND userId = ?");
    $stmt->bind_param("ii", $id, $_SESSION['user_id']);
}
$stmt->execute();
$trip = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$trip) {
    echo 'Výlet nenalezen nebo nemáte oprávnění.';
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

// Načíst stravu
$stmt = $conn->prepare("SELECT den, typ_jidla, typ, nazev_restaurace, adresa_restaurace, kontakt_restaurace, cas, vlastni_text FROM " . $env['TRIPS_MEALS_TABLE'] . " WHERE vyletId = ? ORDER BY den, FIELD(typ_jidla, 'snidane', 'obed', 'vecere')");
$stmt->bind_param("i", $id);
$stmt->execute();
$stravaResult = $stmt->get_result();
$strava = [];
while ($row = $stravaResult->fetch_assoc()) $strava[] = $row;
$stmt->close();
$conn->close();

function h($text) { return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'); }
function fmtDt($val) {
    if (!$val) return '—';
    $d = new DateTime($val);
    return $d->format('j. n. Y H:i');
}

// Seskupit stravu po dnech
$stravaByDay = [];
foreach ($strava as $s) {
    $stravaByDay[(int)$s['den']][] = $s;
}
ksort($stravaByDay);

$mealLabel = ['snidane' => 'Snídaně', 'obed' => 'Oběd', 'vecere' => 'Večeře'];
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
        h2 { font-size: 15px; margin: 20px 0 8px; color: #333; border-bottom: 1px solid #ddd; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        td { padding: 6px 10px; vertical-align: top; }
        td:first-child { width: 38%; font-weight: bold; color: #444; }
        tr:nth-child(even) td { background: #f9f9f9; }
        .harmonogram { white-space: pre-wrap; background: #f9f9f9; border: 1px solid #e0e0e0; border-radius: 4px; padding: 12px; line-height: 1.6; }
        .strava-day { margin-bottom: 14px; }
        .strava-day-title { font-weight: bold; font-size: 13px; color: #444; margin-bottom: 6px; }
        .strava-meal { padding: 6px 10px; border-left: 3px solid #ddd; margin-bottom: 4px; }
        .strava-meal-name { font-weight: bold; }
        .strava-meal-detail { color: #555; font-size: 12px; margin-top: 2px; }
        .btn-print { display: inline-block; padding: 8px 20px; background: #007bff; color: white; border: none; border-radius: 6px; font-size: 13px; cursor: pointer; margin-bottom: 24px; }
        @media print {
            .btn-print { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <button class="btn-print" onclick="window.print()">🖨️ Uložit / Tisknout jako PDF</button>

    <h1><?= h($trip['nazev_vyletu']) ?></h1>
    <div class="subtitle">Harmonogram výletu &bull; Třídy: <?= h(implode(', ', $tridy)) ?></div>

    <h2>Základní informace</h2>
    <table>
        <tr><td>Adresa ubytování</td><td><?= h($trip['adresa_ubytovani']) ?></td></tr>
        <tr><td>Délka pobytu</td><td><?= h($trip['delka_pobytu']) ?></td></tr>
        <tr><td>Učitelé</td><td><?= h($trip['uciitele'] ?: '—') ?></td></tr>
    </table>

    <h2>Doprava tam</h2>
    <table>
        <tr><td>Místo odjezdu</td><td><?= h($trip['misto_odjezdu_tam']) ?></td></tr>
        <tr><td>Datum a čas odjezdu</td><td><?= fmtDt($trip['cas_odjezdu_tam']) ?></td></tr>
        <tr><td>Dopravní prostředek</td><td><?= h($trip['dopravni_prostredek_tam']) ?></td></tr>
    </table>

    <h2>Doprava zpět</h2>
    <table>
        <tr><td>Místo odjezdu</td><td><?= h($trip['misto_odjezdu_zpet']) ?></td></tr>
        <tr><td>Datum a čas odjezdu</td><td><?= fmtDt($trip['cas_odjezdu_zpet']) ?></td></tr>
        <tr><td>Dopravní prostředek</td><td><?= h($trip['dopravni_prostredek_zpet']) ?></td></tr>
    </table>

    <?php if ($trip['harmonogram']): ?>
    <h2>Harmonogram</h2>
    <div class="harmonogram"><?= h($trip['harmonogram']) ?></div>
    <?php endif; ?>

    <?php if (!empty($strava)): ?>
    <h2>Stravování</h2>
    <?php foreach ($stravaByDay as $den => $jidla): ?>
    <div class="strava-day">
        <div class="strava-day-title">Den <?= (int)$den ?></div>
        <?php foreach ($jidla as $j): ?>
        <div class="strava-meal">
            <div class="strava-meal-name"><?= h($mealLabel[$j['typ_jidla']] ?? $j['typ_jidla']) ?>
                – <?= $j['typ'] === 'restaurace' ? 'Restaurace' : 'Vlastní' ?>
            </div>
            <?php if ($j['typ'] === 'restaurace'): ?>
                <div class="strava-meal-detail">
                    <?php if ($j['nazev_restaurace']) echo h($j['nazev_restaurace']); ?>
                    <?php if ($j['adresa_restaurace']) echo ' &bull; ' . h($j['adresa_restaurace']); ?>
                    <?php if ($j['cas']) echo ' &bull; ' . h($j['cas']); ?>
                </div>
            <?php elseif ($j['vlastni_text']): ?>
                <div class="strava-meal-detail"><?= h($j['vlastni_text']) ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <h2>Cena</h2>
    <table>
        <tr><td>Celková cena</td><td><?= number_format((float)$trip['celkova_cena'], 0, ',', ' ') ?> Kč</td></tr>
        <tr><td>Číslo účtu</td><td><?= h($trip['cislo_uctu'] ?: '—') ?></td></tr>
    </table>
</body>
</html>

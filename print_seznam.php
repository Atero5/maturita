<?php
session_start();
$env = parse_ini_file(__DIR__ . '/.env');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit();
}

$role = $_SESSION['role'];
if ($role !== 'teacher' && $role !== 'admin') {
    echo 'Přístup odepřen – pouze pro učitele.';
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo 'Neplatné ID výletu.';
    exit();
}

$conn = new mysqli($env['DB_HOSTNAME'], $env['DB_USERNAME'], $env['DB_PASSWORD'], $env['DB_NAME']);
$conn->set_charset("utf8mb4");

// Načíst výlet
if ($role === 'admin') {
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

// Načíst třídy výletu
$stmt = $conn->prepare("SELECT tridy FROM " . $env['TRIPS_CLASSES_TABLE'] . " WHERE vyletId = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$tridyResult = $stmt->get_result();
$tridy = [];
while ($row = $tridyResult->fetch_assoc()) $tridy[] = $row['tridy'];
$stmt->close();

$studenti = [];
if (!empty($tridy)) {
    $placeholders = implode(',', array_fill(0, count($tridy), '?'));
    $types = str_repeat('s', count($tridy));
    $stmt = $conn->prepare(
        "SELECT u.userId, u.email, u.class FROM " . $env['USER_TABLE'] . " u
         WHERE u.role = 'student' AND u.class IN ($placeholders)
         ORDER BY u.class, u.email"
    );
    $stmt->bind_param($types, ...$tridy);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $studenti[] = $row;
    $stmt->close();
}

// Načíst odhlášené
$stmt = $conn->prepare("SELECT userId FROM " . $env['ODHLASENI_TABLE'] . " WHERE vyletId = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$odhlaseniIds = [];
while ($row = $result->fetch_assoc()) $odhlaseniIds[] = (int)$row['userId'];
$stmt->close();
$conn->close();

$ucastnici = [];
$odhlaseni = [];
foreach ($studenti as $s) {
    if (in_array((int)$s['userId'], $odhlaseniIds)) {
        $odhlaseni[] = $s;
    } else {
        $ucastnici[] = $s;
    }
}

function h($text) { return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'); }
function fmtDt($val) {
    if (!$val) return '—';
    $d = new DateTime($val);
    return $d->format('j. n. Y H:i');
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Seznam žáků – <?= h($trip['nazev_vyletu']) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 13px; color: #222; padding: 30px 40px; }
        h1 { font-size: 22px; margin-bottom: 4px; }
        .subtitle { color: #666; font-size: 13px; margin-bottom: 24px; }
        h2 { font-size: 15px; margin: 24px 0 10px; color: #333; border-bottom: 1px solid #ddd; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th { background: #f0f0f0; text-align: left; padding: 7px 10px; font-size: 12px; color: #555; border-bottom: 2px solid #ddd; }
        td { padding: 7px 10px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        .col-num { width: 40px; color: #999; text-align: center; }
        .col-class { width: 80px; }
        .class-badge { display: inline-block; background: #f0f0f0; color: #555; padding: 2px 10px; border-radius: 20px; font-size: 11px; font-weight: bold; }
        .summary { font-size: 12px; color: #666; margin-bottom: 8px; }
        .odhlaseni-section h2 { color: #e74c3c; }
        .odhlaseni-section .class-badge { background: #fdecea; color: #c0392b; }
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
    <div class="subtitle">
        Seznam žáků &bull; Třídy: <?= h(implode(', ', $tridy)) ?>
        &bull; Odjezd: <?= fmtDt($trip['cas_odjezdu_tam']) ?>
    </div>

    <h2>Účastníci (<?= count($ucastnici) ?>)</h2>
    <div class="summary">Celkem přihlášených: <strong><?= count($ucastnici) ?></strong></div>
    <?php if (!empty($ucastnici)): ?>
    <table>
        <thead>
            <tr>
                <th class="col-num">#</th>
                <th class="col-class">Třída</th>
                <th>E-mail</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ucastnici as $i => $s): ?>
            <tr>
                <td class="col-num"><?= $i + 1 ?></td>
                <td><span class="class-badge"><?= h($s['class']) ?></span></td>
                <td><?= h($s['email']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p style="color:#999; padding: 10px 0;">Žádní přihlášení žáci.</p>
    <?php endif; ?>

    <?php if (!empty($odhlaseni)): ?>
    <div class="odhlaseni-section">
        <h2>Odhlášení (<?= count($odhlaseni) ?>)</h2>
        <table>
            <thead>
                <tr>
                    <th class="col-num">#</th>
                    <th class="col-class">Třída</th>
                    <th>E-mail</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($odhlaseni as $i => $s): ?>
                <tr>
                    <td class="col-num"><?= $i + 1 ?></td>
                    <td><span class="class-badge"><?= h($s['class']) ?></span></td>
                    <td><?= h($s['email']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</body>
</html>

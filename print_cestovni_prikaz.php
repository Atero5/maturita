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

$stmt = $conn->prepare("SELECT tridy FROM " . $env['TRIPS_CLASSES_TABLE'] . " WHERE vyletId = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$tridyResult = $stmt->get_result();
$tridy = [];
while ($row = $tridyResult->fetch_assoc()) $tridy[] = $row['tridy'];
$stmt->close();
$conn->close();

if (!function_exists('h')) {
    function h($text) { return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('fmtDate')) {
    function fmtDate($v) { return $v ? (new DateTime($v))->format('j.m.Y') : ''; }
}
if (!function_exists('fmtTime')) {
    function fmtTime($v) { return $v ? (new DateTime($v))->format('H:i') : ''; }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Cestovní příkaz</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; padding: 20px; }
        h1 { text-align: center; font-size: 16px; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; }
        td { border: 1px solid #000; padding: 5px; vertical-align: top; }
        .header-section table td { padding: 8px; }
        .section-title { font-weight: bold; background: #f0f0f0; }
        .label { width: 25%; font-weight: bold; background: #f9f9f9; }
        .w50 { width: 50%; }
        .w33 { width: 33%; }
        .center { text-align: center; }
        .box { min-height: 30px; }
        .no-print { display: none; }
        @media print {
            body { padding: 0; }
        }
        .btn-print { padding: 8px 16px; background: #007bff; color: white; border: none; cursor: pointer; margin-bottom: 10px; border-radius: 3px; }
    </style>
</head>
<body>
    <button class="btn-print" onclick="window.print()">🖨️ Tisknout / Uložit jako PDF</button>

    <h1>CESTOVNÍ PŘÍKAZ</h1>

    <!-- ČÁST 1: Základní údaje -->
    <table class="header-section">
        <tr>
            <td class="label">1. Název výletu:</td>
            <td colspan="2" class="box"><?= h($trip['nazev_vyletu']) ?></td>
        </tr>
        <tr>
            <td class="label">Místo ubytování:</td>
            <td class="w50 box"><?= h($trip['adresa_ubytovani'] ?? '') ?></td>
            <td class="label">Trída/Třídy:</td>
            <td class="box"><?= h(implode(', ', $tridy)) ?></td>
        </tr>
        <tr>
            <td class="label">Vedoucí (učitelé):</td>
            <td colspan="3" class="box"><?= h($trip['uciitele'] ?? '') ?></td>
        </tr>
    </table>

    <!-- ČÁST 2: Doprava a časy -->
    <table style="margin-top: 10px;">
        <tr>
            <td class="section-title">Doprava tam</td>
            <td class="section-title">Doprava zpět</td>
        </tr>
        <tr>
            <td style="width: 50%;">
                <table style="width: 100%; margin: 0; border: none;">
                    <tr>
                        <td class="label" style="border: none; width: 40%;">Místo:</td>
                        <td style="border: none; padding: 3px;">
                            <?= h($trip['misto_odjezdu_tam'] ?? '') ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="label" style="border: none;">Datum:</td>
                        <td style="border: none; padding: 3px;">
                            <?= fmtDate($trip['cas_odjezdu_tam']) ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="label" style="border: none;">Čas:</td>
                        <td style="border: none; padding: 3px;">
                            <?= fmtTime($trip['cas_odjezdu_tam']) ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="label" style="border: none;">Prostředek:</td>
                        <td style="border: none; padding: 3px;">
                            <?= h($trip['dopravni_prostredek_tam'] ?? '') ?>
                        </td>
                    </tr>
                </table>
            </td>
            <td style="width: 50%; padding: 0;">
                <table style="width: 100%; margin: 0; border: none;">
                    <tr>
                        <td class="label" style="border: none; width: 40%;">Místo:</td>
                        <td style="border: none; padding: 3px;">
                            <?= h($trip['misto_odjezdu_zpet'] ?? '') ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="label" style="border: none;">Datum:</td>
                        <td style="border: none; padding: 3px;">
                            <?= fmtDate($trip['cas_odjezdu_zpet']) ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="label" style="border: none;">Čas:</td>
                        <td style="border: none; padding: 3px;">
                            <?= fmtTime($trip['cas_odjezdu_zpet']) ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="label" style="border: none;">Prostředek:</td>
                        <td style="border: none; padding: 3px;">
                            <?= h($trip['dopravni_prostredek_zpet'] ?? '') ?>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- ČÁST 3: Harmonogram -->
    <table style="margin-top: 10px;">
        <tr>
            <td class="section-title">Harmonogram</td>
        </tr>
        <tr>
            <td style="min-height: 80px; white-space: pre-wrap; font-size: 10px;">
                <?= h($trip['harmonogram'] ?? '') ?>
            </td>
        </tr>
    </table>

    <!-- ČÁST 4: Financování -->
    <table style="margin-top: 10px;">
        <tr>
            <td class="label">Celková cena:</td>
            <td class="w33 box"><?= number_format((float)$trip['celkova_cena'], 0, ',', ' ') ?> Kč</td>
            <td class="label">Číslo účtu:</td>
            <td class="w33 box"><?= h($trip['cislo_uctu'] ?? '') ?></td>
        </tr>
    </table>

    <!-- ČÁST 5: Podpisy -->
    <table style="margin-top: 20px;">
        <tr>
            <td class="center" style="height: 60px; border: 1px solid #000;">
                <div style="height: 40px;"></div>
                <div style="font-size: 9px;">Podpis vedoucího skupiny</div>
            </td>
            <td class="center" style="height: 60px; border: 1px solid #000;">
                <div style="height: 40px;"></div>
                <div style="font-size: 9px;">Podpis učitele</div>
            </td>
            <td class="center" style="height: 60px; border: 1px solid #000;">
                <div style="height: 40px;"></div>
                <div style="font-size: 9px;">Podpis řídícího</div>
            </td>
        </tr>
    </table>

    <div style="text-align: center; font-size: 9px; margin-top: 15px; color: #666;">
        Vygenerováno: <?= date('j.m.Y H:i') ?>
    </div>
</body>
</html>
<?php
session_start();
$env = parse_ini_file(__DIR__ . '/.env');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit();
}

// Cestovní příkaz vidí jen učitelé a admini
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

// Načíst třídy
$stmt = $conn->prepare("SELECT tridy FROM " . $env['TRIPS_CLASSES_TABLE'] . " WHERE vyletId = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$tridyResult = $stmt->get_result();
$tridy = [];
while ($row = $tridyResult->fetch_assoc()) $tridy[] = $row['tridy'];
$stmt->close();

// Načíst počet studentů
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM " . $env['USER_TABLE'] . " u WHERE u.role = 'student' AND u.class IN (" . implode(',', array_fill(0, count($tridy), '?')) . ")");
$types = str_repeat('s', count($tridy));
$stmt->bind_param($types, ...$tridy);
$stmt->execute();
$countResult = $stmt->get_result()->fetch_assoc();
$pocetStudentu = (int)$countResult['cnt'];
$stmt->close();

// Odhlášení
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM " . $env['ODHLASENI_TABLE'] . " WHERE vyletId = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$countResult = $stmt->get_result()->fetch_assoc();
$pocetOdhlasenych = (int)$countResult['cnt'];
$stmt->close();

$pocetUcastniku = $pocetStudentu - $pocetOdhlasenych;
$conn->close();

function h($text) { return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'); }
function fmtDt($val) {
    if (!$val) return '—';
    $d = new DateTime($val);
    return $d->format('j. n. Y H:i');
}
function fmtDate($val) {
    if (!$val) return '—';
    $d = new DateTime($val);
    return $d->format('j. n. Y');
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Cestovní příkaz – <?= h($trip['nazev_vyletu']) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Times New Roman', serif; font-size: 12px; color: #222; padding: 30px 40px; line-height: 1.4; }
        h1 { text-align: center; font-size: 18px; margin-bottom: 20px; text-decoration: underline; }
        .section { margin-bottom: 16px; }
        .section-title { font-weight: bold; text-decoration: underline; margin-bottom: 8px; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        td { padding: 4px 8px; border: 1px solid #333; vertical-align: top; }
        .label { width: 30%; font-weight: bold; }
        .value { width: 70%; }
        .signature-line { border-bottom: 1px solid #333; height: 30px; padding-top: 20px; text-align: center; font-size: 10px; }
        .sig-row { display: flex; justify-content: space-between; margin-top: 20px; }
        .sig-col { width: 45%; text-align: center; }
        @media print {
            body { padding: 0; margin: 0; }
            .no-print { display: none; }
        }
        .btn-print { display: inline-block; padding: 8px 20px; background: #007bff; color: white; border: none; border-radius: 6px; font-size: 13px; cursor: pointer; margin-bottom: 24px; }
    </style>
</head>
<body>
    <button class="btn-print no-print" onclick="window.print()">🖨️ Tisknout / Uložit jako PDF</button>

    <h1>CESTOVNÍ PŘÍKAZ</h1>

    <div class="section">
        <div class="section-title">1. VÝLET</div>
        <table>
            <tr>
                <td class="label">Název výletu:</td>
                <td class="value"><strong><?= h($trip['nazev_vyletu']) ?></strong></td>
            </tr>
            <tr>
                <td class="label">Místo ubytování:</td>
                <td class="value"><?= h($trip['adresa_ubytovani'] ?? '—') ?></td>
            </tr>
            <tr>
                <td class="label">Trvání pobytu:</td>
                <td class="value"><?= h($trip['delka_pobytu'] ?? '—') ?></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">2. ÚČASTNÍCI</div>
        <table>
            <tr>
                <td class="label">Třídy:</td>
                <td class="value"><?= h(implode(', ', $tridy)) ?></td>
            </tr>
            <tr>
                <td class="label">Počet žáků:</td>
                <td class="value"><?= $pocetUcastniku ?> (celkem <?= $pocetStudentu ?>, odhlášených: <?= $pocetOdhlasenych ?>)</td>
            </tr>
            <tr>
                <td class="label">Vedoucí výletu (učitelé):</td>
                <td class="value"><?= h($trip['uciitele'] ?? '—') ?></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">3. DOPRAVA TAM</div>
        <table>
            <tr>
                <td class="label">Místo odjezdu:</td>
                <td class="value"><?= h($trip['misto_odjezdu_tam'] ?? '—') ?></td>
            </tr>
            <tr>
                <td class="label">Datum a čas odjezdu:</td>
                <td class="value"><?= fmtDt($trip['cas_odjezdu_tam']) ?></td>
            </tr>
            <tr>
                <td class="label">Dopravní prostředek:</td>
                <td class="value"><?= h($trip['dopravni_prostredek_tam'] ?? '—') ?></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">4. DOPRAVA ZPĚT</div>
        <table>
            <tr>
                <td class="label">Místo odjezdu:</td>
                <td class="value"><?= h($trip['misto_odjezdu_zpet'] ?? '—') ?></td>
            </tr>
            <tr>
                <td class="label">Datum a čas odjezdu:</td>
                <td class="value"><?= fmtDt($trip['cas_odjezdu_zpet']) ?></td>
            </tr>
            <tr>
                <td class="label">Dopravní prostředek:</td>
                <td class="value"><?= h($trip['dopravni_prostredek_zpet'] ?? '—') ?></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">5. FINANCOVÁNÍ</div>
        <table>
            <tr>
                <td class="label">Celková cena:</td>
                <td class="value"><strong><?= number_format((float)$trip['celkova_cena'], 0, ',', ' ') ?> Kč</strong></td>
            </tr>
            <tr>
                <td class="label">Číslo účtu:</td>
                <td class="value"><?= h($trip['cislo_uctu'] ?? '—') ?></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">6. HARMONOGRAM</div>
        <div style="border: 1px solid #333; padding: 8px; white-space: pre-wrap;"><?= h($trip['harmonogram'] ?? 'Neuvedeno') ?></div>
    </div>

    <div class="section">
        <div class="section-title">7. PODPISY</div>
        <div class="sig-row">
            <div class="sig-col">
                <div class="signature-line"></div>
                <small>Podpis vedoucího skupiny</small>
            </div>
            <div class="sig-col">
                <div class="signature-line"></div>
                <small>Podpis řídícího</small>
            </div>
        </div>
        <div style="text-align: center; font-size: 10px; margin-top: 30px;">
            Formulář byl vygenerován dne: <?= date('j. n. Y') ?>
        </div>
    </div>
</body>
</html>

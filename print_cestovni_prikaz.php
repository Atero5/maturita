<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$env = parse_ini_file(__DIR__ . '/.env');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit();
}

$role = $_SESSION['role'];
if ($role !== 'teacher' && $role !== 'admin') {
    http_response_code(403);
    echo 'Přístup odepřen – pouze pro učitele.';
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    exit();
}

$conn = new mysqli($env['DB_HOSTNAME'], $env['DB_USERNAME'], $env['DB_PASSWORD'], $env['DB_NAME']);
$conn->set_charset("utf8mb4");

if ($role === 'admin') {
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
    http_response_code(404);
    exit();
}

$stmt = $conn->prepare("SELECT tridy FROM " . $env['TRIPS_CLASSES_TABLE'] . " WHERE vyletId = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$tridyResult = $stmt->get_result();
$tridy = [];
while ($row = $tridyResult->fetch_assoc()) $tridy[] = $row['tridy'];
$stmt->close();

$pocetStudentu = 0;
$pocetOdhlasenych = 0;
if (!empty($tridy)) {
    $placeholders = implode(',', array_fill(0, count($tridy), '?'));
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM " . $env['USER_TABLE'] . " WHERE role = 'student' AND class IN ($placeholders)");
    $types = str_repeat('s', count($tridy));
    $stmt->bind_param($types, ...$tridy);
    $stmt->execute();
    $pocetStudentu = (int)$stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();
}

$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM " . $env['ODHLASENI_TABLE'] . " WHERE vyletId = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$pocetOdhlasenych = (int)$stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

$conn->close();

$pocetUcastniku = $pocetStudentu - $pocetOdhlasenych;

function h($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function fmtDt($v) { if (!$v) return '—'; return (new DateTime($v))->format('j. n. Y,  H:i'); }
function val($v, $fallback = '—') { $s = trim((string)($v ?? '')); return $s !== '' ? htmlspecialchars($s, ENT_QUOTES, 'UTF-8') : $fallback; }
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<title>Cestovní příkaz</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 11px; color: #111; background: #fff; padding: 28px 36px; line-height: 1.5; }
  .doc-title { text-align: center; font-size: 18px; font-weight: 700; letter-spacing: 3px; text-transform: uppercase; border-bottom: 2.5px solid #111; padding-bottom: 8px; margin-bottom: 18px; }
  .section { margin-bottom: 14px; }
  .section-header { background: #222; color: #fff; font-weight: 600; font-size: 10px; letter-spacing: 1px; text-transform: uppercase; padding: 4px 8px; }
  table { width: 100%; border-collapse: collapse; }
  td { border: 1px solid #bbb; padding: 5px 8px; vertical-align: top; }
  .lbl { background: #f5f5f5; font-weight: 600; width: 28%; white-space: nowrap; color: #333; }
  .transport-outer { width: 100%; border-collapse: collapse; border: 1px solid #bbb; }
  .transport-outer > tbody > tr > td { padding: 0; vertical-align: top; border: none; }
  .transport-outer > tbody > tr > td:first-child { border-right: 1px solid #bbb; width: 50%; }
  .transport-title { background: #444; color: #fff; font-weight: 600; font-size: 10px; letter-spacing: 1px; text-transform: uppercase; padding: 3px 8px; }
  .transport-inner { width: 100%; border-collapse: collapse; table-layout: fixed; }
  .transport-inner td { border: none; border-bottom: 1px solid #ddd; padding: 5px 8px; vertical-align: middle; }
  .transport-inner tr:last-child td { border-bottom: none; }
  .transport-inner .lbl { width: 42%; background: #f5f5f5; font-weight: 600; color: #333; white-space: nowrap; }
  .harmonogram-box { border: 1px solid #bbb; padding: 8px 10px; white-space: pre-wrap; min-height: 50px; font-size: 11px; background: #fafafa; }
  .podpisy { display: flex; gap: 16px; margin-top: 8px; }
  .podpis-col { flex: 1; text-align: center; }
  .podpis-line { border-bottom: 1.5px solid #555; height: 44px; margin-bottom: 4px; }
  .podpis-label { font-size: 9px; color: #555; letter-spacing: 0.5px; }
  .footer { margin-top: 18px; text-align: center; font-size: 9px; color: #999; border-top: 1px solid #ddd; padding-top: 6px; }
  @media print { body { padding: 12px 20px; } .no-print { display: none !important; } }
</style>
</head>
<body>

<div class="doc-title">Cestovní příkaz</div>

<div class="section">
  <div class="section-header">1. Výlet</div>
  <table>
    <tr><td class="lbl">Název výletu</td><td><strong><?= val($trip['nazev_vyletu']) ?></strong></td></tr>
    <tr><td class="lbl">Místo ubytování</td><td><?= val($trip['adresa_ubytovani']) ?></td></tr>
    <tr><td class="lbl">Délka pobytu</td><td><?= val($trip['delka_pobytu']) ?></td></tr>
  </table>
</div>

<div class="section">
  <div class="section-header">2. Účastníci</div>
  <table>
    <tr><td class="lbl">Třídy</td><td><?= val(implode(', ', $tridy)) ?></td></tr>
    <tr>
      <td class="lbl">Počet žáků</td>
      <td><?= $pocetUcastniku ?> žáků <span style="color:#666;font-size:10px;">(celkem <?= $pocetStudentu ?>, odhlášených: <?= $pocetOdhlasenych ?>)</span></td>
    </tr>
    <tr><td class="lbl">Vedoucí (učitelé)</td><td><?= val($trip['uciitele']) ?></td></tr>
  </table>
</div>

<div class="section">
  <div class="section-header">3. Doprava</div>
  <table class="transport-outer">
    <tr>
      <td>
        <div class="transport-title">Doprava tam</div>
        <table class="transport-inner">
          <tr><td class="lbl">Místo odjezdu</td><td><?= val($trip['misto_odjezdu_tam']) ?></td></tr>
          <tr><td class="lbl">Datum a čas</td><td><?= fmtDt($trip['cas_odjezdu_tam']) ?></td></tr>
          <tr><td class="lbl">Prostředek</td><td><?= val($trip['dopravni_prostredek_tam']) ?></td></tr>
        </table>
      </td>
      <td>
        <div class="transport-title">Doprava zpět</div>
        <table class="transport-inner">
          <tr><td class="lbl">Místo odjezdu</td><td><?= val($trip['misto_odjezdu_zpet']) ?></td></tr>
          <tr><td class="lbl">Datum a čas</td><td><?= fmtDt($trip['cas_odjezdu_zpet']) ?></td></tr>
          <tr><td class="lbl">Prostředek</td><td><?= val($trip['dopravni_prostredek_zpet']) ?></td></tr>
        </table>
      </td>
    </tr>
  </table>
</div>

<div class="section">
  <div class="section-header">4. Financování</div>
  <table>
    <tr><td class="lbl">Celková cena</td><td><strong><?= number_format((float)$trip['celkova_cena'], 0, ',', ' ') ?> Kč</strong></td></tr>
    <tr><td class="lbl">Číslo účtu</td><td><?= val($trip['cislo_uctu']) ?></td></tr>
  </table>
</div>

<div class="section">
  <div class="section-header">5. Harmonogram</div>
  <div class="harmonogram-box"><?= val($trip['harmonogram'], 'Neuvedeno') ?></div>
</div>

<div class="section">
  <div class="section-header">6. Podpisy</div>
  <div class="podpisy">
    <div class="podpis-col"><div class="podpis-line"></div><div class="podpis-label">Podpis vedoucího skupiny</div></div>
    <div class="podpis-col"><div class="podpis-line"></div><div class="podpis-label">Podpis ředitele</div></div>
  </div>
</div>

<div class="footer">Vygenerováno: <?= date('j. n. Y, H:i') ?> &nbsp;·&nbsp; VyletOS</div>

</body>
</html>
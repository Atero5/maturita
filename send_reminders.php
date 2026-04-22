<?php
/**
 * send_reminders.php
 * Spouštěj přes Windows Task Scheduler každý den:
 *   E:\xampp\php\php.exe e:\xampp\htdocs\maturita\send_reminders.php
 *
 * Odešle e-mail všem přihlášeným studentům výletů,
 * jejichž odjezd je za přesně REMINDER_DAYS dní.
 */

/* E:\xampp\php\php.exe e:\xampp\htdocs\maturita\send_reminders.php */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/phpmailer/Exception.php';
require __DIR__ . '/phpmailer/PHPMailer.php';
require __DIR__ . '/phpmailer/SMTP.php';

$env = parse_ini_file(__DIR__ . '/.env');

$reminderDays = isset($env['REMINDER_DAYS']) ? (int)$env['REMINDER_DAYS'] : 7;

$conn = new mysqli($env['DB_HOSTNAME'], $env['DB_USERNAME'], $env['DB_PASSWORD'], $env['DB_NAME']);
$conn->set_charset('utf8mb4');

// Najdi výlety, jejichž odjezd je za přesně $reminderDays dní
$targetDate = (new DateTime())->modify("+{$reminderDays} days")->format('Y-m-d');

$stmt = $conn->prepare(
    "SELECT vyletId, nazev_vyletu, cas_odjezdu_tam, celkova_cena, cislo_uctu
     FROM " . $env['TRIPS_TABLE'] . "
     WHERE DATE(cas_odjezdu_tam) = ?"
);
$stmt->bind_param('s', $targetDate);
$stmt->execute();
$trips = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($trips)) {
    echo "[" . date('Y-m-d H:i:s') . "] Žádné výlety za {$reminderDays} dní.\n";
    $conn->close();
    exit;
}

foreach ($trips as $trip) {
    $vyletId = (int)$trip['vyletId'];

    // Načti třídy výletu
    $stmt = $conn->prepare("SELECT tridy FROM " . $env['TRIPS_CLASSES_TABLE'] . " WHERE vyletId = ?");
    $stmt->bind_param('i', $vyletId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $tridy = array_column($rows, 'tridy');
    if (empty($tridy)) continue;

    // Načti odhlášené studenty
    $stmt = $conn->prepare("SELECT userId FROM " . $env['ODHLASENI_TABLE'] . " WHERE vyletId = ?");
    $stmt->bind_param('i', $vyletId);
    $stmt->execute();
    $odhlaseniRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $odhlaseniIds = array_column($odhlaseniRows, 'userId');

    // Načti přihlášené studenty ze tříd výletu
    $placeholders = implode(',', array_fill(0, count($tridy), '?'));
    $types = str_repeat('s', count($tridy));
    $stmt = $conn->prepare(
        "SELECT userId, email, parent_email FROM " . $env['USER_TABLE'] . "
         WHERE role = 'student' AND class IN ($placeholders)"
    );
    $stmt->bind_param($types, ...$tridy);
    $stmt->execute();
    $studenti = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Filtruj odhlášené
    $prijemci = array_filter($studenti, fn($s) => !in_array((int)$s['userId'], array_map('intval', $odhlaseniIds)));

    if (empty($prijemci)) continue;

    // Připrav text e-mailu
    $nazev   = $trip['nazev_vyletu'];
    $odjezd  = (new DateTime($trip['cas_odjezdu_tam']))->format('j. n. Y H:i');
    $cena    = number_format((float)$trip['celkova_cena'], 0, ',', ' ') . ' Kč';
    $ucet    = $trip['cislo_uctu'] ?: '—';

    $subject = "Připomínka platby: {$nazev}";
    $body = "
        <p>Dobrý den,</p>
        <p>připomínáme, že za <strong>{$reminderDays} dní</strong> odjíždíte na výlet <strong>" . htmlspecialchars($nazev) . "</strong>.</p>
        <table style='border-collapse:collapse;font-family:Arial,sans-serif;font-size:14px;'>
            <tr><td style='padding:4px 12px 4px 0;color:#666;'>Datum odjezdu:</td><td><strong>{$odjezd}</strong></td></tr>
            <tr><td style='padding:4px 12px 4px 0;color:#666;'>Celková cena:</td><td><strong>{$cena}</strong></td></tr>
            <tr><td style='padding:4px 12px 4px 0;color:#666;'>Číslo účtu:</td><td><strong>{$ucet}</strong></td></tr>
        </table>
        <p>Pokud jste platbu dosud neprovedli, prosíme o její uhrazení co nejdříve.</p>
        <p style='color:#999;font-size:12px;margin-top:24px;'>Tato zpráva byla odeslána automaticky systémem VyletOS.</p>
    ";

    // Odešli e-mail každému studentovi (a rodiči, pokud má vyplněný parent_email)
    foreach ($prijemci as $student) {
        sendMail($env, $student['email'], $subject, $body);
        echo "[" . date('Y-m-d H:i:s') . "] Odesláno studentovi: {$student['email']} (výlet: {$nazev})\n";

        if (!empty($student['parent_email'])) {
            sendMail($env, $student['parent_email'], $subject, $body);
            echo "[" . date('Y-m-d H:i:s') . "] Odesláno rodiči: {$student['parent_email']} (výlet: {$nazev})\n";
        }
    }
}

$conn->close();

function sendMail(array $env, string $to, string $subject, string $body): void
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $env['SMTP_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $env['SMTP_USERNAME'];
        $mail->Password   = $env['SMTP_PASSWORD'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)$env['SMTP_PORT'];
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($env['SMTP_USERNAME'], $env['SMTP_FROM_NAME']);
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<p>', '</p>'], ["\n", "\n", "\n"], $body));

        $mail->send();
    } catch (Exception $e) {
        echo "[CHYBA] {$to}: {$mail->ErrorInfo}\n";
    }
}

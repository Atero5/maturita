<?php
session_start();
$env = parse_ini_file(__DIR__ . '/.env');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit();
}

$type = isset($_GET['type']) ? $_GET['type'] : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$type || $id <= 0) {
    http_response_code(400);
    echo 'Neplatné parametry';
    exit();
}

// Načtení obsahu z příslušné print stránky
$content = '';
$filename = '';

if ($type === 'seznam') {
    if ($_SESSION['role'] !== 'teacher' && $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo 'Přístup odepřen';
        exit();
    }
    ob_start();
    include 'print_seznam.php';
    $content = ob_get_clean();
    $filename = 'seznam_zaku.pdf';
} elseif ($type === 'harmonogram') {
    ob_start();
    include 'print_harmonogram.php';
    $content = ob_get_clean();
    $filename = 'harmonogram.pdf';
} elseif ($type === 'cestovni') {
    if ($_SESSION['role'] !== 'teacher' && $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo 'Přístup odepřen';
        exit();
    }
    ob_start();
    include 'print_cestovni_prikaz.php';
    $content = ob_get_clean();
    $filename = 'cestovni_prikaz.pdf';
} else {
    http_response_code(400);
    echo 'Neznámý typ';
    exit();
}

if (empty($content)) {
    http_response_code(500);
    echo 'Chyba při generování obsahu';
    exit();
}

// Použití TCPDF pro generování skutečného PDF
require_once('tcpdf/tcpdf.php');

class MYPDF extends TCPDF {
    public function Header() {
        // Bez hlavičky
    }
    public function Footer() {
        // Bez patičky
    }
}

$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(TRUE, 15);
$pdf->AddPage();
$pdf->SetFont('dejavusans', '', 10);

// Vložit HTML obsah
$pdf->writeHTML($content, true, false, true, false, '');

// Vrátit PDF ke stažení
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo $pdf->Output('', 'S');


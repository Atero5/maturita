<?php
session_start();

// Load environment variables
$env = parse_ini_file(__DIR__ . '/.env');

// jen admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

$conn = new mysqli($env['DB_HOSTNAME'], $env['DB_USERNAME'], $env['DB_PASSWORD'], $env['DB_NAME']);

if ($conn->connect_error) {
    die("Chyba připojení: " . $conn->connect_error);
}

// Získání ID a role
$id = $_GET['id'];
$role = $_GET['role'];

// Pokud měníme na studenta, potřebujeme třídu
if ($role === 'student') {
    // Pokud je třída v GET, použij ji, jinak zobraz formulář
    if (isset($_GET['class'])) {
        $class = $_GET['class'];
        $stmt = $conn->prepare("UPDATE " . $env['USER_TABLE'] . " SET role = ?, class = ? WHERE userId = ?");
        $stmt->bind_param("ssi", $role, $class, $id);
        $stmt->execute();
        $stmt->close();
        $conn->close();
        header("Location: admin.html");
        exit();
    } else {
        // Zobraz formulář pro zadání třídy
        echo '<form method="get" action="change_role.php">';
        echo '<input type="hidden" name="id" value="' . htmlspecialchars($id) . '">';
        echo '<input type="hidden" name="role" value="student">';
        echo 'Zadejte třídu: <input type="text" name="class" required> ';
        echo '<button type="submit">Potvrdit</button>';
        echo '</form>';
        $conn->close();
        exit();
    }
}

// Ostatní role
if ($role === 'teacher') {
    // Nastav roli a class na NULL
    $stmt = $conn->prepare("UPDATE " . $env['USER_TABLE'] . " SET role = ?, class = NULL WHERE userId = ?");
    $stmt->bind_param("si", $role, $id);
    $stmt->execute();
    $stmt->close();
    $conn->close();
    header("Location: admin.html");
    exit();
} else {
    $stmt = $conn->prepare("UPDATE " . $env['USER_TABLE'] . " SET role = ? WHERE userId = ?");
    $stmt->bind_param("si", $role, $id);
    $stmt->execute();
    $stmt->close();
    $conn->close();
    header("Location: admin.html");
    exit();
}
?>
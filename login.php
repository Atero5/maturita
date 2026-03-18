<?php
session_start();

// Připojení k databázi
$conn = new mysqli("localhost", "root", "", "vyletos");

// Kontrola připojení
if ($conn->connect_error) {
    die("Chyba připojení: " . $conn->connect_error);
}

// Kontrola, že přišla data z formuláře
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = $_POST['email'];
    $password = $_POST['password'];

    // SQL dotaz – načteme i roli
    $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($id, $hashed_password, $role);
    $stmt->fetch();

    // Ověření uživatele
    if ($stmt->num_rows > 0 && password_verify($password, $hashed_password)) {

        // Uložení do session
        $_SESSION['user_id'] = $id;
        $_SESSION['email'] = $email;
        $_SESSION['role'] = $role;

        // 🔥 Přesměrování podle role
        if ($role === "teacher") {
            header("Location: Teacher.php");
        } elseif ($role === "admin") {
            header("Location: Admin.php");
        } else {
            header("Location: User.php");
        }

        exit();

    } else {
        // Špatné přihlašovací údaje
        header("Location: login.html?error=1");
        exit();
    }

    $stmt->close();
}

$conn->close();
?>
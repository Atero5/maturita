<?php
session_start();

// Připojení k databázi
$conn = new mysqli("localhost", "root", "", "vyletos");

// Kontrola připojení
if ($conn->connect_error) {
    die("Chyba připojení: " . $conn->connect_error);
}

// Kontrola, jestli přišla data z formuláře
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = $_POST['email'];
    $password = $_POST['password'];

    // Připravený dotaz
    $stmt = $conn->prepare("SELECT id, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($id, $hashed_password);
    $stmt->fetch();

    // Ověření uživatele
    if ($stmt->num_rows > 0 && password_verify($password, $hashed_password)) {

        // Uložení do session
        $_SESSION['user_id'] = $id;
        $_SESSION['email'] = $email;

        // Přesměrování na chráněnou stránku
        header("Location: User.php");
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
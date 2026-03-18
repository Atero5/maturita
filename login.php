<?php
session_start();

// Připojení k databázi
$conn = new mysqli("localhost", "root", "", "vyletos");

// Kontrola připojení
if ($conn->connect_error) {
    die("Chyba připojení: " . $conn->connect_error);
}

<<<<<<< HEAD
// Kontrola, že přišla data z formuláře
=======
// Kontrola, jestli přišla data z formuláře
>>>>>>> 33699bd (prihlaseni/registrace php)
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = $_POST['email'];
    $password = $_POST['password'];

<<<<<<< HEAD
    // SQL dotaz – načteme i roli
    $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($id, $hashed_password, $role);
=======
    // Připravený dotaz
    $stmt = $conn->prepare("SELECT id, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($id, $hashed_password);
>>>>>>> 33699bd (prihlaseni/registrace php)
    $stmt->fetch();

    // Ověření uživatele
    if ($stmt->num_rows > 0 && password_verify($password, $hashed_password)) {

        // Uložení do session
        $_SESSION['user_id'] = $id;
        $_SESSION['email'] = $email;
<<<<<<< HEAD
        $_SESSION['role'] = $role;

        // 🔥 Přesměrování podle role
        if ($role === "teacher") {
            header("Location: Teacher.html");
        } elseif ($role === "admin") {
            header("Location: Admin.html");
        } else {
            header("Location: User.html");
        }

        exit();

    } else {
=======

        // Přesměrování na chráněnou stránku
        header("Location: User.php");
        exit();

    } else {

>>>>>>> 33699bd (prihlaseni/registrace php)
        // Špatné přihlašovací údaje
        header("Location: login.html?error=1");
        exit();
    }

    $stmt->close();
}

$conn->close();
?>
<?php
// Připojení k databázi
$conn = new mysqli("localhost", "root", "", "vyletos");

// Kontrola připojení
if ($conn->connect_error) {
    die("Chyba připojení: " . $conn->connect_error);
}

// Získání dat z formuláře
$email = $_POST['email'];
$password = $_POST['password'];
$confirm_password = $_POST['confirm_password'];

// 1️⃣ Kontrola, zda jsou pole vyplněná
if (empty($email) || empty($password) || empty($confirm_password)) {
    die("Všechna pole musí být vyplněna.");
}

// 2️⃣ Kontrola shody hesel
if ($password !== $confirm_password) {
    die("Hesla se neshodují.");
}

// 3️⃣ Zahashování hesla (bezpečnost!)
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// 4️⃣ Připravený dotaz (ochrana proti SQL injection)
$stmt = $conn->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
$stmt->bind_param("ss", $email, $hashed_password);

// 5️⃣ Pokus o uložení
if ($stmt->execute()) {
    echo "Registrace proběhla úspěšně!";
} else {
    echo "Chyba: Tento email už může být registrován.";
}

// Zavření spojení
$stmt->close();
$conn->close();
?>
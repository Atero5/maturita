<?php
// Load environment variables
$env = parse_ini_file(__DIR__ . '/.env');

// Připojení k databázi
$conn = new mysqli($env['DB_HOSTNAME'], $env['DB_USERNAME'], $env['DB_PASSWORD'], $env['DB_NAME']);

// Kontrola připojení
if ($conn->connect_error) {
    die("Chyba připojení: " . $conn->connect_error);
}

// Získání dat z formuláře
$email = $_POST['email'];
$password = $_POST['password'];
$confirm_password = $_POST['confirm_password'];
$class = $_POST['class'];
$role = $_POST['role'];

// 1️⃣ Kontrola, zda jsou pole vyplněná
if (empty($email) || empty($password) || empty($confirm_password) || empty($class) || empty($role)) {
    die("Všechna pole musí být vyplněna.");
}

// 2️⃣ Kontrola shody hesel
if ($password !== $confirm_password) {
    die("Hesla se neshodují.");
}

// 3️⃣ Zahashování hesla
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// 4️⃣ Připravený dotaz - přidán sloupec class i role
$stmt = $conn->prepare("INSERT INTO " . $env['USER_TABLE'] . " (email, password, class, role) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $email, $hashed_password, $class, $role);

// 5️⃣ Pokus o uložení
if ($stmt->execute()) {
    // Úspěšná registrace - přesměrování na login s parametrem
    header("Location: login.html?registered=true");
    exit();
} else {
    echo "Chyba: " . $stmt->error;
}

// Zavření spojení
$stmt->close();
$conn->close();
?>
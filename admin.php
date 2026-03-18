<?php
session_start();

// 🔒 ochrana – jen admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

// připojení k databázi
$conn = new mysqli("localhost", "root", "", "vyletos");

if ($conn->connect_error) {
    die("Chyba připojení: " . $conn->connect_error);
}

// načtení uživatelů
$result = $conn->query("SELECT id, email, role FROM users");
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Admin panel</title>
</head>
<body>

<h1>Admin panel</h1>
<p>Přihlášen jako: <?php echo $_SESSION['email']; ?></p>

<table border="1" cellpadding="10">
    <tr>
        <th>ID</th>
        <th>Email</th>
        <th>Role</th>
        <th>Akce</th>
    </tr>

    <?php while($user = $result->fetch_assoc()): ?>
    <tr>
        <td><?php echo $user['id']; ?></td>
        <td><?php echo $user['email']; ?></td>
        <td><?php echo $user['role']; ?></td>
        <td>
            <a href="change_role.php?id=<?php echo $user['id']; ?>&role=student">Student</a> |
            <a href="change_role.php?id=<?php echo $user['id']; ?>&role=teacher">Teacher</a> |
            <a href="change_role.php?id=<?php echo $user['id']; ?>&role=admin">Admin</a> |
            
            <a href="delete_user.php?id=<?php echo $user['id']; ?>" 
               onclick="return confirm('Opravdu chcete smazat tohoto uživatele?');">
               Smazat
            </a>
        </td>
    </tr>
    <?php endwhile; ?>

</table>

<br>
<a href="logout.php">Odhlásit se</a>

</body>
</html>

<?php
$conn->close();
?>
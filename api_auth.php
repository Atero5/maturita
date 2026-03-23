<?php
session_start();

// Nastavíme, že vracíme JSON
header('Content-Type: application/json');

$response = [
    'authenticated' => isset($_SESSION['user_id']),
    'email' => isset($_SESSION['email']) ? $_SESSION['email'] : null
];

echo json_encode($response);
exit();
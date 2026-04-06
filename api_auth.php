<?php
session_start();

// vrací JSON
header('Content-Type: application/json');

$response = [
    'authenticated' => isset($_SESSION['user_id']),
    'email' => isset($_SESSION['email']) ? $_SESSION['email'] : null,
    'role' => $_SESSION['role'] ?? null
];

echo json_encode($response);
exit();
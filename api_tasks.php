<?php
session_start();
header('Content-Type: application/json');

$env = parse_ini_file(__DIR__ . '/.env');

// Kontrola přihlášení
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nepovolený přístup']);
    exit();
}

$conn = new mysqli($env['DB_HOSTNAME'], $env['DB_USERNAME'], $env['DB_PASSWORD'], $env['DB_NAME']);
$conn->set_charset("utf8mb4");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Chyba připojení k DB']);
    exit();
}

$userId = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

// POST - vytvoření úkolu
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $task = $data['task'] ?? null;
    $end_time = $data['end_time'] ?? null;
    if ($task && $end_time) {
        $stmt = $conn->prepare('INSERT INTO ' . $env['TASKS_TABLE'] . ' (user_id, task, end_time) VALUES (?, ?, ?)');
        $stmt->bind_param('iss', $userId, $task, $end_time);
        $success = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $success]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Chybí data']);
    }
    exit;
}

// GET - načtení všech úkolů přihlášeného učitele
if ($method === 'GET') {
    $stmt = $conn->prepare('SELECT taskId, task, end_time, date FROM ' . $env['TASKS_TABLE'] . ' WHERE user_id = ? ORDER BY taskId DESC');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $tasks = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    echo json_encode(['success' => true, 'tasks' => $tasks]);
    exit;
}

// PATCH - přiřazení úkolu k datu (nebo odebrání - date = null)
if ($method === 'PATCH') {
    $data = json_decode(file_get_contents('php://input'), true);
    $taskId = (int)($data['taskId'] ?? 0);
    $date = isset($data['date']) ? ($data['date'] ?: null) : null;
    if ($taskId) {
        $stmt = $conn->prepare('UPDATE ' . $env['TASKS_TABLE'] . ' SET date = ? WHERE taskId = ? AND user_id = ?');
        $stmt->bind_param('sii', $date, $taskId, $userId);
        $success = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $success]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Chybí taskId']);
    }
    exit;
}

// DELETE - smazání úkolu
if ($method === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    $taskId = (int)($data['taskId'] ?? 0);
    if ($taskId) {
        $stmt = $conn->prepare('DELETE FROM ' . $env['TASKS_TABLE'] . ' WHERE taskId = ? AND user_id = ?');
        $stmt->bind_param('ii', $taskId, $userId);
        $success = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $success]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Chybí taskId']);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Neplatný požadavek']);
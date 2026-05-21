<?php
session_start();
if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['error' => 'authentication required']);
    exit;
}

$maintenancePath = __DIR__ . '/maintenance.json';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true);
    $note = trim($payload['note'] ?? '');
    if ($note === '') {
        http_response_code(400);
        echo json_encode(['error' => 'note is required']);
        exit;
    }

    $items = [];
    if (file_exists($maintenancePath)) {
        $items = json_decode(file_get_contents($maintenancePath), true);
        if (!is_array($items)) {
            $items = [];
        }
    }

    $items[] = [
        'created' => date('c'),
        'note' => $note,
    ];
    file_put_contents($maintenancePath, json_encode($items, JSON_PRETTY_PRINT));

    echo json_encode(['success' => true]);
    exit;
}

$items = [];
if (file_exists($maintenancePath)) {
    $items = json_decode(file_get_contents($maintenancePath), true);
    if (!is_array($items)) {
        $items = [];
    }
}

echo json_encode($items);

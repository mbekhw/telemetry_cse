<?php
session_start();
if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['error' => 'authentication required']);
    exit;
}

$storagePath = __DIR__ . '/storage';
if (!is_dir($storagePath)) {
    mkdir($storagePath, 0755, true);
}

if (isset($_GET['file'])) {
    $file = basename($_GET['file']);
    $path = $storagePath . '/' . $file;
    if (!file_exists($path)) {
        http_response_code(404);
        echo json_encode(['error' => 'file not found']);
        exit;
    }

    header('Content-Type: application/json');
    echo file_get_contents($path);
    exit;
}

$files = [];
foreach (scandir($storagePath) as $entry) {
    if ($entry === '.' || $entry === '..') {
        continue;
    }
    if (substr($entry, -5) !== '.json') {
        continue;
    }
    $path = $storagePath . '/' . $entry;
    $files[] = [
        'name' => $entry,
        'timestamp' => date('c', filemtime($path)),
    ];
}

usort($files, fn($a, $b) => strcmp($b['name'], $a['name']));

header('Content-Type: application/json');
echo json_encode(['files' => $files]);

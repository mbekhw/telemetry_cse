<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: ../login.php');
    exit;
}

$settingsPath = __DIR__ . '/settings.json';
$apiBaseUrl = 'https://api.entreprise-b.com';
$azureBlobUrl = '';
$azureSasToken = '';
if (file_exists($settingsPath)) {
    $settings = json_decode(file_get_contents($settingsPath), true);
    if (!empty($settings['api_base_url'])) {
        $apiBaseUrl = rtrim($settings['api_base_url'], '/');
    }
    if (!empty($settings['azure_blob_url'])) {
        $azureBlobUrl = rtrim($settings['azure_blob_url'], '/');
    }
    if (!empty($settings['azure_sas_token'])) {
        $azureSasToken = ltrim($settings['azure_sas_token'], '?');
    }
}

$response = @file_get_contents($apiBaseUrl);
if ($response === false) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'could not fetch API data']);
    exit;
}

$fileName = gmdate('Y-m-d_H-i-s') . '.json';

if ($azureBlobUrl !== '' && $azureSasToken !== '') {
    $uploadUrl = $azureBlobUrl . '/' . rawurlencode($fileName) . '?' . $azureSasToken;
    $ch = curl_init($uploadUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'x-ms-blob-type: BlockBlob',
        'Content-Type: application/json',
        'Content-Length: ' . strlen($response),
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
    curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status < 200 || $status >= 300) {
        $storagePath = __DIR__ . '/storage';
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }
        file_put_contents($storagePath . '/' . $fileName, $response);
    }
} else {
    $storagePath = __DIR__ . '/storage';
    if (!is_dir($storagePath)) {
        mkdir($storagePath, 0755, true);
    }
    file_put_contents($storagePath . '/' . $fileName, $response);
}

header('Content-Type: application/json');
echo $response;
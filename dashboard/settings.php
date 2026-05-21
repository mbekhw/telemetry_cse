<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: ../login.php');
    exit;
}

$page_title = 'Dashboard Settings';
include '../includes/header.html';
include '../includes/navbar.html';

$settingsPath = __DIR__ . '/settings.json';
$settings = [];
if (file_exists($settingsPath)) {
    $settings = json_decode(file_get_contents($settingsPath), true);
    if (!is_array($settings)) {
        $settings = [];
    }
}

$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings['api_base_url'] = trim($_POST['api_base_url'] ?? '');
    $settings['poll_interval'] = max(10, (int) ($_POST['poll_interval'] ?? 60));
    $settings['azure_blob_url'] = trim($_POST['azure_blob_url'] ?? '');
    $settings['azure_sas_token'] = trim($_POST['azure_sas_token'] ?? '');
    file_put_contents($settingsPath, json_encode($settings, JSON_PRETTY_PRINT));
    $success = true;
}

$apiUrl = htmlspecialchars($settings['api_base_url'] ?? 'https://api.entreprise-b.com');
$pollInterval = htmlspecialchars($settings['poll_interval'] ?? 60);
$azureBlobUrl = htmlspecialchars($settings['azure_blob_url'] ?? '');
$azureSasToken = htmlspecialchars($settings['azure_sas_token'] ?? '');
?>

<div class="container py-4">
    <h2>Dashboard Settings</h2>

    <?php if ($success): ?>
        <div class="alert alert-success">Settings saved.</div>
    <?php endif; ?>

    <form method="post" class="mb-4">
        <div class="form-group">
            <label for="api_base_url">API Base URL</label>
            <input type="text" id="api_base_url" name="api_base_url" class="form-control" value="<?php echo $apiUrl; ?>" placeholder="https://api.entreprise-b.com">
            <small class="form-text text-muted">Use this URL to fetch the reactor data.</small>
        </div>
        <div class="form-group">
            <label for="poll_interval">Polling interval (seconds)</label>
            <input type="number" id="poll_interval" name="poll_interval" class="form-control" value="<?php echo $pollInterval; ?>" min="10">
        </div>
        <hr>
        <h5>Azure Blob Storage</h5>
        <div class="form-group">
            <label for="azure_blob_url">Azure Blob base URL</label>
            <input type="text" id="azure_blob_url" name="azure_blob_url" class="form-control" value="<?php echo $azureBlobUrl; ?>" placeholder="https://<account>.blob.core.windows.net/<container>">
        </div>
        <div class="form-group">
            <label for="azure_sas_token">Azure SAS token</label>
            <input type="text" id="azure_sas_token" name="azure_sas_token" class="form-control" value="<?php echo $azureSasToken; ?>" placeholder="?sv=...&ss=...&srt=...&sp=...">
            <small class="form-text text-muted">Leave blank to keep saving locally in dashboard/storage.</small>
        </div>
        <button type="submit" class="btn btn-primary">Save settings</button>
    </form>
</div>

<?php include '../includes/footer.html'; ?>

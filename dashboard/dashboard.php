<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: ../login.php');
    exit;
}

$page_title = 'Reactor Dashboard';
include '../includes/header.html';
include '../includes/navbar.html';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2>Reactor Monitoring Dashboard</h2>
            <p class="text-muted">Live reactor metrics and simple historical data storage.</p>
        </div>
        <a href="settings.php" class="btn btn-outline-secondary">Settings</a>
    </div>

    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">Real-time data</div>
                <div class="card-body">
                    <p><strong>Status:</strong> <span id="reactorStatus">Unknown</span></p>
                    <p><strong>Temperature:</strong> <span id="reactorTemperature">N/A</span></p>
                    <p><strong>Pressure:</strong> <span id="reactorPressure">N/A</span></p>
                    <p><strong>Alerts:</strong> <span id="alerts" class="badge badge-secondary">No alerts</span></p>
                    <h5 class="mt-4">Sensor details</h5>
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th>Metric</th>
                                <th>Value</th>
                            </tr>
                        </thead>
                        <tbody id="realtimeDetails"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">Maintenance planning</div>
                <div class="card-body">
                    <form id="maintenanceForm">
                        <div class="form-group">
                            <label for="maintenanceNote">New maintenance note</label>
                            <textarea id="maintenanceNote" class="form-control" rows="3"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Save note</button>
                    </form>
                    <hr>
                    <h5>Planned maintenance</h5>
                    <ul id="maintenanceList" class="list-group"></ul>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12 mb-4">
            <div class="card">
                <div class="card-header">Historical data files</div>
                <div class="card-body">
                    <p class="text-muted">Each API response is saved as a local JSON file in dashboard/storage.</p>
                    <div class="row">
                        <div class="col-md-4">
                            <h6>Recent files</h6>
                            <ul id="historyList" class="list-group"></ul>
                        </div>
                        <div class="col-md-8">
                            <h6>Selected file contents</h6>
                            <pre id="historyDetails" class="p-3 border" style="background:#f8f9fa;white-space:pre-wrap;word-break:break-word;max-height:400px;overflow:auto;"></pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="js/dashboard.js"></script>

<?php include '../includes/footer.html'; ?>
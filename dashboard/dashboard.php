<?php
/*
 * dashboard.php
 *
 * Purpose:
 *   Main dashboard UI for the Reactor Monitoring feature.
 *   Shows real-time metrics, recent historical files, and a simple
 *   maintenance planning area. This is a presentation page only;
 *   live data and persisted history/maintenance are provided by
 *   companion endpoints in the same folder (api_fetch.php,
 *   history.php, maintenance.php, store_data.php).
 *
 * Interconnections:
 *   - Includes `../includes/header.html` and `../includes/navbar.html`
 *     to reuse site-wide layout and session initialization.
 *   - The JavaScript client `js/dashboard.js` calls:
 *       - `api_fetch.php` to fetch live reactor JSON and trigger
 *         saving of that JSON as files (or to Azure blob storage)
 *       - `history.php` to list and retrieve saved JSON files
 *       - `maintenance.php` to list and save maintenance notes
 *   - `settings.php` provides a simple UI to change `api_base_url`,
 *     polling interval, and optional Azure Blob settings which affect
 *     `api_fetch.php` and `store_data.php` behavior.
 *
 * Security / auth:
 *   This page requires a logged-in user; the session check below
 *   prevents unauthenticated access and redirects to the login page.
 */

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
    <style>
        .dashboard-hero {
            background: linear-gradient(135deg, #0d6efd 0%, #6610f2 100%);
            color: white;
            border-radius: 1rem;
            padding: 1.75rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 25px 50px rgba(13, 110, 253, 0.12);
        }
        .dashboard-hero h2 {
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .dashboard-hero p {
            opacity: 0.85;
            margin-bottom: 0;
        }
        .dashboard-card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.08);
        }
        .status-summary {
            min-width: 160px;
            text-align: center;
            border-radius: 1rem;
            padding: 0.8rem 1rem;
        }
        .status-ok { background: #d1e7dd; color: #0f5132; }
        .status-warning { background: #fff3cd; color: #664d03; }
        .status-critical { background: #f8d7da; color: #842029; }
        .summary-table {
            width: max-content;
            min-width: 100%;
            table-layout: fixed;
        }
        .summary-table th,
        .summary-table td {
            border-top: none;
            padding: 0.75rem 1rem;
            vertical-align: middle;
            white-space: normal;
            word-break: break-word;
            overflow-wrap: anywhere;
            min-width: 140px;
        }
        .summary-table th {
            font-weight: 700;
            background: rgba(248, 249, 250, 0.98);
            position: sticky;
            top: 0;
            z-index: 5;
            border-bottom: 2px solid #dee2e6;
        }
        .summary-table td {
            font-weight: 400;
            max-width: none;
        }
        .summary-table th .col-label {
            display: inline-block;
            max-width: calc(100% - 12px);
            vertical-align: middle;
        }
        .summary-table th .col-resizer {
            position: absolute;
            right: 0;
            top: 0;
            width: 12px;
            height: 100%;
            cursor: col-resize;
            user-select: none;
        }
        .summary-table th {
            position: sticky;
            top: 0;
            overflow: visible;
        }
        .table-responsive {
            overflow-x: auto;
            overflow-y: auto;
        }
        .summary-table tr.status-ok td {
            background: #e9f7ef;
        }
        .summary-table tr.status-warning td {
            background: #fff7e0;
        }
        .summary-table tr.status-critical td {
            background: #fde2e1;
        }
        .summary-status {
            font-weight: 700;
            text-transform: uppercase;
        }
        .code-preview {
            background: #0f172a;
            color: #f8fafc;
            border-radius: 0.8rem;
            padding: 1rem;
            overflow-x: auto;
            cursor: default;
        }
        .card-header { font-weight: 700; }
    </style>

    <div class="dashboard-hero">
        <div class="d-flex justify-content-between align-items-start flex-wrap">
            <div>
                <h2>Reactor Monitoring Dashboard</h2>
                <p class="mb-0">Live reactor metrics, historical snapshots, and operational context in one place.</p>
            </div>
            <a href="settings.php" class="btn btn-light text-primary mt-3">Settings</a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Real-time data</span>
                    <button type="button" id="refreshBtn" class="btn btn-sm btn-primary">Refresh</button>
                </div>
                <div class="card-body">
                    <p><strong>Status:</strong> <span id="reactorStatus">Unknown</span></p>
                    <p><strong>Temperature:</strong> <span id="reactorTemperature">N/A</span></p>
                    <p><strong>Pressure:</strong> <span id="reactorPressure">N/A</span></p>
                    <p><strong>Alerts:</strong> <span id="alerts" class="badge badge-secondary">No alerts</span></p>
                    <p class="text-muted small mb-3" id="apiStatus">Click refresh to load live data.</p>
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

    <div class="row mb-4">
        <div class="col-lg-12">
            <div class="card dashboard-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Summary table - Historical files</span>
                    <button type="button" id="refreshSummaryBtn" class="btn btn-sm btn-primary">Refresh</button>
                </div>
                <div class="card-body">
                    <div class="table-responsive" style="max-height: 450px; overflow-x: auto; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 0.5rem;">
                        <table class="table table-sm summary-table mb-0">
                            <thead id="summaryTableHead">
                                <tr>
                                    <th>Filename</th>
                                </tr>
                            </thead>
                            <tbody id="summaryTableBody">
                                <tr><td colspan="100%" class="text-muted text-center py-3">No files loaded yet. Click refresh to load historical data.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12 mb-4">
            <div class="card dashboard-card">
                <div class="card-header">Historical data files</div>
                <div class="card-body">
                    <p class="text-muted">Each sensor reading is saved locally or in the cloud.</p>
                    <div class="row">
                        <div class="col-md-4">
                            <h6>Recent files</h6>
                            <ul id="historyList" class="list-group"></ul>
                        </div>
                        <div class="col-md-8">
                            <h6>Selected file contents</h6>
                            <pre id="historyDetails" class="code-preview"></pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="js/dashboard.js"></script>

<?php include '../includes/footer.html'; ?>
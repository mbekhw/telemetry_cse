/**
 * js/dashboard.js
 *
 * Purpose:
 *   Client-side behavior for the Reactor Monitoring dashboard. This
 *   script is responsible for:
 *     - polling `api_fetch.php` for live reactor JSON
 *     - rendering realtime values into the dashboard DOM
 *     - loading the list of historical files from `history.php`
 *     - loading and saving maintenance notes via `maintenance.php`
 *
 * Interconnections:
 *   - `api_fetch.php` returns live JSON and persists snapshots to
 *     `dashboard/storage` (or to Azure when configured).
 *   - `history.php` provides file listings and file contents.
 *   - `maintenance.php` handles GET/POST of maintenance notes.
 */

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('maintenanceForm').addEventListener('submit', async event => {
        event.preventDefault();
        const note = document.getElementById('maintenanceNote').value.trim();
        if (!note) {
            return;
        }
        await saveMaintenance(note);
        document.getElementById('maintenanceNote').value = '';
        await loadMaintenance();
    });

    // Fetch live data from the server; server also persists the JSON
    // snapshot for history browsing. Errors are surfaced in a small
    // status element on the page so the operator can see failures.
    async function fetchRealtime() {
        const output = document.getElementById('apiStatus');
        try {
            const response = await fetch('api_fetch.php');
            if (!response.ok) {
                output.textContent = `Error ${response.status}`;
                return;
            }
            const data = await response.json();
            renderRealtime(data);
            output.textContent = 'Live reactor data loaded';
        } catch (error) {
            output.textContent = 'Unable to fetch realtime data';
        }
    }

    // Load the list of recent saved JSON files and populate the left column
    async function fetchHistory() {
        const historyList = document.getElementById('historyList');
        try {
            const response = await fetch('history.php');
            if (!response.ok) {
                historyList.innerHTML = '<li class="list-group-item text-danger">Unable to load history list</li>';
                return;
            }
            const data = await response.json();
            historyList.innerHTML = '';
            if (!Array.isArray(data.files) || data.files.length === 0) {
                historyList.innerHTML = '<li class="list-group-item">No historical file available</li>';
                return;
            }
            data.files.slice(0, 10).forEach(file => {
                const item = document.createElement('li');
                item.className = 'list-group-item d-flex justify-content-between align-items-center';
                item.innerHTML = `<span>${file.name}</span><span class="badge badge-secondary badge-pill">${file.timestamp}</span>`;
                item.addEventListener('click', () => loadHistoryFile(file.name));
                historyList.appendChild(item);
            });
        } catch (error) {
            historyList.innerHTML = '<li class="list-group-item text-danger">Unable to fetch history</li>';
        }
    }

    // Load all history files and populate summary table with all files and their JSON properties
    async function loadSummaryTable() {
        const tableHead = document.getElementById('summaryTableHead');
        const tableBody = document.getElementById('summaryTableBody');
        try {
            const response = await fetch('history.php');
            if (!response.ok) {
                tableBody.innerHTML = '<tr><td colspan="100%" class="text-danger">Unable to load history</td></tr>';
                return;
            }
            const data = await response.json();
            if (!Array.isArray(data.files) || data.files.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="100%" class="text-muted text-center py-3">No historical files available</td></tr>';
                return;
            }

            // Fetch content for all files and collect all unique keys
            const allFiles = data.files;
            const fileDataList = [];
            const allKeys = new Set(['Filename']);

            for (const file of allFiles) {
                try {
                    const fileResponse = await fetch(`history.php?file=${encodeURIComponent(file.name)}`);
                    if (fileResponse.ok) {
                        const fileData = await fileResponse.json();
                        const normalizedData = { ...fileData };
                        if (normalizedData.details && typeof normalizedData.details === 'object' && !Array.isArray(normalizedData.details)) {
                            Object.entries(normalizedData.details).forEach(([subKey, subValue]) => {
                                normalizedData[subKey] = subValue;
                                allKeys.add(subKey);
                            });
                            delete normalizedData.details;
                        }
                        fileDataList.push({ name: file.name, data: normalizedData });
                        if (typeof normalizedData === 'object' && normalizedData !== null) {
                            Object.keys(normalizedData).forEach(key => {
                                if (key === 'source') {
                                    return;
                                }
                                allKeys.add(key);
                            });
                        }
                    } else {
                        fileDataList.push({ name: file.name, data: {} });
                    }
                } catch (error) {
                    fileDataList.push({ name: file.name, data: {} });
                }
            }

            // Build table header from all unique keys, keeping important keys first.
            // If detail keys are present, they are promoted to their own columns.
            const preferredOrder = ['Filename', 'timestamp', 'status', 'temperature', 'pressure', 'alerts'];
            const extraKeys = Array.from(allKeys).filter(key => !preferredOrder.includes(key)).sort();
            const keyArray = [
                ...preferredOrder.filter(key => allKeys.has(key)),
                ...extraKeys,
            ];

            const headerRow = document.createElement('tr');
            keyArray.forEach(key => {
                const th = document.createElement('th');
                th.style.position = 'relative';
                th.innerHTML = `<span class="col-label">${key}</span><span class="col-resizer"></span>`;
                headerRow.appendChild(th);
            });
            tableHead.innerHTML = '';
            tableHead.appendChild(headerRow);
            initResizableSummaryColumns();

            // Build table rows with one column per key.
            tableBody.innerHTML = '';
            fileDataList.forEach(fileInfo => {
                const row = document.createElement('tr');
                const statusValue = String(fileInfo.data.status || '').toLowerCase();
                if (['ok', 'warning', 'critical'].includes(statusValue)) {
                    row.classList.add(`status-${statusValue}`);
                }

                keyArray.forEach(key => {
                    const td = document.createElement('td');
                    if (key === 'Filename') {
                        td.textContent = fileInfo.name;
                    } else {
                        const value = fileInfo.data[key];
                        if (value !== undefined && value !== null) {
                            if (key === 'timestamp') {
                                const date = new Date(value);
                                td.textContent = isNaN(date.getTime()) ? String(value) : date.toLocaleString();
                            } else if (Array.isArray(value)) {
                                td.textContent = value.join(', ');
                            } else if (typeof value === 'object') {
                                td.textContent = JSON.stringify(value);
                            } else {
                                td.textContent = String(value);
                            }
                        } else {
                            td.textContent = '-';
                        }
                    }
                    if (key === 'status') {
                        td.className = 'summary-status';
                    }
                    row.appendChild(td);
                });
                tableBody.appendChild(row);
            });
        } catch (error) {
            tableBody.innerHTML = '<tr><td colspan="100%" class="text-danger">Error loading summary table</td></tr>';
        }
    }

    function initResizableSummaryColumns() {
        const table = document.querySelector('.summary-table');
        if (!table) return;

        const headerCells = table.querySelectorAll('th');
        headerCells.forEach(th => {
            const resizer = th.querySelector('.col-resizer');
            if (!resizer) return;
            let startX = 0;
            let startWidth = 0;

            const onMouseMove = e => {
                const delta = e.pageX - startX;
                th.style.width = `${Math.max(startWidth + delta, 100)}px`;
            };

            const onMouseUp = () => {
                document.removeEventListener('mousemove', onMouseMove);
                document.removeEventListener('mouseup', onMouseUp);
            };

            resizer.addEventListener('mousedown', e => {
                e.preventDefault();
                startX = e.pageX;
                startWidth = th.offsetWidth;
                document.addEventListener('mousemove', onMouseMove);
                document.addEventListener('mouseup', onMouseUp);
            });
        });
    }

    // Request a particular historical JSON file and show its contents
    async function loadHistoryFile(name) {
        const details = document.getElementById('historyDetails');
        try {
            const response = await fetch(`history.php?file=${encodeURIComponent(name)}`);
            if (!response.ok) {
                details.textContent = 'Unable to load file contents';
                return;
            }
            const data = await response.json();
            details.textContent = JSON.stringify(data, null, 2);
        } catch (error) {
            details.textContent = 'Unable to load file contents';
        }
    }

    // Load maintenance notes and render into the maintenance list
    async function loadMaintenance() {
        const list = document.getElementById('maintenanceList');
        try {
            const response = await fetch('maintenance.php');
            if (!response.ok) {
                list.innerHTML = '<li class="list-group-item text-danger">Could not load maintenance plan</li>';
                return;
            }
            const items = await response.json();
            list.innerHTML = '';
            if (!items.length) {
                list.innerHTML = '<li class="list-group-item">No maintenance items planned yet.</li>';
                return;
            }
            items.forEach(item => {
                const li = document.createElement('li');
                li.className = 'list-group-item';
                li.innerHTML = `<strong>${item.created}</strong><br>${item.note}`;
                list.appendChild(li);
            });
        } catch (error) {
            list.innerHTML = '<li class="list-group-item text-danger">Could not load maintenance plan</li>';
        }
    }

    // POST a maintenance note to the server
    async function saveMaintenance(note) {
        try {
            await fetch('maintenance.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ note }),
            });
        } catch (error) {
            console.error('Failed to save maintenance note', error);
        }
    }

    // Given the live API JSON, update UI elements (status, metrics,
    // alerts) and populate the details table.
    function renderRealtime(data) {
        const status = document.getElementById('reactorStatus');
        const temperature = document.getElementById('reactorTemperature');
        const pressure = document.getElementById('reactorPressure');
        const alertBadge = document.getElementById('alerts');
        status.textContent = data.status || 'Unknown';
        temperature.textContent = data.temperature !== undefined ? `${data.temperature} °C` : 'N/A';
        pressure.textContent = data.pressure !== undefined ? `${data.pressure} bar` : 'N/A';
        const statusClass = (data.status || '').toLowerCase();

        // BEST PRACTICE: Do NOT use inner HTML with unsanitized data in applications.
        alertBadge.innerHTML = data.alerts || 'No alerts';
        
        alertBadge.className = 'badge ' + (statusClass === 'critical' ? 'badge-danger' : statusClass === 'warning' ? 'badge-warning' : 'badge-success');

        const detailsBody = document.getElementById('realtimeDetails');
        detailsBody.innerHTML = '';
        if (data.details && typeof data.details === 'object') {
            Object.entries(data.details).forEach(([key, value]) => {
                const row = document.createElement('tr');
                row.innerHTML = `<td>${key}</td><td>${value}</td>`;
                detailsBody.appendChild(row);
            });
        }
    }

    function renderSummary(data) {
        const summaryBody = document.getElementById('summaryTableBody');
        const badge = document.getElementById('summaryStatusBadge');
        if (!summaryBody || !badge) {
            return;
        }

        const status = data.status || 'Unknown';
        const alerts = Array.isArray(data.alerts) ? data.alerts.join(', ') : (data.alerts || 'None');
        const rows = [];

        rows.push(`<tr><th>Timestamp</th><td>${data.timestamp || 'N/A'}</td></tr>`);
        rows.push(`<tr><th>Temperature</th><td>${data.temperature !== undefined ? `${data.temperature} °C` : 'N/A'}</td></tr>`);
        rows.push(`<tr><th>Pressure</th><td>${data.pressure !== undefined ? `${data.pressure} bar` : 'N/A'}</td></tr>`);
        rows.push(`<tr><th>Status</th><td>${status}</td></tr>`);
        rows.push(`<tr><th>Alerts</th><td>${alerts}</td></tr>`);

        if (data.details && typeof data.details === 'object') {
            Object.entries(data.details).forEach(([key, value]) => {
                rows.push(`<tr><th>${key}</th><td>${value}</td></tr>`);
            });
        }

        summaryBody.innerHTML = rows.join('');

        badge.textContent = status;
        badge.className = 'status-summary ' + (status.toLowerCase() === 'critical' ? 'status-critical' : status.toLowerCase() === 'warning' ? 'status-warning' : 'status-ok');
    }

    // Initial population on page load
    fetchRealtime();
    fetchHistory();
    loadMaintenance();
    loadSummaryTable();

    // Attach click handlers to Refresh buttons for manual data updates
    const refreshBtn = document.getElementById('refreshBtn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', fetchRealtime);
    }

    const refreshSummaryBtn = document.getElementById('refreshSummaryBtn');
    if (refreshSummaryBtn) {
        refreshSummaryBtn.addEventListener('click', loadSummaryTable);
        refreshSummaryBtn.addEventListener('click', fetchHistory);
    }
});

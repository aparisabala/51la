@extends('admin.app')
@section('admin_content')

    <style>
        body { font-size: 12px; background: #f8f9fa; }

        /* ── Header rows ───────────────────────── */
        .app-header-row th {
            background: #2c3e50 !important;
            color: #fff !important;
            text-align: center;
            font-size: 13px;
            border: 1px solid #1a252f !important;
        }
        .sub-header-row th {
            background: #34495e !important;
            color: #ecf0f1 !important;
            text-align: center;
            font-size: 11px;
            white-space: nowrap;
            border: 1px solid #2c3e50 !important;
        }

        /* ── Data rows ─────────────────────────── */
        tr.row-cumulative td {
            background: #ffffff !important;
            font-weight: 600;
        }
        tr.row-interval td {
            background: #fff3cd !important; /* Bootstrap warning yellow ~ orange */
            color: #7a5c00;
            font-size: 11px;
        }
        tr.row-cumulative td,
        tr.row-interval td {
            text-align: right;
            border: 1px solid #dee2e6 !important;
            padding: 3px 6px !important;
        }
        /* Time column — left align */
        tr.row-cumulative td:first-child,
        tr.row-interval td:first-child {
            text-align: center;
            font-weight: bold;
            background: #ecf0f1 !important;
            color: #2c3e50;
        }

        /* Sticky first column */
        table.dataTable tbody tr td:first-child,
        table.dataTable thead tr th:first-child {
            position: sticky;
            left: 0;
            z-index: 2;
        }

        #report-table-wrapper { overflow-x: auto; }

        .date-picker { max-width: 200px; }

        /* Loading spinner */
        #loading {
            display: none;
            position: fixed;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            z-index: 9999;
        }
    </style>

    <div class="container-fluid py-3">

        <div class="d-flex align-items-center gap-3 mb-3">
            <h5 class="mb-0">Date:</h5>
            <input type="date" id="report-date" class="form-control date-picker"
                value="{{ $date }}" >
            <button id="btn-load" class="btn btn-success btn-sm">Load</button>
            <button id="btn-export" class="btn btn-danger btn-sm">⬇ Export Excel</button>
            <span id="last-updated" class="text-muted small"></span>


            <h5 class="mb-0">
                Time <span style="font-weight: 100;">(Loading for Current Date Only)</span>: 
            </h5>
            <div class="input-group" style="width:auto;">
                <select id="time_slot" class="form-select" style="width:150px;">
                    @foreach($allSlots as $slot)
                    <option value="{{ $slot }}">{{ $slot }}</option>
                    @endforeach
                </select>
            </div>
            <button id="btn-fetch" class="btn btn-primary btn-sm">⬇ Fetch Data</button>


        </div>

        <div id="loading">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>

        <div id="report-table-wrapper">
            <!-- Table is built dynamically by JS -->
            <table id="report-table" class="table table-bordered table-sm nowrap w-100">
                <thead id="report-thead"></thead>
                <tbody id="report-tbody"></tbody>
            </table>
        </div>

    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <script>
        const SUB_COLS = [
            { key: '__time__',        label: '时间'          },
            { key: 'ip_51la',         label: '51la IP'       },
            { key: 'total_install',   label: '总安装'       },
            { key: 'total_click',     label: '总点击'         },
            { key: 'click_ratio',     label: '点击比'   },
            { key: 'ip_click_ratio',  label: 'IP点击比'},
            { key: 'conversion_rate', label: '转化率'    },
        ];

        let dtInstance = null;

        function showLoading(show) {
            document.getElementById('loading').style.display = show ? 'block' : 'none';
        }

        function buildTable(data) {
            const apps = data.apps;   // [{id, name}, ...]
            const rows = data.rows;   // [{time, row_type, apps: {app_id: {cols...}}}]

            const thead = document.getElementById('report-thead');
            const tbody  = document.getElementById('report-tbody');

            // ── Clear existing DataTable ────────────────
            if (dtInstance) {
                dtInstance.destroy();
                dtInstance = null;
            }
            thead.innerHTML = '';
            tbody.innerHTML = '';

            // ── Build header row 1: App names ───────────
            let tr1 = '<tr class="app-header-row">';
            apps.forEach(app => {
                tr1 += `<th colspan="${SUB_COLS.length}" style="text-align:center;">${escHtml(app.name)}</th>`;
            });
            tr1 += '</tr>';

            // ── Build header row 2: Sub-columns ─────────
            let tr2 = '<tr class="sub-header-row">';
            apps.forEach(() => {
                SUB_COLS.forEach(col => {
                    tr2 += `<th>${col.label}</th>`;
                });
            });
            tr2 += '</tr>';

            thead.innerHTML = tr1 + tr2;

            // ── Build body rows ──────────────────────────
            let tbodyHtml = '';
            rows.forEach(row => {
                const cls = row.row_type === 'cumulative' ? 'row-cumulative' : 'row-interval';
                tbodyHtml += `<tr class="${cls}">`;

                apps.forEach(app => {
                    const d = row.apps[app.id] || {};
                    SUB_COLS.forEach(col => {
                        // __time__ = special: show the time value
                        if (col.key === '__time__') {
                            tbodyHtml += `<td style="text-align:center;background:#ecf0f1 !important;font-weight:700;color:#2c3e50;">${escHtml(row.time)}</td>`;
                            return;
                        }
                        const val = d[col.key] ?? '-';
                        // Red cell if Conv. Rate < 30%
                        if (col.key === 'conversion_rate') {
                            const num = parseFloat(String(val).replace('%', ''));
                            if (!isNaN(num) && num < 30) {
                                tbodyHtml += `<td style="background:#ff4444 !important;color:#fff !important;font-weight:700;">${escHtml(String(val))}</td>`;
                                return;
                            }
                        }
                        tbodyHtml += `<td>${escHtml(String(val))}</td>`;
                    });
                });

                tbodyHtml += '</tr>';
            });

            tbody.innerHTML = tbodyHtml;

            // ── Init DataTable ───────────────────────────
            dtInstance = $('#report-table').DataTable({
                paging:   false,
                ordering: false,
                searching: false,
                info:     false,
                scrollX:  true,
                fixedHeader: true,
                fixedColumns: false,
                columnDefs: [{ targets: '_all', orderable: false }],
            });

            document.getElementById('last-updated').textContent =
                'Updated: ' + new Date().toLocaleTimeString();
        }

        function loadData() {
            const date = document.getElementById('report-date').value;
            if (!date) return;

            showLoading(true);

            $.getJSON(`{{ route('report.data') }}`, { date })
                .done(buildTable)
                .fail(() => alert('Failed to load data. Please try again.'))
                .always(() => showLoading(false));
        }

        function escHtml(str) {
            return str
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }

        // ── Auto-refresh every 60 seconds ───────────
        let autoRefresh = null;

        function startAutoRefresh() {
            clearInterval(autoRefresh);
            autoRefresh = setInterval(loadData, 60_000); // 1 minute
        }

        document.getElementById('btn-load').addEventListener('click', () => {
            loadData();
            startAutoRefresh();
        });


        // excel export
        document.getElementById('btn-export').addEventListener('click', () => {
            const date = document.getElementById('report-date').value;
            window.location.href = `{{ route('report.export') }}?date=${date}`;
        });

        document.getElementById('btn-fetch').addEventListener('click', () => {
            const date = document.getElementById('report-date').value;
            const time = document.getElementById('time_slot').value;
            if (!confirm(`Fetch data for ${date} at ${time}? This will insert missing slots only.`)) return;
            window.location.href = `{{ route('report.fetch') }}?date=${date}&time=${time}`;
        });

        // Load on page open
        loadData();
        startAutoRefresh();
    </script>

@endsection


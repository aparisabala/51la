@extends('admin.app')
@section('admin_content')

<div class="container-fluid py-4">
    <div class="card shadow-sm" style="max-width: 700px;">
        <div class="card-header">
            <h5 class="mb-0"> Manual IP Entry</h5>
        </div>
        <div class="card-body">

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="row g-3 mb-4">
                <div class="col-sm-6">
                    <label class="form-label fw-semibold">App</label>
                    <select id="appSelect" class="form-select">
                        <option value="">— Select App —</option>
                        @foreach($apps as $app)
                            <option value="{{ $app->id }}">{{ $app->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6">
                    <label class="form-label fw-semibold">Date</label>
                    <input type="date" id="dateInput" class="form-control"
                           value="{{ \Carbon\Carbon::today()->toDateString() }}">
                </div>
            </div>

            <div class="mb-3 d-flex align-items-center gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="fillHourSlots()">
                    🕐 Fill 24-Hour Slots
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="clearTable()">
                    🗑 Clear
                </button>
                <span id="loadStatus" class="text-muted small ms-1"></span>
            </div>

            <form method="POST" action="{{ route('metrics.manual-ip.store') }}" id="metricForm">
                @csrf
                <input type="hidden" name="app_id" id="hiddenApp">
                <input type="hidden" name="date"   id="hiddenDate">

                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle text-center">
                        <thead class="table-dark">
                            <tr>
                                <th style="width:120px">Time Slot</th>
                                <th>IP (Cumulative)</th>
                                <th class="table-secondary">Δ Interval IP <small class="text-muted fw-normal">(auto)</small></th>
                                <th style="width:50px"></th>
                            </tr>
                        </thead>
                        <tbody id="tableBody"></tbody>
                    </table>
                </div>

                <div class="d-flex gap-2 mt-3">
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="addRow()">
                        ＋ Add Row
                    </button>
                    <button type="submit" class="btn btn-success px-4" onclick="prepareSubmit()">
                        Save
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<script>
    const SLOTS_24 = [
        '00:00','01:00','02:00','03:00','04:00','05:00','06:00','07:00',
        '08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00',
        '16:00','17:00','18:00','19:00','20:00','21:00','22:00','23:00'
    ];

    let rowIndex = 0;

    async function fillHourSlots() {
        const appId = document.getElementById('appSelect').value;
        const date  = document.getElementById('dateInput').value;
        const status = document.getElementById('loadStatus');

        if (!appId || !date) {
            status.textContent = 'Please select an app and date first.';
            status.className = 'text-danger small ms-1';
            return;
        }

        clearTable();
        status.textContent = 'Loading...';
        status.className = 'text-muted small ms-1';

        // Build a lookup map from existing DB data
        let existingMap = {};
        try {
            const res = await fetch(`{{ route('metrics.manual-ip.existing') }}?app_id=${appId}&date=${date}`);
            const data = await res.json();
            data.forEach(row => { existingMap[row.time_slot] = row.ip_51la; });

            const count = Object.keys(existingMap).length;
            if (count > 0) {
                status.textContent = `Loaded ${count} existing record(s).`;
                status.className = 'text-success small ms-1';
            } else {
                status.textContent = 'No existing data — starting fresh.';
                status.className = 'text-muted small ms-1';
            }
        } catch (e) {
            status.textContent = 'Could not load existing data.';
            status.className = 'text-danger small ms-1';
        }

        // Fill all 24 slots, pre-populating with existing values where available
        SLOTS_24.forEach(slot => addRow(slot, existingMap[slot] ?? 0));
    }

    function clearTable() {
        document.getElementById('tableBody').innerHTML = '';
        document.getElementById('loadStatus').textContent = '';
        rowIndex = 0;
    }

    function addRow(slot = '', ip = 0) {
        const idx = rowIndex++;
        const tr = document.createElement('tr');
        tr.id = 'row_' + idx;

        // Highlight rows that had existing data
        const hasData = ip > 0;
        tr.className = hasData ? 'table-warning' : '';

        tr.innerHTML =
            '<td><input type="text" class="form-control form-control-sm text-center" placeholder="HH:MM"' +
            ' value="' + slot + '" maxlength="5" oninput="recalcAll()" data-field="slot"></td>' +
            '<td><input type="number" min="0" class="form-control form-control-sm text-center"' +
            ' value="' + ip + '" oninput="recalcAll(); markEdited(this)" data-field="ip"></td>' +
            '<td class="table-secondary fw-semibold" data-calc="dip">0</td>' +
            '<td><button type="button" class="btn btn-outline-danger btn-sm" onclick="removeRow(' + idx + ')">✕</button></td>';

        document.getElementById('tableBody').appendChild(tr);
        recalcAll();
    }

    function markEdited(input) {
        // Remove the yellow highlight once user edits a pre-filled row
        input.closest('tr').classList.remove('table-warning');
        input.closest('tr').classList.add('table-info');
    }

    function removeRow(idx) {
        document.getElementById('row_' + idx)?.remove();
        recalcAll();
    }

    function recalcAll() {
        let prevIp = null;
        document.querySelectorAll('#tableBody tr').forEach(tr => {
            const ip  = parseFloat(tr.querySelector('[data-field="ip"]').value) || 0;
            const dIp = prevIp !== null ? Math.max(0, ip - prevIp) : 0;
            tr.querySelector('[data-calc="dip"]').textContent = dIp;
            prevIp = ip;
        });
    }

    function prepareSubmit() {
        document.getElementById('hiddenApp').value  = document.getElementById('appSelect').value;
        document.getElementById('hiddenDate').value = document.getElementById('dateInput').value;
        document.querySelectorAll('.dynamic-input').forEach(e => e.remove());
        const form = document.getElementById('metricForm');
        document.querySelectorAll('#tableBody tr').forEach((tr, i) => {
            [['time_slot', tr.querySelector('[data-field="slot"]').value.trim()],
            ['ip',        tr.querySelector('[data-field="ip"]').value]
            ].forEach(([key, val]) => {
                const inp = document.createElement('input');
                inp.type = 'hidden'; inp.name = 'metrics[' + i + '][' + key + ']';
                inp.value = val; inp.classList.add('dynamic-input');
                form.appendChild(inp);
            });
        });
    }
</script>
@endsection



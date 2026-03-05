@extends('admin.app')
@section('admin_content')

<div class="container-fluid py-4">
    <div class="card shadow-sm" style="max-width: 740px;">
        <div class="card-header">
            <h5 class="mb-0">🖊 Manual IP Entry</h5>
        </div>
        <div class="card-body">

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            {{-- App + Date --}}
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

            {{-- Mode toolbar --}}
            <div class="d-flex align-items-center gap-2 flex-wrap mb-3">

                {{-- Mode A: Fill 24h --}}
                <div class="input-group" style="width:auto;">
                    <span class="input-group-text fw-semibold">🕐 Fill 24h in difference with</span>
                  
                    <input type="text" style="border: 1px solid #6c757d; text-align: center; width: 10%; color: #6c757d;" id="intervalSelect" value="{{ $active_time_slot->time_difference }}" readonly>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="fillHourSlots()">
                        Minutes Fill Slots
                    </button>
                </div>

                <span class="text-muted small">or</span>

                {{-- Mode B: Add single row --}}
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="addNextRow()">
                    ＋ Add Single Row
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
                                <th style="width:150px">Time Slot</th>
                                <th>IP (Cumulative)</th>
                                <th class="table-secondary">
                                    Δ Interval IP
                                    <small class="text-muted fw-normal">(auto)</small>
                                </th>
                                <th style="width:50px"></th>
                            </tr>
                        </thead>
                        <tbody id="tableBody"></tbody>
                    </table>
                </div>

                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-success px-4" onclick="prepareSubmit()">
                        💾 Save
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<script>
    let rowIndex   = 0;
    let existingMap = {};   // module-level cache: { "HH:MM": ip_value }

    /* ── Helpers ─────────────────────────────────────────────── */

    /** "HH:MM" → total minutes, or null if invalid */
    function slotToMin(slot) {
        if (!slot) return null;
        const [h, m] = slot.split(':').map(Number);
        return (isNaN(h) || isNaN(m)) ? null : h * 60 + m;
    }

    /** Total minutes → "HH:MM" (wraps around midnight) */
    function minToSlot(m) {
        m = ((m % 1440) + 1440) % 1440;
        return String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');
    }

    /** Generate every slot in a full 24h day for a given interval */
    function generateSlots(intervalMins) {
        const slots = [];
        for (let m = 0; m < 1440; m += intervalMins) slots.push(minToSlot(m));
        return slots;
    }

    /**
     * Guess the next time slot to pre-fill when adding a single row.
     * - 0 rows  → "00:00"
     * - 1 row   → last + selected interval
     * - 2+ rows → infer interval from the last two rows and continue the pattern
     */
    function guessNextSlot() {
        const selectedInterval = parseInt(document.getElementById('intervalSelect').value, 10) || 60;
        const rows = document.querySelectorAll('#tableBody tr');
        if (rows.length === 0) return '00:00';

        const lastSlot = rows[rows.length - 1].querySelector('[data-field="slot"]').value.trim();
        const mLast = slotToMin(lastSlot);
        if (mLast === null) return '';

        if (rows.length === 1) return minToSlot(mLast + selectedInterval);

        const prevSlot = rows[rows.length - 2].querySelector('[data-field="slot"]').value.trim();
        const mPrev = slotToMin(prevSlot);
        if (mPrev === null) return minToSlot(mLast + selectedInterval);

        // Compute positive diff (handles edge cases)
        let diff = mLast - mPrev;
        if (diff <= 0) diff = selectedInterval;
        return minToSlot(mLast + diff);
    }

    /* ── Core row management ─────────────────────────────────── */

    function addRow(slot = '', ip = 0, highlight = false) {
        const idx = rowIndex++;
        const tr  = document.createElement('tr');
        tr.id     = 'row_' + idx;
        tr.className = highlight ? 'table-warning' : '';

        tr.innerHTML =
            // Text input forced to HH:MM 24h format with pattern validation
            '<td>' +
              '<input type="text" class="form-control form-control-sm text-center font-monospace"' +
              ' value="' + slot + '" placeholder="HH:MM" maxlength="5"' +
              ' pattern="([01][0-9]|2[0-3]):[0-5][0-9]"' +
              ' oninput="formatTimeInput(this); recalcAll()"' +
              ' onblur="padTimeInput(this); recalcAll()"' +
              ' data-field="slot">' +
            '</td>' +
            // Cumulative IP value
            '<td>' +
              '<input type="number" min="0" class="form-control form-control-sm text-center"' +
              ' value="' + ip + '"' +
              ' oninput="recalcAll(); markEdited(this)" data-field="ip">' +
            '</td>' +
            // Auto-calculated Δ
            '<td class="table-secondary fw-semibold" data-calc="dip">0</td>' +
            // Remove button
            '<td>' +
              '<button type="button" class="btn btn-outline-danger btn-sm"' +
              ' onclick="removeRow(' + idx + ')">✕</button>' +
            '</td>';

        document.getElementById('tableBody').appendChild(tr);
        recalcAll();
        return tr;
    }

    /**
     * Find an IP value from existingMap for a given guessed slot.
     * First tries exact match, then looks for any record whose time falls
     * within [slotMin, slotMin + intervalMins) — so "01:01" is found when
     * the guessed slot is "01:00" with a 60-min interval.
     */
    function findExistingIp(slot) {
        // 1. Exact match
        if (existingMap[slot] !== undefined) return existingMap[slot];

        // 2. Fuzzy: find any key within the interval window
        const interval = parseInt(document.getElementById('intervalSelect').value, 10) || 60;
        const mSlot    = slotToMin(slot);
        if (mSlot === null) return 0;

        for (const [key, val] of Object.entries(existingMap)) {
            const mKey = slotToMin(key);
            if (mKey !== null && mKey >= mSlot && mKey < mSlot + interval) {
                return val;
            }
        }
        return 0;
    }

    /** Add one row with a smart-guessed next time, pre-filling IP from DB if it exists */
    async function addNextRow() {


        const appId = document.getElementById('appSelect').value;
        const date  = document.getElementById('dateInput').value;
        const status = document.getElementById('loadStatus');

        if (!appId || !date) {
            status.textContent = 'Please select an app and date first.';
            status.className   = 'text-danger small ms-1';
            return;
        }
        
        // Fetch existing data once if not yet loaded
        if (appId && date && Object.keys(existingMap).length === 0) {
            try {
                const res  = await fetch(`{{ route('metrics.manual-ip.existing') }}?app_id=${appId}&date=${date}`);
                const data = await res.json();
                data.forEach(row => { existingMap[row.time_slot] = row.ip_51la; });
                if (Object.keys(existingMap).length > 0) {
                    status.textContent = `${Object.keys(existingMap).length} existing record(s) in DB.`;
                    status.className   = 'text-success small ms-1';
                }
            } catch (e) {
                // silently ignore — user can still enter data manually
            }
        }

        const slot = guessNextSlot();
        const ip   = findExistingIp(slot);
        const tr   = addRow(slot, ip, ip > 0);
        tr.querySelector('[data-field="ip"]').focus();
    }

    function markEdited(input) {
        input.closest('tr').classList.remove('table-warning');
        input.closest('tr').classList.add('table-info');
    }

    function removeRow(idx) {
        document.getElementById('row_' + idx)?.remove();
        recalcAll();
    }

    function clearTable() {
        document.getElementById('tableBody').innerHTML = '';
        document.getElementById('loadStatus').textContent = '';
        rowIndex    = 0;
        existingMap = {};
    }

    /* ── Fill 24h mode ───────────────────────────────────────── */

    async function fillHourSlots() {
        const appId    = document.getElementById('appSelect').value;
        const date     = document.getElementById('dateInput').value;
        const interval = parseInt(document.getElementById('intervalSelect').value, 10);
        const status   = document.getElementById('loadStatus');

        if (!appId || !date) {
            status.textContent = 'Please select an app and date first.';
            status.className   = 'text-danger small ms-1';
            return;
        }

        clearTable();
        status.textContent = 'Loading…';
        status.className   = 'text-muted small ms-1';

        existingMap = {};
        try {
            const res  = await fetch(`{{ route('metrics.manual-ip.existing') }}?app_id=${appId}&date=${date}`);
            const data = await res.json();
            data.forEach(row => { existingMap[row.time_slot] = row.ip_51la; });

            const count = Object.keys(existingMap).length;
            status.textContent = count > 0
                ? `Loaded ${count} existing record(s).`
                : 'No existing data — starting fresh.';
            status.className = count > 0 ? 'text-success small ms-1' : 'text-muted small ms-1';
        } catch (e) {
            status.textContent = 'Could not load existing data.';
            status.className   = 'text-danger small ms-1';
        }

        generateSlots(interval).forEach(slot => {
            const ip = existingMap[slot] ?? 0;
            addRow(slot, ip, ip > 0);
        });
    }

    /* ── Time input helpers (enforce HH:MM 24h) ─────────────── */

    /** Auto-insert colon after 2 digits, strip non-numeric chars */
    function formatTimeInput(input) {
        let v = input.value.replace(/[^0-9]/g, '');
        if (v.length > 2) v = v.slice(0, 2) + ':' + v.slice(2, 4);
        input.value = v;
        // Live validity highlight
        const full = /^([01][0-9]|2[0-3]):[0-5][0-9]$/.test(input.value);
        input.classList.toggle('is-invalid', input.value.length === 5 && !full);
        input.classList.toggle('is-valid',   full);
    }

    /** On blur: pad single-digit hour (e.g. "9:30" → "09:30") */
    function padTimeInput(input) {
        const v = input.value.trim();
        const match = v.match(/^(\d):([0-5][0-9])$/);
        if (match) input.value = '0' + match[1] + ':' + match[2];
        const full = /^([01][0-9]|2[0-3]):[0-5][0-9]$/.test(input.value);
        input.classList.toggle('is-invalid', !full);
        input.classList.toggle('is-valid',   full);
    }

    /* ── Δ recalculation ─────────────────────────────────────── */

    function recalcAll() {
        let prevIp = null;
        document.querySelectorAll('#tableBody tr').forEach(tr => {
            const ip  = parseFloat(tr.querySelector('[data-field="ip"]').value) || 0;
            const dIp = prevIp !== null ? Math.max(0, ip - prevIp) : 0;
            tr.querySelector('[data-calc="dip"]').textContent = dIp;
            prevIp = ip;
        });
    }

    /* ── Form submission ─────────────────────────────────────── */

    function prepareSubmit() {
        document.getElementById('hiddenApp').value  = document.getElementById('appSelect').value;
        document.getElementById('hiddenDate').value = document.getElementById('dateInput').value;
        document.querySelectorAll('.dynamic-input').forEach(e => e.remove());

        const form = document.getElementById('metricForm');
        document.querySelectorAll('#tableBody tr').forEach((tr, i) => {
            [
                ['time_slot', tr.querySelector('[data-field="slot"]').value.trim()],
                ['ip',        tr.querySelector('[data-field="ip"]').value],
            ].forEach(([key, val]) => {
                const inp = document.createElement('input');
                inp.type  = 'hidden';
                inp.name  = `metrics[${i}][${key}]`;
                inp.value = val;
                inp.classList.add('dynamic-input');
                form.appendChild(inp);
            });
        });
    }
</script>
@endsection
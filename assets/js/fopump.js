function recalcTotals() {
    const sums = { prod: 0, assy: 0, export: 0 };
    ['prod', 'assy', 'export'].forEach(group => {
        document.querySelectorAll(`.f-qty[data-group="${group}"]`).forEach(el => {
            const n = parseFloat(el.value);
            if (!isNaN(n)) sums[group] += n;
        });
    });
    // To Sparepart PTC quantity counts towards the Assembly Line total only —
    // it does not get its own separate Total.
    const display = { prod: sums.prod, assy: sums.assy + sums.export };
    Object.keys(display).forEach(group => {
        const cell = document.querySelector(`.f-total[data-group="${group}"]`);
        if (cell) {
            const v = display[group];
            cell.textContent = Number.isInteger(v) ? v : v.toFixed(2);
        }
    });
}

document.querySelectorAll('.f-qty').forEach(el => el.addEventListener('input', recalcTotals));
recalcTotals();

document.getElementById('btn-submit').addEventListener('click', async () => {
    const rows = [];
    for (let i = 1; i <= ROW_COUNT; i++) {
        const get = f => document.querySelector(`[data-row="${i}"][data-field="${f}"]`)?.value ?? '';
        const row = {
            row_no: i,
            prod_model: get('prod_model'),
            prod_qty: get('prod_qty'),
            assy_model: get('assy_model'),
            assy_qty: get('assy_qty'),
            export_model: get('export_model'),
            export_qty: get('export_qty'),
        };
        const hasValue = row.prod_model || row.prod_qty || row.assy_model || row.assy_qty || row.export_model || row.export_qty;
        if (hasValue) rows.push(row);
    }

    const payload = {
        department_id: DEPARTMENT_ID,
        tanggal: REPORT_DATE,
        employee: document.getElementById('f_employee').value,
        working_time: document.getElementById('f_working_time').value,
        shift: document.getElementById('f_shift').value,
        operator_name: document.getElementById('f_operator').value,
        foreman_id: document.getElementById('f_foreman').value || null,
        supervisor_id: document.getElementById('f_supervisor').value || null,
        convert_prod: document.getElementById('convert_prod').value,
        convert_assy: document.getElementById('convert_assy').value,
        convert_export: document.getElementById('convert_export').value,
        accum_prod: document.getElementById('accum_prod').value,
        accum_assy: document.getElementById('accum_assy').value,
        accum_export: document.getElementById('accum_export').value,
        rows,
    };

    const res = await fetch('ajax/save_fopump.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    });
    const data = await res.json();

    if (data.success) {
        alert('Report saved.');
    } else {
        alert('Failed to save: ' + (data.error || 'unknown error'));
    }
});

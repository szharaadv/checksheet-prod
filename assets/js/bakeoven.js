function applyTempClass(el) {
    el.classList.remove('temp-ok', 'temp-ng');
    if (el.value === '') return;
    const v = parseFloat(el.value);
    if (isNaN(v)) return;
    el.classList.add(v < TEMP_MIN || v > TEMP_MAX ? 'temp-ng' : 'temp-ok');
}

document.querySelectorAll('.temp-input').forEach((el) => {
    applyTempClass(el);
    el.addEventListener('input', () => applyTempClass(el));
});

document.getElementById('btn-submit').addEventListener('click', async () => {
    const rows = [];
    for (let day = 1; day <= DAYS_IN_MONTH; day++) {
        const row = { day };
        let hasValue = false;
        TIME_KEYS.forEach((key) => {
            const val = document.querySelector(`[data-day="${day}"][data-field="t_${key}"]`)?.value ?? '';
            row['t_' + key] = val;
            if (val !== '') hasValue = true;
        });
        if (hasValue) rows.push(row);
    }

    const res = await fetch('ajax/save_bakeoven_entries.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            department_id: DEPARTMENT_ID,
            month: MONTH,
            year: YEAR,
            rows,
            keterangan: document.getElementById('f_keterangan')?.value ?? '',
            asst_foreman_id: document.getElementById('f_asst_foreman')?.value || null,
            foreman_id: document.getElementById('f_foreman')?.value || null,
            supervisor_id: document.getElementById('f_supervisor')?.value || null,
        }),
    });
    const data = await res.json();

    if (data.success) {
        alert('Entries saved.');
    } else {
        alert('Failed to save: ' + (data.error || 'unknown error'));
    }
});

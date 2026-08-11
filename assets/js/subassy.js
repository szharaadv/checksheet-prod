document.getElementById('btn-submit').addEventListener('click', async () => {
    const fields = ['surface_outside', 'parting_line', 'surface_upper', 'cleanliness', 'checker_id', 'supervisor_id'];
    const rows = [];

    for (let day = 1; day <= DAYS_IN_MONTH; day++) {
        const row = { day };
        let hasValue = false;
        for (const f of fields) {
            const val = document.querySelector(`[data-day="${day}"][data-field="${f}"]`)?.value ?? '';
            row[f] = val || null;
            if (val) hasValue = true;
        }
        if (hasValue) rows.push(row);
    }

    const res = await fetch('ajax/save_subassy_entries.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            department_id: DEPARTMENT_ID,
            month: MONTH,
            year: YEAR,
            rows,
        }),
    });
    const data = await res.json();

    if (data.success) {
        alert('Entries saved.');
    } else {
        alert('Failed to save: ' + (data.error || 'unknown error'));
    }
});

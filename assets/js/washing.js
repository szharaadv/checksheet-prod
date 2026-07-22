document.getElementById('btn-submit').addEventListener('click', async () => {
    const rows = [];
    for (let day = 1; day <= DAYS_IN_MONTH; day++) {
        const gantiAir = document.querySelector(`[data-day="${day}"][data-field="ganti_air"]`)?.value ?? '';
        const temperaturAir = document.querySelector(`[data-day="${day}"][data-field="temperatur_air"]`)?.value ?? '';
        const penambahanGildaon = document.querySelector(`[data-day="${day}"][data-field="penambahan_gildaon"]`)?.value ?? '';
        const totalAcid = document.querySelector(`[data-day="${day}"][data-field="total_acid"]`)?.value ?? '';
        const checkerId = document.querySelector(`[data-day="${day}"][data-field="checker_id"]`)?.value ?? '';
        const supervisorId = document.querySelector(`[data-day="${day}"][data-field="supervisor_id"]`)?.value ?? '';

        const hasValue = gantiAir || temperaturAir || penambahanGildaon || totalAcid || checkerId || supervisorId;
        if (!hasValue) continue;

        rows.push({
            day,
            ganti_air: gantiAir,
            temperatur_air: temperaturAir,
            penambahan_gildaon: penambahanGildaon,
            total_acid: totalAcid,
            checker_id: checkerId || null,
            supervisor_id: supervisorId || null,
        });
    }

    const res = await fetch('ajax/save_washing_entries.php', {
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

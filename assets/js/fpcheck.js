const variantSelect = document.getElementById('f_variant');
const head = document.getElementById('fpcheck-head');
const body = document.getElementById('fpcheck-body');
const colgroup = document.getElementById('fpcheck-cols');

const COL_W_ACTIVE = 66;   // filled checking-number column: just fits the dropdown
const COL_W_OFF = 38;      // empty column: collapsed narrow

let currentDraftId = typeof DRAFT_ID !== 'undefined' ? DRAFT_ID : null;
const draftCols = typeof DRAFT_COLS !== 'undefined' ? DRAFT_COLS : {};
const draftCells = typeof DRAFT_CELLS !== 'undefined' ? DRAFT_CELLS : {};
let firstRender = true;

function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
}

function currentVariant() {
    return VARIANTS.find(v => String(v.id) === String(variantSelect.value)) || VARIANTS[0];
}

function stdColCount(variant) {
    return (variant && variant.std_label_2 && variant.std_label_2 !== '') ? 2 : 1;
}

function cellInput(point, col) {
    const t = point.input_type;
    if (t === 'okng') {
        return `<select class="fp-cell" data-point="${point.id}" data-col="${col}"><option value=""></option><option value="OK">OK</option><option value="NG">NG</option></select>`;
    }
    if (t === 'truefalse') {
        return `<select class="fp-cell" data-point="${point.id}" data-col="${col}"><option value=""></option><option value="TRUE">TRUE</option><option value="FALSE">FALSE</option></select>`;
    }
    return `<input type="text" class="fp-cell" data-point="${point.id}" data-col="${col}">`;
}

function render() {
    const variant = currentVariant();
    const sc = stdColCount(variant);
    const std = STANDARDS[variant.id] || {};

    // <colgroup>: NO + CHECK POINT (flexible, absorbs slack) + standard(s) + 6 check cols.
    let cg = '<col style="width:42px">';
    cg += '<col>'; // CHECK POINT — no width, takes remaining space
    cg += '<col style="width:120px">';
    if (sc === 2) cg += '<col style="width:120px">';
    for (let c = 1; c <= COL_COUNT; c++) cg += `<col data-col="${c}" style="width:${COL_W_OFF}px">`;
    colgroup.innerHTML = cg;

    // Header: NO | CHECK POINT | STANDARD(s) | CHECKING NUMBER (col headers editable)
    let h = '<tr>';
    h += '<th rowspan="2" style="width:44px;">NO</th>';
    h += '<th rowspan="2" style="min-width:200px;">CHECK POINT</th>';
    h += `<th rowspan="2">${esc(variant.std_label_1 || 'STANDARD')}</th>`;
    if (sc === 2) h += `<th rowspan="2">${esc(variant.std_label_2)}</th>`;
    h += `<th colspan="${COL_COUNT}">CHECKING NUMBER</th>`;
    h += '</tr><tr>';
    for (let c = 1; c <= COL_COUNT; c++) {
        h += `<th style="padding:4px;"><input type="text" class="fp-col-head" data-col="${c}" placeholder="${esc(COL_PLACEHOLDERS[c - 1] || '')}"></th>`;
    }
    h += '</tr>';
    head.innerHTML = h;

    // Body
    let b = '';
    for (const p of POINTS) {
        if (p.row_type === 'group') {
            b += `<tr class="fp-group"><td class="fp-no">${esc(p.no)}</td><td class="fp-point">${esc(p.check_point)}</td><td colspan="${sc + COL_COUNT}"></td></tr>`;
            continue;
        }
        const s = std[p.id] || {};
        b += '<tr>';
        b += `<td class="fp-no">${esc(p.no)}</td>`;
        b += `<td class="fp-point">${esc(p.check_point)}</td>`;
        b += `<td class="fp-std">${esc(s.std_1 ?? '')}</td>`;
        if (sc === 2) b += `<td class="fp-std">${esc(s.std_2 ?? '')}</td>`;
        for (let c = 1; c <= COL_COUNT; c++) b += `<td>${cellInput(p, c)}</td>`;
        b += '</tr>';
    }
    body.innerHTML = b;

    // Prefill saved draft values on the first render only (variant changes reset).
    if (firstRender) {
        firstRender = false;
        document.querySelectorAll('.fp-col-head').forEach(el => {
            const v = draftCols[el.dataset.col];
            if (v != null && v !== '') el.value = v;
        });
        document.querySelectorAll('.fp-cell').forEach(el => {
            const byPoint = draftCells[el.dataset.point];
            const v = byPoint ? byPoint[el.dataset.col] : null;
            if (v != null && v !== '') el.value = v;
        });
    }

    refreshAllColumns();
}

// A column's cells are only editable once its Checking Number header is filled;
// empty (placeholder-only) columns stay disabled so you don't fill unused ones.
function updateColumnState(col) {
    const colHead = document.querySelector(`.fp-col-head[data-col="${col}"]`);
    const active = colHead && colHead.value.trim() !== '';
    // Widen the whole column via its <col> when filled; collapse when empty.
    const colEl = colgroup.querySelector(`col[data-col="${col}"]`);
    if (colEl) colEl.style.width = (active ? COL_W_ACTIVE : COL_W_OFF) + 'px';
    if (colHead) colHead.classList.toggle('col-off', !active);
    document.querySelectorAll(`.fp-cell[data-col="${col}"]`).forEach(el => {
        el.disabled = !active;
        el.classList.toggle('col-off', !active);
    });
}
function refreshAllColumns() {
    for (let c = 1; c <= COL_COUNT; c++) updateColumnState(c);
}

// Header inputs are re-created on each render, but the <thead> element persists,
// so delegate the input handler once.
head.addEventListener('input', (e) => {
    if (e.target.classList.contains('fp-col-head')) updateColumnState(e.target.dataset.col);
});

variantSelect.addEventListener('change', render);
render();

function buildPayload(status) {
    const columns = [];
    document.querySelectorAll('.fp-col-head').forEach(el => {
        const label = el.value.trim() || el.getAttribute('placeholder') || '';
        columns.push({ col_index: parseInt(el.dataset.col, 10), label });
    });

    const cells = [];
    document.querySelectorAll('.fp-cell').forEach(el => {
        const val = (el.value ?? '').trim();
        if (val !== '') cells.push({ point_id: parseInt(el.dataset.point, 10), col_index: parseInt(el.dataset.col, 10), value: val });
    });

    return {
        header_id: currentDraftId,
        status,
        section_id: SECTION_ID,
        department_id: DEPARTMENT_ID,
        tanggal: document.getElementById('f_tanggal').value,
        variant_id: variantSelect.value,
        model: document.getElementById('f_model').value,
        p_code: document.getElementById('f_pcode').value,
        part_no: document.getElementById('f_partno').value,
        prod_date: document.getElementById('f_proddate').value,
        check_method: document.getElementById('f_method').value,
        checker: document.getElementById('f_checker').value,
        foreman: document.getElementById('f_foreman').value,
        supervisor: document.getElementById('f_supervisor').value,
        columns,
        cells,
    };
}

async function save(status) {
    const res = await fetch('ajax/save_fpcheck.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(buildPayload(status)),
    });
    const data = await res.json();
    if (!data.success) {
        alert('Failed to save: ' + (data.error || 'unknown error'));
        return;
    }
    if (status === 'draft') {
        currentDraftId = data.header_id;
        alert('Saved as draft. Continue it later from the My Drafts menu.');
    } else {
        alert('Check sheet submitted.');
        window.location.href = 'view_fpcheck_checksheets.php';
    }
}

document.getElementById('btn-draft').addEventListener('click', () => save('draft'));
document.getElementById('btn-submit').addEventListener('click', () => save('submitted'));

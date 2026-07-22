const modelSelect = document.getElementById('f_model');
const tbody = document.getElementById('assy-tbody');
let currentItems = [];
let currentDraftId = typeof DRAFT_ID !== 'undefined' ? DRAFT_ID : null;
const draftValues = typeof DRAFT_VALUES !== 'undefined' ? DRAFT_VALUES : {};

function renderRows(items) {
    if (!items.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="empty">No checklist items set up for this model yet.</td></tr>';
        return;
    }

    tbody.innerHTML = items.map(item => {
        const saved = draftValues[item.id] || null;
        return `<tr>
            <td>${escapeHtml(item.checking_item)}</td>
            <td>${escapeHtml(item.standard ?? '-')}</td>
            <td>${escapeHtml(item.standard_min ?? '-')}</td>
            <td>${escapeHtml(item.standard_max ?? '-')}</td>
            <td><input type="text" class="actual-input" data-item-id="${item.id}" data-field="actual" value="${escapeHtml(saved?.actual ?? '')}"></td>
            <td><input type="text" class="actual-input" data-item-id="${item.id}" data-field="consumable" value="${escapeHtml(saved?.consumable ?? '')}"></td>
        </tr>`;
    }).join('');
}

function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, m => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[m]));
}

async function loadItems() {
    tbody.innerHTML = '<tr><td colspan="6" class="empty">Loading data...</td></tr>';
    const modelId = modelSelect.value;
    if (!modelId) {
        tbody.innerHTML = '<tr><td colspan="6" class="empty">No models set up for this department yet.</td></tr>';
        currentItems = [];
        return;
    }
    const res = await fetch(`ajax/get_assy_items.php?model_id=${modelId}`);
    const data = await res.json();
    currentItems = data.items || [];
    renderRows(currentItems);
}

modelSelect.addEventListener('change', loadItems);

function buildPayload(status) {
    const rows = currentItems.map(item => {
        const actualEl = document.querySelector(`[data-item-id="${item.id}"][data-field="actual"]`);
        const consumableEl = document.querySelector(`[data-item-id="${item.id}"][data-field="consumable"]`);
        return {
            checklist_item_id: item.id,
            actual_result: actualEl ? actualEl.value : null,
            consumable_item: consumableEl ? consumableEl.value : null,
        };
    });

    return {
        header_id: currentDraftId,
        status,
        tanggal: document.getElementById('f_tanggal').value,
        department_id: DEPARTMENT_ID,
        model_id: modelSelect.value,
        checker_id: document.getElementById('f_checker').value,
        mark_crank_shaft: document.getElementById('f_mark_crank_shaft').value,
        mark_conrod: document.getElementById('f_mark_conrod').value,
        mark_fo_pump: document.getElementById('f_mark_fo_pump').value,
        no_cyl_block: document.getElementById('f_no_cyl_block').value,
        no_engine: document.getElementById('f_no_engine').value,
        detail_model: document.getElementById('f_detail_model').value,
        rows,
    };
}

async function saveChecksheet(status) {
    const payload = buildPayload(status);

    const res = await fetch('ajax/save_assy_checksheet.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    });
    const data = await res.json();

    if (!data.success) {
        alert('Failed to save: ' + (data.error || 'unknown error'));
        return;
    }

    if (status === 'draft') {
        currentDraftId = data.header_id;
        alert('Saved as draft. You can continue it later from the My Drafts menu.');
    } else {
        alert('Checksheet submitted successfully.');
        window.location.href = 'view_assy_checksheets.php';
    }
}

document.getElementById('btn-draft').addEventListener('click', () => saveChecksheet('draft'));
document.getElementById('btn-submit').addEventListener('click', () => saveChecksheet('submitted'));

loadItems();

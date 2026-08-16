<?php
require_once __DIR__ . '/../config/db.php';
$pdo = get_db();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id = $_POST['id'] ?? '';
    $tanggal = trim($_POST['tanggal'] ?? '');
    $label = trim($_POST['label'] ?? '');
    $isWorkday = !empty($_POST['is_workday']) ? 1 : 0;

    if ($tanggal === '' || $label === '') {
        $error = 'Date and Label are required.';
    } else {
        try {
            if ($id !== '') {
                $stmt = $pdo->prepare('UPDATE m_calendar_holiday SET tanggal = ?, label = ?, is_workday = ? WHERE id = ?');
                $stmt->execute([$tanggal, $label, $isWorkday, (int)$id]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO m_calendar_holiday (tanggal, label, is_workday) VALUES (?, ?, ?)');
                $stmt->execute([$tanggal, $label, $isWorkday]);
            }
            header('Location: calendar_holidays.php?year=' . substr($tanggal, 0, 4) . '&saved=1');
            exit;
        } catch (PDOException $e) {
            $error = 'A holiday entry for that date already exists.';
        }
    }
}

if (($_GET['action'] ?? '') === 'toggle' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('UPDATE m_calendar_holiday SET is_active = NOT is_active WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    header('Location: calendar_holidays.php?year=' . (int)($_GET['year'] ?? date('Y')));
    exit;
}

if (($_GET['action'] ?? '') === 'delete' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('DELETE FROM m_calendar_holiday WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    header('Location: calendar_holidays.php?year=' . (int)($_GET['year'] ?? date('Y')) . '&deleted=1');
    exit;
}

$selected_year = (int)($_GET['year'] ?? date('Y'));

$editRow = null;
if (($_GET['action'] ?? '') === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM m_calendar_holiday WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    $editRow = $stmt->fetch();
    if ($editRow) {
        $selected_year = (int) substr($editRow['tanggal'], 0, 4);
    }
}

$stmt = $pdo->prepare('SELECT * FROM m_calendar_holiday WHERE tanggal BETWEEN ? AND ? ORDER BY tanggal');
$stmt->execute([$selected_year . '-01-01', $selected_year . '-12-31']);
$rows = $stmt->fetchAll();

$base_url = '../';
$active_nav = 'config-calendar';
$page_title = 'Company Calendar';
$page_subtitle = 'Master Data';
require __DIR__ . '/../includes/app_top.php';
?>

<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-ok">Data saved.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-ok">Data deleted.</div><?php endif; ?>

<p class="admin-form-hint">Holidays and collective leave dates used by the Date picker on Torque / FO Pump Assy check sheets — holiday dates can't be picked there. Tick "Still a working day" for dates that are marked as a holiday on the calendar but are actually worked (stays pickable, just labeled).</p>

<form method="get" class="filter-form">
    <label>Year:</label>
    <select name="year" onchange="this.form.submit()">
        <?php for ($y = date('Y') - 1; $y <= date('Y') + 2; $y++): ?>
            <option value="<?= $y ?>" <?= $y == $selected_year ? 'selected' : '' ?>><?= $y ?></option>
        <?php endfor; ?>
    </select>
</form>

<form method="post" class="admin-form">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= htmlspecialchars($editRow['id'] ?? '') ?>">

    <div class="form-grid">
        <div class="form-row">
            <label>Date</label>
            <input type="date" name="tanggal" value="<?= htmlspecialchars($editRow['tanggal'] ?? '') ?>" required>
        </div>
        <div class="form-row">
            <label>Label</label>
            <input type="text" name="label" value="<?= htmlspecialchars($editRow['label'] ?? '') ?>" required>
        </div>
        <div class="form-row">
            <label>&nbsp;</label>
            <label style="font-weight: 400; display: flex; align-items: center; gap: 6px;">
                <input type="checkbox" name="is_workday" value="1" <?= !empty($editRow['is_workday']) ? 'checked' : '' ?> style="width: auto;">
                Still a working day (stays pickable)
            </label>
        </div>
    </div>

    <div class="form-row">
        <button type="submit" class="btn"><?= $editRow ? 'Update' : 'Add' ?></button>
        <?php if ($editRow): ?><a href="calendar_holidays.php?year=<?= $selected_year ?>" class="btn btn-secondary">Cancel</a><?php endif; ?>
    </div>
</form>

<div class="table-scroll">
<table class="admin-table">
    <thead>
        <tr>
            <th>Date</th>
            <th>Label</th>
            <th>Type</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
            <td><?= htmlspecialchars(date('d M Y (D)', strtotime($row['tanggal']))) ?></td>
            <td><?= htmlspecialchars($row['label']) ?></td>
            <td><?= $row['is_workday'] ? '<span class="badge badge-ok">Working Day</span>' : '<span class="badge badge-off" style="background:#f5d7d7;color:#8a2b2b;">Holiday</span>' ?></td>
            <td><?= $row['is_active'] ? '<span class="badge badge-ok">Active</span>' : '<span class="badge badge-off">Inactive</span>' ?></td>
            <td class="row-actions">
                <a href="calendar_holidays.php?action=edit&id=<?= $row['id'] ?>">Edit</a>
                <a href="calendar_holidays.php?action=toggle&id=<?= $row['id'] ?>&year=<?= $selected_year ?>"><?= $row['is_active'] ? 'Deactivate' : 'Activate' ?></a>
                <a href="calendar_holidays.php?action=delete&id=<?= $row['id'] ?>&year=<?= $selected_year ?>" onclick="return confirm('Delete this date?')" class="danger">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5" class="empty">No holidays entered for <?= $selected_year ?> yet.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>

<?php require __DIR__ . '/../includes/app_bottom.php'; ?>

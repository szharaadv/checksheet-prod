<?php
require_once __DIR__ . '/../config/db.php';
$pdo = get_db();

$UPLOAD_DIR = __DIR__ . '/../assets/img/guides';
$UPLOAD_WEB = 'assets/img/guides';
$ALLOWED = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

if (!is_dir($UPLOAD_DIR)) {
    @mkdir($UPLOAD_DIR, 0777, true);
}

/** Handle an optional uploaded file field; returns web path or null. Throws on invalid. */
function handle_upload(string $field, array $allowed, string $dir, string $web): ?string
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed (code ' . $f['error'] . ').');
    }
    if ($f['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Image too large (max 5 MB).');
    }
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        throw new RuntimeException('Only JPG, PNG, WEBP, or GIF images are allowed.');
    }
    $info = @getimagesize($f['tmp_name']);
    if ($info === false) {
        throw new RuntimeException('File is not a valid image.');
    }
    $name = 'guide_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException('Could not store the uploaded file.');
    }
    return $web . '/' . $name;
}

// The Checking Guide feature (photo-illustrated part/checking-item guide) is
// only used by Sub Assembly — there's no need for a Section picker here.
$section = $pdo->query(
    "SELECT s.id, s.name, d.name AS dept_name
     FROM m_checksheet_section s
     JOIN m_department d ON d.id = s.department_id
     WHERE s.route = 'subassy_list.php' AND s.is_active = 1
     LIMIT 1"
)->fetch();

$error = null;

// ---- Save guide header (upsert by section) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_guide') {
    $section_id = (int)($_POST['section_id'] ?? 0);
    try {
        if (!$section_id) {
            throw new RuntimeException('Please choose a section.');
        }
        $stmt = $pdo->prepare('SELECT * FROM m_check_guide WHERE section_id = ?');
        $stmt->execute([$section_id]);
        $existing = $stmt->fetch();

        $part_image = handle_upload('part_image', $ALLOWED, $UPLOAD_DIR, $UPLOAD_WEB) ?? ($existing['part_image'] ?? null);

        $data = [
            trim($_POST['title'] ?? '') ?: null,
            trim($_POST['part_name'] ?? '') ?: null,
            $part_image,
            trim($_POST['pic_text'] ?? '') ?: null,
            trim($_POST['legend'] ?? '') ?: null,
        ];

        if ($existing) {
            $stmt = $pdo->prepare('UPDATE m_check_guide SET title=?, part_name=?, part_image=?, pic_text=?, legend=? WHERE id=?');
            $stmt->execute(array_merge($data, [$existing['id']]));
        } else {
            $stmt = $pdo->prepare('INSERT INTO m_check_guide (title, part_name, part_image, pic_text, legend, section_id) VALUES (?,?,?,?,?,?)');
            $stmt->execute(array_merge($data, [$section_id]));
        }
        header("Location: check_guides.php?section_id={$section_id}&saved=1");
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

// ---- Save item (add/edit) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_item') {
    $section_id = (int)($_POST['section_id'] ?? 0);
    $item_id = (int)($_POST['item_id'] ?? 0);
    try {
        $stmt = $pdo->prepare('SELECT id FROM m_check_guide WHERE section_id = ?');
        $stmt->execute([$section_id]);
        $guide_id = (int)$stmt->fetchColumn();
        if (!$guide_id) {
            throw new RuntimeException('Save the guide header first, then add items.');
        }

        $existingPhoto = null;
        if ($item_id) {
            $s = $pdo->prepare('SELECT photo FROM m_check_guide_item WHERE id = ? AND guide_id = ?');
            $s->execute([$item_id, $guide_id]);
            $existingPhoto = $s->fetchColumn() ?: null;
        }
        $photo = handle_upload('photo', $ALLOWED, $UPLOAD_DIR, $UPLOAD_WEB) ?? $existingPhoto;

        $data = [
            (int)($_POST['sort_order'] ?? 0),
            trim($_POST['method'] ?? '') ?: null,
            trim($_POST['checking_item'] ?? '') ?: null,
            trim($_POST['frequency'] ?? '') ?: null,
            $photo,
            trim($_POST['caption'] ?? '') ?: null,
        ];

        if ($item_id) {
            $stmt = $pdo->prepare('UPDATE m_check_guide_item SET sort_order=?, method=?, checking_item=?, frequency=?, photo=?, caption=? WHERE id=? AND guide_id=?');
            $stmt->execute(array_merge($data, [$item_id, $guide_id]));
        } else {
            $stmt = $pdo->prepare('INSERT INTO m_check_guide_item (sort_order, method, checking_item, frequency, photo, caption, guide_id) VALUES (?,?,?,?,?,?,?)');
            $stmt->execute(array_merge($data, [$guide_id]));
        }
        header("Location: check_guides.php?section_id={$section_id}&saved=1");
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

// ---- Delete item ----
if (($_GET['action'] ?? '') === 'delete_item' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('DELETE FROM m_check_guide_item WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    header('Location: check_guides.php?section_id=' . (int)($_GET['section_id'] ?? 0) . '&deleted=1');
    exit;
}

// ---- Delete the entire guide (cascade removes its items) ----
if (($_GET['action'] ?? '') === 'delete_guide' && isset($_GET['section_id'])) {
    $sid = (int)$_GET['section_id'];
    $stmt = $pdo->prepare('SELECT id FROM m_check_guide WHERE section_id = ?');
    $stmt->execute([$sid]);
    $gid = (int)$stmt->fetchColumn();
    if ($gid) {
        // Collect uploaded images to remove (only files we manage under /guides).
        $paths = [];
        $s = $pdo->prepare('SELECT part_image FROM m_check_guide WHERE id = ?');
        $s->execute([$gid]);
        $paths[] = $s->fetchColumn();
        $s = $pdo->prepare('SELECT photo FROM m_check_guide_item WHERE guide_id = ?');
        $s->execute([$gid]);
        foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $p) {
            $paths[] = $p;
        }
        $pdo->prepare('DELETE FROM m_check_guide WHERE id = ?')->execute([$gid]);
        foreach ($paths as $p) {
            if ($p && strpos($p, 'assets/img/guides/') === 0) {
                $full = __DIR__ . '/../' . $p;
                if (is_file($full)) {
                    @unlink($full);
                }
            }
        }
    }
    header('Location: check_guides.php?section_id=' . $sid . '&deleted=1');
    exit;
}

$selected_section_id = $section ? (int) $section['id'] : 0;

$stmt = $pdo->prepare('SELECT * FROM m_check_guide WHERE section_id = ?');
$stmt->execute([$selected_section_id]);
$guide = $stmt->fetch();

$items = [];
if ($guide) {
    $stmt = $pdo->prepare('SELECT * FROM m_check_guide_item WHERE guide_id = ? ORDER BY sort_order, id');
    $stmt->execute([$guide['id']]);
    $items = $stmt->fetchAll();
}

$editItem = null;
if (($_GET['action'] ?? '') === 'edit_item' && isset($_GET['id'])) {
    foreach ($items as $it) {
        if ($it['id'] == (int)$_GET['id']) { $editItem = $it; break; }
    }
}

$base_url = '../';
$active_nav = 'config-guide';
$page_title = 'Checking Guide';
$page_subtitle = 'Master Data';
require __DIR__ . '/../includes/app_top.php';
?>

<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-ok">Data saved.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-ok">Item deleted.</div><?php endif; ?>

<p style="margin:0 0 18px;color:#8b93a1;font-size:13px;">
    Section: <strong style="color:#1f2430;"><?= $section ? htmlspecialchars($section['dept_name'] . ' · ' . $section['name']) : '—' ?></strong>
    <span style="color:#c2c7cf;">(Checking Guide is only used by Sub Assembly)</span>
</p>

<h3 style="margin:18px 0 8px;font:600 15px Inter,sans-serif;">Guide Header</h3>
<form method="post" class="admin-form" enctype="multipart/form-data">
    <input type="hidden" name="action" value="save_guide">
    <input type="hidden" name="section_id" value="<?= $selected_section_id ?>">
    <div class="form-grid">
        <div class="form-row">
            <label>Title / Header line</label>
            <input type="text" name="title" placeholder="e.g. JIG FOR GUIDEN ASSEMBLY OIL SEAL STARTING SHAFT" value="<?= htmlspecialchars($guide['title'] ?? '') ?>">
        </div>
        <div class="form-row">
            <label>Legend (optional)</label>
            <input type="text" name="legend" placeholder="e.g. V = OK, X = NG" value="<?= htmlspecialchars($guide['legend'] ?? '') ?>">
        </div>
        <div class="form-row">
            <label>Part Name</label>
            <input type="text" name="part_name" value="<?= htmlspecialchars($guide['part_name'] ?? '') ?>">
        </div>
        <div class="form-row">
            <label>PIC (use new lines for extra text)</label>
            <textarea name="pic_text" rows="2" placeholder="Operator&#10;sub assy gear case"><?= htmlspecialchars($guide['pic_text'] ?? '') ?></textarea>
        </div>
        <div class="form-row">
            <label>Part Image <?php if (!empty($guide['part_image'])): ?><small>(leave empty to keep current)</small><?php endif; ?></label>
            <input type="file" name="part_image" accept="image/*">
            <?php if (!empty($guide['part_image'])): ?>
                <img src="<?= $base_url . htmlspecialchars($guide['part_image']) ?>" alt="" style="height:70px;margin-top:8px;border:1px solid #e3e5e9;border-radius:6px;">
            <?php endif; ?>
        </div>
    </div>
    <div class="form-row">
        <button type="submit" class="btn"><?= $guide ? 'Update Header' : 'Create Guide' ?></button>
        <?php if ($guide): ?>
            <a href="check_guides.php?section_id=<?= $selected_section_id ?>&action=delete_guide"
               class="btn btn-secondary danger"
               onclick="return confirm('Delete the entire checking guide for this section (header + all photos)? This cannot be undone.')">Delete Guide</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($guide): ?>
<h3 style="margin:24px 0 8px;font:600 15px Inter,sans-serif;"><?= $editItem ? 'Edit Item' : 'Add Item / Photo' ?></h3>
<form method="post" class="admin-form" enctype="multipart/form-data">
    <input type="hidden" name="action" value="save_item">
    <input type="hidden" name="section_id" value="<?= $selected_section_id ?>">
    <input type="hidden" name="item_id" value="<?= htmlspecialchars($editItem['id'] ?? '') ?>">
    <div class="form-grid">
        <div class="form-row">
            <label>No / Order</label>
            <input type="number" name="sort_order" value="<?= htmlspecialchars($editItem['sort_order'] ?? (count($items) + 1)) ?>">
        </div>
        <div class="form-row">
            <label>Checking Method</label>
            <input type="text" name="method" placeholder="e.g. Touched" value="<?= htmlspecialchars($editItem['method'] ?? '') ?>">
        </div>
        <div class="form-row">
            <label>Checking Item</label>
            <input type="text" name="checking_item" placeholder="e.g. Surface outside of jig" value="<?= htmlspecialchars($editItem['checking_item'] ?? '') ?>">
        </div>
        <div class="form-row">
            <label>Checking Frequency</label>
            <input type="text" name="frequency" placeholder="e.g. Before work activity" value="<?= htmlspecialchars($editItem['frequency'] ?? '') ?>">
        </div>
        <div class="form-row">
            <label>Photo Caption</label>
            <input type="text" name="caption" placeholder="e.g. 1. Surface outside jig" value="<?= htmlspecialchars($editItem['caption'] ?? '') ?>">
        </div>
        <div class="form-row">
            <label>Photo <?php if (!empty($editItem['photo'])): ?><small>(leave empty to keep current)</small><?php endif; ?></label>
            <input type="file" name="photo" accept="image/*">
            <?php if (!empty($editItem['photo'])): ?>
                <img src="<?= $base_url . htmlspecialchars($editItem['photo']) ?>" alt="" style="height:70px;margin-top:8px;border:1px solid #e3e5e9;border-radius:6px;">
            <?php endif; ?>
        </div>
    </div>
    <div class="form-row">
        <button type="submit" class="btn"><?= $editItem ? 'Update Item' : 'Add Item' ?></button>
        <?php if ($editItem): ?><a href="check_guides.php?section_id=<?= $selected_section_id ?>" class="btn btn-secondary">Cancel</a><?php endif; ?>
    </div>
</form>

<div class="table-scroll table-scroll-natural">
<table class="admin-table">
    <thead>
        <tr>
            <th>No</th><th>Photo</th><th>Method</th><th>Checking Item</th><th>Frequency</th><th>Caption</th><th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($items as $it): ?>
        <tr>
            <td><?= htmlspecialchars($it['sort_order']) ?></td>
            <td><?php if (!empty($it['photo'])): ?><img src="<?= $base_url . htmlspecialchars($it['photo']) ?>" alt="" style="height:44px;border-radius:4px;"><?php else: ?>-<?php endif; ?></td>
            <td><?= htmlspecialchars($it['method'] ?: '-') ?></td>
            <td><?= htmlspecialchars($it['checking_item'] ?: '-') ?></td>
            <td><?= htmlspecialchars($it['frequency'] ?: '-') ?></td>
            <td><?= htmlspecialchars($it['caption'] ?: '-') ?></td>
            <td class="row-actions">
                <a href="check_guides.php?section_id=<?= $selected_section_id ?>&action=edit_item&id=<?= $it['id'] ?>">Edit</a>
                <a href="check_guides.php?section_id=<?= $selected_section_id ?>&action=delete_item&id=<?= $it['id'] ?>" onclick="return confirm('Delete this item?')" class="danger">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?><tr><td colspan="7" class="empty">No items yet. Add the first one above.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/app_bottom.php'; ?>

<?php
/**
 * Renders the editable "Checking Guide" panel (photos + instruction table)
 * for a check sheet section, if one is configured and active.
 *
 * Data is managed from admin/check_guides.php and stored in
 * m_check_guide (one per section) + m_check_guide_item (rows/photos).
 *
 * Usage (inside a section page, after $pdo is available):
 *     render_check_guide($pdo, 'subassy_list.php');
 */
function render_check_guide(PDO $pdo, string $route): void
{
    $stmt = $pdo->prepare(
        'SELECT g.* FROM m_check_guide g
         JOIN m_checksheet_section s ON s.id = g.section_id
         WHERE s.route = ? AND g.is_active = 1
         LIMIT 1'
    );
    $stmt->execute([$route]);
    $guide = $stmt->fetch();
    if (!$guide) {
        return;
    }

    $stmt = $pdo->prepare('SELECT * FROM m_check_guide_item WHERE guide_id = ? ORDER BY sort_order, id');
    $stmt->execute([$guide['id']]);
    $items = $stmt->fetchAll();

    $e = fn($v) => htmlspecialchars((string)($v ?? ''));
    $photoItems = array_values(array_filter($items, fn($it) => !empty($it['photo'])));
    $headerLine = trim(($guide['title'] ?? '') . (($guide['title'] && $guide['legend']) ? ' · ' : '') . ($guide['legend'] ?? ''));
    ?>
    <details class="ref-panel" open>
        <summary>
            <span><?= $e($headerLine) ?: 'Checking guide' ?></span>
            <span class="ref-toggle-hint">Checking guide</span>
        </summary>

        <div class="ref-body">
            <?php if ($items): ?>
            <table class="ref-table">
                <thead>
                    <tr>
                        <th>Part Name</th>
                        <th>Checking Method</th>
                        <th>Checking Item</th>
                        <th>Checking Frequency</th>
                        <th>PIC</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $i => $it): ?>
                    <tr>
                        <?php if ($i === 0): ?>
                        <td rowspan="<?= count($items) ?>" class="ref-part">
                            <?php if (!empty($guide['part_image'])): ?>
                                <img src="<?= $e($guide['part_image']) ?>" alt="<?= $e($guide['part_name']) ?>">
                            <?php endif; ?>
                            <span><?= $e($guide['part_name']) ?></span>
                        </td>
                        <?php endif; ?>
                        <td><span class="ref-num"><?= $e($it['sort_order'] ?: $i + 1) ?></span> <?= $e($it['method']) ?></td>
                        <td><?= $e($it['checking_item']) ?></td>
                        <td><?= $e($it['frequency']) ?></td>
                        <?php if ($i === 0): ?>
                        <td rowspan="<?= count($items) ?>" class="ref-pic"><?= nl2br($e($guide['pic_text'])) ?></td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <?php if ($photoItems): ?>
            <div class="ref-photos">
                <?php foreach ($photoItems as $it): ?>
                    <figure>
                        <img src="<?= $e($it['photo']) ?>" alt="<?= $e($it['caption']) ?>">
                        <?php if (!empty($it['caption'])): ?><figcaption><?= $e($it['caption']) ?></figcaption><?php endif; ?>
                    </figure>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </details>
    <?php
}

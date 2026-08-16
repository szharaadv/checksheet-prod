<?php
/**
 * Keeps Checked By (m_checker) entries in sync with a person's section
 * routing (m_user_section), so the live check sheet dropdowns show exactly
 * who's routed to that section — nothing more, nothing less.
 */

/**
 * Ensure a m_checker row exists (and has the right role) for $name in every
 * section in $sectionIds. Claims an existing unassigned row for the same
 * name/department first (so we don't create a duplicate), otherwise creates
 * a fresh row. Never deletes anything — sections no longer assigned are
 * left alone (a Checked By row may already be referenced by past check
 * sheets); use the "Other Checked By Entries" cleanup list for those.
 */
function sync_checker_entries(PDO $pdo, string $name, ?string $checkerRole, array $sectionIds, array $sectionsById): void
{
    foreach ($sectionIds as $sid) {
        if (!isset($sectionsById[$sid])) {
            continue;
        }
        $deptId = (int) $sectionsById[$sid]['department_id'];

        $stmt = $pdo->prepare('SELECT id FROM m_checker WHERE name = ? AND section_id = ?');
        $stmt->execute([$name, $sid]);
        $checkerId = $stmt->fetchColumn();

        if ($checkerId) {
            // Reactivate too: this section may have been deactivated earlier
            // (e.g. auto-pruned) before being reassigned — keep it in step.
            $pdo->prepare('UPDATE m_checker SET role = ?, is_active = 1 WHERE id = ?')->execute([$checkerRole, $checkerId]);
            continue;
        }

        $stmt = $pdo->prepare('SELECT id FROM m_checker WHERE name = ? AND department_id = ? AND section_id IS NULL LIMIT 1');
        $stmt->execute([$name, $deptId]);
        $unassignedId = $stmt->fetchColumn();

        if ($unassignedId) {
            $pdo->prepare('UPDATE m_checker SET section_id = ?, role = ?, is_active = 1 WHERE id = ?')->execute([$sid, $checkerRole, $unassignedId]);
        } else {
            $pdo->prepare('INSERT INTO m_checker (department_id, section_id, name, role) VALUES (?, ?, ?, ?)')->execute([$deptId, $sid, $name, $checkerRole]);
        }
    }
}

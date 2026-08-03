<?php
/**
 * Builds the check sheet location breadcrumb: Department [ › Section ].
 *
 * The section crumb is only added when the department actually has more
 * than one active section (e.g. Assembling -> Torque / Washing). For
 * single-section departments (e.g. Painting) only the department crumb
 * is shown, matching the "Painting goes straight in" flow.
 *
 * Returns an ordered array of crumbs: ['label' => ..., 'href' => ..., 'title' => ...].
 * Consumed by includes/app_top.php as $breadcrumb.
 */
function build_checksheet_breadcrumb(PDO $pdo, array $department, string $current_route): array
{
    $crumbs = [];

    // Department crumb -> back to department picker.
    $crumbs[] = [
        'label' => $department['name'],
        'href'  => 'index.php',
        'title' => 'Change Department',
    ];

    // How many active sections does this department have?
    $stmt = $pdo->prepare('SELECT name FROM m_checksheet_section WHERE department_id = ? AND is_active = 1');
    $stmt->execute([$department['id']]);
    $sections = $stmt->fetchAll();

    if (count($sections) > 1) {
        // Current section name (fall back to nothing if route not found).
        $stmt = $pdo->prepare('SELECT name FROM m_checksheet_section WHERE department_id = ? AND route = ? AND is_active = 1');
        $stmt->execute([$department['id'], $current_route]);
        $sectionName = $stmt->fetchColumn();

        if ($sectionName !== false) {
            $crumbs[] = [
                'label' => $sectionName,
                'href'  => 'select_section.php?department_id=' . $department['id'],
                'title' => 'Change Section',
            ];
        }
    }

    return $crumbs;
}

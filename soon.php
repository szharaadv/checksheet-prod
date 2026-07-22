<?php
$feature = $_GET['feature'] ?? 'This feature';
$base_url = '';
$active_nav = '';
$page_title = $feature;
$page_subtitle = 'Coming soon';
require __DIR__ . '/includes/app_top.php';
?>
<div class="empty-state">
    <p><strong><?= htmlspecialchars($feature) ?></strong> isn't available yet. Coming in a future update.</p>
</div>
<?php require __DIR__ . '/includes/app_bottom.php'; ?>

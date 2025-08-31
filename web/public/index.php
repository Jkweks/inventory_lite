<?php
ob_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
$p = $_GET['p'] ?? 'dashboard';
$allowed = ['dashboard','items','item','jobs','cycle_counts','reports','report_designer','import','settings','cycle_count_sheet','cycle_count_import'];
if (!in_array($p, $allowed, true)) {
    $p = 'dashboard';
}
include __DIR__ . '/partials/header.php';
include __DIR__ . "/pages/{$p}.php";
include __DIR__ . '/partials/footer.php';
ob_end_flush();

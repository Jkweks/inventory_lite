<?php
require_once __DIR__ . '/config.php'; require_once __DIR__ . '/db.php';
include __DIR__ . '/partials/header.php';
$p = $_GET['p'] ?? 'dashboard';
$allowed = ['dashboard','items','jobs','cycle_counts','reports'];
if (!in_array($p, $allowed, true)) $p = 'dashboard';
include __DIR__ . "/pages/{$p}.php";
include __DIR__ . '/partials/footer.php';

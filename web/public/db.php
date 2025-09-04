<?php
require_once __DIR__ . '/config.php';
function db() {
  static $pdo = null;
  global $DB_HOST, $DB_PORT, $DB_NAME, $DB_USER, $DB_PASS;
  if ($pdo === null) {
    $dsn = "pgsql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};";
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
  }
  return $pdo;
}
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function number_fmt($n){
  if ($n === null) return '0';
  return number_format((int)round($n), 0, '.', '');
}
function money_fmt($n){
  if ($n === null) return '0.00';
  return number_format((float)$n, 2, '.', '');
}
function date_fmt($d){
  if (!$d) return '';
  return date('m/d/Y', strtotime($d));
}
function datetime_fmt($d){
  if (!$d) return '';
  return date('m/d/Y H:i', strtotime($d));
}

<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
$pdo = db();
$report = $_GET['report'] ?? 'full';
$allowed_reports = ['full','low','accounting'];
if (!in_array($report, $allowed_reports, true)) {
    $report = 'full';
}
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="inventory_' . $report . '_' . date('Ymd_His') . '.csv"');
$out=fopen('php://output','w');
if($report==='full'){
  fputcsv($out,['SKU','Name','Unit','On Hand','Committed','Available']);
  $stmt=$pdo->query("SELECT base_sku AS sku, MIN(name) AS name, MIN(unit) AS unit, SUM(qty_on_hand) AS qty_on_hand, SUM(qty_committed) AS qty_committed, SUM(qty_on_hand-qty_committed) AS available FROM (SELECT COALESCE(parent_sku,sku) AS base_sku, name, unit, qty_on_hand, qty_committed FROM inventory_items WHERE archived=false) t GROUP BY base_sku ORDER BY base_sku");
  while($row=$stmt->fetch(PDO::FETCH_ASSOC)){
      fputcsv($out,[$row['sku'],$row['name'],$row['unit'],$row['qty_on_hand'],$row['qty_committed'],$row['available']]);
  }
}elseif($report==='low'){
  fputcsv($out,['SKU','Name','Min Qty','Available']);
  $stmt=$pdo->query("SELECT base_sku AS sku, MIN(name) AS name, MIN(min_qty) AS min_qty, SUM(qty_on_hand-qty_committed) AS available FROM (SELECT COALESCE(parent_sku,sku) AS base_sku, name, min_qty, qty_on_hand, qty_committed FROM inventory_items WHERE archived=false) t GROUP BY base_sku HAVING SUM(qty_on_hand-qty_committed) < MIN(min_qty) ORDER BY available ASC");
  while($row=$stmt->fetch(PDO::FETCH_ASSOC)){
      fputcsv($out,[$row['sku'],$row['name'],$row['min_qty'],$row['available']]);
  }
}elseif($report==='accounting'){
  fputcsv($out,['SKU','Name','Unit','Cost USD','On Hand','Value']);
  $stmt=$pdo->query("SELECT base_sku AS sku, MIN(name) AS name, MIN(unit) AS unit, MIN(cost_usd) AS cost_usd, SUM(qty_on_hand) AS qty_on_hand, SUM(cost_usd*qty_on_hand) AS total FROM (SELECT COALESCE(parent_sku,sku) AS base_sku, name, unit, cost_usd, qty_on_hand FROM inventory_items WHERE archived=false) t GROUP BY base_sku ORDER BY base_sku");
  while($row=$stmt->fetch(PDO::FETCH_ASSOC)){
      fputcsv($out,[$row['sku'],$row['name'],$row['unit'],$row['cost_usd'],$row['qty_on_hand'],$row['total']]);
  }
}else{
  fputcsv($out,['Unsupported report']);
}
fclose($out); exit;

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
  $stmt=$pdo->query("SELECT sku,name,unit,qty_on_hand,qty_committed,(qty_on_hand-qty_committed) AS available FROM inventory_items WHERE archived=false ORDER BY sku");
  while($row=$stmt->fetch(PDO::FETCH_ASSOC)){
      fputcsv($out,[$row['sku'],$row['name'],$row['unit'],$row['qty_on_hand'],$row['qty_committed'],$row['available']]);
  }
}elseif($report==='low'){
  fputcsv($out,['SKU','Name','Min Qty','Available']);
  $stmt=$pdo->query("SELECT sku,name,min_qty,(qty_on_hand-qty_committed) AS available FROM inventory_items WHERE archived=false AND min_qty IS NOT NULL AND (qty_on_hand-qty_committed) < min_qty ORDER BY available ASC");
  while($row=$stmt->fetch(PDO::FETCH_ASSOC)){
      fputcsv($out,[$row['sku'],$row['name'],$row['min_qty'],$row['available']]);
  }
}elseif($report==='accounting'){
  fputcsv($out,['SKU','Name','Unit','Cost USD','On Hand','Value']);
  $stmt=$pdo->query("SELECT sku,name,unit,cost_usd,qty_on_hand,(cost_usd*qty_on_hand) AS total FROM inventory_items WHERE archived=false ORDER BY sku");
  while($row=$stmt->fetch(PDO::FETCH_ASSOC)){
      fputcsv($out,[$row['sku'],$row['name'],$row['unit'],$row['cost_usd'],$row['qty_on_hand'],$row['total']]);
  }
}else{
  fputcsv($out,['Unsupported report']);
}
fclose($out); exit;

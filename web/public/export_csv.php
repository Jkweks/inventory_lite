<?php
require_once __DIR__ . '/config.php'; require_once __DIR__ . '/db.php'; $pdo=db();
$report=$_GET['report'] ?? 'snapshot';
header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="inventory_'+$report+'_'+date('Ymd_His')+'.csv"');
$out=fopen('php://output','w');
if($report==='snapshot'){
  fputcsv($out,['SKU','Name','Unit','On Hand','Committed','Available']);
  $stmt=$pdo->query("SELECT sku,name,unit,qty_on_hand,qty_committed,(qty_on_hand-qty_committed) AS available FROM inventory_items ORDER BY sku");
  while($row=$stmt->fetch(PDO::FETCH_ASSOC)){ fputcsv($out,[$row['sku'],$row['name'],$row['unit'],$row['qty_on_hand'],$row['qty_committed'],$row['available']]); }
}else{ fputcsv($out,['Unsupported report']); }
fclose($out); exit;

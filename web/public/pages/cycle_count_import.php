<?php
$pdo = db();
$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv'])) {
  $pdo->beginTransaction();
  try {
    $handle = fopen($_FILES['csv']['tmp_name'], 'r');
    $headers = array_map('strtolower', fgetcsv($handle));
    $deltas = [];
    $totals = [];
    while (($row = fgetcsv($handle)) !== false) {
      $data = array_combine($headers, $row);
      $sku = trim($data['sku'] ?? $data['part number'] ?? '');
      $loc = trim($data['location'] ?? '');
      $count = (int)($data['count'] ?? $data['counted qty'] ?? 0);
      if ($sku === '' || $loc === '' || !is_numeric($count)) {
        continue;
      }
      $stmt = $pdo->prepare('SELECT id, qty_on_hand FROM inventory_items WHERE sku = ? FOR UPDATE');
      $stmt->execute([$sku]);
      $item = $stmt->fetch();
      if (!$item) {
        continue;
      }
      $item_id = (int)$item['id'];
        $totals[$item_id] = ($totals[$item_id] ?? 0) + $count;
      $locStmt = $pdo->prepare('SELECT qty_on_hand FROM item_locations WHERE item_id = ? AND location = ? FOR UPDATE');
      $locStmt->execute([$item_id, $loc]);
        $locRow = $locStmt->fetch();
        $prev_loc = $locRow ? (int)$locRow['qty_on_hand'] : 0;
        $delta = $count - $prev_loc;
      $deltas[$item_id] = ($deltas[$item_id] ?? 0) + $delta;
      if ($locRow) {
        $pdo->prepare('UPDATE item_locations SET qty_on_hand = ? WHERE item_id = ? AND location = ?')
            ->execute([$count, $item_id, $loc]);
      } else {
        $pdo->prepare('INSERT INTO item_locations (item_id, location, qty_on_hand) VALUES (?,?,?)')
            ->execute([$item_id, $loc, $count]);
      }
    }
    fclose($handle);
    foreach ($deltas as $item_id => $delta) {
      $counted = $totals[$item_id] ?? 0;
      $pdo->prepare('INSERT INTO cycle_counts (item_id, counted_qty, note) VALUES (?,?,?)')
          ->execute([$item_id, $counted, 'import']);
      $pdo->prepare('INSERT INTO inventory_txns (item_id, txn_type, qty_delta, ref_table, note) VALUES (?,?,?,?,?)')
          ->execute([$item_id, 'cycle_count', $delta, 'cycle_count_import', 'import']);
      $pdo->prepare('UPDATE inventory_items SET qty_on_hand = ? WHERE id = ?')
          ->execute([$counted, $item_id]);
    }
    $pdo->commit();
    $message = 'Import complete';
  } catch (Exception $e) {
    $pdo->rollBack();
    throw $e;
  }
}
?>
<h1 class="h3 mb-3">Import Cycle Counts</h1>
<?php if ($message): ?><div class="alert alert-success"><?= h($message) ?></div><?php endif; ?>
<div class="card"><div class="card-body">
<form method="post" enctype="multipart/form-data">
<div class="mb-2"><label class="form-label">CSV File</label><input type="file" name="csv" class="form-control" required></div>
<button class="btn btn-primary">Import</button>
</form></div></div>


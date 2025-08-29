<?php
$pdo=db();
if($_SERVER['REQUEST_METHOD']==='POST' && ( $_POST['form'] ?? '' )==='cycle_count'){
  $item_id=(int)$_POST['item_id']; $counted=(float)$_POST['counted_qty']; $note=$_POST['note']??null;
  $pdo->beginTransaction();
  try{
    $cur=$pdo->prepare("SELECT qty_on_hand FROM inventory_items WHERE id=? FOR UPDATE"); $cur->execute([$item_id]);
    $row=$cur->fetch(); if(!$row) throw new Exception("Item not found");
    $prev=(float)$row['qty_on_hand']; $delta=$counted-$prev;
    $pdo->prepare("INSERT INTO cycle_counts (item_id,counted_qty,note) VALUES (?,?,?)")->execute([$item_id,$counted,$note]);
    $pdo->prepare("INSERT INTO inventory_txns (item_id,txn_type,qty_delta,ref_table,note) VALUES (?,?,?,?,?)")->execute([$item_id,'cycle_count',$delta,'cycle_counts',$note]);
    $pdo->prepare("UPDATE inventory_items SET qty_on_hand=? WHERE id=?")->execute([$counted,$item_id]);
    $pdo->commit(); header("Location: /index.php?p=cycle_counts&ok=1"); exit;
  }catch(Exception $e){ $pdo->rollBack(); throw $e; }
}
$item_id_prefill=isset($_GET['item_id'])?(int)$_GET['item_id']:null;
$items=$pdo->query("SELECT id, sku, name FROM inventory_items ORDER BY sku")->fetchAll();
$recent=$pdo->query("SELECT c.id,i.sku,i.name,c.counted_qty,c.count_date,c.note FROM cycle_counts c JOIN inventory_items i ON i.id=c.item_id ORDER BY c.id DESC LIMIT 20")->fetchAll();
?>
<div class="row g-3"><div class="col-lg-5"><div class="card"><div class="card-body">
<h1 class="h5 mb-3">Cycle Count</h1>
<form method="post"><input type="hidden" name="form" value="cycle_count">
<div class="mb-2"><label class="form-label">Item</label><select name="item_id" class="form-select" required>
<option value="">Select item…</option><?php foreach($items as $it): ?><option value="<?= $it['id'] ?>" <?= $item_id_prefill===$it['id']?'selected':'' ?>><?= h($it['sku']) ?> — <?= h($it['name']) ?></option><?php endforeach; ?></select></div>
<div class="mb-2"><label class="form-label">Counted Qty</label><input type="number" step="0.001" name="counted_qty" class="form-control" required></div>
<div class="mb-2"><label class="form-label">Note</label><input name="note" class="form-control" placeholder="optional"></div>
<button class="btn btn-primary">Save Count</button></form></div></div></div>
<div class="col-lg-7"><div class="card"><div class="card-body"><h2 class="h6">Recent Counts</h2>
<div class="table-responsive"><table class="table table-sm table-striped">
<thead><tr><th>SKU</th><th>Name</th><th class="text-end">Qty</th><th>Date</th><th>Note</th></tr></thead>
<tbody><?php foreach($recent as $r): ?><tr>
<td><?= h($r['sku']) ?></td><td><?= h($r['name']) ?></td><td class="text-end"><?= number_fmt($r['counted_qty']) ?></td>
<td><?= h($r['count_date']) ?></td><td><?= h($r['note']) ?></td></tr><?php endforeach; ?></tbody></table></div></div></div></div>

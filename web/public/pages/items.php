<?php
$pdo=db();
if($_SERVER['REQUEST_METHOD']==='POST' && ( $_POST['form'] ?? '' )==='create_item'){
  $stmt=$pdo->prepare("INSERT INTO inventory_items (sku,name,unit,qty_on_hand,qty_committed,min_qty) VALUES (?,?,?,?,0,?)");
  $stmt->execute([$_POST['sku'],$_POST['name'],$_POST['unit']?:'ea',(float)$_POST['qty_on_hand'],(float)$_POST['min_qty']]);
  header("Location: /index.php?p=items&created=1"); exit;
}
$items=$pdo->query("SELECT * FROM inventory_items ORDER BY sku")->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h3 mb-0">Items</h1></div>
<div class="row g-3"><div class="col-lg-5"><div class="card"><div class="card-body">
<h2 class="h5">Add Item</h2>
<form method="post"><input type="hidden" name="form" value="create_item">
<div class="mb-2"><label class="form-label">SKU</label><input name="sku" class="form-control" required></div>
<div class="mb-2"><label class="form-label">Name</label><input name="name" class="form-control" required></div>
<div class="mb-2"><label class="form-label">Unit</label><input name="unit" class="form-control" placeholder="ea"></div>
<div class="mb-2"><label class="form-label">Starting On Hand</label><input name="qty_on_hand" type="number" step="0.001" class="form-control" value="0"></div>
<div class="mb-2"><label class="form-label">Min Qty (optional)</label><input name="min_qty" type="number" step="0.001" class="form-control" value="0"></div>
<button class="btn btn-primary">Save</button></form></div></div></div>
<div class="col-lg-7"><div class="card"><div class="card-body"><h2 class="h5">All Items</h2>
<div class="table-responsive"><table class="table table-sm table-striped align-middle">
<thead><tr><th>SKU</th><th>Name</th><th>Unit</th><th class="text-end">On Hand</th><th class="text-end">Committed</th></tr></thead>
<tbody><?php foreach($items as $it): ?><tr>
<td><?= h($it['sku']) ?></td><td><?= h($it['name']) ?></td><td><?= h($it['unit']) ?></td>
<td class="text-end"><?= number_fmt($it['qty_on_hand']) ?></td><td class="text-end"><?= number_fmt($it['qty_committed']) ?></td></tr><?php endforeach; ?>
</tbody></table></div></div></div></div></div>

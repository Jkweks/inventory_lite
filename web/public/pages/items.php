<?php
$pdo=db();
if($_SERVER['REQUEST_METHOD']==='POST' && ( $_POST['form'] ?? '' )==='create_item'){
  $pdo->beginTransaction();
  try{
    $stmt=$pdo->prepare("INSERT INTO inventory_items (sku,name,unit,category,item_type,item_use,description,cost_usd,sage_id,qty_on_hand,qty_committed,min_qty) VALUES (?,?,?,?,?,?,?,?,?,0,0,?)");
    $stmt->execute([
      $_POST['sku'],
      $_POST['name'],
      $_POST['unit']?:'ea',
      $_POST['category']?:null,
      $_POST['item_type']?:null,
      $_POST['item_use']?:null,
      $_POST['description']?:null,
      (float)$_POST['cost_usd'],
      $_POST['sage_id']?:null,
      (float)$_POST['min_qty']
    ]);
    $item_id=$pdo->lastInsertId();
    $total=0;
    $locations=preg_split('/\r?\n/', trim($_POST['locations']??''));
    foreach($locations as $line){
      $line=trim($line); if($line==='') continue;
      if(!preg_match('/^([A-Z]\.\d+\.\d+\.\d+)=(\d+(?:\.\d+)?)$/',$line,$m)) continue;
      $pdo->prepare("INSERT INTO item_locations (item_id,location,qty_on_hand) VALUES (?,?,?)")->execute([$item_id,$m[1],$m[2]]);
      $total+=$m[2];
    }
    $pdo->prepare("UPDATE inventory_items SET qty_on_hand=? WHERE id=?")->execute([$total,$item_id]);
    $pdo->commit();
    header("Location: /index.php?p=items&created=1"); exit;
  }catch(Exception $e){ $pdo->rollBack(); throw $e; }
}
$items=$pdo->query("SELECT * FROM inventory_items ORDER BY category, item_type, sku")->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h3 mb-0">Items</h1><a href="/index.php?p=import" class="btn btn-outline-primary btn-sm">Import CSV</a></div>
<div class="row g-3"><div class="col-lg-5"><div class="card"><div class="card-body">
<h2 class="h5">Add Item</h2>
<form method="post"><input type="hidden" name="form" value="create_item">
<div class="mb-2"><label class="form-label">SKU</label><input name="sku" class="form-control" required></div>
<div class="mb-2"><label class="form-label">Name</label><input name="name" class="form-control" required></div>
<div class="mb-2"><label class="form-label">Unit</label><input name="unit" class="form-control" placeholder="ea"></div>
<div class="mb-2"><label class="form-label">Category</label><input name="category" class="form-control"></div>
<div class="mb-2"><label class="form-label">Type</label><input name="item_type" class="form-control"></div>
<div class="mb-2"><label class="form-label">Use</label><input name="item_use" class="form-control"></div>
<div class="mb-2"><label class="form-label">Description</label><input name="description" class="form-control"></div>
<div class="mb-2"><label class="form-label">Cost (USD)</label><input name="cost_usd" type="number" step="0.01" class="form-control" value="0"></div>
<div class="mb-2"><label class="form-label">Sage ID</label><input name="sage_id" class="form-control"></div>
<div class="mb-2"><label class="form-label">Locations (A.1.2.3=qty per line)</label><textarea name="locations" class="form-control" rows="3" placeholder="A.1.2.3=5"></textarea></div>
<div class="mb-2"><label class="form-label">Min Qty (optional)</label><input name="min_qty" type="number" step="0.001" class="form-control" value="0"></div>
<button class="btn btn-primary">Save</button></form></div></div></div>
<div class="col-lg-7"><div class="card"><div class="card-body"><h2 class="h5">All Items</h2>
<div class="table-responsive"><table class="table table-sm table-striped align-middle">
<thead><tr><th>Category</th><th>Type</th><th>SKU</th><th>Name</th><th class="text-end">On Hand</th><th class="text-end">Committed</th><th>Actions</th></tr></thead>
<tbody><?php foreach($items as $it): ?><tr>
<td><?= h($it['category']) ?></td><td><?= h($it['item_type']) ?></td><td><a href="/index.php?p=item&sku=<?= urlencode($it['sku']) ?>"><?= h($it['sku']) ?></a></td><td><?= h($it['name']) ?></td>
<td class="text-end"><?= number_fmt($it['qty_on_hand']) ?></td><td class="text-end"><?= number_fmt($it['qty_committed']) ?></td><td><a class="btn btn-sm btn-outline-secondary" href="/index.php?p=item&sku=<?= urlencode($it['sku']) ?>">Edit</a></td>
</tr><?php endforeach; ?>
</tbody></table></div></div></div></div></div>

<?php
$pdo=db();
$sku=$_GET['sku']??'';
$stmt=$pdo->prepare("SELECT * FROM inventory_items WHERE sku=?");
$stmt->execute([$sku]);
$item=$stmt->fetch();
if(!$item){ echo '<div class="alert alert-danger">Item not found</div>'; return; }
if($_SERVER['REQUEST_METHOD']==='POST' && ( $_POST['form'] ?? '' )==='update_item'){
  $pdo->beginTransaction();
  try{
    $pdo->prepare("UPDATE inventory_items SET name=?,unit=?,category=?,item_type=?,item_use=?,description=?,cost_usd=?,sage_id=?,min_qty=? WHERE id=?")
        ->execute([
          $_POST['name'],
          $_POST['unit']?:'ea',
          $_POST['category']?:null,
          $_POST['item_type']?:null,
          $_POST['item_use']?:null,
          $_POST['description']?:null,
          (float)$_POST['cost_usd'],
          $_POST['sage_id']?:null,
          (float)$_POST['min_qty'],
          $item['id']
        ]);
    $pdo->prepare("DELETE FROM item_locations WHERE item_id=?")->execute([$item['id']]);
    $total=0;
    $locations=preg_split('/\r?\n/', trim($_POST['locations']??''));
    foreach($locations as $line){
      $line=trim($line); if($line==='') continue;
      if(!preg_match('/^([A-Z]\.\d+\.\d+\.\d+)=(\d+(?:\.\d+)?)$/',$line,$m)) continue;
      $pdo->prepare("INSERT INTO item_locations (item_id,location,qty_on_hand) VALUES (?,?,?)")
          ->execute([$item['id'],$m[1],$m[2]]);
      $total+=$m[2];
    }
    $pdo->prepare("UPDATE inventory_items SET qty_on_hand=? WHERE id=?")->execute([$total,$item['id']]);
    $pdo->commit();
    header("Location: /index.php?p=item&sku=".urlencode($sku)."&updated=1"); exit;
  }catch(Exception $e){ $pdo->rollBack(); throw $e; }
}
$locs=$pdo->prepare("SELECT location,qty_on_hand FROM item_locations WHERE item_id=? ORDER BY location");
$locs->execute([$item['id']]);
$loc_lines=[]; while($row=$locs->fetch(PDO::FETCH_ASSOC)){ $loc_lines[]=$row['location'].'='.$row['qty_on_hand']; }
$loc_text=implode("\n",$loc_lines);
?>
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h3 mb-0">Edit Item</h1><a href="/index.php?p=items" class="btn btn-outline-secondary btn-sm">Back</a></div>
<?php if(isset($_GET['updated'])): ?><div class="alert alert-success">Item updated</div><?php endif; ?>
<div class="card"><div class="card-body"><form method="post"><input type="hidden" name="form" value="update_item">
<div class="mb-2"><label class="form-label">SKU</label><input name="sku" class="form-control" value="<?= h($item['sku']) ?>" readonly></div>
<div class="mb-2"><label class="form-label">Name</label><input name="name" class="form-control" value="<?= h($item['name']) ?>" required></div>
<div class="mb-2"><label class="form-label">Unit</label><input name="unit" class="form-control" value="<?= h($item['unit']) ?>"></div>
<div class="mb-2"><label class="form-label">Category</label><input name="category" class="form-control" value="<?= h($item['category']) ?>"></div>
<div class="mb-2"><label class="form-label">Type</label><input name="item_type" class="form-control" value="<?= h($item['item_type']) ?>"></div>
<div class="mb-2"><label class="form-label">Use</label><input name="item_use" class="form-control" value="<?= h($item['item_use']) ?>"></div>
<div class="mb-2"><label class="form-label">Description</label><input name="description" class="form-control" value="<?= h($item['description']) ?>"></div>
<div class="mb-2"><label class="form-label">Cost (USD)</label><input name="cost_usd" type="number" step="0.01" class="form-control" value="<?= h($item['cost_usd']) ?>"></div>
<div class="mb-2"><label class="form-label">Sage ID</label><input name="sage_id" class="form-control" value="<?= h($item['sage_id']) ?>"></div>
<div class="mb-2"><label class="form-label">Locations (A.1.2.3=qty per line)</label><textarea name="locations" class="form-control" rows="3"><?= h($loc_text) ?></textarea></div>
<div class="mb-2"><label class="form-label">Min Qty</label><input name="min_qty" type="number" step="0.001" class="form-control" value="<?= h($item['min_qty']) ?>"></div>
<button class="btn btn-primary">Save</button></form></div></div>

<?php
$pdo=db();
$id=$_GET['id']??($_GET['item_id']??null);
if($id){
  $stmt=$pdo->prepare("SELECT * FROM inventory_items WHERE id=?");
  $stmt->execute([$id]);
  $item=$stmt->fetch();
  $sku=$item['sku']??'';
}else{
  $sku=$_GET['sku']??'';
  $stmt=$pdo->prepare("SELECT * FROM inventory_items WHERE sku=?");
  $stmt->execute([$sku]);
  $item=$stmt->fetch();
  $id=$item['id']??null;
}
if(!$item){ echo '<div class="alert alert-danger">Item not found</div>'; return; }
if($_SERVER['REQUEST_METHOD']==='POST'){
  $form=$_POST['form']??'';
  if($form==='update_item'){
    $pdo->beginTransaction();
    try{
      $image_url=$item['image_url'];
      if(!empty($_FILES['image_file']['tmp_name'])){
        $img=@imagecreatefromstring(file_get_contents($_FILES['image_file']['tmp_name']));
        if($img){
          $dir=dirname(__DIR__).'/uploads';
          if(!is_dir($dir)) mkdir($dir,0777,true);
          $fname=uniqid().'.jpg';
          imagejpeg($img,$dir.'/'.$fname);
          imagedestroy($img);
          $image_url='/uploads/'.$fname;
        }
      }
      $pdo->prepare("UPDATE inventory_items SET parent_sku=?, finish=?, name=?,unit=?,category=?,item_type=?,item_use=?,description=?,image_url=?,cost_usd=?,sage_id=?,min_qty=?,archived=? WHERE id=?")
          ->execute([
            $_POST['parent_sku']?:null,
            $_POST['finish']?:null,
            $_POST['name'],
            $_POST['unit']?:'ea',
            $_POST['category']?:null,
            $_POST['item_type']?:null,
            $_POST['item_use']?:null,
            $_POST['description']?:null,
            $image_url,
            (float)$_POST['cost_usd'],
            $_POST['sage_id']?:null,
            (float)$_POST['min_qty'],
            isset($_POST['archived'])?1:0,
            $item['id']
          ]);
      $pdo->prepare("DELETE FROM item_locations WHERE item_id=?")->execute([$item['id']]);
      $total=0;
      $locations=preg_split('/\r?\n/', trim($_POST['locations']??''));
      foreach($locations as $line){
        $line=trim($line); if($line==='') continue;
        if(!preg_match('/^([A-Z]\.\d+\.\d+)=(\d+(?:\.\d+)?)$/',$line,$m)) continue;
        $pdo->prepare("INSERT INTO item_locations (item_id,location,qty_on_hand) VALUES (?,?,?)")
            ->execute([$item['id'],$m[1],$m[2]]);
        $total+=$m[2];
      }
      $pdo->prepare("UPDATE inventory_items SET qty_on_hand=? WHERE id=?")->execute([$total,$item['id']]);
      $pdo->commit();
      header("Location: /index.php?p=item&id=".$item['id']."&updated=1"); exit;
    }catch(Exception $e){ $pdo->rollBack(); throw $e; }
  }elseif($form==='delete_item'){
    $pdo->prepare("DELETE FROM inventory_items WHERE id=?")->execute([$item['id']]);
    header("Location: /index.php?p=items&deleted=1"); exit;
  }
}
$locs=$pdo->prepare("SELECT location,qty_on_hand FROM item_locations WHERE item_id=? ORDER BY location");
$locs->execute([$item['id']]);
$loc_lines=[]; while($row=$locs->fetch(PDO::FETCH_ASSOC)){ $loc_lines[]=$row['location'].'='.$row['qty_on_hand']; }
$loc_text=implode("\n",$loc_lines);
$variants=[];
if(!$item['parent_sku']){
  $vs=$pdo->prepare("SELECT id,sku,finish,qty_on_hand,qty_committed FROM inventory_items WHERE parent_sku=? ORDER BY sku");
  $vs->execute([$item['sku']]);
  $variants=$vs->fetchAll(PDO::FETCH_ASSOC);
}
?>
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h3 mb-0">Edit Item</h1><a href="/index.php?p=items" class="btn btn-outline-secondary btn-sm">Back</a></div>
<?php if(isset($_GET['updated'])): ?><div class="alert alert-success">Item updated</div><?php endif; ?>
<?php if($variants): ?><div class="card mb-3"><div class="card-body">
  <h2 class="h5 mb-3">Variants</h2>
  <div class="table-responsive"><table class="table table-sm align-middle">
    <thead><tr><th>SKU</th><th>Finish</th><th class="text-end">On Hand</th><th class="text-end">Committed</th><th>Actions</th></tr></thead>
    <tbody><?php foreach($variants as $v): ?><tr>
      <td><a href="/index.php?p=item&id=<?= $v['id'] ?>"><?= h($v['sku']) ?></a></td>
      <td><?= h($v['finish']) ?></td>
      <td class="text-end"><?= number_fmt($v['qty_on_hand']) ?></td>
      <td class="text-end"><?= number_fmt($v['qty_committed']) ?></td>
      <td><a class="btn btn-sm btn-outline-secondary" href="/index.php?p=item&id=<?= $v['id'] ?>">Edit</a></td>
    </tr><?php endforeach; ?></tbody>
  </table></div>
</div></div><?php endif; ?>
<div class="card"><div class="card-body"><form method="post" enctype="multipart/form-data"><input type="hidden" name="form" value="update_item">
<div class="mb-2"><label class="form-label">SKU</label><input name="sku" class="form-control" value="<?= h($item['sku']) ?>" readonly></div>
<div class="mb-2"><label class="form-label">Parent SKU (optional)</label><input name="parent_sku" class="form-control" value="<?= h($item['parent_sku']) ?>"></div>
<div class="mb-2"><label class="form-label">Finish</label><input name="finish" class="form-control" value="<?= h($item['finish']) ?>"></div>
<div class="mb-2"><label class="form-label">Name</label><input name="name" class="form-control" value="<?= h($item['name']) ?>" required></div>
<div class="mb-2"><label class="form-label">Unit</label><input name="unit" class="form-control" value="<?= h($item['unit']) ?>"></div>
<div class="mb-2"><label class="form-label">Category</label><input name="category" class="form-control" value="<?= h($item['category']) ?>"></div>
<div class="mb-2"><label class="form-label">Type</label><input name="item_type" class="form-control" value="<?= h($item['item_type']) ?>"></div>
<div class="mb-2"><label class="form-label">Use</label><input name="item_use" class="form-control" value="<?= h($item['item_use']) ?>"></div>
<div class="mb-2"><label class="form-label">Description</label><input name="description" class="form-control" value="<?= h($item['description']) ?>"></div>
<div class="mb-2"><label class="form-label">Image</label><input type="file" name="image_file" class="form-control"><?php if($item['image_url']): ?><img src="<?= h($item['image_url']) ?>" alt="" class="img-thumbnail mt-2" style="width:80px;height:80px;object-fit:cover;"><?php endif; ?></div>
<div class="mb-2"><label class="form-label">Cost (USD)</label><input name="cost_usd" type="number" step="0.01" class="form-control" value="<?= h($item['cost_usd']) ?>"></div>
<div class="mb-2"><label class="form-label">Sage ID</label><input name="sage_id" class="form-control" value="<?= h($item['sage_id']) ?>"></div>
<div class="mb-2"><label class="form-label">Locations (A.1.2=qty per line)</label><textarea name="locations" class="form-control" rows="3"><?= h($loc_text) ?></textarea></div>
<div class="mb-2"><label class="form-label">Min Qty</label><input name="min_qty" type="number" step="0.001" class="form-control" value="<?= h($item['min_qty']) ?>"></div>
<div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="archived" name="archived" value="1" <?= $item['archived']?'checked':'' ?>>
<label class="form-check-label" for="archived">Archived</label></div>
<button class="btn btn-primary">Save</button></form>
<form method="post" class="mt-3" onsubmit="return confirm('Delete this item?');">
<input type="hidden" name="form" value="delete_item">
<button class="btn btn-outline-danger">Delete Item</button>
</form></div></div>

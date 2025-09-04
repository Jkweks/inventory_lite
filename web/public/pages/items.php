<?php
$pdo=db();
$variantView=$pdo->query("SELECT value FROM settings WHERE key='variant_view'")->fetchColumn() ?: 'individual';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $form=$_POST['form']??'';
  if($form==='create_item'){
    $pdo->beginTransaction();
    try{
      $image_url=null;
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
      $stmt=$pdo->prepare("INSERT INTO inventory_items (sku,parent_sku,finish,name,unit,category,item_type,item_use,description,image_url,cost_usd,sage_id,qty_on_hand,qty_committed,min_qty) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,0,0,?)");
      $stmt->execute([
        $_POST['sku'],
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
        (int)$_POST['min_qty']
      ]);
      $item_id=$pdo->lastInsertId();
      $total=0;
      $locations=preg_split('/\r?\n/', trim($_POST['locations']??''));
        foreach($locations as $line){
          $line=trim($line); if($line==='') continue;
          if(!preg_match('/^([A-Z]\.\d+\.\d+)=(\d+)$/',$line,$m)) continue;
          $qty=(int)$m[2];
          $pdo->prepare("INSERT INTO item_locations (item_id,location,qty_on_hand) VALUES (?,?,?)")->execute([$item_id,$m[1],$qty]);
          $total+=$qty;
        }
        $pdo->prepare("UPDATE inventory_items SET qty_on_hand=? WHERE id=?")->execute([$total,$item_id]);
      $pdo->commit();
      header("Location: /index.php?p=items&created=1"); exit;
    }catch(Exception $e){ $pdo->rollBack(); throw $e; }
  }
}
$validSort=['category','item_type','sku','name','qty_on_hand','qty_committed'];
$sort=$_GET['sort']??'category';
if(!in_array($sort,$validSort)) $sort='category';
$dir=strtolower($_GET['dir']??'asc')==='desc'?'desc':'asc';
function sort_link($col,$label,$sort,$dir){
  $newDir=($sort===$col && $dir==='asc')?'desc':'asc';
  $indicator=$sort===$col?($dir==='asc'?'&uarr;':'&darr;'):'';
  return "<a href=\"?p=items&sort=$col&dir=$newDir\">$label $indicator</a>";
}
if($variantView==='grouped'){
  $sortMap=[
    'category'=>'MIN(category)',
    'item_type'=>'MIN(item_type)',
    'sku'=>'sku',
    'name'=>'MIN(name)',
    'qty_on_hand'=>'SUM(qty_on_hand)',
    'qty_committed'=>'SUM(qty_committed)'
  ];
  $sortExpr=$sortMap[$sort];
  $items=$pdo->query("SELECT COALESCE(MIN(CASE WHEN parent_sku IS NULL THEN id END), MIN(id)) AS id, COALESCE(parent_sku,sku) AS sku, MIN(name) AS name, MIN(unit) AS unit, MIN(category) AS category, MIN(item_type) AS item_type, MIN(image_url) AS image_url, SUM(qty_on_hand) AS qty_on_hand, SUM(qty_committed) AS qty_committed FROM inventory_items WHERE archived=false GROUP BY COALESCE(parent_sku,sku) ORDER BY $sortExpr $dir, sku ASC")->fetchAll();
}else{
  $items=$pdo->query("SELECT * FROM inventory_items WHERE archived=false AND sku NOT IN (SELECT parent_sku FROM inventory_items WHERE parent_sku IS NOT NULL) ORDER BY $sort $dir, sku ASC")->fetchAll();
}

// Fetch job commitments for each item or grouped SKU
$jobMap=[];
if($items){
  if($variantView==='grouped'){
    $skus=array_column($items,'sku');
    $ph=implode(',',array_fill(0,count($skus),'?'));
    $stmt=$pdo->prepare("SELECT COALESCE(i.parent_sku,i.sku) AS base_sku, j.job_number, SUM(jm.qty_committed) AS qty FROM job_materials jm JOIN inventory_items i ON i.id=jm.item_id JOIN jobs j ON j.id=jm.job_id WHERE COALESCE(i.parent_sku,i.sku) IN ($ph) GROUP BY base_sku, j.job_number ORDER BY j.job_number");
    $stmt->execute($skus);
    while($row=$stmt->fetch(PDO::FETCH_ASSOC)){
      $jobMap[$row['base_sku']][]=['job'=>$row['job_number'],'qty'=>$row['qty']];
    }
  }else{
    $ids=array_column($items,'id');
    $ph=implode(',',array_fill(0,count($ids),'?'));
    $stmt=$pdo->prepare("SELECT jm.item_id, j.job_number, jm.qty_committed AS qty FROM job_materials jm JOIN jobs j ON j.id=jm.job_id WHERE jm.item_id IN ($ph) ORDER BY j.job_number");
    $stmt->execute($ids);
    while($row=$stmt->fetch(PDO::FETCH_ASSOC)){
      $jobMap[$row['item_id']][]=['job'=>$row['job_number'],'qty'=>$row['qty']];
    }
  }
}
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h3 mb-0">Items</h1>
  <div class="d-flex gap-2">
    <a href="/index.php?p=import" class="btn btn-outline-primary btn-sm">Import CSV</a>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addItemModal">Add Item</button>
  </div>
</div>
<?php if(isset($_GET['deleted'])): ?><div class="alert alert-success">Item deleted</div><?php endif; ?>
<div class="card"><div class="card-body"><h2 class="h5">All Items</h2>
<div class="table-responsive"><table class="table table-sm table-striped align-middle">
<thead><tr><th><?= sort_link('category','Category',$sort,$dir) ?></th><th><?= sort_link('item_type','Type',$sort,$dir) ?></th><th><?= sort_link('sku','SKU',$sort,$dir) ?></th><th>Img</th><th><?= sort_link('name','Name',$sort,$dir) ?></th><th class="text-end"><?= sort_link('qty_on_hand','On Hand',$sort,$dir) ?></th><th class="text-end"><?= sort_link('qty_committed','Committed',$sort,$dir) ?></th><th>Actions</th></tr></thead>
<tbody><?php foreach($items as $it): ?><tr>
<td><?= h($it['category']) ?></td><td><?= h($it['item_type']) ?></td><td><a href="/index.php?p=item&id=<?= $it['id'] ?>"><?= h($it['sku']) ?></a></td>
<td><?php if($it['image_url']): ?><img src="<?= h($it['image_url']) ?>" alt="" style="width:32px;height:32px;object-fit:cover;"><?php endif; ?></td>
<td><?= h($it['name']) ?></td>
<td class="text-end"><?= number_fmt($it['qty_on_hand']) ?></td>
<?php $key=$variantView==='grouped'?$it['sku']:$it['id']; ?>
<td class="text-end">
  <?= number_fmt($it['qty_committed']) ?>
  <?php if(!empty($jobMap[$key])): ?>
    <div class="small text-muted text-start">
      <?php foreach($jobMap[$key] as $jm): ?>
        <div><?= h($jm['job']) ?> (<?= number_fmt($jm['qty']) ?>)</div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</td>
<td>
  <a class="btn btn-sm btn-outline-secondary" href="/index.php?p=item&id=<?= $it['id'] ?>">Edit</a>
</td>
</tr><?php endforeach; ?>
</tbody></table></div></div></div>

<div class="modal fade" id="addItemModal" tabindex="-1" aria-labelledby="addItemModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title" id="addItemModalLabel">Add Item</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="form" value="create_item">
          <div class="mb-2"><label class="form-label">SKU</label><input name="sku" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">Parent SKU (optional)</label><input name="parent_sku" class="form-control" placeholder="E4531"></div>
          <div class="mb-2"><label class="form-label">Finish</label><input name="finish" class="form-control"></div>
          <div class="mb-2"><label class="form-label">Name</label><input name="name" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">Unit</label><input name="unit" class="form-control" placeholder="ea"></div>
          <div class="mb-2"><label class="form-label">Category</label><input name="category" class="form-control"></div>
          <div class="mb-2"><label class="form-label">Type</label><input name="item_type" class="form-control"></div>
          <div class="mb-2"><label class="form-label">Use</label><input name="item_use" class="form-control"></div>
          <div class="mb-2"><label class="form-label">Description</label><input name="description" class="form-control"></div>
          <div class="mb-2"><label class="form-label">Image</label><input type="file" name="image_file" class="form-control"></div>
          <div class="mb-2"><label class="form-label">Cost (USD)</label><input name="cost_usd" type="number" step="0.01" class="form-control" value="0"></div>
          <div class="mb-2"><label class="form-label">Sage ID</label><input name="sage_id" class="form-control"></div>
          <div class="mb-2"><label class="form-label">Locations (A.1.2=qty per line)</label><textarea name="locations" class="form-control" rows="3" placeholder="A.1.2=5"></textarea></div>
          <div class="mb-2"><label class="form-label">Min Qty (optional)</label><input name="min_qty" type="number" step="1" class="form-control" value="0"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

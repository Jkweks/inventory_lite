<?php
$pdo=db();
$message=null;
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_FILES['csv'])){
  $handle=fopen($_FILES['csv']['tmp_name'],'r');
  $first=true;
  while(($row=fgetcsv($handle))!==false){
    if($first){
      $first=false;
      $chk=strtolower(trim($row[0] ?? ''));
      if($chk==='part number' || $chk==='sku' || $chk==='part number or sku'){
        continue; // skip header row
      }
    }
    $row=array_pad($row,5,null);
    $sku=trim($row[0]);
    if($sku==='') continue;
    $type=trim($row[1] ?? '') ?: null;
    $category=trim($row[2] ?? '') ?: null;
    $use=trim($row[3] ?? '') ?: null;
    $description=trim($row[4] ?? '') ?: null;
    $stmt=$pdo->prepare("INSERT INTO inventory_items (sku,name,unit,category,item_type,item_use,description,qty_on_hand,qty_committed,min_qty) VALUES (?,?,?,?,?,?,?,0,0,0) ON CONFLICT (sku) DO NOTHING");
    $stmt->execute([
      $sku,
      $description,
      'ea',
      $category,
      $type,
      $use,
      $description
    ]);
  }
  fclose($handle);
  $message='Import complete';
}
?>
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h3 mb-0">Import Products</h1></div>
<?php if($message): ?><div class="alert alert-success"><?= h($message) ?></div><?php endif; ?>
<div class="card"><div class="card-body">
<form method="post" enctype="multipart/form-data">
<div class="mb-2"><label class="form-label">CSV File</label><input type="file" name="csv" class="form-control" required></div>
<button class="btn btn-primary">Import</button>
</form></div></div>

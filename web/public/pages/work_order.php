<?php
$pdo=db();
$wo_id=(int)($_GET['wo'] ?? ($_POST['wo_id'] ?? 0));
$work_order=null;
if($wo_id){
  $st=$pdo->prepare("SELECT wo.*, j.job_number, j.name, j.id AS job_id FROM work_orders wo JOIN jobs j ON j.id=wo.job_id WHERE wo.id=?");
  $st->execute([$wo_id]);
  $work_order=$st->fetch();
}
if(!$work_order){
  echo '<div class="alert alert-danger">Work order not found.</div>';
  return;
}
$job_id=(int)$work_order['job_id'];

if($_SERVER['REQUEST_METHOD']==='POST'){
  $form=$_POST['form'] ?? '';
  if($form==='commit_material'){
    $item_id=(int)$_POST['item_id']; $qty=max(0,(int)$_POST['qty_committed']);
    $pdo->beginTransaction();
    try{
      $cur=$pdo->prepare("SELECT qty_on_hand, qty_committed FROM inventory_items WHERE id=? FOR UPDATE"); $cur->execute([$item_id]);
      $item=$cur->fetch(); if(!$item) throw new Exception('Item not found');
      $on_hand_before=(int)$item['qty_on_hand']; $committed_before=(int)$item['qty_committed'];
      $available_before=$on_hand_before-$committed_before;
      $over_commit=max(0,$qty-$available_before);
      $ins=$pdo->prepare("INSERT INTO job_materials (job_id,item_id,qty_committed) VALUES (?,?,?) ON CONFLICT (job_id,item_id) DO UPDATE SET qty_committed=job_materials.qty_committed+EXCLUDED.qty_committed");
      $ins->execute([$job_id,$item_id,$qty]);
      $pdo->prepare("UPDATE inventory_items SET qty_committed=qty_committed+? WHERE id=?")->execute([$qty,$item_id]);
      $note=$over_commit>0?('Commit to WO '.$work_order['wo_number'].' (OVER by '.$over_commit.')'):'Commit to WO '.$work_order['wo_number'];
      $pdo->prepare("INSERT INTO inventory_txns (item_id,txn_type,qty_delta,ref_table,ref_id,note) VALUES (?,?,?,?,?,?)")
          ->execute([$item_id,'job_release',0,'jobs',$job_id,$note]);
      $pdo->commit();
      header("Location: /index.php?p=work_order&wo={$wo_id}".($over_commit>0?("&oc=".$over_commit):"")); exit;
    }catch(Exception $e){ $pdo->rollBack(); $err=$e->getMessage(); }
  }elseif($form==='return_material'){
    $jm_id=(int)$_POST['jm_id']; $qty=max(0,(int)$_POST['qty_return']);
    $pdo->beginTransaction();
    try{
      $cur=$pdo->prepare("SELECT jm.item_id, jm.qty_committed FROM job_materials jm WHERE jm.id=? AND jm.job_id=? FOR UPDATE");
      $cur->execute([$jm_id,$job_id]);
      $row=$cur->fetch(); if(!$row) throw new Exception('Material not found');
      $committed=(int)$row['qty_committed']; if($qty>$committed) $qty=$committed;
      $pdo->prepare("UPDATE job_materials SET qty_committed=qty_committed-? WHERE id=?")->execute([$qty,$jm_id]);
      $pdo->prepare("UPDATE inventory_items SET qty_on_hand=qty_on_hand+?, qty_committed=qty_committed-? WHERE id=?")
          ->execute([$qty,$qty,$row['item_id']]);
      if($qty>0){
        $pdo->prepare("INSERT INTO inventory_txns (item_id,txn_type,qty_delta,ref_table,ref_id,note) VALUES (?,?,?,?,?,?)")
            ->execute([$row['item_id'],'return',$qty,'jobs',$job_id,'Unused returned from WO '.$work_order['wo_number']]);
      }
      $pdo->commit(); header("Location: /index.php?p=work_order&wo={$wo_id}"); exit;
    }catch(Exception $e){ $pdo->rollBack(); $err=$e->getMessage(); }
  }elseif($form==='consume_material'){
    $jm_id=(int)$_POST['jm_id']; $qty=max(0,(int)$_POST['qty_consume']);
    $pdo->beginTransaction();
    try{
      $cur=$pdo->prepare("SELECT jm.item_id, jm.qty_committed FROM job_materials jm WHERE jm.id=? AND jm.job_id=? FOR UPDATE");
      $cur->execute([$jm_id,$job_id]);
      $row=$cur->fetch(); if(!$row) throw new Exception('Material not found');
      $committed=(int)$row['qty_committed']; if($qty>$committed) $qty=$committed;
      $pdo->prepare("UPDATE job_materials SET qty_committed=qty_committed-?, qty_used=qty_used+? WHERE id=?")
          ->execute([$qty,$qty,$jm_id]);
      $pdo->prepare("UPDATE inventory_items SET qty_on_hand=qty_on_hand-?, qty_committed=qty_committed-? WHERE id=?")
          ->execute([$qty,$qty,$row['item_id']]);
      $pdo->prepare("INSERT INTO job_consumptions (job_id,item_id,work_order_id,qty_used) VALUES (?,?,?,?)")
          ->execute([$job_id,$row['item_id'],$wo_id,$qty]);
      if($qty>0){
        $pdo->prepare("INSERT INTO inventory_txns (item_id,txn_type,qty_delta,ref_table,ref_id,note) VALUES (?,?,?,?,?,?)")
            ->execute([$row['item_id'],'job_consume',-$qty,'jobs',$job_id,'Consumed by WO '.$work_order['wo_number']]);
      }
      $pdo->commit(); header("Location: /index.php?p=work_order&wo={$wo_id}"); exit;
    }catch(Exception $e){ $pdo->rollBack(); $err=$e->getMessage(); }
  }
}

$items=$pdo->query("SELECT id, sku, name FROM inventory_items WHERE archived=false ORDER BY sku")->fetchAll();
$m=$pdo->prepare("SELECT jm.id as jm_id, i.sku, i.name, i.unit, jm.qty_committed, jm.qty_used, i.id as item_id FROM job_materials jm JOIN inventory_items i ON i.id=jm.item_id WHERE jm.job_id=? ORDER BY i.sku");
$m->execute([$job_id]); $materials=$m->fetchAll();
$c=$pdo->prepare("SELECT jc.qty_used, jc.date_used, i.sku, i.name FROM job_consumptions jc JOIN inventory_items i ON i.id=jc.item_id WHERE jc.work_order_id=? ORDER BY jc.date_used");
$c->execute([$wo_id]); $consumptions=$c->fetchAll();
?>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="card mb-3"><div class="card-body">
      <h2 class="h5 mb-0">Work Order <?= h($work_order['wo_number']) ?> <small class="text-secondary">Job <?= h($work_order['job_number']) ?></small></h2>
      <a class="btn btn-sm btn-outline-secondary mt-2" href="/index.php?p=jobs&view=<?= $job_id ?>">&laquo; Back to Job</a>
      <?php if(isset($_GET['oc']) && (int)$_GET['oc']>0): ?>
        <div class="alert alert-danger mt-3 mb-0"><strong>Over-commit:</strong> You committed <?= number_fmt((int)$_GET['oc']) ?> more than available. Item availability may be negative until stock arrives or counts are adjusted.</div>
      <?php endif; ?>
    </div></div>
    <div class="card mb-3"><div class="card-body">
      <h3 class="h6">Commit Material</h3>
      <form method="post" class="row gy-2 gx-2 align-items-end">
        <input type="hidden" name="form" value="commit_material">
        <input type="hidden" name="wo_id" value="<?= $wo_id ?>">
        <input type="hidden" name="job_id" value="<?= $job_id ?>">
        <div class="col-md-6"><label class="form-label">Item</label>
          <select name="item_id" class="form-select" required>
            <option value="">Select item…</option>
            <?php foreach($items as $it): ?><option value="<?= $it['id'] ?>"><?= h($it['sku']) ?> — <?= h($it['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3"><label class="form-label">Qty</label><input type="number" step="1" name="qty_committed" class="form-control" required></div>
        <div class="col-md-3"><button class="btn btn-success w-100">Commit</button></div>
      </form>
    </div></div>
    <div class="card"><div class="card-body">
      <h3 class="h6">Materials</h3>
      <div class="table-responsive"><table class="table table-sm table-striped align-middle">
        <thead><tr><th>SKU</th><th>Name</th><th class="text-end">Committed</th><th class="text-end">Used</th><th class="text-end">Consume</th><th class="text-end">Return</th></tr></thead>
        <tbody><?php foreach($materials as $m): ?>
          <tr>
            <td><?= h($m['sku']) ?></td><td><?= h($m['name']) ?></td>
            <td class="text-end"><?= number_fmt($m['qty_committed']) ?> <span class="text-secondary">(<?= h($m['unit']) ?>)</span></td>
            <td class="text-end"><?= number_fmt($m['qty_used']) ?></td>
            <td class="text-end">
              <form method="post" class="d-inline">
                <input type="hidden" name="form" value="consume_material">
                <input type="hidden" name="wo_id" value="<?= $wo_id ?>">
                <input type="hidden" name="job_id" value="<?= $job_id ?>">
                <input type="hidden" name="jm_id" value="<?= $m['jm_id'] ?>">
                <div class="input-group input-group-sm">
                  <input type="number" step="1" name="qty_consume" class="form-control form-control-sm" style="width:6rem">
                  <button class="btn btn-outline-primary">Use</button>
                </div>
              </form>
            </td>
            <td class="text-end">
              <form method="post" class="d-inline">
                <input type="hidden" name="form" value="return_material">
                <input type="hidden" name="wo_id" value="<?= $wo_id ?>">
                <input type="hidden" name="job_id" value="<?= $job_id ?>">
                <input type="hidden" name="jm_id" value="<?= $m['jm_id'] ?>">
                <div class="input-group input-group-sm">
                  <input type="number" step="1" name="qty_return" class="form-control form-control-sm" style="width:6rem">
                  <button class="btn btn-outline-warning">Return</button>
                </div>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div></div>
    <div class="card mt-3"><div class="card-body">
      <h3 class="h6">Consumption Log</h3>
      <div class="table-responsive"><table class="table table-sm table-striped">
        <thead><tr><th>Date</th><th>SKU</th><th class="text-end">Qty</th></tr></thead>
        <tbody><?php foreach($consumptions as $c): ?><tr>
          <td><?= datetime_fmt($c['date_used']) ?></td>
          <td><?= h($c['sku']) ?></td>
          <td class="text-end"><?= number_fmt($c['qty_used']) ?></td>
        </tr><?php endforeach; ?></tbody>
      </table></div>
    </div></div>
  </div>
</div>

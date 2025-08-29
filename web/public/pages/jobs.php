<?php
$pdo=db();
if($_SERVER['REQUEST_METHOD']==='POST' && ( $_POST['form'] ?? '' )==='create_job'){
  $stmt=$pdo->prepare("INSERT INTO jobs (job_number,name,status,date_released,notes) VALUES (?,?,?,?,?)");
  $stmt->execute([$_POST['job_number'],$_POST['name']?:null,'released',$_POST['date_released']?:null,$_POST['notes']?:null]);
  header("Location: /index.php?p=jobs&job=".urlencode($_POST['job_number'])); exit;
}
if($_SERVER['REQUEST_METHOD']==='POST' && ( $_POST['form'] ?? '' )==='commit_material'){
  $job_id=(int)$_POST['job_id']; $item_id=(int)$_POST['item_id']; $qty=max(0,(float)$_POST['qty_committed']);
  $pdo->beginTransaction();
  try{
    $cur=$pdo->prepare("SELECT qty_on_hand, qty_committed FROM inventory_items WHERE id=? FOR UPDATE"); $cur->execute([$item_id]);
    $item=$cur->fetch(); if(!$item) throw new Exception("Item not found");
    $on_hand_before=(float)$item['qty_on_hand']; $over_commit=max(0,$qty-$on_hand_before);
    $ins=$pdo->prepare("INSERT INTO job_materials (job_id,item_id,qty_committed) VALUES (?,?,?) ON CONFLICT (job_id,item_id) DO UPDATE SET qty_committed=job_materials.qty_committed+EXCLUDED.qty_committed");
    $ins->execute([$job_id,$item_id,$qty]);
    $pdo->prepare("UPDATE inventory_items SET qty_on_hand=qty_on_hand-?, qty_committed=qty_committed+? WHERE id=?")->execute([$qty,$qty,$item_id]);
    $note=$over_commit>0?('Commit to job (OVER by '.$over_commit.')'):'Commit to job';
    $pdo->prepare("INSERT INTO inventory_txns (item_id,txn_type,qty_delta,ref_table,ref_id,note) VALUES (?,?,?,?,?,?)")->execute([$item_id,'job_release',-$qty,'jobs',$job_id,$note]);
    $pdo->commit(); $redir="/index.php?p=jobs&view={$job_id}"+($over_commit>0?("&oc=".$over_commit):"")+"&ok=1";
    header("Location: ".$redir); exit;
  }catch(Exception $e){ $pdo->rollBack(); $err=$e->getMessage(); }
}
if($_SERVER['REQUEST_METHOD']==='POST' && ( $_POST['form'] ?? '' )==='complete_job'){
  $job_id=(int)$_POST['job_id'];
  $pdo->beginTransaction();
  try{
    $materials=$pdo->prepare("SELECT jm.id, jm.item_id, jm.qty_committed, jm.qty_used FROM job_materials jm WHERE jm.job_id=? FOR UPDATE"); $materials->execute([$job_id]);
    $rows=$materials->fetchAll();
    foreach($rows as $r){
      $committed=(float)$r['qty_committed']; $used=isset($_POST['used'][$r['id']])?max(0,(float)$_POST['used'][$r['id']]):0; if($used>$committed) $used=$committed;
      $returned=$committed-$used;
      $pdo->prepare("UPDATE job_materials SET qty_used=? WHERE id=?")->execute([$used,$r['id']]);
      $pdo->prepare("UPDATE inventory_items SET qty_committed=qty_committed-?, qty_on_hand=qty_on_hand+? WHERE id=?")->execute([$committed,$returned,$r['item_id']]);
      if($used>0){ $pdo->prepare("INSERT INTO inventory_txns (item_id,txn_type,qty_delta,ref_table,ref_id,note) VALUES (?,?,?,?,?,?)")->execute([$r['item_id'],'job_complete',-$used,'jobs',$job_id,'Consumed by job']); }
      if($returned>0){ $pdo->prepare("INSERT INTO inventory_txns (item_id,txn_type,qty_delta,ref_table,ref_id,note) VALUES (?,?,?,?,?,?)")->execute([$r['item_id'],'return',$returned,'jobs',$job_id,'Unused returned']); }
    }
    $pdo->prepare("UPDATE jobs SET status='completed', date_completed=CURRENT_DATE WHERE id=?")->execute([$job_id]);
    $pdo->commit(); header("Location: /index.php?p=jobs&view=".$job_id."&done=1"); exit;
  }catch(Exception $e){ $pdo->rollBack(); $err=$e->getMessage(); }
}
$items=$pdo->query("SELECT id, sku, name FROM inventory_items ORDER BY sku")->fetchAll();
$jobs=$pdo->query("SELECT * FROM jobs ORDER BY created_at DESC LIMIT 50")->fetchAll();
$view_job=null;
if(isset($_GET['view'])){
  $jid=(int)$_GET['view']; $st=$pdo->prepare("SELECT * FROM jobs WHERE id=?"); $st->execute([$jid]); $view_job=$st->fetch();
  if($view_job){
    $m=$pdo->prepare("SELECT jm.id as jm_id, i.sku, i.name, i.unit, jm.qty_committed, jm.qty_used, i.id as item_id FROM job_materials jm JOIN inventory_items i ON i.id=jm.item_id WHERE jm.job_id=? ORDER BY i.sku");
    $m->execute([$jid]); $materials=$m->fetchAll();
  }
}
?>
<div class="row g-3">
  <div class="col-lg-5">
    <div class="card mb-3"><div class="card-body">
      <h2 class="h5">Create Job</h2>
      <form method="post"><input type="hidden" name="form" value="create_job">
        <div class="mb-2"><label class="form-label">Job #</label><input name="job_number" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">Name</label><input name="name" class="form-control"></div>
        <div class="mb-2"><label class="form-label">Date Released</label><input type="date" name="date_released" class="form-control"></div>
        <div class="mb-2"><label class="form-label">Notes</label><input name="notes" class="form-control"></div>
        <button class="btn btn-primary">Save</button>
      </form>
    </div></div>
    <div class="card"><div class="card-body">
      <h2 class="h6">Recent Jobs</h2>
      <div class="table-responsive"><table class="table table-sm table-striped">
        <thead><tr><th>Job #</th><th>Name</th><th>Status</th><th>Date Released</th><th></th></tr></thead>
        <tbody><?php foreach($jobs as $j): ?><tr>
          <td><?= h($j['job_number']) ?></td><td><?= h($j['name']) ?></td>
          <td><span class="badge text-bg-<?= $j['status']==='completed'?'success':'secondary' ?>"><?= h($j['status']) ?></span></td>
          <td><?= h($j['date_released']) ?></td>
          <td><a class="btn btn-sm btn-outline-primary" href="/index.php?p=jobs&view=<?= $j['id'] ?>">Open</a></td>
        </tr><?php endforeach; ?></tbody></table></div>
    </div></div>
  </div>
  <div class="col-lg-7">
    <?php if($view_job): ?>
      <?php if(isset($_GET['oc']) && (float)$_GET['oc']>0): ?>
        <div class="alert alert-danger"><strong>Over-commit:</strong> You committed <?= number_fmt((float)$_GET['oc']) ?> more than On Hand. Item On Hand may be negative until stock arrives or counts are adjusted.</div>
      <?php endif; ?>
      <div class="card mb-3"><div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h2 class="h5 mb-0">Job <?= h($view_job['job_number']) ?> <small class="text-secondary"><?= h($view_job['name']) ?></small></h2>
          <span class="badge text-bg-<?= $view_job['status']==='completed'?'success':'secondary' ?>"><?= h($view_job['status']) ?></span>
        </div>
        <form method="post" class="row gy-2 gx-2 align-items-end">
          <input type="hidden" name="form" value="commit_material">
          <input type="hidden" name="job_id" value="<?= $view_job['id'] ?>">
          <div class="col-md-6"><label class="form-label">Item</label>
            <select name="item_id" class="form-select" required>
              <option value="">Select item…</option>
              <?php foreach($items as $it): ?><option value="<?= $it['id'] ?>"><?= h($it['sku']) ?> — <?= h($it['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3"><label class="form-label">Qty</label><input type="number" step="0.001" name="qty_committed" class="form-control" required></div>
          <div class="col-md-3"><button class="btn btn-success w-100">Commit to Job</button></div>
        </form>
        <div class="form-text mt-1">Over-committing is allowed; shortages will be highlighted on the dashboard.</div>
      </div></div>
      <div class="card"><div class="card-body">
        <h3 class="h6">Materials</h3>
        <form method="post"><input type="hidden" name="form" value="complete_job"><input type="hidden" name="job_id" value="<?= $view_job['id'] ?>">
          <div class="table-responsive"><table class="table table-sm table-striped align-middle">
            <thead><tr><th>SKU</th><th>Name</th><th class="text-end">Committed</th><th class="text-end">Used</th><th class="text-end">Return</th></tr></thead>
            <tbody><?php foreach($materials as $m): $ret=max(0,(float)$m['qty_committed']-(float)$m['qty_used']); ?>
              <tr><td><?= h($m['sku']) ?></td><td><?= h($m['name']) ?></td>
              <td class="text-end"><?= number_fmt($m['qty_committed']) ?> <span class="text-secondary">(<?= h($m['unit']) ?>)</span></td>
              <td class="text-end"><input type="number" step="0.001" class="form-control form-control-sm text-end" name="used[<?= $m['jm_id'] ?>]" value="<?= number_fmt($m['qty_used']) ?>"></td>
              <td class="text-end"><?= number_fmt($ret) ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
          <?php if($view_job['status']!=='completed'): ?><button class="btn btn-primary">Complete Job</button>
          <?php else: ?><div class="alert alert-success mt-3 mb-0">Job completed on <?= h($view_job['date_completed']) ?>.</div><?php endif; ?>
        </form>
      </div></div>
    <?php else: ?><div class="alert alert-info">Select a job from the left to view/allocate materials.</div><?php endif; ?>
  </div>
</div>

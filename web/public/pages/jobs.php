<?php
$pdo=db();
if($_SERVER['REQUEST_METHOD']==='POST' && ( $_POST['form'] ?? '' )==='create_job'){
  $stmt=$pdo->prepare("INSERT INTO jobs (job_number,name,status,date_released,notes) VALUES (?,?,?,?,?)");
  $stmt->execute([$_POST['job_number'],$_POST['name']?:null,'bid',$_POST['date_released']?:null,$_POST['notes']?:null]);
  header("Location: /index.php?p=jobs&job=".urlencode($_POST['job_number'])); exit;
}
if($_SERVER['REQUEST_METHOD']==='POST' && ( $_POST['form'] ?? '' )==='commit_material'){
  $job_id=(int)$_POST['job_id']; $item_id=(int)$_POST['item_id']; $qty=max(0,(float)$_POST['qty_committed']);
  $pdo->beginTransaction();
  try{
    $cur=$pdo->prepare("SELECT qty_on_hand, qty_committed FROM inventory_items WHERE id=? FOR UPDATE"); $cur->execute([$item_id]);
    $item=$cur->fetch(); if(!$item) throw new Exception("Item not found");
    $on_hand_before=(float)$item['qty_on_hand']; $committed_before=(float)$item['qty_committed'];
    $available_before=$on_hand_before-$committed_before;
    $over_commit=max(0,$qty-$available_before);
    $ins=$pdo->prepare("INSERT INTO job_materials (job_id,item_id,qty_committed) VALUES (?,?,?) ON CONFLICT (job_id,item_id) DO UPDATE SET qty_committed=job_materials.qty_committed+EXCLUDED.qty_committed");
    $ins->execute([$job_id,$item_id,$qty]);
    $pdo->prepare("UPDATE inventory_items SET qty_committed=qty_committed+? WHERE id=?")->execute([$qty,$item_id]);
    $note=$over_commit>0?('Commit to job (OVER by '.$over_commit.')'):'Commit to job';
    $pdo->prepare("INSERT INTO inventory_txns (item_id,txn_type,qty_delta,ref_table,ref_id,note) VALUES (?,?,?,?,?,?)")
        ->execute([$item_id,'job_release',0,'jobs',$job_id,$note]);
    $pdo->commit(); $redir="/index.php?p=jobs&view={$job_id}"+($over_commit>0?("&oc=".$over_commit):"")+"&ok=1";
    header("Location: ".$redir); exit;
  }catch(Exception $e){ $pdo->rollBack(); $err=$e->getMessage(); }
}

if($_SERVER['REQUEST_METHOD']==='POST' && ( $_POST['form'] ?? '' )==='import_materials'){
  $job_id=(int)$_POST['job_id'];
  if(isset($_FILES['xlsx']) && is_uploaded_file($_FILES['xlsx']['tmp_name'])){
    require_once __DIR__.'/../modules/job_material_import.php';
    try{
      $rows=parse_job_materials_xlsx($_FILES['xlsx']['tmp_name']);
      $imported=0;
      $report=[];
      foreach($rows as $r){
        $qty=max(0,(float)$r[0]);
        $base=trim($r[1] ?? '');
        $finish=strtoupper(trim($r[2] ?? ''));
        if($qty<=0 || $base==='') continue;
        $sku=$base.($finish!==''?('-'.$finish):'');
        $report[]="$qty x $sku";
        $pdo->beginTransaction();
        $cur=$pdo->prepare("SELECT id, qty_on_hand, qty_committed FROM inventory_items WHERE sku=? FOR UPDATE");
        $cur->execute([$sku]);
        $item=$cur->fetch();
        if(!$item){ $pdo->rollBack(); continue; }
        $item_id=(int)$item['id'];
        $on_hand_before=(float)$item['qty_on_hand'];
        $committed_before=(float)$item['qty_committed'];
        $available_before=$on_hand_before-$committed_before;
        $over_commit=max(0,$qty-$available_before);
        $pdo->prepare("INSERT INTO job_materials (job_id,item_id,qty_committed) VALUES (?,?,?) ON CONFLICT (job_id,item_id) DO UPDATE SET qty_committed=job_materials.qty_committed+EXCLUDED.qty_committed")->execute([$job_id,$item_id,$qty]);
        $pdo->prepare("UPDATE inventory_items SET qty_committed=qty_committed+? WHERE id=?")->execute([$qty,$item_id]);
        $note=$over_commit>0?('Commit to job (OVER by '.$over_commit.')'):'Commit to job';
        $pdo->prepare("INSERT INTO inventory_txns (item_id,txn_type,qty_delta,ref_table,ref_id,note) VALUES (?,?,?,?,?,?)")->execute([$item_id,'job_release',0,'jobs',$job_id,$note]);
        $pdo->commit();
        $imported++;
      }
      if(PHP_SAPI==='cli'){
        foreach($report as $line){
          fwrite(STDOUT,$line.PHP_EOL);
        }
      }
      if($imported===0){
        throw new Exception('No materials were imported');
      }
      header("Location: /index.php?p=jobs&view=".$job_id."&imp=1"); exit;
    }catch(Exception $e){ $err=$e->getMessage(); }
  }
}

if($_SERVER['REQUEST_METHOD']==='POST' && ( $_POST['form'] ?? '' )==='return_material'){
  $job_id=(int)$_POST['job_id']; $jm_id=(int)$_POST['jm_id']; $qty=max(0,(float)$_POST['qty_return']);
  $pdo->beginTransaction();
  try{
    $cur=$pdo->prepare("SELECT jm.item_id, jm.qty_committed FROM job_materials jm WHERE jm.id=? AND jm.job_id=? FOR UPDATE");
    $cur->execute([$jm_id,$job_id]);
    $row=$cur->fetch(); if(!$row) throw new Exception("Material not found");
    $committed=(float)$row['qty_committed'];
    if($qty>$committed) $qty=$committed;
    $pdo->prepare("UPDATE job_materials SET qty_committed=qty_committed-? WHERE id=?")->execute([$qty,$jm_id]);
    $pdo->prepare("UPDATE inventory_items SET qty_on_hand=qty_on_hand+?, qty_committed=qty_committed-? WHERE id=?")
        ->execute([$qty,$qty,$row['item_id']]);
    if($qty>0){
      $pdo->prepare("INSERT INTO inventory_txns (item_id,txn_type,qty_delta,ref_table,ref_id,note) VALUES (?,?,?,?,?,?)")
          ->execute([$row['item_id'],'return',$qty,'jobs',$job_id,'Unused returned']);
    }
    $pdo->commit(); header("Location: /index.php?p=jobs&view=".$job_id); exit;
  }catch(Exception $e){ $pdo->rollBack(); $err=$e->getMessage(); }
}

if($_SERVER['REQUEST_METHOD']==='POST' && ( $_POST['form'] ?? '' )==='update_status'){
  $job_id=(int)$_POST['job_id'];
  $new_status=$_POST['status'] ?? 'bid';
  if(!in_array($new_status,['bid','active'])) $new_status='bid';
  $stmt=$pdo->prepare("UPDATE jobs SET status=? WHERE id=?");
  $stmt->execute([$new_status,$job_id]);
  header("Location: /index.php?p=jobs&view=".$job_id); exit;
}
if($_SERVER['REQUEST_METHOD']==='POST' && ( $_POST['form'] ?? '' )==='archive_job'){
  $job_id=(int)$_POST['job_id'];
  $archived=isset($_POST['archived'])?1:0;
  $stmt=$pdo->prepare("UPDATE jobs SET archived=? WHERE id=?");
  $stmt->execute([$archived,$job_id]);
  $redir="/index.php?p=jobs".($archived?"&show_archived=1":"");
  header("Location: $redir&view=".$job_id); exit;
}
if($_SERVER['REQUEST_METHOD']==='POST' && ( $_POST['form'] ?? '' )==='complete_job'){
  $job_id=(int)$_POST['job_id'];
  $pdo->beginTransaction();
  try{
    $materials=$pdo->prepare("SELECT jm.id, jm.item_id, jm.qty_committed, jm.qty_used FROM job_materials jm WHERE jm.job_id=? FOR UPDATE");
    $materials->execute([$job_id]);
    $rows=$materials->fetchAll();
    foreach($rows as $r){
      $committed=(float)$r['qty_committed'];
      $used=isset($_POST['used'][$r['id']])?max(0,(float)$_POST['used'][$r['id']]):0;
      if($used!=$committed){ throw new Exception('All items must be returned before completing job'); }
      $pdo->prepare("UPDATE job_materials SET qty_used=qty_used+?, qty_committed=qty_committed-? WHERE id=?")
          ->execute([$used,$used,$r['id']]);
      $pdo->prepare("UPDATE inventory_items SET qty_on_hand=qty_on_hand-?, qty_committed=qty_committed-? WHERE id=?")
          ->execute([$used,$used,$r['item_id']]);
      if($used>0){
        $pdo->prepare("INSERT INTO inventory_txns (item_id,txn_type,qty_delta,ref_table,ref_id,note) VALUES (?,?,?,?,?,?)")
            ->execute([$r['item_id'],'job_complete',-$used,'jobs',$job_id,'Consumed by job']);
      }
    }
    $pdo->prepare("UPDATE jobs SET status='complete', date_completed=CURRENT_DATE WHERE id=?")
        ->execute([$job_id]);
    $pdo->commit();
    header("Location: /index.php?p=jobs&view=".$job_id."&done=1"); exit;
  }catch(Exception $e){ $pdo->rollBack(); $err=$e->getMessage(); }
}
$items=$pdo->query("SELECT id, sku, name FROM inventory_items WHERE archived=false ORDER BY sku")->fetchAll();
$show_archived=isset($_GET['show_archived']);
$job_stmt=$pdo->prepare("SELECT * FROM jobs".($show_archived?"":" WHERE archived=false")." ORDER BY created_at DESC LIMIT 50");
$job_stmt->execute();
$jobs=$job_stmt->fetchAll();
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
      <form method="get" class="form-check mb-2">
        <input type="hidden" name="p" value="jobs">
        <input class="form-check-input" type="checkbox" id="show_archived" name="show_archived" value="1" <?= $show_archived?'checked':'' ?> onchange="this.form.submit()">
        <label class="form-check-label" for="show_archived">Show Archived</label>
      </form>
      <div class="table-responsive"><table class="table table-sm table-striped">
        <thead><tr><th>Job #</th><th>Name</th><th>Status</th><th>Date Released</th><th></th></tr></thead>
        <tbody><?php foreach($jobs as $j): ?><tr>
          <td><?= h($j['job_number']) ?></td><td><?= h($j['name']) ?></td>
          <td><span class="badge text-bg-<?= $j['status']==='complete'?'success':'secondary' ?>"><?= h($j['status']) ?></span></td>
          <td><?= h($j['date_released']) ?></td>
          <td><a class="btn btn-sm btn-outline-primary" href="/index.php?p=jobs&view=<?= $j['id'] ?>">Open</a></td>
        </tr><?php endforeach; ?></tbody></table></div>
    </div></div>
  </div>
  <div class="col-lg-7">
    <?php if($view_job): ?>
      <?php if(isset($_GET['oc']) && (float)$_GET['oc']>0): ?>
        <div class="alert alert-danger"><strong>Over-commit:</strong> You committed <?= number_fmt((float)$_GET['oc']) ?> more than Available. Item availability may be negative until stock arrives or counts are adjusted.</div>
      <?php endif; ?>
      <?php if(isset($_GET['imp'])): ?>
        <div class="alert alert-success">Material import complete.</div>
      <?php endif; ?>
      <div class="card mb-3"><div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h2 class="h5 mb-0">Job <?= h($view_job['job_number']) ?> <small class="text-secondary"><?= h($view_job['name']) ?></small></h2>
          <div>
            <form method="post" class="d-inline">
              <input type="hidden" name="form" value="update_status">
              <input type="hidden" name="job_id" value="<?= $view_job['id'] ?>">
              <select name="status" class="form-select form-select-sm d-inline w-auto" onchange="this.form.submit()" <?= $view_job['status']==='complete'?'disabled':'' ?>>
                <option value="bid" <?= $view_job['status']==='bid'?'selected':'' ?>>bid</option>
                <option value="active" <?= $view_job['status']==='active'?'selected':'' ?>>active</option>
              </select>
              </form>
            <span class="badge text-bg-<?= $view_job['status']==='complete'?'success':'secondary' ?> ms-1"><?= h($view_job['status']) ?></span>
            <?php if(!$view_job['archived']): ?>
              <form method="post" class="d-inline ms-2">
                <input type="hidden" name="form" value="archive_job">
                <input type="hidden" name="job_id" value="<?= $view_job['id'] ?>">
                <input type="hidden" name="archived" value="1">
                <button class="btn btn-outline-secondary btn-sm">Archive</button>
              </form>
            <?php else: ?>
              <form method="post" class="d-inline ms-2">
                <input type="hidden" name="form" value="archive_job">
                <input type="hidden" name="job_id" value="<?= $view_job['id'] ?>">
                <input type="hidden" name="archived" value="0">
                <button class="btn btn-outline-secondary btn-sm">Unarchive</button>
              </form>
            <?php endif; ?>
          </div>
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
        <form method="post" enctype="multipart/form-data" class="row gy-2 gx-2 align-items-end mt-3">
          <input type="hidden" name="form" value="import_materials">
          <input type="hidden" name="job_id" value="<?= $view_job['id'] ?>">
          <div class="col-md-6"><label class="form-label">Import Spreadsheet</label><input type="file" name="xlsx" accept=".xlsx,.xlsm" class="form-control" required></div>
          <div class="col-md-3"><label class="form-label">&nbsp;</label><button class="btn btn-outline-primary w-100">Import</button></div>
        </form>
        <div class="form-text mt-1">Over-committing is allowed; shortages will be highlighted on the dashboard.</div>
      </div></div>
      <div class="card"><div class="card-body">
        <h3 class="h6">Materials</h3>
        <form id="completeForm" method="post">
          <input type="hidden" name="form" value="complete_job">
          <input type="hidden" name="job_id" value="<?= $view_job['id'] ?>">
        </form>
        <div class="table-responsive"><table class="table table-sm table-striped align-middle">
          <thead><tr><th>SKU</th><th>Name</th><th class="text-end">Committed</th><th class="text-end">Used</th><th class="text-end">Return</th></tr></thead>
          <tbody><?php foreach($materials as $m): ?>
            <tr>
              <td><?= h($m['sku']) ?></td><td><?= h($m['name']) ?></td>
              <td class="text-end"><?= number_fmt($m['qty_committed']) ?> <span class="text-secondary">(<?= h($m['unit']) ?>)</span></td>
              <td class="text-end"><input type="number" step="0.001" class="form-control form-control-sm text-end" name="used[<?= $m['jm_id'] ?>]" value="<?= number_fmt($m['qty_committed']) ?>" form="completeForm"></td>
              <td class="text-end">
                <?php if($view_job['status']!=='complete'): ?>
                  <form method="post" class="d-inline">
                    <input type="hidden" name="form" value="return_material">
                    <input type="hidden" name="job_id" value="<?= $view_job['id'] ?>">
                    <input type="hidden" name="jm_id" value="<?= $m['jm_id'] ?>">
                    <div class="input-group input-group-sm">
                      <input type="number" step="0.001" name="qty_return" class="form-control form-control-sm" style="width:6rem">
                      <button class="btn btn-outline-warning">Return</button>
                    </div>
                  </form>
                <?php else: ?>-
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
        <?php if($view_job['status']!=='complete'): ?>
          <button class="btn btn-primary" form="completeForm">Complete Job</button>
        <?php else: ?>
          <div class="alert alert-success mt-3 mb-0">Job completed on <?= h($view_job['date_completed']) ?>.</div>
        <?php endif; ?>
      </div></div>
    <?php else: ?><div class="alert alert-info">Select a job from the left to view/allocate materials.</div><?php endif; ?>
  </div>
</div>

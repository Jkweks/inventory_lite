<?php
$pdo=db();
$mods=$pdo->query("SELECT value FROM settings WHERE key='dashboard_modules'")->fetchColumn();
$mods=$mods?array_filter(explode(',', $mods)):['parts_list'];
?>
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h3 mb-0">Dashboard</h1>
<div><a href="/index.php?p=items" class="btn btn-primary btn-sm">New Item</a>
<a href="/index.php?p=jobs" class="btn btn-outline-secondary btn-sm">Jobs</a>
<a href="/index.php?p=cycle_counts" class="btn btn-outline-secondary btn-sm">Cycle Counts</a></div></div>
<?php
foreach($mods as $m){
  $file=__DIR__.'/../modules/'.basename($m).'.php';
  if(is_file($file)) include $file;
}
?>

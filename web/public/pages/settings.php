<?php
$pdo=db();
$saved=false;
$freq=$pdo->query("SELECT value FROM settings WHERE key='cycle_count_frequency'")->fetchColumn();
$display=$pdo->query("SELECT value FROM settings WHERE key='dashboard_display'")->fetchColumn() ?: 'grouped';
$modulesVal=$pdo->query("SELECT value FROM settings WHERE key='dashboard_modules'")->fetchColumn();
$modules=$modulesVal?explode(',', $modulesVal):['parts_list'];
if($_SERVER['REQUEST_METHOD']==='POST'){
  $pdo->prepare("INSERT INTO settings (key,value) VALUES ('cycle_count_frequency',?) ON CONFLICT (key) DO UPDATE SET value=excluded.value")->execute([$_POST['frequency']]);
  $pdo->prepare("INSERT INTO settings (key,value) VALUES ('dashboard_display',?) ON CONFLICT (key) DO UPDATE SET value=excluded.value")->execute([$_POST['dashboard_display']]);
  $pdo->prepare("INSERT INTO settings (key,value) VALUES ('dashboard_modules',?) ON CONFLICT (key) DO UPDATE SET value=excluded.value")->execute([implode(',', $_POST['modules'] ?? [])]);
  $freq=$_POST['frequency'];
  $display=$_POST['dashboard_display'];
  $modules=$_POST['modules'] ?? [];
  $saved=true;
}
$options=['weekly','bi-weekly','monthly','quarterly','semi-annual','annual'];
$availableModules=[
  'parts_list'=>'Parts List',
  'low_stock'=>'Low Stock Parts',
  'counts_due'=>'Counts Due'
];
?>
<h1 class="h3 mb-3">Settings</h1>
<?php if($saved): ?><div class="alert alert-success">Saved</div><?php endif; ?>
<form method="post">
<div class="mb-3"><label class="form-label">Cycle Count Frequency</label>
<select name="frequency" class="form-select" required>
<?php foreach($options as $o): ?><option value="<?= $o ?>" <?= $freq===$o?'selected':'' ?>><?= ucfirst($o) ?></option><?php endforeach; ?>
</select></div>

<div class="mb-3"><label class="form-label">Dashboard Parts Display</label>
<select name="dashboard_display" class="form-select">
<option value="grouped" <?= $display==='grouped'?'selected':'' ?>>Grouped</option>
<option value="table" <?= $display==='table'?'selected':'' ?>>Table</option>
</select></div>

<div class="mb-3"><label class="form-label">Dashboard Modules</label>
<?php foreach($availableModules as $key=>$label): ?>
<div class="form-check">
  <input class="form-check-input" type="checkbox" name="modules[]" value="<?= $key ?>" id="mod-<?= $key ?>" <?= in_array($key,$modules)?'checked':'' ?>>
  <label class="form-check-label" for="mod-<?= $key ?>"><?= $label ?></label>
</div>
<?php endforeach; ?>
</div>

<button class="btn btn-primary">Save</button>
</form>

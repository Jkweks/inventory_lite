<?php
$pdo=db();
$saved=false;
$freq=$pdo->query("SELECT value FROM settings WHERE key='cycle_count_frequency'")->fetchColumn();
if($_SERVER['REQUEST_METHOD']==='POST'){
  $pdo->prepare("INSERT INTO settings (key,value) VALUES ('cycle_count_frequency',?) ON CONFLICT (key) DO UPDATE SET value=excluded.value")->execute([$_POST['frequency']]);
  $freq=$_POST['frequency'];
  $saved=true;
}
$options=['weekly','bi-weekly','monthly','quarterly','semi-annual','annual'];
?>
<h1 class="h3 mb-3">Settings</h1>
<?php if($saved): ?><div class="alert alert-success">Saved</div><?php endif; ?>
<form method="post">
<div class="mb-3"><label class="form-label">Cycle Count Frequency</label>
<select name="frequency" class="form-select" required>
<?php foreach($options as $o): ?><option value="<?= $o ?>" <?= $freq===$o?'selected':'' ?>><?= ucfirst($o) ?></option><?php endforeach; ?>
</select></div>
<button class="btn btn-primary">Save</button>
</form>

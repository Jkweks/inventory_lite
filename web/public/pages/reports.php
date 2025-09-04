<?php
$pdo = db();
$type = $_GET['type'] ?? 'full';
$valid = ['full','low','accounting','cost_savings'];
if (!in_array($type, $valid, true)) {
    $type = 'full';
}

switch ($type) {
    case 'low':
        $title = 'Low Inventory';
        $tableId = 'lowTable';
        $items = $pdo->query("SELECT base_sku AS sku, MIN(name) AS name, MIN(min_qty) AS min_qty, SUM(qty_on_hand-qty_committed) AS available FROM (SELECT COALESCE(parent_sku,sku) AS base_sku, name, min_qty, qty_on_hand, qty_committed FROM inventory_items WHERE archived=false) t GROUP BY base_sku HAVING SUM(qty_on_hand-qty_committed) < MIN(min_qty) ORDER BY available ASC")->fetchAll();
        break;
    case 'accounting':
        $title = 'Inventory for Accounting';
        $tableId = 'acctTable';
        $items = $pdo->query("SELECT base_sku AS sku, MIN(name) AS name, MIN(unit) AS unit, MIN(cost_usd) AS cost_usd, SUM(qty_on_hand) AS qty_on_hand, SUM(cost_usd*qty_on_hand) AS total FROM (SELECT COALESCE(parent_sku,sku) AS base_sku, name, unit, cost_usd, qty_on_hand FROM inventory_items WHERE archived=false) t GROUP BY base_sku ORDER BY base_sku")->fetchAll();
        break;
    case 'cost_savings':
        $title = 'Cost Savings';
        $tableId = null;
        $items = [];
        break;
    default:
        $title = 'Full Inventory';
        $tableId = 'fullTable';
        $items = $pdo->query("SELECT base_sku AS sku, MIN(name) AS name, MIN(unit) AS unit, MIN(category) AS category, MIN(item_type) AS item_type, SUM(qty_on_hand) AS qty_on_hand, SUM(qty_committed) AS qty_committed, SUM(qty_on_hand-qty_committed) AS available FROM (SELECT COALESCE(parent_sku,sku) AS base_sku, name, unit, category, item_type, qty_on_hand, qty_committed FROM inventory_items WHERE archived=false) t GROUP BY base_sku ORDER BY MIN(category), MIN(item_type), base_sku")->fetchAll();
        break;
}
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h3 mb-0">Reports</h1>
  <?php if($tableId): ?>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-primary btn-sm" href="/export_csv.php?report=<?= h($type) ?>">Export CSV</a>
    <button class="btn btn-primary btn-sm" onclick="exportTableToPDF('<?= h($tableId) ?>','<?= h($title) ?>')">Export PDF</button>
  </div>
  <?php endif; ?>
</div>
<ul class="nav nav-pills mb-3">
  <li class="nav-item"><a class="nav-link<?= $type==='full'?' active':'' ?>" href="?p=reports&type=full">Full Inventory</a></li>
  <li class="nav-item"><a class="nav-link<?= $type==='low'?' active':'' ?>" href="?p=reports&type=low">Low Inventory</a></li>
  <li class="nav-item"><a class="nav-link<?= $type==='accounting'?' active':'' ?>" href="?p=reports&type=accounting">Accounting</a></li>
  <li class="nav-item"><a class="nav-link<?= $type==='cost_savings'?' active':'' ?>" href="?p=reports&type=cost_savings">Cost Savings</a></li>
</ul>

<?php if($type==='full'): ?>
<div class="card"><div class="card-body">
  <h2 class="h6">Full Inventory</h2>
  <div class="table-responsive"><table id="<?= h($tableId) ?>" class="table table-striped table-hover">
    <thead><tr><th>Category</th><th>Type</th><th>SKU</th><th>Name</th><th>Unit</th><th class="text-end">On Hand</th><th class="text-end">Committed</th><th class="text-end">Available</th></tr></thead>
    <tbody><?php $curCat=null; $curType=null; foreach($items as $it): ?>
    <?php if($it['category']!==$curCat){ $curCat=$it['category']; $curType=null; ?><tr class="table-secondary"><th colspan="8"><?= h($curCat) ?></th></tr><?php } ?>
    <?php if($it['item_type']!==$curType){ $curType=$it['item_type']; ?><tr class="table-light"><th colspan="8" class="ps-4"><?= h($curType) ?></th></tr><?php } ?>
    <tr>
      <td><?= h($it['category']) ?></td>
      <td><?= h($it['item_type']) ?></td>
      <td><?= h($it['sku']) ?></td>
      <td><?= h($it['name']) ?></td>
      <td><?= h($it['unit']) ?></td>
      <td class="text-end"><?= number_fmt($it['qty_on_hand']) ?></td>
      <td class="text-end"><?= number_fmt($it['qty_committed']) ?></td>
      <td class="text-end"><?= number_fmt($it['available']) ?></td>
    </tr><?php endforeach; ?></tbody></table></div>
</div></div>
<?php elseif($type==='low'): ?>
<div class="card"><div class="card-body">
  <h2 class="h6">Low Inventory</h2>
  <div class="table-responsive"><table id="<?= h($tableId) ?>" class="table table-striped table-hover">
    <thead><tr><th>SKU</th><th>Name</th><th class="text-end">Min Qty</th><th class="text-end">Available</th></tr></thead>
    <tbody><?php foreach($items as $it): ?>
    <tr>
      <td><?= h($it['sku']) ?></td>
      <td><?= h($it['name']) ?></td>
      <td class="text-end"><?= number_fmt($it['min_qty']) ?></td>
      <td class="text-end"><?= number_fmt($it['available']) ?></td>
    </tr><?php endforeach; ?></tbody></table></div>
</div></div>
<?php elseif($type==='accounting'): ?>
<div class="card"><div class="card-body">
  <h2 class="h6">Inventory for Accounting</h2>
  <div class="table-responsive"><table id="<?= h($tableId) ?>" class="table table-striped table-hover">
    <thead><tr><th>SKU</th><th>Name</th><th>Unit</th><th class="text-end">Cost</th><th class="text-end">On Hand</th><th class="text-end">Value</th></tr></thead>
    <tbody><?php foreach($items as $it): ?>
    <tr>
      <td><?= h($it['sku']) ?></td>
      <td><?= h($it['name']) ?></td>
      <td><?= h($it['unit']) ?></td>
        <td class="text-end">$<?= money_fmt($it['cost_usd']) ?></td>
        <td class="text-end"><?= number_fmt($it['qty_on_hand']) ?></td>
        <td class="text-end">$<?= money_fmt($it['total']) ?></td>
    </tr><?php endforeach; ?></tbody></table></div>
</div></div>
<?php else: ?>
<div class="card"><div class="card-body">
  <h2 class="h6">Cost Savings</h2>
  <p class="mb-0 text-secondary">This report is under development.</p>
</div></div>
<?php endif; ?>


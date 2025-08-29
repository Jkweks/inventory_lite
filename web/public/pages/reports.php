<?php
$pdo=db();
$items=$pdo->query("SELECT sku,name,unit,qty_on_hand,qty_committed,(qty_on_hand-qty_committed) AS available FROM inventory_items ORDER BY sku")->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h3 mb-0">Reports</h1>
<div class="d-flex gap-2"><a class="btn btn-outline-primary btn-sm" href="/export_csv.php?report=snapshot">Export CSV</a>
<button class="btn btn-primary btn-sm" onclick="exportTableToPDF('snapshotTable','Inventory Snapshot')">Export PDF</button></div></div>
<div class="card"><div class="card-body"><h2 class="h6">Inventory Snapshot</h2>
<div class="table-responsive"><table id="snapshotTable" class="table table-striped table-hover">
<thead><tr><th>SKU</th><th>Name</th><th>Unit</th><th class="text-end">On Hand</th><th class="text-end">Committed</th><th class="text-end">Available</th></tr></thead>
<tbody><?php foreach($items as $it): ?><tr>
<td><?= h($it['sku']) ?></td><td><?= h($it['name']) ?></td><td><?= h($it['unit']) ?></td>
<td class="text-end"><?= number_fmt($it['qty_on_hand']) ?></td><td class="text-end"><?= number_fmt($it['qty_committed']) ?></td><td class="text-end"><?= number_fmt($it['available']) ?></td>
</tr><?php endforeach; ?></tbody></table></div></div></div>

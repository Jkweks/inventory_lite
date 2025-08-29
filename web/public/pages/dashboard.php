<?php
$pdo=db();
$items=$pdo->query("SELECT id, sku, name, unit, qty_on_hand, qty_committed, (qty_on_hand - qty_committed) AS available FROM inventory_items ORDER BY sku")->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h3 mb-0">Dashboard</h1>
<div><a href="/index.php?p=items" class="btn btn-primary btn-sm">New Item</a>
<a href="/index.php?p=jobs" class="btn btn-outline-secondary btn-sm">Jobs</a>
<a href="/index.php?p=cycle_counts" class="btn btn-outline-secondary btn-sm">Cycle Counts</a></div></div>
<div class="card"><div class="card-body"><div class="table-responsive"><table class="table table-striped table-hover align-middle">
<thead><tr><th>SKU</th><th>Name</th><th class="text-end">On Hand</th><th class="text-end">Committed</th><th class="text-end">Available</th><th>Status</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach($items as $it): $short_onhand=((float)$it['qty_on_hand'])<0; $short_avail=((float)$it['available'])<0; ?>
<tr class="<?= ($short_onhand||$short_avail)?'table-danger':'' ?>">
<td><?= h($it['sku']) ?></td>
<td><?= h($it['name']) ?> <span class="text-secondary">(<?= h($it['unit']) ?>)</span></td>
<td class="text-end"><?= number_fmt($it['qty_on_hand']) ?></td>
<td class="text-end"><?= number_fmt($it['qty_committed']) ?></td>
<td class="text-end"><?= number_fmt($it['available']) ?></td>
<td><?php if($short_onhand): ?><span class="badge badge-short">NEG On Hand</span><?php endif; ?><?php if($short_avail): ?><span class="badge text-bg-danger">Over-committed</span><?php endif; ?></td>
<td><a class="btn btn-sm btn-outline-primary" href="/index.php?p=cycle_counts&item_id=<?= $it['id'] ?>">Count</a>
<a class="btn btn-sm btn-outline-success" href="/index.php?p=jobs&action=add_material&item_id=<?= $it['id'] ?>">Commit</a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div></div></div>

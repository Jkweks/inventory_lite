<?php
$items=$pdo->query("SELECT base_sku AS sku, MIN(name) AS name, MIN(min_qty) AS min_qty, SUM(qty_on_hand-qty_committed) AS available FROM (SELECT COALESCE(parent_sku,sku) AS base_sku, name, min_qty, qty_on_hand, qty_committed FROM inventory_items WHERE archived=false) t GROUP BY base_sku HAVING MIN(min_qty) IS NOT NULL AND SUM(qty_on_hand-qty_committed) < MIN(min_qty) ORDER BY available ASC LIMIT 10")->fetchAll();
?>
<div class="card mb-3"><div class="card-body">
<h2 class="h5 mb-3">Low Stock Parts</h2>
<?php if($items): ?>
<ul class="list-group list-group-flush">
<?php foreach($items as $it): ?>
<li class="list-group-item d-flex justify-content-between align-items-center">
<span><?= h($it['sku']).' - '.h($it['name']) ?></span>
<span class="badge text-bg-danger"><?= number_fmt($it['available']) ?>/<?= number_fmt($it['min_qty']) ?></span>
</li>
<?php endforeach; ?>
</ul>
<?php else: ?>
<p class="mb-0 text-secondary">No low stock parts.</p>
<?php endif; ?>
</div></div>

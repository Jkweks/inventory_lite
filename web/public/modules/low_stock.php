<?php
$items=$pdo->query("SELECT id, sku, name, (qty_on_hand - qty_committed) AS available, min_qty FROM inventory_items WHERE archived=false AND min_qty IS NOT NULL AND (qty_on_hand - qty_committed) < min_qty ORDER BY available ASC LIMIT 10")->fetchAll();
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

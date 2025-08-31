<?php
$pdo=db();
$items=$pdo->query("SELECT i.category,i.item_type,i.sku,i.name,i.image_url,l.location FROM inventory_items i LEFT JOIN item_locations l ON l.item_id=i.id WHERE i.archived=false ORDER BY i.category,i.item_type,i.sku,l.location")->fetchAll();
?>
<h1 class="h3 mb-3">Cycle Count Worksheet</h1>
<div class="table-responsive"><table class="table table-bordered">
<thead><tr><th>Category</th><th>Type</th><th>SKU</th><th>Img</th><th>Name</th><th>Location</th><th>Count</th></tr></thead>
<tbody><?php foreach($items as $it): ?><tr>
<td><?= h($it['category']) ?></td><td><?= h($it['item_type']) ?></td><td><?= h($it['sku']) ?></td><td><?php if($it['image_url']): ?><img src="<?= h($it['image_url']) ?>" alt="" style="width:32px;height:32px;object-fit:cover;"><?php endif; ?></td><td><?= h($it['name']) ?></td><td><?= h($it['location']) ?></td><td></td>
</tr><?php endforeach; ?></tbody></table></div>

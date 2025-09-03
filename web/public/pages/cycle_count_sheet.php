<?php
$pdo=db();
$items=$pdo->query("SELECT i.sku,i.name,l.location FROM inventory_items i LEFT JOIN item_locations l ON l.item_id=i.id WHERE i.archived=false AND i.sku NOT IN (SELECT parent_sku FROM inventory_items WHERE parent_sku IS NOT NULL) ORDER BY i.sku,l.location")->fetchAll();
?>
<h1 class="h3 mb-3">Cycle Count Worksheet</h1>
<div class="mb-3">
  <button class="btn btn-outline-secondary btn-sm" onclick="exportTableToPDF('cycle-count-table','Cycle Count Worksheet')">Export PDF</button>
</div>
<div class="table-responsive"><table id="cycle-count-table" class="table table-bordered">
<thead><tr><th>SKU</th><th>Name</th><th>Location</th><th>Count</th></tr></thead>
<tbody><?php foreach($items as $it): ?><tr>
<td><?= h($it['sku']) ?></td><td><?= h($it['name']) ?></td><td><?= h($it['location']) ?></td><td></td>
</tr><?php endforeach; ?></tbody></table></div>

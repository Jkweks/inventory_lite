<?php
$display=$pdo->query("SELECT value FROM settings WHERE key='dashboard_display'")->fetchColumn() ?: 'grouped';
$variantView=$pdo->query("SELECT value FROM settings WHERE key='variant_view'")->fetchColumn() ?: 'individual';
if($variantView==='grouped'){
  $items=$pdo->query("SELECT MIN(id) AS id, COALESCE(parent_sku,sku) AS sku, MIN(name) AS name, MIN(unit) AS unit, MIN(category) AS category, MIN(item_type) AS item_type, MIN(image_url) AS image_url, SUM(qty_on_hand) AS qty_on_hand, SUM(qty_committed) AS qty_committed, SUM(qty_on_hand-qty_committed) AS available FROM inventory_items WHERE archived=false GROUP BY COALESCE(parent_sku,sku) ORDER BY MIN(category), MIN(item_type), sku")->fetchAll();
}else{
  $items=$pdo->query("SELECT id, sku, name, unit, category, item_type, image_url, qty_on_hand, qty_committed, (qty_on_hand - qty_committed) AS available FROM inventory_items WHERE archived=false AND sku NOT IN (SELECT parent_sku FROM inventory_items WHERE parent_sku IS NOT NULL) ORDER BY category, item_type, sku")->fetchAll();
}
?>
<div class="card mb-3"><div class="card-body">
<h2 class="h5 mb-3">Parts List</h2>
<?php if($display==='table'): ?>
<div class="table-responsive"><table class="table table-striped table-hover align-middle">
<thead><tr><th>Category</th><th>Type</th><th>SKU</th><th>Name</th><th class="text-end">On Hand</th><th class="text-end">Committed</th><th class="text-end">Available</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach($items as $it): $short_onhand=((int)$it['qty_on_hand'])<0; $short_avail=((int)$it['available'])<0; ?>
<tr class="<?= ($short_onhand||$short_avail)?'table-danger':'' ?>">
<td><?= h($it['category']) ?></td>
<td><?= h($it['item_type']) ?></td>
<td><?= h($it['sku']) ?></td>
<td><?php if($it['image_url']): ?><img src="<?= h($it['image_url']) ?>" alt="" style="width:32px;height:32px;object-fit:cover;" class="me-1"><?php endif; ?><?= h($it['name']) ?> <span class="text-secondary">(<?= h($it['unit']) ?>)</span></td>
<td class="text-end"><?= number_fmt($it['qty_on_hand']) ?></td>
<td class="text-end"><?= number_fmt($it['qty_committed']) ?></td>
<td class="text-end"><?= number_fmt($it['available']) ?></td>
<td><a class="btn btn-sm btn-outline-secondary" href="/index.php?p=item&id=<?= $it['id'] ?>">Edit</a>
<a class="btn btn-sm btn-outline-primary" href="/index.php?p=cycle_counts&item_id=<?= $it['id'] ?>">Count</a>
<a class="btn btn-sm btn-outline-success" href="/index.php?p=jobs&action=add_material&item_id=<?= $it['id'] ?>">Commit</a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php else: ?>
<div class="table-responsive"><table class="table table-striped table-hover align-middle">
<thead><tr><th>SKU</th><th>Name</th><th class="text-end">On Hand</th><th class="text-end">Committed</th><th class="text-end">Available</th><th>Status</th><th>Actions</th></tr></thead>
<tbody>
<?php $curCat=null; $curType=null; foreach($items as $it): $short_onhand=((int)$it['qty_on_hand'])<0; $short_avail=((int)$it['available'])<0; ?>
<?php if($it['category']!==$curCat){ $curCat=$it['category']; $curType=null; ?>
<tr class="table-secondary"><th colspan="7"><?= h($curCat) ?></th></tr>
<?php } ?>
<?php if($it['item_type']!==$curType){ $curType=$it['item_type']; ?>
<tr class="table-light"><th colspan="7" class="ps-4"><?= h($curType) ?></th></tr>
<?php } ?>
<tr class="<?= ($short_onhand||$short_avail)?'table-danger':'' ?>">
<td><?= h($it['sku']) ?></td>
<td><?php if($it['image_url']): ?><img src="<?= h($it['image_url']) ?>" alt="" style="width:32px;height:32px;object-fit:cover;" class="me-1"><?php endif; ?><?= h($it['name']) ?> <span class="text-secondary">(<?= h($it['unit']) ?>)</span></td>
<td class="text-end"><?= number_fmt($it['qty_on_hand']) ?></td>
<td class="text-end"><?= number_fmt($it['qty_committed']) ?></td>
<td class="text-end"><?= number_fmt($it['available']) ?></td>
<td><?php if($short_onhand): ?><span class="badge badge-short">NEG On Hand</span><?php endif; ?><?php if($short_avail): ?><span class="badge text-bg-danger">Over-committed</span><?php endif; ?></td>
<td><a class="btn btn-sm btn-outline-secondary" href="/index.php?p=item&id=<?= $it['id'] ?>">Edit</a>
<a class="btn btn-sm btn-outline-primary" href="/index.php?p=cycle_counts&item_id=<?= $it['id'] ?>">Count</a>
<a class="btn btn-sm btn-outline-success" href="/index.php?p=jobs&action=add_material&item_id=<?= $it['id'] ?>">Commit</a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</div></div>

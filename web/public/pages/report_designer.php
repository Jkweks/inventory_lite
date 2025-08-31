<?php ?>
<h1 class="h3 mb-3">Report Designer</h1>
<div class="row">
  <div class="col-md-6">
    <label for="cssInput" class="form-label">Custom CSS</label>
    <textarea id="cssInput" class="form-control" rows="10">table.report-preview { width:100%; border-collapse:collapse; }
table.report-preview th, table.report-preview td { border:1px solid #ccc; padding:4px; }
table.report-preview th { background:#eee; }</textarea>
    <button class="btn btn-primary mt-2" onclick="updatePreview()">Update Preview</button>
  </div>
  <div class="col-md-6">
    <h2 class="h6">Preview</h2>
    <div id="preview" class="border p-2">
      <table class="report-preview">
        <thead><tr><th>SKU</th><th>Name</th><th>Qty</th></tr></thead>
        <tbody><tr><td>ABC123</td><td>Sample Part</td><td>10</td></tr></tbody>
      </table>
    </div>
  </div>
</div>
<style id="designerStyles"></style>
<script>
function updatePreview(){
  document.getElementById('designerStyles').textContent = document.getElementById('cssInput').value;
}
updatePreview();
</script>


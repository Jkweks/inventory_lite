(function(){
  const themeSwitch = document.getElementById('themeSwitch');
  function setTheme(theme){
    document.documentElement.setAttribute('data-bs-theme', theme);
    localStorage.setItem('theme', theme);
  }
  const initial = localStorage.getItem('theme') || 'dark';
  setTheme(initial);
  if(themeSwitch){
    themeSwitch.checked = initial === 'light';
    themeSwitch.addEventListener('change', ()=> setTheme(themeSwitch.checked ? 'light' : 'dark'));
  }

  window.exportTableToPDF = function(tableId, title){
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({orientation:'landscape', unit:'pt', format:'letter'});
    const table = document.getElementById(tableId);
    if(!table) return alert('Table not found');

    const margin = 40;
    const pageWidth = doc.internal.pageSize.getWidth();
    const pageHeight = doc.internal.pageSize.getHeight();
    const usableWidth = pageWidth - margin*2;
    const rowHeight = 20;

    doc.setFontSize(16);
    let y = margin;
    doc.text(title || 'Inventory Report', margin, y);
    y += 30;

    const headers = [];
    table.querySelectorAll('thead th').forEach(th => headers.push(th.innerText.trim()));
    const rows = [];
    table.querySelectorAll('tbody tr').forEach(tr => {
      const row = [];
      tr.querySelectorAll('td').forEach(td => row.push(td.innerText.trim()));
      rows.push(row);
    });
    const colWidth = usableWidth / headers.length;

    function drawHeader(){
      doc.setFontSize(12);
      doc.setFont('helvetica','bold');
      headers.forEach((h,i)=>{
        doc.rect(margin + i*colWidth, y, colWidth, rowHeight);
        doc.text(h, margin + i*colWidth + 2, y + 14);
      });
      doc.setFont('helvetica','normal');
      y += rowHeight;
    }

    drawHeader();

    rows.forEach(r => {
      if(y + rowHeight > pageHeight - margin){
        doc.addPage();
        y = margin;
        drawHeader();
      }
      r.forEach((cell,i)=>{
        doc.rect(margin + i*colWidth, y, colWidth, rowHeight);
        doc.text(String(cell), margin + i*colWidth + 2, y + 14);
      });
      y += rowHeight;
    });

    doc.save((title || 'report') + '.pdf');
  };
})();


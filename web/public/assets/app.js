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
    let y = 40;
    doc.setFontSize(16);
    doc.text(title || 'Inventory Report', 40, y);
    y += 20;
    const headers = [];
    table.querySelectorAll('thead th').forEach(th => headers.push(th.innerText.trim()));
    const rows = [];
    table.querySelectorAll('tbody tr').forEach(tr => {
      const row = [];
      tr.querySelectorAll('td').forEach(td => row.push(td.innerText.trim()));
      rows.push(row);
    });
    const colWidth = 720 / headers.length;
    doc.setFont('helvetica','bold');
    headers.forEach((h,i)=> doc.text(h, 40 + i*colWidth, y));
    doc.line(40, y+2, 40 + headers.length*colWidth, y+2);
    y += 14;
    doc.setFont('helvetica','normal');
    rows.forEach(r => {
      r.forEach((cell,i)=> doc.text(String(cell), 40 + i*colWidth, y));
      doc.line(40, y+2, 40 + headers.length*colWidth, y+2);
      y += 14;
      if(y > 540){
        doc.addPage();
        y = 40;
      }
    });
    doc.save((title || 'report') + '.pdf');
  };
})();


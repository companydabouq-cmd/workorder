    </div><!-- /content -->
  </div><!-- /main -->
</div><!-- /layout -->

<script>
// إغلاق النوافذ المنبثقة
function openModal(id){document.getElementById(id).classList.add('show')}
function closeModal(id){document.getElementById(id).classList.remove('show')}
document.addEventListener('click',function(e){
  if(e.target.classList.contains('modal-bg')) e.target.classList.remove('show');
});
// بحث فوري بسيط في الجداول (client-side)
function tableSearch(input, tableId){
  var q = input.value.trim().toLowerCase();
  var rows = document.querySelectorAll('#'+tableId+' tbody tr');
  rows.forEach(function(r){
    if(r.classList.contains('empty-row')) return;
    r.style.display = r.textContent.toLowerCase().indexOf(q) > -1 ? '' : 'none';
  });
}
// طباعة جدول كـ Excel (تصدير CSV بسيط)
function exportTableCSV(tableId, filename){
  var rows = document.querySelectorAll('#'+tableId+' tr');
  var csv = [];
  rows.forEach(function(tr){
    var cols = tr.querySelectorAll('th,td'), line=[];
    cols.forEach(function(c){
      if(c.classList.contains('no-export')) return;
      line.push('"'+c.innerText.replace(/"/g,'""').trim()+'"');
    });
    csv.push(line.join(','));
  });
  var blob = new Blob(["\uFEFF"+csv.join("\n")], {type:'text/csv;charset=utf-8;'});
  var a = document.createElement('a');
  a.href = URL.createObjectURL(blob); a.download = (filename||'export')+'.csv'; a.click();
}
function printTable(){ window.print(); }
</script>
</body>
</html>

<?php
require_once __DIR__ . '/../includes/functions.php';
require_staff();
$PAGE_KEY='ctitles'; $PAGE_TITLE='المسميات الوظيفية للمقاول';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $act = $_POST['act'] ?? '';
    if ($act==='save') {
        $id=(int)($_POST['id']??0); $name=trim($_POST['title_name']??'');
        if ($id) q("UPDATE contractor_job_titles SET title_name=? WHERE id=?", [$name,$id]);
        else     q("INSERT INTO contractor_job_titles (title_name) VALUES (?)", [$name]);
        flash_set('ok','تم الحفظ');
    } elseif ($act==='delete') {
        q("DELETE FROM contractor_job_titles WHERE id=?", [(int)$_POST['id']]);
        flash_set('ok','تم الحذف');
    }
    header('Location: contractor_titles.php'); exit;
}
$rows = q_all("SELECT * FROM contractor_job_titles ORDER BY id DESC");
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div class="page-title"><span class="ic">🏷️</span> المسميات الوظيفية للمقاول</div>
  <button class="btn btn-primary" onclick="addNew()">➕ إضافة مسمى وظيفي جديد للمقاول</button>
</div>
<?= flash_get() ?>
<div class="panel">
  <div class="table-tools">
    <div><button class="btn btn-soft btn-sm" onclick="exportTableCSV('tbl','المسميات الوظيفية للمقاول')">Excel</button>
    <button class="btn btn-soft btn-sm" onclick="printTable()">طباعة</button></div>
    <input class="search-box" placeholder="بحث..." onkeyup="tableSearch(this,'tbl')">
  </div>
  <table class="tbl" id="tbl">
    <thead><tr><th>المسمى الوظيفي</th><th class="no-export">الإجراء</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr class="empty-row"><td colspan="2" class="empty">لا توجد بيانات بعد</td></tr>
    <?php else: foreach ($rows as $r): ?>
      <tr><td><?= e($r['title_name']) ?></td>
      <td class="no-export"><div class="act-btns">
        <button class="icon-btn icon-edit" onclick='editRow(<?= json_encode($r, JSON_UNESCAPED_UNICODE) ?>)'>✏️</button>
        <button class="icon-btn icon-del" onclick="delRow(<?= $r['id'] ?>)">🗑️</button>
      </div></td></tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<div class="modal-bg" id="m1"><div class="modal">
  <div class="modal-head" id="m1title">إضافة مسمى وظيفي جديد للمقاول</div>
  <form method="post"><div class="modal-body">
    <?= mq_csrf_field() ?><input type="hidden" name="act" value="save"><input type="hidden" name="id" id="f_id">
    <div class="field"><label>المسمى الوظيفي <span class="req">*</span></label><input name="title_name" id="f_name" required></div>
  </div><div class="modal-foot">
    <button class="btn btn-primary">💾 حفظ البيانات</button>
    <button type="button" class="btn btn-soft" onclick="clearForm()">مسح البيانات</button>
    <button type="button" class="btn btn-warn" onclick="closeModal('m1')">إغلاق</button>
  </div></form>
</div></div>
<form method="post" id="delForm" style="display:none"><?= mq_csrf_field() ?>
  <input type="hidden" name="act" value="delete"><input type="hidden" name="id" id="del_id"></form>
<script>
function addNew(){clearForm();document.getElementById('m1title').textContent='إضافة مسمى وظيفي جديد للمقاول';openModal('m1');}
function clearForm(){['f_id','f_name'].forEach(i=>document.getElementById(i).value='');}
function editRow(r){document.getElementById('m1title').textContent='تعديل مسمى وظيفي';
  document.getElementById('f_id').value=r.id;document.getElementById('f_name').value=r.title_name||'';openModal('m1');}
function delRow(id){if(confirm('هل أنت متأكد من الحذف؟')){document.getElementById('del_id').value=id;document.getElementById('delForm').submit();}}
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>

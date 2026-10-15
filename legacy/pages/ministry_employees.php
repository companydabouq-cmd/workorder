<?php
require_once __DIR__ . '/../includes/functions.php';
require_staff();
$PAGE_KEY='memployees'; $PAGE_TITLE='الموظفين';

$titles    = q_all("SELECT id,title_name FROM ministry_job_titles ORDER BY title_name");
$contracts = q_all("SELECT id,contract_name FROM contracts ORDER BY id DESC");
$ministry  = get_ministry();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $act=$_POST['act']??'';
    if ($act==='save') {
        $id=(int)($_POST['id']??0);
        $d=[trim($_POST['emp_name']??''),trim($_POST['job_number']??''),trim($_POST['civil_id']??''),
            trim($_POST['phone']??''),($_POST['job_title_id']?:null),trim($_POST['entity']??''),($_POST['contract_id']?:null)];
        if ($id){ $d[]=$id;
          q("UPDATE ministry_employees SET emp_name=?,job_number=?,civil_id=?,phone=?,job_title_id=?,entity=?,contract_id=? WHERE id=?",$d);
        } else {
          q("INSERT INTO ministry_employees (emp_name,job_number,civil_id,phone,job_title_id,entity,contract_id) VALUES (?,?,?,?,?,?,?)",$d);
        }
        flash_set('ok','تم الحفظ');
    } elseif ($act==='delete') {
        q("DELETE FROM ministry_employees WHERE id=?", [(int)$_POST['id']]);
        flash_set('ok','تم الحذف');
    }
    header('Location: ministry_employees.php'); exit;
}
$rows = q_all("SELECT e.*, t.title_name, c.contract_name FROM ministry_employees e
               LEFT JOIN ministry_job_titles t ON t.id=e.job_title_id
               LEFT JOIN contracts c ON c.id=e.contract_id ORDER BY e.id DESC");
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div class="page-title"><span class="ic">👥</span> الموظفين</div>
  <button class="btn btn-primary" onclick="addNew()">➕ إضافة موظف جديد</button>
</div>
<?= flash_get() ?>
<div class="panel">
  <div class="table-tools">
    <div><button class="btn btn-soft btn-sm" onclick="exportTableCSV('tbl','الموظفين')">Excel</button>
    <button class="btn btn-soft btn-sm" onclick="printTable()">طباعة</button></div>
    <input class="search-box" placeholder="بحث..." onkeyup="tableSearch(this,'tbl')">
  </div>
  <div style="overflow-x:auto">
  <table class="tbl" id="tbl">
    <thead><tr>
      <th>اسم الموظف</th><th>الرقم الوظيفي</th><th>الرقم المدني</th><th>رقم التلفون</th>
      <th>المسمى الوظيفي</th><th>الجهة التابعة لها</th><th>الوزارة</th><th>العقد</th><th class="no-export">الإجراء</th>
    </tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr class="empty-row"><td colspan="9" class="empty">لا توجد بيانات بعد</td></tr>
    <?php else: foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['emp_name']) ?></td><td><?= e($r['job_number']) ?></td><td><?= e($r['civil_id']) ?></td>
        <td><?= e($r['phone']) ?></td><td><?= e($r['title_name'] ?? '—') ?></td><td><?= e($r['entity']) ?></td>
        <td><?= e($ministry['ministry_name']) ?></td><td><?= e($r['contract_name'] ?? '—') ?></td>
        <td class="no-export"><div class="act-btns">
          <button class="icon-btn icon-edit" onclick='editRow(<?= json_encode($r, JSON_UNESCAPED_UNICODE) ?>)'>✏️</button>
          <button class="icon-btn icon-del" onclick="delRow(<?= $r['id'] ?>)">🗑️</button>
        </div></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
  </div>
</div>
<div class="modal-bg" id="m1"><div class="modal">
  <div class="modal-head" id="m1title">إضافة موظف جديد</div>
  <form method="post"><div class="modal-body">
    <?= mq_csrf_field() ?><input type="hidden" name="act" value="save"><input type="hidden" name="id" id="f_id">
    <div class="field"><label>اسم الموظف <span class="req">*</span></label><input name="emp_name" id="f_name" required></div>
    <div class="grid2">
      <div class="field"><label>الرقم المدني</label><input name="civil_id" id="f_civil"></div>
      <div class="field"><label>الرقم الوظيفي</label><input name="job_number" id="f_job"></div>
    </div>
    <div class="field"><label>رقم التلفون</label><input name="phone" id="f_phone"></div>
    <div class="field"><label>المسمى الوظيفي</label><select name="job_title_id" id="f_title">
      <option value="">- المسمى الوظيفي -</option><?= options($titles,'id','title_name') ?></select></div>
    <div class="field"><label>الجهة التابعة لها</label><input name="entity" id="f_entity"></div>
    <div class="field"><label>العقد</label><select name="contract_id" id="f_cid">
      <option value="">- العقد -</option><?= options($contracts,'id','contract_name') ?></select></div>
  </div><div class="modal-foot">
    <button class="btn btn-primary">💾 حفظ البيانات</button>
    <button type="button" class="btn btn-soft" onclick="clearForm()">مسح البيانات</button>
    <button type="button" class="btn btn-warn" onclick="closeModal('m1')">إغلاق</button>
  </div></form>
</div></div>
<form method="post" id="delForm" style="display:none"><?= mq_csrf_field() ?>
  <input type="hidden" name="act" value="delete"><input type="hidden" name="id" id="del_id"></form>
<script>
var F=['f_id','f_name','f_civil','f_job','f_phone','f_title','f_entity','f_cid'];
function addNew(){clearForm();document.getElementById('m1title').textContent='إضافة موظف جديد';openModal('m1');}
function clearForm(){F.forEach(i=>document.getElementById(i).value='');}
function editRow(r){document.getElementById('m1title').textContent='تعديل موظف';
  document.getElementById('f_id').value=r.id;document.getElementById('f_name').value=r.emp_name||'';
  document.getElementById('f_civil').value=r.civil_id||'';document.getElementById('f_job').value=r.job_number||'';
  document.getElementById('f_phone').value=r.phone||'';document.getElementById('f_title').value=r.job_title_id||'';
  document.getElementById('f_entity').value=r.entity||'';document.getElementById('f_cid').value=r.contract_id||'';openModal('m1');}
function delRow(id){if(confirm('هل أنت متأكد من الحذف؟')){document.getElementById('del_id').value=id;document.getElementById('delForm').submit();}}
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>

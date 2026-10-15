<?php
require_once __DIR__ . '/../includes/functions.php';
require_staff();
$PAGE_KEY='cemployees'; $PAGE_TITLE='موظفين المقاول';

$titles = q_all("SELECT id,title_name FROM contractor_job_titles ORDER BY title_name");

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $act=$_POST['act']??'';
    if ($act==='save') {
        $id=(int)($_POST['id']??0);
        $name=trim($_POST['emp_name']??''); $job=trim($_POST['job_number']??'');
        $civil=trim($_POST['civil_id']??''); $phone=trim($_POST['phone']??'');
        $tid=$_POST['job_title_id']?:null;
        // الصور (اختيارية) - يُحتفظ بالقديمة إن لم تُرفع جديدة
        $imgC = upload_image('img_civil','employees');
        $imgP = upload_image('img_passport','employees');
        $imgE = upload_image('img_experience','employees');
        if ($id) {
            $old = q_row("SELECT * FROM contractor_employees WHERE id=?",[$id]);
            $imgC = $imgC ?: $old['img_civil'];
            $imgP = $imgP ?: $old['img_passport'];
            $imgE = $imgE ?: $old['img_experience'];
            q("UPDATE contractor_employees SET emp_name=?,job_number=?,civil_id=?,phone=?,job_title_id=?,img_civil=?,img_passport=?,img_experience=? WHERE id=?",
              [$name,$job,$civil,$phone,$tid,$imgC,$imgP,$imgE,$id]);
        } else {
            q("INSERT INTO contractor_employees (emp_name,job_number,civil_id,phone,job_title_id,img_civil,img_passport,img_experience) VALUES (?,?,?,?,?,?,?,?)",
              [$name,$job,$civil,$phone,$tid,$imgC,$imgP,$imgE]);
        }
        flash_set('ok','تم الحفظ');
    } elseif ($act==='delete') {
        q("DELETE FROM contractor_employees WHERE id=?", [(int)$_POST['id']]);
        flash_set('ok','تم الحذف');
    }
    header('Location: contractor_employees.php'); exit;
}
$rows = q_all("SELECT e.*, t.title_name FROM contractor_employees e
               LEFT JOIN contractor_job_titles t ON t.id=e.job_title_id ORDER BY e.id DESC");
$BASE = base_url('');
require __DIR__ . '/../includes/header.php';
function img_cell($path,$base){
  if(!$path) return '—';
  return '<a href="'.$base.e($path).'" target="_blank">عرض</a>';
}
?>
<div class="page-head">
  <div class="page-title"><span class="ic">👷</span> موظفين المقاول</div>
  <button class="btn btn-primary" onclick="addNew()">➕ إضافة موظف جديد للمقاول</button>
</div>
<?= flash_get() ?>
<div class="panel">
  <div class="table-tools">
    <div><button class="btn btn-soft btn-sm" onclick="exportTableCSV('tbl','موظفين المقاول')">Excel</button>
    <button class="btn btn-soft btn-sm" onclick="printTable()">طباعة</button></div>
    <input class="search-box" placeholder="بحث..." onkeyup="tableSearch(this,'tbl')">
  </div>
  <div style="overflow-x:auto">
  <table class="tbl" id="tbl">
    <thead><tr>
      <th>اسم الموظف</th><th>الرقم الوظيفي</th><th>الرقم المدني</th><th>رقم التلفون</th>
      <th>المسمى الوظيفي</th><th class="no-export">صورة المدنية</th><th class="no-export">صورة الجواز</th>
      <th class="no-export">صورة الخبرة</th><th class="no-export">الإجراء</th>
    </tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr class="empty-row"><td colspan="9" class="empty">لا توجد بيانات بعد</td></tr>
    <?php else: foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['emp_name']) ?></td><td><?= e($r['job_number']) ?></td><td><?= e($r['civil_id']) ?></td>
        <td><?= e($r['phone']) ?></td><td><?= e($r['title_name'] ?? '—') ?></td>
        <td class="no-export"><?= img_cell($r['img_civil'],$BASE) ?></td>
        <td class="no-export"><?= img_cell($r['img_passport'],$BASE) ?></td>
        <td class="no-export"><?= img_cell($r['img_experience'],$BASE) ?></td>
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
  <div class="modal-head" id="m1title">إضافة موظف جديد للمقاول</div>
  <form method="post" enctype="multipart/form-data"><div class="modal-body">
    <?= mq_csrf_field() ?><input type="hidden" name="act" value="save"><input type="hidden" name="id" id="f_id">
    <div class="field"><label>اسم الموظف <span class="req">*</span></label><input name="emp_name" id="f_name" required></div>
    <div class="grid2">
      <div class="field"><label>الرقم المدني</label><input name="civil_id" id="f_civil"></div>
      <div class="field"><label>الرقم الوظيفي</label><input name="job_number" id="f_job"></div>
    </div>
    <div class="field"><label>رقم التلفون</label><input name="phone" id="f_phone"></div>
    <div class="field"><label>المسمى الوظيفي</label><select name="job_title_id" id="f_title">
      <option value="">- المسمى الوظيفي -</option><?= options($titles,'id','title_name') ?></select></div>
    <div class="field"><label>صورة المدنية</label><input type="file" name="img_civil" accept="image/*,application/pdf"></div>
    <div class="field"><label>صورة الجواز</label><input type="file" name="img_passport" accept="image/*,application/pdf"></div>
    <div class="field"><label>صورة الخبرة</label><input type="file" name="img_experience" accept="image/*,application/pdf"></div>
    <p class="muted" id="editnote" style="display:none">ملاحظة: اترك حقل الصورة فارغاً للإبقاء على الصورة الحالية.</p>
  </div><div class="modal-foot">
    <button class="btn btn-primary">💾 حفظ البيانات</button>
    <button type="button" class="btn btn-soft" onclick="clearForm()">مسح البيانات</button>
    <button type="button" class="btn btn-warn" onclick="closeModal('m1')">إغلاق</button>
  </div></form>
</div></div>
<form method="post" id="delForm" style="display:none"><?= mq_csrf_field() ?>
  <input type="hidden" name="act" value="delete"><input type="hidden" name="id" id="del_id"></form>
<script>
function addNew(){clearForm();document.getElementById('m1title').textContent='إضافة موظف جديد للمقاول';
  document.getElementById('editnote').style.display='none';openModal('m1');}
function clearForm(){['f_id','f_name','f_civil','f_job','f_phone','f_title'].forEach(i=>document.getElementById(i).value='');}
function editRow(r){document.getElementById('m1title').textContent='تعديل موظف';
  document.getElementById('editnote').style.display='block';
  document.getElementById('f_id').value=r.id;document.getElementById('f_name').value=r.emp_name||'';
  document.getElementById('f_civil').value=r.civil_id||'';document.getElementById('f_job').value=r.job_number||'';
  document.getElementById('f_phone').value=r.phone||'';document.getElementById('f_title').value=r.job_title_id||'';openModal('m1');}
function delRow(id){if(confirm('هل أنت متأكد من الحذف؟')){document.getElementById('del_id').value=id;document.getElementById('delForm').submit();}}
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>

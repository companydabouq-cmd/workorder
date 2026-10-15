<?php
require_once __DIR__ . '/../includes/functions.php';
require_staff();
$PAGE_KEY='worksites'; $PAGE_TITLE='مواقع العمل';

$depts = q_all("SELECT id, dept_name, dept_code FROM departments ORDER BY dept_name");
$hasArea = has_site_area();
$hasExtra = has_extra();   // النسب الإضافية للمناطق (القائمة من صفحة «اسم المقاول ونسبة الخصم»)
$extraAreas = $hasExtra ? q_all("SELECT id, area_name, percent, apply_discount FROM extra_areas ORDER BY area_name") : [];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $act = $_POST['act'] ?? '';
    if ($act==='save') {
        $id   = (int)($_POST['id'] ?? 0);
        $code = trim($_POST['site_code'] ?? '');
        $name = trim($_POST['site_name'] ?? '');
        $did  = (int)($_POST['dept_id'] ?? 0);
        $area = trim($_POST['area'] ?? '');
        if (!$did) { flash_set('er','اختر الإدارة'); header('Location: worksites.php'); exit; }
        // هل للمنطقة نسبة إضافية؟ نعم ← تُربط بإحدى مناطق الإعدادات (قيمتها وموقعها من هناك)
        $extraId = (!empty($_POST['has_extra']) && (int)($_POST['extra_area_id'] ?? 0)) ? (int)$_POST['extra_area_id'] : null;
        if ($extraId && !in_array($extraId, array_map('intval', array_column($extraAreas, 'id')), true)) $extraId = null;
        if ($hasArea) {
            if ($id) q("UPDATE worksites SET site_code=?,site_name=?,area=?,dept_id=? WHERE id=?", [$code,$name,$area,$did,$id]);
            else     q("INSERT INTO worksites (site_code,site_name,area,dept_id) VALUES (?,?,?,?)", [$code,$name,$area,$did]);
        } else {
            if ($id) q("UPDATE worksites SET site_code=?,site_name=?,dept_id=? WHERE id=?", [$code,$name,$did,$id]);
            else     q("INSERT INTO worksites (site_code,site_name,dept_id) VALUES (?,?,?)", [$code,$name,$did]);
        }
        if ($hasExtra) {
            $siteId = $id ?: (int)db()->lastInsertId();
            q("UPDATE worksites SET extra_area_id=? WHERE id=?", [$extraId, $siteId]);
        }
        flash_set('ok','تم الحفظ بنجاح');
    } elseif ($act==='delete') {
        q("DELETE FROM worksites WHERE id=?", [(int)$_POST['id']]);
        flash_set('ok','تم الحذف');
    }
    header('Location: worksites.php'); exit;
}

$rows = q_all("SELECT w.*, d.dept_name, d.dept_code" . ($hasExtra ? ", ea.area_name AS extra_name, ea.percent AS extra_pct, ea.apply_discount AS extra_disc" : "") . "
               FROM worksites w LEFT JOIN departments d ON d.id=w.dept_id"
               . ($hasExtra ? " LEFT JOIN extra_areas ea ON ea.id=w.extra_area_id" : "") . " ORDER BY w.id DESC");

require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div class="page-title"><span class="ic">📍</span> مواقع العمل</div>
  <button class="btn btn-primary" onclick="addNew()">➕ إضافة موقع عمل جديد</button>
</div>
<?= flash_get() ?>
<div class="panel">
  <div class="table-tools">
    <div>
      <button class="btn btn-soft btn-sm" onclick="exportTableCSV('tbl','مواقع العمل')">Excel</button>
      <button class="btn btn-soft btn-sm" onclick="printTable()">طباعة</button>
    </div>
    <input class="search-box" placeholder="بحث..." onkeyup="tableSearch(this,'tbl')">
  </div>
  <table class="tbl" id="tbl">
    <thead><tr>
      <th>رمز الإدارة</th><th>الإدارة</th><th>رمز مكان العمل</th><th>مكان العمل</th><th>المنطقة</th><th>النسبة الإضافية</th><th class="no-export">الإجراء</th>
    </tr></thead>
    <tbody>
    <?php if (!$rows): ?>
      <tr class="empty-row"><td colspan="7" class="empty">لا توجد بيانات بعد</td></tr>
    <?php else: foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['dept_code']) ?></td>
        <td><?= e($r['dept_name'] ?? '—') ?></td>
        <td><?= e($r['site_code']) ?></td>
        <td><?= e($r['site_name']) ?></td>
        <td><?= e($r['area'] ?? '') ?></td>
        <td><?= !empty($r['extra_name']) ? e(pct_label($r['extra_pct']).'% — '.$r['extra_name'].((int)$r['extra_disc'] ? '' : ' (بدون خصم)')) : '—' ?></td>
        <td class="no-export"><div class="act-btns">
          <button class="icon-btn icon-edit" onclick='editRow(<?= json_encode($r, JSON_UNESCAPED_UNICODE) ?>)'>✏️</button>
          <button class="icon-btn icon-del" onclick="delRow(<?= $r['id'] ?>)">🗑️</button>
        </div></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<div class="modal-bg" id="m1">
  <div class="modal">
    <div class="modal-head" id="m1title">إضافة موقع عمل جديد</div>
    <form method="post">
      <div class="modal-body">
        <?= mq_csrf_field() ?>
        <input type="hidden" name="act" value="save">
        <input type="hidden" name="id" id="f_id">
        <div class="field"><label>الإدارة <span class="req">*</span></label>
          <select name="dept_id" id="f_did" required>
            <option value="">- الإدارة -</option>
            <?= options($depts,'id','dept_name') ?>
          </select></div>
        <div class="field"><label>رمز موقع العمل</label>
          <input name="site_code" id="f_code"></div>
        <div class="field"><label>موقع العمل <span class="req">*</span></label>
          <input name="site_name" id="f_name" required></div>
        <?php if ($hasArea): ?>
        <div class="field"><label>المنطقة</label>
          <input name="area" id="f_area" placeholder="أدخل اسم المنطقة"></div>
        <?php endif; ?>
        <?php if ($hasExtra): ?>
        <div class="field"><label>هل يوجد نسبة إضافية لهذه المنطقة؟</label>
          <label class="opt"><input type="radio" name="has_extra" value="0" id="f_ex_no" checked onchange="toggleExtra()"> لا</label>
          <label class="opt"><input type="radio" name="has_extra" value="1" id="f_ex_yes" onchange="toggleExtra()"> نعم</label>
        </div>
        <div class="field" id="exBox" style="display:none">
          <?php if ($extraAreas): ?>
          <label>المنطقة ونسبتها <span class="req">*</span></label>
          <select name="extra_area_id" id="f_extra" onchange="extraPicked()">
            <option value="">- اختر -</option>
            <?php foreach ($extraAreas as $ea): ?>
              <option value="<?= (int)$ea['id'] ?>" data-name="<?= e($ea['area_name']) ?>"><?= e($ea['area_name']) ?> — <?= e(pct_label($ea['percent'])) ?>%<?= (int)$ea['apply_discount'] ? '' : ' (بدون خصم)' ?></option>
            <?php endforeach; ?>
          </select>
          <p class="muted" style="margin-top:4px">قيمة النسبة وموقعها (قبل الخصم أو بعده) وهل يُطبق الخصم فيها: من صفحة «اسم المقاول ونسبة الخصم».</p>
          <?php else: ?>
          <p class="muted">لم تُضف مناطق بعد — أضفها مع نسبها من
            <a href="<?= base_url('pages/contractor_settings.php') ?>"><b>صفحة «اسم المقاول ونسبة الخصم»</b></a>.</p>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
      <div class="modal-foot">
        <button class="btn btn-primary">💾 حفظ البيانات</button>
        <button type="button" class="btn btn-soft" onclick="clearForm()">مسح البيانات</button>
        <button type="button" class="btn btn-warn" onclick="closeModal('m1')">إغلاق</button>
      </div>
    </form>
  </div>
</div>
<form method="post" id="delForm" style="display:none">
  <?= mq_csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" id="del_id">
</form>
<script>
function addNew(){ clearForm(); document.getElementById('m1title').textContent='إضافة موقع عمل جديد'; openModal('m1'); }
function clearForm(){ ['f_id','f_name','f_code','f_did','f_area','f_extra'].forEach(i=>{ var el=document.getElementById(i); if(el) el.value=''; }); setExtra(false); }
function setExtra(on){
  var no=document.getElementById('f_ex_no'), yes=document.getElementById('f_ex_yes');
  if(!no) return;
  (on?yes:no).checked=true; toggleExtra();
}
function toggleExtra(){
  var yes=document.getElementById('f_ex_yes'), box=document.getElementById('exBox'), sel=document.getElementById('f_extra');
  if(!yes) return;
  box.style.display = yes.checked ? '' : 'none';
  if(sel) sel.required = yes.checked;
}
// اختيار منطقة النسبة يملأ خانة «المنطقة» إن كانت فارغة
function extraPicked(){
  var sel=document.getElementById('f_extra'), area=document.getElementById('f_area');
  var opt=sel.options[sel.selectedIndex];
  if(area && !area.value.trim() && opt && opt.dataset.name) area.value=opt.dataset.name;
}
function editRow(r){
  document.getElementById('m1title').textContent='تعديل موقع عمل';
  document.getElementById('f_id').value=r.id;
  document.getElementById('f_name').value=r.site_name||'';
  document.getElementById('f_code').value=r.site_code||'';
  document.getElementById('f_did').value=r.dept_id||'';
  if(document.getElementById('f_area')) document.getElementById('f_area').value=r.area||'';
  if(document.getElementById('f_extra')) document.getElementById('f_extra').value=r.extra_area_id||'';
  setExtra(!!r.extra_area_id);
  openModal('m1');
}
function delRow(id){ if(confirm('هل أنت متأكد من الحذف؟')){ document.getElementById('del_id').value=id; document.getElementById('delForm').submit(); } }
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>

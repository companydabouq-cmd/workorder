<?php
require_once __DIR__ . '/../includes/functions.php';
require_staff();
$PAGE_KEY='worktypes'; $PAGE_TITLE='أنواع الأعمال';
has_work_types();   // يُنشئ الجدول ويعبّئه بالأنواع المعتادة أول مرة
$wtCodes = work_types_enabled();   // رمز النوع مع رقم المستند: مع ترويستي «البلدية» و«المالية» فقط

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $act = $_POST['act'] ?? '';
    if ($act==='save') {
        $id   = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['type_name'] ?? '');
        $code = mb_substr(trim($_POST['type_code'] ?? ''), 0, 10);
        if ($name === '') { flash_set('er','اكتب اسم نوع العمل'); header('Location: work_types.php'); exit; }
        if ($id) q("UPDATE work_types SET type_name=?, type_code=? WHERE id=?", [$name, $code, $id]);
        else     q("INSERT INTO work_types (type_name, type_code) VALUES (?,?)", [$name, $code]);
        flash_set('ok','تم الحفظ بنجاح');
    } elseif ($act==='delete') {
        q("DELETE FROM work_types WHERE id=?", [(int)$_POST['id']]);
        flash_set('ok','تم الحذف — المستندات السابقة تحتفظ بنوعها ورمزه');
    }
    header('Location: work_types.php'); exit;
}

$rows = q_all("SELECT * FROM work_types ORDER BY id");

require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div class="page-title"><span class="ic">🧰</span> أنواع الأعمال</div>
  <button class="btn btn-primary" onclick="addNew()">➕ إضافة نوع عمل</button>
</div>
<?= flash_get() ?>
<div class="panel">
  <p class="muted" style="margin-bottom:12px">
    يُختار نوع العمل في الحصر المبدئي وحصر العمل وأمر العمل<?= $wtCodes ? '، ويُكتب رمزه مع رقم المستند مثل <b>54/ت</b>.' : '. (رمز النوع يُكتب مع رقم المستند مع نوع المشروع «البلدية» أو «المالية» فقط.)' ?>
    المستند يحفظ اسم النوع ورمزه وقت حفظه، فتعديل النوع هنا لا يغيّر المستندات السابقة.
    <br>الأنواع التي تظهر في المستندات هي <b>أنواع أعمال العقد</b> — يختارها مدير النظام من صفحة «بيانات العقد».
  </p>
  <table class="tbl" id="tbl">
    <thead><tr><th>نوع العمل</th><th>رمز نوع العمل</th><th>مثال للرقم</th><th>ضمن أعمال العقد</th><th class="no-export">الإجراء</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?>
      <tr class="empty-row"><td colspan="5" class="empty">لا توجد أنواع بعد — بدونها يبقى «نوع العمل» خانة نص حر</td></tr>
    <?php else: foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['type_name']) ?></td>
        <td><?= e($r['type_code']) ?></td>
        <td><?= $wtCodes ? e(doc_no_label(54, $r['type_code'])) : '54' ?></td>
        <td><?= !empty($r['in_contract']) ? '✓' : '—' ?></td>
        <td class="no-export"><div class="act-btns">
          <button class="icon-btn icon-edit" title="تعديل" onclick='editRow(<?= json_encode($r, JSON_UNESCAPED_UNICODE) ?>)'>✏️</button>
          <button class="icon-btn icon-del" title="حذف" onclick="delRow(<?= (int)$r['id'] ?>)">🗑️</button>
        </div></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<div class="modal-bg" id="m1">
  <div class="modal">
    <div class="modal-head" id="m1title">إضافة نوع عمل</div>
    <form method="post">
      <div class="modal-body">
        <?= mq_csrf_field() ?>
        <input type="hidden" name="act" value="save">
        <input type="hidden" name="id" id="f_id">
        <div class="field"><label>نوع العمل <span class="req">*</span></label>
          <input name="type_name" id="f_name" required placeholder="مثل: اعمال مدنية"></div>
        <div class="field"><label>رمز نوع العمل</label>
          <input name="type_code" id="f_code" maxlength="10" placeholder="مثل: م">
          <p class="muted" style="margin-top:4px">حرف أو أكثر يُكتب بعد رقم المستند: 54/م. اتركه فارغاً ليظهر الرقم وحده.</p></div>
      </div>
      <div class="modal-foot">
        <button class="btn btn-primary">💾 حفظ البيانات</button>
        <button type="button" class="btn btn-warn" onclick="closeModal('m1')">إغلاق</button>
      </div>
    </form>
  </div>
</div>
<form method="post" id="delForm" style="display:none">
  <?= mq_csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" id="del_id">
</form>
<script>
function addNew(){ ['f_id','f_name','f_code'].forEach(i=>document.getElementById(i).value=''); document.getElementById('m1title').textContent='إضافة نوع عمل'; openModal('m1'); }
function editRow(r){
  document.getElementById('m1title').textContent='تعديل نوع عمل';
  document.getElementById('f_id').value=r.id;
  document.getElementById('f_name').value=r.type_name||'';
  document.getElementById('f_code').value=r.type_code||'';
  openModal('m1');
}
function delRow(id){ if(confirm('حذف نوع العمل؟ المستندات السابقة تحتفظ بنوعها.')){ document.getElementById('del_id').value=id; document.getElementById('delForm').submit(); } }
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>

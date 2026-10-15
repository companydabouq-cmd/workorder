<?php
/**
 * المستخدمون — لمدير النظام فقط.
 * الأدوار (التفصيل في includes/functions.php): مدير النظام / مستخدم / مشاهد.
 */
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$PAGE_KEY='users'; $PAGE_TITLE='المستخدمون';

$ROLES = role_labels();
$me    = (int)$_SESSION['user_id'];
$adminCount = function () { return (int)q_val("SELECT COUNT(*) FROM users WHERE role='admin'"); };

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $act = $_POST['act'] ?? '';
    $back = function ($type, $msg) { flash_set($type, $msg); header('Location: users.php'); exit; };

    if ($act==='save') {
        $id    = (int)($_POST['id'] ?? 0);
        $uname = trim($_POST['username'] ?? '');
        $dname = trim($_POST['display_name'] ?? '');
        $role  = $_POST['role'] ?? '';
        $pass  = (string)($_POST['password'] ?? '');

        if (!preg_match('/^[A-Za-z0-9_.\-]{3,50}$/', $uname)) $back('er', 'اسم المستخدم: 3 أحرف على الأقل، حروف إنجليزية وأرقام فقط.');
        if (!isset($ROLES[$role]))                           $back('er', 'اختر الصلاحية.');
        if (q_val("SELECT id FROM users WHERE username=? AND id<>?", [$uname, $id])) $back('er', 'اسم المستخدم مستعمل من قبل.');
        if ((!$id || $pass !== '') && strlen($pass) < 8)     $back('er', 'كلمة المرور قصيرة (8 أحرف على الأقل).');

        if ($id) {
            $cur = q_row("SELECT role FROM users WHERE id=?", [$id]);
            if (!$cur) $back('er', 'المستخدم غير موجود.');
            // لا يُترك النظام بلا مدير
            if ($cur['role']==='admin' && $role!=='admin' && $adminCount()<=1) $back('er', 'لا يمكن تغيير صلاحية آخر مدير للنظام.');
            q("UPDATE users SET username=?, display_name=?, role=? WHERE id=?", [$uname, $dname, $role, $id]);
            if ($pass !== '') q("UPDATE users SET password_hash=? WHERE id=?", [password_hash($pass, PASSWORD_BCRYPT), $id]);
            $back('ok', 'تم حفظ المستخدم.');
        }
        q("INSERT INTO users (username, password_hash, display_name, role) VALUES (?,?,?,?)",
          [$uname, password_hash($pass, PASSWORD_BCRYPT), $dname, $role]);
        $back('ok', 'تمت إضافة المستخدم.');
    }

    if ($act==='delete') {
        $id  = (int)($_POST['id'] ?? 0);
        $cur = q_row("SELECT role FROM users WHERE id=?", [$id]);
        if (!$cur)                                   $back('er', 'المستخدم غير موجود.');
        if ($id === $me)                             $back('er', 'لا يمكنك حذف حسابك وأنت داخل به.');
        if ($cur['role']==='admin' && $adminCount()<=1) $back('er', 'لا يمكن حذف آخر مدير للنظام.');
        q("DELETE FROM users WHERE id=?", [$id]);
        $back('ok', 'تم حذف المستخدم.');
    }
    header('Location: users.php'); exit;
}

$rows = q_all("SELECT id, username, display_name, role, created_at FROM users ORDER BY id");

require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div class="page-title"><span class="ic">👤</span> المستخدمون</div>
  <button class="btn btn-primary" onclick="addNew()">➕ إضافة مستخدم</button>
</div>
<?= flash_get() ?>
<div class="panel">
  <div class="muted" style="margin-bottom:12px;line-height:1.9">
    <b>مدير النظام:</b> كل الصلاحيات. &nbsp;
    <b>مستخدم:</b> المستندات والتقارير والإدارات والمواقع والموظفين — بلا بيانات العقد، اسم المقاول ونسبة الخصم،
    الترويسة والتذييل، اسم الوزارة، جداول الكميات. &nbsp;
    <b>مشاهد:</b> يرى أوامر العمل ويطبعها فقط.
  </div>
  <table class="tbl" id="tbl">
    <thead><tr><th>اسم المستخدم</th><th>الاسم الظاهر</th><th>الصلاحية</th><th class="no-export">الإجراء</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td dir="ltr"><?= e($r['username']) ?><?= (int)$r['id']===$me ? ' <span class="muted">(أنت)</span>' : '' ?></td>
        <td><?= e($r['display_name']) ?></td>
        <td><?= e($ROLES[$r['role']] ?? $r['role']) ?></td>
        <td class="no-export"><div class="act-btns">
          <button class="icon-btn icon-edit" title="تعديل"
            data-u="<?= e(json_encode(['id'=>(int)$r['id'],'username'=>$r['username'],'display_name'=>$r['display_name'],'role'=>$r['role']], JSON_UNESCAPED_UNICODE)) ?>"
            onclick="editRow(JSON.parse(this.dataset.u))">✏️</button>
          <?php if ((int)$r['id'] !== $me): ?>
          <button class="icon-btn icon-del" title="حذف" onclick="delRow(<?= (int)$r['id'] ?>)">🗑️</button>
          <?php endif; ?>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="modal-bg" id="m1">
  <div class="modal">
    <div class="modal-head" id="m1title">إضافة مستخدم</div>
    <form method="post" autocomplete="off">
      <div class="modal-body">
        <?= mq_csrf_field() ?>
        <input type="hidden" name="act" value="save">
        <input type="hidden" name="id" id="f_id">
        <div class="field"><label>اسم المستخدم (للدخول) <span class="req">*</span></label>
          <input name="username" id="f_user" dir="ltr" required pattern="[A-Za-z0-9_.\-]{3,50}" placeholder="حروف إنجليزية وأرقام"></div>
        <div class="field"><label>الاسم الظاهر</label>
          <input name="display_name" id="f_name" placeholder="يظهر أعلى الشاشة"></div>
        <div class="field"><label>الصلاحية <span class="req">*</span></label>
          <select name="role" id="f_role" required>
            <?php foreach ($ROLES as $k => $lbl): ?><option value="<?= e($k) ?>"><?= e($lbl) ?></option><?php endforeach; ?>
          </select></div>
        <div class="field"><label id="passLbl">كلمة المرور <span class="req">*</span></label>
          <input name="password" id="f_pass" type="password" minlength="8" autocomplete="new-password" placeholder="8 أحرف على الأقل">
          <p class="muted" id="passHint" style="margin-top:4px;display:none">اتركها فارغة لتبقى كلمة المرور الحالية.</p></div>
      </div>
      <div class="modal-foot">
        <button class="btn btn-primary">💾 حفظ</button>
        <button type="button" class="btn btn-warn" onclick="closeModal('m1')">إغلاق</button>
      </div>
    </form>
  </div>
</div>
<form method="post" id="delForm" style="display:none">
  <?= mq_csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" id="del_id">
</form>
<script>
function addNew(){
  ['f_id','f_user','f_name','f_pass'].forEach(i=>document.getElementById(i).value='');
  document.getElementById('f_role').value='user';
  document.getElementById('f_pass').required=true;
  document.getElementById('passHint').style.display='none';
  document.getElementById('m1title').textContent='إضافة مستخدم';
  openModal('m1');
}
function editRow(r){
  document.getElementById('m1title').textContent='تعديل مستخدم';
  document.getElementById('f_id').value=r.id;
  document.getElementById('f_user').value=r.username||'';
  document.getElementById('f_name').value=r.display_name||'';
  document.getElementById('f_role').value=r.role||'user';
  document.getElementById('f_pass').value='';
  document.getElementById('f_pass').required=false;
  document.getElementById('passHint').style.display='';
  openModal('m1');
}
function delRow(id){ if(confirm('حذف هذا المستخدم؟')){ document.getElementById('del_id').value=id; document.getElementById('delForm').submit(); } }
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>

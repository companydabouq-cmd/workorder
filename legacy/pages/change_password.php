<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
if (is_viewer()) { header('Location: '.base_url('pages/work_orders.php')); exit; }
$PAGE_KEY=''; $PAGE_TITLE='تغيير كلمة المرور';
$msg='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $old=$_POST['old']??''; $new=$_POST['new']??''; $confirm=$_POST['confirm']??'';
    $u=q_row("SELECT * FROM users WHERE id=?", [$_SESSION['user_id']]);
    if (!password_verify($old,$u['password_hash'])) $msg='<div class="flash er">كلمة المرور الحالية غير صحيحة</div>';
    elseif (strlen($new)<6) $msg='<div class="flash er">كلمة المرور الجديدة قصيرة (6 أحرف على الأقل)</div>';
    elseif ($new!==$confirm) $msg='<div class="flash er">كلمة المرور الجديدة غير متطابقة</div>';
    else {
        q("UPDATE users SET password_hash=? WHERE id=?", [password_hash($new,PASSWORD_BCRYPT),$_SESSION['user_id']]);
        $msg='<div class="flash ok">تم تغيير كلمة المرور بنجاح</div>';
    }
}
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div class="page-title"><span class="ic">🔒</span> تغيير كلمة المرور</div></div>
<?= $msg ?>
<div class="panel" style="max-width:480px">
  <form method="post"><?= mq_csrf_field() ?>
    <div class="field"><label>كلمة المرور الحالية</label><input type="password" name="old" required></div>
    <div class="field"><label>كلمة المرور الجديدة</label><input type="password" name="new" required></div>
    <div class="field"><label>تأكيد كلمة المرور الجديدة</label><input type="password" name="confirm" required></div>
    <button class="btn btn-primary">💾 تغيير</button>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

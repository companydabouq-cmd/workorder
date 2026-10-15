<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$PAGE_KEY='ministry'; $PAGE_TITLE='اسم الوزارة';

$m = get_ministry();
$mid = $m['id'] ?? null;

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    // المسميات الثلاثة انتقلت إلى صفحة «الترويسة والتذييل» — لا تُمسّ من هنا
    $name = trim($_POST['ministry_name'] ?? '');
    if ($mid) q("UPDATE ministry_settings SET ministry_name=? WHERE id=?", [$name,$mid]);
    else      q("INSERT INTO ministry_settings (ministry_name) VALUES (?)", [$name]);
    flash_set('ok','تم حفظ إعدادات الوزارة');
    header('Location: ministry_settings.php'); exit;
}

require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div class="page-title"><span class="ic">⚙️</span> اسم الوزارة</div></div>
<?= flash_get() ?>
<div class="panel" style="max-width:620px">
  <form method="post">
    <?= mq_csrf_field() ?>
    <div class="field">
      <label>اسم الوزارة التابعة لها الأعمال <span class="req">*</span></label>
      <input name="ministry_name" required value="<?= e($m['ministry_name'] ?? '') ?>" placeholder="أدخل اسم الجهة">
    </div>
    <button class="btn btn-primary">💾 حفظ</button>
  </form>
  <p class="muted" style="margin-top:14px">
    يظهر هذا الاسم في السطر الأول من ترويسة طباعة حصر العمل وأمر العمل والتقارير.
  </p>
  <p class="muted" style="margin-top:6px">
    باقي نصوص الترويسة وكتلة التواقيع ومحضر الاستلام والشعار
    ← <a href="<?= base_url('pages/print_settings.php') ?>"><b>صفحة «الترويسة والتذييل»</b></a>.
  </p>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

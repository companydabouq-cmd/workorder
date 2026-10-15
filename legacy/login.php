<?php
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) { header('Location: ' . base_url('index.php')); exit; }

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    $st = db()->prepare("SELECT * FROM users WHERE username=? LIMIT 1");
    $st->execute([$u]);
    $user = $st->fetch();
    if ($user && password_verify($p, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['display_name'] = $user['display_name'];
        $_SESSION['role'] = $user['role'] ?? 'admin';
        // المشاهد يُوجّه مباشرة لأوامر العمل
        if (($_SESSION['role']) === 'viewer') {
            header('Location: ' . base_url('pages/work_orders.php'));
        } else {
            header('Location: ' . base_url('index.php'));
        }
        exit;
    } else {
        $err = 'اسم المستخدم أو كلمة المرور غير صحيحة';
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>تسجيل الدخول — <?= e(app_title()) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Tajawal',sans-serif;direction:rtl;min-height:100vh;display:flex;align-items:center;justify-content:center;
    background:linear-gradient(135deg,#1b1f2a,#222b45);padding:20px}
  .box{background:#fff;border-radius:20px;padding:42px 38px;width:100%;max-width:400px;box-shadow:0 30px 80px rgba(0,0,0,.4)}
  .logo{width:64px;height:64px;border-radius:18px;background:linear-gradient(135deg,#5b86e5,#3b6fd6);margin:0 auto 18px;
    display:flex;align-items:center;justify-content:center;color:#fff;font-size:28px;font-weight:800}
  h1{text-align:center;font-size:21px;margin-bottom:6px;color:#2b3242}
  .sub{text-align:center;color:#8a92a4;font-size:13px;margin-bottom:26px}
  label{display:block;font-size:13.5px;font-weight:600;margin-bottom:7px;color:#445}
  input{width:100%;border:1px solid #d8dde7;border-radius:11px;padding:12px 14px;font-family:inherit;font-size:15px;margin-bottom:16px}
  input:focus{outline:0;border-color:#3b6fd6;box-shadow:0 0 0 3px rgba(59,111,214,.13)}
  .btn{width:100%;border:0;border-radius:11px;padding:13px;background:linear-gradient(180deg,#5b86e5,#3b6fd6);
    color:#fff;font-size:16px;font-weight:700;cursor:pointer;font-family:inherit}
  .btn:hover{filter:brightness(.97)}
  .err{background:#fee2e2;color:#991b1b;padding:11px 14px;border-radius:10px;margin-bottom:16px;font-size:13.5px;text-align:center}
</style>
</head>
<body>
<form class="box" method="post">
  <div class="logo">
    <?php if (has_logo()): ?>
      <img src="<?= e(print_logo_url()) ?>" alt="" style="width:100%;height:100%;object-fit:contain;border-radius:inherit;background:#fff">
    <?php else: ?>
      <?= e(mb_substr(app_title(), 0, 1, 'UTF-8')) ?>
    <?php endif; ?>
  </div>
  <h1>تسجيل الدخول</h1>
  <div class="sub"><?= e(app_title()) ?></div>
  <?php if ($err): ?><div class="err"><?= e($err) ?></div><?php endif; ?>
  <?= mq_csrf_field() ?>
  <label>اسم المستخدم</label>
  <input type="text" name="username" autofocus required>
  <label>كلمة المرور</label>
  <input type="password" name="password" required>
  <button class="btn">دخول</button>
</form>
</body>
</html>

<?php
require_once __DIR__ . '/functions.php';
require_login();
$display = $_SESSION['display_name'] ?? 'مستخدم';
$active = $PAGE_KEY ?? '';
function navlink($key, $href, $icon, $label){
  global $active;
  $cls = $active === $key ? 'active' : '';
  echo '<a class="'.$cls.'" href="'.base_url($href).'"><span class="ic">'.$icon.'</span>'.e($label).'</a>';
}
// عنوان مجموعة قابل للطي. $keys: مفاتيح الصفحات داخلها (لفتحها تلقائياً إن كانت نشطة)
function navgroup_open($id, $icon, $title, $activeKeys){
  global $active;
  $isOpen = in_array($active, $activeKeys);
  echo '<button type="button" class="nav-head'.($isOpen?' open':'').'" onclick="toggleGroup(this)">'
      .'<span class="ic">'.$icon.'</span><span class="nh-txt">'.e($title).'</span><span class="nh-arrow">▾</span></button>';
  echo '<div class="nav-collapse'.($isOpen?' show':'').'">';
}
function navgroup_close(){ echo '</div>'; }
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= isset($PAGE_TITLE) ? e($PAGE_TITLE).' — ' : '' ?><?= e(app_title()) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>?v=4">
<style>
/* تنسيق المجموعات القابلة للطي (مضمّن لضمان العمل) */
.nav-head{display:flex;align-items:center;gap:11px;width:100%;padding:12px 20px;background:transparent !important;border:0;
  color:#c7ccd8;font-family:inherit;font-size:13.5px;font-weight:700;cursor:pointer;text-align:right;
  border-right:3px solid transparent;transition:.15s}
.nav-head:hover{background:#222838 !important;color:#fff}
.nav-head .ic{width:18px;text-align:center;opacity:.85}
.nav-head .nh-txt{flex:1}
.nav-head .nh-arrow{transition:transform .2s;opacity:.7;font-size:12px}
.nav-head.open .nh-arrow{transform:rotate(180deg)}
.nav-head.open{color:#fff;background:#1b2130 !important}
.nav-collapse{display:none;background:rgba(0,0,0,.15)}
.nav-collapse.show{display:block}
.nav-collapse a{padding-right:34px;font-size:13px}
</style>
</head>
<body>
<div class="layout">
  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <div class="logo">
        <?php if (has_logo()): ?>
          <img src="<?= e(print_logo_url()) ?>" alt="" style="width:100%;height:100%;object-fit:contain;border-radius:inherit">
        <?php else: ?>
          <?= e(mb_substr(app_title(), 0, 1, 'UTF-8')) ?>
        <?php endif; ?>
      </div>
      <b><?= e(app_title()) ?></b>
    </div>
    <nav class="nav">
      <?php if (is_viewer()): ?>
      <!-- قائمة المشاهد: أوامر العمل فقط -->
      <div class="nav-group" style="margin-top:10px">
        <?php navlink('orders','pages/work_orders.php','📦','أوامر العمل'); ?>
      </div>
      <?php else: ?>
      <div class="nav-group">
        <?php navlink('dashboard','index.php','📊','لوحة البيانات'); ?>
        <?php if (is_admin()): /* بيانات العقد والمقاول والترويسات: لمدير النظام فقط */ ?>
        <?php navlink('contract','pages/contract.php','📄','بيانات العقد'); ?>
        <?php navlink('printset','pages/print_settings.php','🖨️','الترويسة والتذييل'); ?>
        <?php navlink('users','pages/users.php','👤','المستخدمون'); ?>
        <?php endif; ?>
      </div>

      <?php navgroup_open('g_ministry','⚙️','إعدادات النظام للوزارة',['ministry','departments','worksites','worktypes','mtitles','memployees']); ?>
        <?php if (is_admin()) navlink('ministry','pages/ministry_settings.php','⚙️','اسم الوزارة'); ?>
        <?php navlink('departments','pages/departments.php','🏢','الإدارات'); ?>
        <?php navlink('worksites','pages/worksites.php','📍','مواقع العمل'); ?>
        <?php navlink('worktypes','pages/work_types.php','🧰','أنواع الأعمال'); ?>
        <?php navlink('mtitles','pages/ministry_titles.php','🏷️','المسميات الوظيفية'); ?>
        <?php navlink('memployees','pages/ministry_employees.php','👥','الموظفين'); ?>
      <?php navgroup_close(); ?>

      <?php navgroup_open('g_contractor','⚙️','إعدادات النظام للمقاول',['contractor','ctitles','cemployees']); ?>
        <?php if (is_admin()) navlink('contractor','pages/contractor_settings.php','⚙️','اسم المقاول ونسبة الخصم'); ?>
        <?php navlink('ctitles','pages/contractor_titles.php','🏷️','المسميات الوظيفية للمقاول'); ?>
        <?php navlink('cemployees','pages/contractor_employees.php','👷','موظفين المقاول'); ?>
      <?php navgroup_close(); ?>

      <?php if (is_admin()): ?>
      <div class="nav-title">جداول الكميات</div>
      <div class="nav-group">
        <?php navlink('boq','pages/boq.php','📋','جداول الكميات'); ?>
      </div>
      <?php endif; ?>

      <div class="nav-title">حصر العمل المبدئي</div>
      <div class="nav-group">
        <?php navlink('prelim_new','pages/prelim_survey_form.php','➕','حصر عمل مبدئي جديد'); ?>
        <?php navlink('prelims','pages/prelim_surveys.php','📋','الأعمال المبدئية المحصورة'); ?>
      </div>

      <div class="nav-title">حصر العمل</div>
      <div class="nav-group">
        <?php navlink('survey_new','pages/work_survey_form.php','➕','حصر عمل جديد'); ?>
        <?php navlink('surveys','pages/work_surveys.php','📑','الأعمال المحصورة'); ?>
      </div>

      <div class="nav-title">أوامر العمل</div>
      <div class="nav-group">
        <?php navlink('order_new','pages/work_order_form.php','➕','أمر عمل جديد'); ?>
        <?php navlink('orders','pages/work_orders.php','📦','أوامر العمل'); ?>
      </div>

      <?php navgroup_open('g_reports','📊','التقارير',['rpt_orders','rpt_surveys','rpt_period']); ?>
        <?php navlink('rpt_orders','reports/orders_summary.php','📊','ملخص أوامر العمل'); ?>
        <?php navlink('rpt_surveys','reports/surveys_summary.php','📊','ملخص حصورات العمل'); ?>
        <?php navlink('rpt_period','reports/items_period.php','📈','حصر بنود خلال فترة'); ?>
      <?php navgroup_close(); ?>
      <?php endif; ?>

      <div class="nav-group" style="margin-top:10px;border-top:1px solid #232838">
        <?php if (!is_viewer()): ?>
        <a href="<?= base_url('pages/change_password.php') ?>"><span class="ic">🔒</span>تغيير كلمة المرور</a>
        <?php endif; ?>
        <a href="<?= base_url('logout.php') ?>"><span class="ic">🚪</span>تسجيل الخروج</a>
      </div>
    </nav>
  </aside>
  <script>
  // طي/فتح مجموعات القائمة (مضمّن لضمان العمل دون انتظار الفوتر)
  function toggleGroup(btn){
    btn.classList.toggle('open');
    var box = btn.nextElementSibling;
    if(box && box.classList.contains('nav-collapse')) box.classList.toggle('show');
  }
  </script>

  <div class="main">
    <div class="topbar">
      <button class="menu-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
      <div class="ava"><?= e(mb_substr($display,0,1)) ?></div>
      <div class="welcome">مرحباً، <?= e($display) ?></div>
      <div class="spacer"></div>
    </div>
    <div class="content">

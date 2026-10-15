<?php
require_once __DIR__ . '/includes/functions.php';
require_staff();
$PAGE_KEY = 'dashboard';
$PAGE_TITLE = 'لوحة البيانات';

$contract   = get_contract();
$contractor = get_contractor();
$disc       = (float)$contractor['discount_percent'];

// حسابات أوامر العمل — كل أمر بنسخته من نسبة الخصم والنسبة الإضافية (كما في القوائم والطباعة)
$hasExtra = has_extra();
$orders = db()->query("
  SELECT wo.discount_percent, " . ($hasExtra ? "wo.extra_percent" : "0 AS extra_percent") . ",
         COALESCE((SELECT SUM(it.total_before) FROM work_order_items it WHERE it.order_id = wo.id),0) AS total_before
  FROM work_orders wo
")->fetchAll();

$cnt = count($orders);                                       // عدد أوامر العمل
$total_before = 0;                                           // مبلغ الأعمال قبل الخصم
$total_after  = 0;                                           // بعد الخصم (شامل النسبة الإضافية)
foreach ($orders as $o) {
    $total_before += (float)$o['total_before'];
    $total_after  += doc_net((float)$o['total_before'], (float)$o['discount_percent'], (float)$o['extra_percent']);
}
$actual_value = $contract ? (float)$contract['actual_value'] : 0;
$remaining    = $actual_value - $total_after;                // المتبقي من العقد

require __DIR__ . '/includes/header.php';
?>
<div class="dash-wrap">
  <div class="dash-head"><span>📊</span> لوحة البيانات</div>
  <?= flash_get() ?>

  <?php /* لوحة البيانات تُعرض كاملة للمدير والمستخدم (عرض فقط). التعديل من صفحات بيانات العقد والمقاول
           والترويسة وجداول الكميات — وهي لمدير النظام وحده. */ ?>

  <!-- صف بيانات العقد -->
  <div class="cards" style="margin-bottom:16px">
    <div class="stat" style="background:#7c4a52">
      <div class="lbl">اسم العقد</div>
      <div class="val" style="font-size:16px;line-height:1.6"><?= $contract ? e($contract['contract_name']) : '— غير محدد —' ?></div>
    </div>
    <div class="stat" style="background:#7c4a52">
      <div class="lbl">رقم العقد</div>
      <div class="val"><?= $contract ? e($contract['contract_no']) : '—' ?></div>
    </div>
    <div class="stat" style="background:#7c4a52">
      <div class="lbl">الوزارة</div>
      <div class="val" style="font-size:18px"><?= e(get_ministry()['ministry_name'] ?: '—') ?></div>
    </div>
  </div>

  <!-- صف معلومات العقد -->
  <div class="cards" style="margin-bottom:16px">
    <div class="stat" style="background:#e8a33d">
      <div class="lbl">نسبة الخصم</div>
      <div class="val"><?= $disc>0 ? '-'.rtrim(rtrim(number_format($disc,3,'.',''),'0'),'.').'%' : '0%' ?></div>
    </div>
    <div class="stat" style="background:#8b6ce8">
      <div class="lbl">القيمة الفعلية للعقد</div>
      <div class="val"><?= fmt($actual_value) ?></div>
    </div>
    <div class="stat" style="background:#b8c0d0;color:#2b3242">
      <div class="lbl">نهاية العقد</div>
      <div class="val"><?= $contract ? fmt_date($contract['end_date']) : '—' ?></div>
    </div>
  </div>

  <!-- صف الأعمال -->
  <div class="cards">
    <div class="stat" style="background:#cdeee6;color:#1c8576">
      <div class="lbl">بداية العقد</div>
      <div class="val"><?= $contract ? fmt_date($contract['start_date']) : '—' ?></div>
    </div>
    <div class="stat" style="background:#36b3a0">
      <div class="lbl">عدد الأعمال الحالية</div>
      <div class="val"><?= number_format($cnt) ?></div>
    </div>
    <div class="stat" style="background:#5b86e5">
      <div class="lbl">مبلغ الأعمال الحالية بعد الخصم</div>
      <div class="val"><?= fmt($total_after) ?></div>
    </div>
  </div>

  <div class="cards" style="margin-top:16px">
    <div class="stat" style="background:#1e2433">
      <div class="lbl">مبلغ الأعمال الحالية قبل الخصم</div>
      <div class="val"><?= fmt($total_before) ?></div>
    </div>
    <div class="stat" style="background:#ef5b5b">
      <div class="lbl">المبلغ المتبقي من العقد</div>
      <div class="val"><?= fmt($remaining) ?></div>
    </div>
    <div class="stat" style="background:#cdeee6;color:#1c8576">
      <div class="lbl">المقاول</div>
      <div class="val" style="font-size:16px"><?= e($contractor['contractor_name'] ?: '—') ?></div>
    </div>
  </div>

  <!-- التقارير السريعة -->
  <div class="dash-head" style="margin-top:26px;font-size:18px"><span>📊</span> التقارير</div>
  <div class="cards">
    <a class="stat report-card" style="background:#3a4a5e" href="<?= base_url('reports/orders_summary.php') ?>">
      <div class="lbl">📊 ملخص أوامر العمل</div>
      <div class="val" style="font-size:14px">كشف إجمالي خلال فترة أو نطاق أرقام</div>
    </a>
    <a class="stat report-card" style="background:#2e7d6b" href="<?= base_url('reports/surveys_summary.php') ?>">
      <div class="lbl">📊 ملخص حصورات العمل</div>
      <div class="val" style="font-size:14px">كشف إجمالي خلال فترة أو نطاق أرقام</div>
    </a>
    <a class="stat report-card" style="background:#6a5acd" href="<?= base_url('reports/items_period.php') ?>">
      <div class="lbl">📈 حصر بنود خلال فترة</div>
      <div class="val" style="font-size:14px">حصر كميات بند معيّن خلال فترة</div>
    </a>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

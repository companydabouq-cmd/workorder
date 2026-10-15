<?php
/**
 * تقرير ملخص (كشف إجمالي) لأوامر العمل أو حصورات العمل
 * يدعم التصفية بالتاريخ أو بنطاق الأرقام
 * متغيرات مطلوبة قبل التضمين:
 *   $RPT_DOC    : 'order' | 'survey'
 *   $RPT_TITLE  : 'كشف إجمالي أوامر العمل' | 'كشف إجمالي حصورات العمل'
 *   $TBL_MASTER : 'work_orders' | 'work_surveys'
 *   $TBL_ITEMS  : 'work_order_items' | 'work_survey_items'
 *   $NO_FIELD   : 'order_no' | 'survey_no'
 *   $NO_LABEL   : 'رقم أمر العمل' | 'رقم حصر العمل'
 *   $PAGE_KEY   : مفتاح القائمة
 */
require_once __DIR__ . '/../includes/functions.php';
require_staff();
has_extra();        // عمود النسبة الإضافية في المستندات (يدخل في «المجموع بعد الخصم»)
has_work_types();   // رمز نوع العمل مع رقم المستند

$mode = $_GET['mode'] ?? 'date';   // date | range
$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';
$numFrom = (int)($_GET['num_from'] ?? 0);
$numTo   = (int)($_GET['num_to'] ?? 0);
$ran = false; $rows = [];

if (isset($_GET['go'])) {
    $ran = true;
    $w = []; $p = [];
    if ($mode === 'range') {
        if ($numFrom>0){ $w[]="m.$NO_FIELD >= ?"; $p[]=$numFrom; }
        if ($numTo>0){ $w[]="m.$NO_FIELD <= ?"; $p[]=$numTo; }
    } else {
        if ($from!==''){ $w[]="m.start_date >= ?"; $p[]=$from; }
        if ($to!==''){ $w[]="m.start_date <= ?"; $p[]=$to; }
    }
    $where = $w ? ('WHERE '.implode(' AND ',$w)) : '';
    $rows = q_all("SELECT m.*, d.dept_name, s.site_name,
                   COALESCE((SELECT SUM(total_before) FROM $TBL_ITEMS WHERE {$RPT_DOC}_id=m.id),0) AS total_before,
                   (SELECT emp_name FROM ministry_employees WHERE id=m.supervisor1_id) AS sup_name
                   FROM $TBL_MASTER m
                   LEFT JOIN departments d ON d.id=m.dept_id
                   LEFT JOIN worksites s ON s.id=m.site_id
                   $where ORDER BY m.$NO_FIELD ASC", $p);
}

// وضع الطباعة (بدون قائمة جانبية)
$isPrint = isset($_GET['print']);

if (!$isPrint) require __DIR__ . '/../includes/header.php';

$ministry=get_ministry()['ministry_name'];
$contractor=get_contractor()['contractor_name'];
$contract=get_contract() ?: [];

// اسم الإدارة في الترويسة: إن كانت كل المستندات لنفس الإدارة يظهر اسمها،
// وإلا يظهر النص العام من صفحة «الترويسة والتذييل»
$headDept = print_settings_all()['summary_dept_line'];
if ($rows) {
    $depts = array_unique(array_filter(array_map(function($r){ return $r['dept_name']; }, $rows)));
    if (count($depts) === 1) $headDept = reset($depts);
}

// حساب الإجماليات
$grandBefore=0; $grandAfter=0;
foreach($rows as $r){
    $grandBefore += $r['total_before'];
    $grandAfter  += doc_net($r['total_before'], (float)$r['discount_percent'], (float)($r['extra_percent'] ?? 0));
}
$logo = print_logo_url();
$PS   = print_settings_all();
?>
<?php if($isPrint): ?>
<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8">
<title><?= e($RPT_TITLE) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Tajawal',sans-serif;direction:rtl;color:#111;font-size:11px;background:#fff;padding:8mm}
.head{display:flex;justify-content:space-between;align-items:center;border-bottom:2.5px solid #1a1a1a;padding-bottom:8px}
.head .r{font-weight:800;font-size:13px;line-height:1.8}
.head .l{font-weight:800;font-size:11px;text-align:left;white-space:nowrap;max-width:260px}
.head .c img{width:66px;height:66px;object-fit:contain}
.contract-box{border:1.5px solid #1a1a1a;margin:8px 0;padding:7px;text-align:center;font-weight:800;font-size:12px;background:#f6f7f9;border-radius:5px}
.contract-box .name{font-weight:500;font-size:10.5px;margin-top:4px}
.rpt-title{text-align:center;font-size:16px;font-weight:800;margin:12px 0 4px}
.rpt-period{text-align:center;font-size:12px;font-weight:700;margin-bottom:10px}
table{width:100%;border-collapse:collapse;margin-top:6px}
th{border:1px solid #333;background:#eef0f3;padding:5px;font-size:10px;font-weight:800}
td{border:1px solid #333;padding:4px 5px;font-size:9.5px;text-align:center}
tbody tr:nth-child(even){background:#f7f8fa}
tfoot td{font-weight:800;background:#eef0f3;font-size:11px}
.toolbar{margin-bottom:10px}
.toolbar button{background:#1a1a1a;color:#fff;border:0;padding:10px 20px;border-radius:8px;cursor:pointer;font-family:inherit;font-size:13px;font-weight:700}
@media print{.toolbar{display:none}body{padding:0}}
@page{size:A4;margin:8mm}
</style></head><body>
<div class="toolbar"><button onclick="window.print()">🖨️ طباعة</button></div>
<div class="head">
  <div class="r"><?= e($ministry) ?><br><?= e($headDept) ?></div>
  <div class="c"><img src="<?= e($logo) ?>" alt="logo"></div>
  <div class="l"><?= e($contractor) ?><?php if($PS['head_left_line2']!==''): ?><br><?= e($PS['head_left_line2']) ?><?php endif; ?></div>
</div>
<div class="contract-box"><?= e($PS['contract_label']) ?> ( <?= e($contract['contract_no']??'') ?> )
  <div class="name"><?= e($contract['contract_name']??'') ?></div></div>
<div class="rpt-title"><?= e($RPT_TITLE) ?></div>
<div class="rpt-period">
  <?php if($mode==='range'): ?>
    من رقم: <?= (int)$numFrom ?> &nbsp; إلى رقم: <?= (int)$numTo ?>
  <?php else: ?>
    خلال الفترة من : <?= e($from?fmt_date($from):'—') ?> &nbsp; الى : <?= e($to?fmt_date($to):'—') ?>
  <?php endif; ?>
</div>
<table>
  <thead><tr>
    <th style="width:30px">م</th><th style="width:55px"><?= e($NO_LABEL) ?></th>
    <th>اسم الإدارة</th><th>موقع العمل</th>
    <th style="width:62px">تاريخ بدء العمل</th><th style="width:62px">تاريخ نهاية العمل</th>
    <th style="width:40px">مدة التنفيذ</th><th>اسم المشرف</th><th style="width:75px">المجموع بعد الخصم</th>
  </tr></thead>
  <tbody>
  <?php $n=0; foreach($rows as $r): $n++; $after=doc_net($r['total_before'], (float)$r['discount_percent'], (float)($r['extra_percent'] ?? 0)); ?>
    <tr>
      <td><?= $n ?></td><td><?= e(doc_no_label($r[$NO_FIELD], $r['work_type_code'] ?? '')) ?></td>
      <td><?= e($r['dept_name']??'—') ?></td><td><?= e($r['site_name']??'—') ?></td>
      <td><?= fmt_date($r['start_date']) ?></td><td><?= fmt_date($r['end_date']) ?></td>
      <td><?= (int)$r['exec_days'] ?></td><td><?= e($r['sup_name']??'') ?></td>
      <td><?= fmt($after) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr><td colspan="8">الإجمالي العام بعد الخصم — عدد المستندات: <?= count($rows) ?></td>
        <td><?= fmt($grandAfter) ?></td></tr>
  </tfoot>
</table>
</body></html>
<?php return; endif; ?>

<div class="page-head"><div class="page-title"><span class="ic">📊</span> <?= e($RPT_TITLE) ?></div></div>

<div class="panel" style="margin-bottom:18px">
  <form method="get" id="rptForm">
    <input type="hidden" name="mode" id="modeField" value="<?= e($mode) ?>">
    <div style="display:flex;gap:10px;margin-bottom:14px">
      <button type="button" class="btn <?= $mode==='date'?'btn-primary':'btn-soft' ?>" onclick="setMode('date')">تصفية بالتاريخ</button>
      <button type="button" class="btn <?= $mode==='range'?'btn-primary':'btn-soft' ?>" onclick="setMode('range')">تصفية بنطاق الأرقام</button>
    </div>
    <div id="dateBox" class="grid4" style="align-items:end;<?= $mode==='range'?'display:none':'' ?>">
      <div class="field"><label>من تاريخ</label><input type="date" name="from" value="<?= e($from) ?>"></div>
      <div class="field"><label>إلى تاريخ</label><input type="date" name="to" value="<?= e($to) ?>"></div>
    </div>
    <div id="rangeBox" class="grid4" style="align-items:end;<?= $mode==='date'?'display:none':'' ?>">
      <div class="field"><label>من رقم</label><input type="number" name="num_from" value="<?= $numFrom?:'' ?>"></div>
      <div class="field"><label>إلى رقم</label><input type="number" name="num_to" value="<?= $numTo?:'' ?>"></div>
    </div>
    <div style="margin-top:14px">
      <button class="btn btn-primary" name="go" value="1">📊 عرض الكشف</button>
      <?php if($ran && $rows): ?>
        <a class="btn btn-green" target="_blank" href="?print=1&<?= http_build_query(['mode'=>$mode,'from'=>$from,'to'=>$to,'num_from'=>$numFrom,'num_to'=>$numTo,'go'=>1]) ?>">🖨️ طباعة الكشف</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<?php if($ran): ?>
<div class="panel">
  <div style="overflow-x:auto">
  <table class="tbl">
    <thead><tr>
      <th>م</th><th><?= e($NO_LABEL) ?></th><th>الإدارة</th><th>موقع العمل</th>
      <th>تاريخ البداية</th><th>تاريخ النهاية</th><th>مدة التنفيذ</th><th>المشرف</th><th>المجموع بعد الخصم</th>
    </tr></thead>
    <tbody>
    <?php if(!$rows): ?><tr><td colspan="9" class="empty">لا توجد نتائج</td></tr>
    <?php else: $n=0; foreach($rows as $r): $n++; $after=doc_net($r['total_before'], (float)$r['discount_percent'], (float)($r['extra_percent'] ?? 0)); ?>
      <tr>
        <td><?= $n ?></td><td><?= e(doc_no_label($r[$NO_FIELD], $r['work_type_code'] ?? '')) ?></td>
        <td><?= e($r['dept_name']??'—') ?></td><td><?= e($r['site_name']??'—') ?></td>
        <td><?= fmt_date($r['start_date']) ?></td><td><?= fmt_date($r['end_date']) ?></td>
        <td><?= (int)$r['exec_days'] ?></td><td><?= e($r['sup_name']??'') ?></td>
        <td><?= fmt($after) ?></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
    <?php if($rows): ?>
    <tfoot><tr style="font-weight:800;background:#f6f0e0">
      <td colspan="8">الإجمالي العام بعد الخصم — العدد: <?= count($rows) ?></td>
      <td><?= fmt($grandAfter) ?></td>
    </tr></tfoot>
    <?php endif; ?>
  </table>
  </div>
</div>
<?php endif; ?>

<script>
function setMode(m){
  document.getElementById('modeField').value=m;
  document.getElementById('dateBox').style.display = (m==='date')?'':'none';
  document.getElementById('rangeBox').style.display = (m==='range')?'':'none';
  document.querySelectorAll('#rptForm .btn-primary, #rptForm .btn-soft').forEach(function(b){
    if(b.name==='go') return;
    b.className='btn '+((b.textContent.indexOf(m==='date'?'التاريخ':'الأرقام')>=0)?'btn-primary':'btn-soft');
  });
}
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>

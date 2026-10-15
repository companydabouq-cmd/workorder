<?php
/**
 * قالب طباعة مشترك. متغيرات مطلوبة:
 *  $DOC ('survey'|'order'), $DOC_TITLE, $TBL_MASTER, $TBL_ITEMS, $NO_FIELD,
 *  $WITH_HANDOVER (bool) - صفحة محضر الاستلام (لأمر العمل فقط)
 */
require_once __DIR__ . '/../includes/functions.php';
require_login();
// المشاهد يطبع أوامر العمل فقط (لا الحصر المبدئي ولا حصر العمل)
if (is_viewer() && $DOC !== 'order') { header('Location: ' . base_url('pages/work_orders.php')); exit; }
has_extra();        // عمودا النسبة الإضافية في المستندات (قبل قراءة المستند)
has_work_types();   // رمز نوع العمل وبيان الأعمال

$id=(int)($_GET['id']??0);
$doc=q_row("SELECT * FROM $TBL_MASTER WHERE id=?", [$id]);
if(!$doc){ die('المستند غير موجود'); }
$items=q_all("SELECT * FROM $TBL_ITEMS WHERE {$DOC}_id=? ORDER BY id ASC", [$id]);

/* كل نص ثابت في الترويسة وكتلة التواقيع ومحضر الاستلام يأتي من
   صفحة «الترويسة والتذييل» (pages/print_settings.php) — لا شيء مكتوب هنا. */
$PS         = print_settings_all();
$ministry   = get_ministry()['ministry_name'] ?? '';
$contractor = get_contractor()['contractor_name'];
$contract   = get_contract() ?: [];
$disc       = (float)$doc['discount_percent'];

function name_of($tbl,$id){ if(!$id) return ''; $r=q_row("SELECT emp_name FROM $tbl WHERE id=?", [$id]); return $r?$r['emp_name']:''; }
$dept=q_val("SELECT dept_name FROM departments WHERE id=?", [$doc['dept_id']]);
$site=q_val("SELECT site_name FROM worksites WHERE id=?", [$doc['site_id']]);
$sup1=name_of('ministry_employees',$doc['supervisor1_id']);
$sup2=name_of('ministry_employees',$doc['supervisor2_id']);
$headEng=name_of('ministry_employees',$doc['head_eng_id']);
$rep=name_of('contractor_employees',$doc['rep_contractor_id']);

$sup1Title=''; $sup1Entity='';
if($doc['supervisor1_id']){
  $e=q_row("SELECT e.entity,t.title_name FROM ministry_employees e LEFT JOIN ministry_job_titles t ON t.id=e.job_title_id WHERE e.id=?",[$doc['supervisor1_id']]);
  if($e){ $sup1Title=$e['title_name']; $sup1Entity=$e['entity']; }
}
$repTitle='';
if($doc['rep_contractor_id']){
  $repTitle=q_val("SELECT t.title_name FROM contractor_employees e LEFT JOIN contractor_job_titles t ON t.id=e.job_title_id WHERE e.id=?",[$doc['rep_contractor_id']]);
}

$totalBefore=0; foreach($items as $it) $totalBefore+=$it['total_before'];
// النسبة الإضافية للمنطقة (نسخة المستند) والخصم — ترتيبهما من صفحة «اسم المقاول ونسبة الخصم»
$extraPct    = (float)($doc['extra_percent'] ?? 0);
$extraArea   = (string)($doc['extra_area'] ?? '');
$TOT         = doc_totals($totalBefore, $disc, $extraPct, (float)$doc['delay_fine']);
$discAmount  = $TOT['disc'];
$extraAmount = $TOT['extra'];
$dueAmount   = $TOT['due'];
$extraText   = $extraPct > 0 ? extra_line($extraPct, $extraArea) : '';
$discLabel   = pct_label($disc);

$logo = print_logo_url();

/* شكل الطباعة من «الترويسة والتذييل» (الأسماء في print_layouts()): «البلدية» (الافتراضي) أو «المالية» أو «الإعاقة + الشئون».
   يمكن تجاوزه مؤقتاً للرابط: ?layout=classic أو landscape أو payment */
$LAYOUT = in_array($_GET['layout'] ?? '', ['classic', 'landscape', 'payment'], true) ? $_GET['layout'] : $PS['print_layout'];
if ($LAYOUT === 'payment') {
    require __DIR__ . '/print_payment.php';
    return;
}
if ($LAYOUT !== 'classic') {
    require __DIR__ . '/print_landscape.php';
    return;
}

/* ما تحتاجه الترويسة وكتلة التواقيع — يُمرَّر كمصفوفة واحدة بدل عشرة وسائط */
$HEAD = [
  'ministry'   => $ministry,
  'line2'      => $PS['head_right_line2'],
  'contractor' => $contractor,
  'cline2'     => $PS['head_left_line2'],
  'logo'       => $logo,
  'contract'   => $contract,
  'clabel'     => $PS['contract_label'],
];
$CLOSE = [
  'sign1'       => $PS['sign1_title'],
  'sign2'       => $PS['sign2_title'],
  'sign3'       => $PS['sign3_title'],
  'repRow'      => $PS['rep_row_label'],
  'bottomRight' => $PS['bottom_right_label'],
  'bottomLeft'  => $PS['bottom_left_label'],
  'sup1'        => $sup1,
  'discLabel'   => $discLabel,
  'totalBefore' => $totalBefore,
  'discAmount'  => $discAmount,
  'delayFine'   => $doc['delay_fine'],
  'dueAmount'   => $dueAmount,
  // سطور المبالغ بعد «الإجمالي قبل الخصم»: النسبة الإضافية قبل الخصم أو بعده، ثم الغرامة والمستحق
  'amtRows'     => array_merge(
      $extraPct > 0 && $disc <= 0
        ? [[$extraText, '+' . fmt($extraAmount), '']]          // منطقة بلا خصم: النسبة الإضافية فقط
        : ($extraPct > 0 ? ($TOT['before'] ? [[$extraText, '+' . fmt($extraAmount), ''], ['مبلغ الخصم ' . $discLabel . '%-', '-' . fmt($discAmount), '']]
                          : [['مبلغ الخصم ' . $discLabel . '%-', '-' . fmt($discAmount), ''], [$extraText, '+' . fmt($extraAmount), '']])
        : [['مبلغ الخصم ' . $discLabel . '%-', '-' . fmt($discAmount), '']]),
      [['غرامة التأخير', fmt($doc['delay_fine']), ''], ['المبلغ المستحق', fmt($dueAmount), 'due']]
  ),
  'small'       => !empty($WITH_HANDOVER),
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title><?= e($DOC_TITLE) ?> رقم <?= (int)$doc[$NO_FIELD] ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Tajawal','Arial',sans-serif;direction:rtl;color:#111;font-size:12px;background:#9aa0aa}
  /* حشوة المعاينة 14mm = هامش الطباعة 8mm + حشوتها 6mm: مساحة المحتوى في المعاينة تطابق الورقة المطبوعة تماماً
     (182mm × 269mm)، فقياس كتلة التواقيع وتوزيع الفراغ في المعاينة يصحّان للطباعة */
  .page{width:210mm;min-height:297mm;background:#fff;margin:12px auto;padding:14mm;
        page-break-after:always;position:relative;box-shadow:0 4px 24px rgba(0,0,0,.25)}
  .page.page-auto{min-height:auto}
  .page:last-child{page-break-after:auto}

  /* الترويسة */
  .head{display:flex;justify-content:space-between;align-items:center;border-bottom:2.5px solid #1a1a1a;padding-bottom:10px}
  .head .r{font-weight:800;font-size:13.5px;line-height:1.9}
  .head .l{font-weight:800;font-size:12px;text-align:left;line-height:1.6;max-width:260px;white-space:nowrap}
  .head .c{text-align:center}
  .head .c img{width:74px;height:74px;object-fit:contain}

  .contract-box{border:1.5px solid #1a1a1a;margin:10px 0;padding:9px;text-align:center;font-weight:800;font-size:12.5px;
        background:#f6f7f9;border-radius:5px}
  .contract-box .name{font-weight:500;font-size:11px;margin-top:5px;line-height:1.7}

  .doc-no{text-align:center;font-size:19px;font-weight:800;margin:12px 0;color:#1a1a1a}
  .doc-no span{background:#3a4a5e;color:#fff;padding:2px 16px;border-radius:7px;font-size:17px}

  table.info{width:100%;border-collapse:collapse}
  table.info td{border:1px solid #333;padding:5px 7px;font-size:11px}
  table.info .lbl{background:#eef0f3;font-weight:800;width:92px;white-space:nowrap}

  table.items{width:100%;border-collapse:collapse;margin-top:8px;border:2px solid #1a1a1a}
  table.items thead{display:table-header-group}
  table.items tr{page-break-inside:avoid}
  table.items th.rpt-head-cell{border:none;border-bottom:2px solid #1a1a1a;background:transparent;padding:0 0 8px;font-weight:400;text-align:right}
  table.items th{border:1px solid #333;background:#eef0f3;padding:6px;font-size:11px;font-weight:800}
  table.items td{border:1px solid #333;padding:5px 6px;font-size:10.5px;vertical-align:top}
  table.items .c{text-align:center}
  table.items tbody tr:nth-child(even){background:#f7f8fa}

  /* كتلة الإجماليات والتواقيع - لا تنكسر بين صفحتين */
  .closing{margin-top:12px;page-break-inside:avoid}
  .totals{width:100%;border-collapse:collapse}
  .totals td{border:1px solid #333;padding:6px;font-size:10.5px}
  .totals .lbl{background:#eef0f3;font-weight:800;text-align:center}
  .sign-col{width:150px}
  .totals .lbl.amt-label{width:130px;font-size:9.5px;padding:5px 4px}
  .totals.amt-sm .lbl.amt-label{width:100px;font-size:8.5px;padding:4px 3px}
  .totals.amt-sm .num{width:60px;font-size:9px}
  .stamp-cell{vertical-align:top;text-align:center;font-weight:800;font-size:11.5px;height:150px;padding-top:8px}
  .rep-row{text-align:center;font-weight:800;font-size:12px;background:#eef0f3}
  .rep-row-space{height:120px;border:1px solid #333}
  /* خانات التوقيع المقسّمة (مثل النموذج): عمود عناوين صغير + مساحة كتابة، صفّان مفصولان بخط */
  .sg-box{padding:0 !important;vertical-align:top;height:1px} /* height:1px يجعل الجدول الداخلي يملأ الخلية مهما زاد عدد سطور المبالغ */
  .sg-split{width:100%;height:100%;border-collapse:collapse}
  .sg-split td{border:1px solid #333;font-size:10px;height:37px}
  .sg-split tr:first-child td{border-bottom:none}
  .sg-lbl{width:34%;text-align:center;font-weight:700;background:#f3f5f8;padding:4px 2px;vertical-align:middle;white-space:nowrap}
  .sg-space{width:66%;text-align:center;font-weight:700;font-size:10.5px;vertical-align:middle;padding:4px 3px}
  .rep-lbl{width:18%}
  .rep-name-row{text-align:center;font-weight:700;font-size:11px;padding:8px}
  .mgr-top{border:none !important;text-align:center;font-weight:800;font-size:12px;vertical-align:top;padding-top:6px}
  .mgr-top-space{border:none !important;height:120px}
  .num{text-align:center;font-weight:700;width:72px;font-size:10px}
  .due{font-weight:800;font-size:11px;background:#eef6ff}

  /* الصف السفلي: مراقب ومدير الخدمات */
  .bottom-sign{margin-top:20px;margin-bottom:30px;width:100%;border-collapse:collapse}
  .bottom-sign>tbody>tr>td{vertical-align:top;padding:0}
  .rep-col{width:50%;padding-left:8px}
  .mgr-col{width:50%;padding-right:8px}
  .rep-box{width:100%;border-collapse:collapse}
  .rep-box td{border:1px solid #333;padding:8px;font-size:11px;text-align:center}
  .rep-box .lbl{background:#eef0f3;font-weight:800}
  .rep-name{height:90px;vertical-align:top;font-weight:700;font-size:11.5px;padding-top:10px}
  .mgr-label{text-align:center;font-weight:800;font-size:12px;padding:8px 0 0;margin-top:0}
  .mgr-space{height:150px}

  /* محضر الاستلام */
  .ho-table{width:100%;border-collapse:collapse;margin-top:8px}
  .ho-table td,.ho-table th{border:1px solid #333;padding:7px;font-size:11px;text-align:center}
  .under-sign{width:100%;border-collapse:collapse;margin-top:16px}
  .under-sign th{border:1px solid #333;background:#eef0f3;font-weight:800;font-size:12px;padding:7px;text-align:center}
  .under-sign td{border:1px solid #333}
  .ho-table th{background:#eef0f3;font-weight:800}
  .ho-note{margin:14px 0;font-size:11.5px;line-height:2;font-weight:600}
  .ho-signoff{margin-top:30px;width:240px;margin-left:0;margin-right:auto}

  .toolbar{position:fixed;top:12px;left:12px;z-index:99;display:flex;gap:8px}
  .toolbar button{background:#1a1a1a;color:#fff;border:0;padding:11px 22px;border-radius:9px;cursor:pointer;
        font-family:inherit;font-size:14px;font-weight:700;box-shadow:0 3px 10px rgba(0,0,0,.3)}
  .toolbar button:hover{background:#333}

  @media print{
    html,body{background:#fff;margin:0;padding:0}
    .toolbar{display:none}
    /* الحشوة = 14mm ناقص هامش الورقة الفعلي: مع هامش الصفحة المعتاد (8mm) تساوي 6mm كما كانت؛ وإن فرضت الطابعة أو خيار
       «الهوامش» في نافذة الطباعة هامشاً آخر يبقى المحتوى بمقاسه ومكانه (182mm × 269mm) فيصحّ قياس كتلة التواقيع وتمديد الصفوف */
    .page{margin:0;box-shadow:none;width:100%;min-height:auto;max-width:none;page-break-after:always;
          padding:max(0mm, 14mm - (297mm - 100vh) / 2) max(0mm, 14mm - (210mm - 100vw) / 2)}
    .page:last-child{page-break-after:auto}
    /* تكرار رأس الجدول (الترويسة + عناوين الأعمدة) في كل صفحة */
    table.items thead{display:table-header-group}
    table.items tfoot{display:table-footer-group}
    tr{page-break-inside:avoid}
    thead{break-inside:avoid}
    .closing{page-break-inside:avoid}
  }
  @page{size:A4 portrait;margin:8mm}
</style>
</head>
<body>
<div class="toolbar">
  <button onclick="window.print()">🖨️ طباعة</button>
</div>

<script>
/* ===== كتلة التواقيع وتوزيع الفراغ (بالقياس الفعلي لا بالتقدير) =====
   التقسيم أعلاه تقديري (أوزان)، فيُحسم مكان كتلة التواقيع هنا بقياس المساحة الحقيقية:
     1) الكتلة وحدة واحدة لا تنقسم.
     2) إن اتسعت لها المساحة المتبقية تحت جدول البنود في آخر صفحة بنود: توضع هناك، بلا أي تمديد.
     3) وإلا تنتقل كاملة إلى صفحة تالية، والمساحة التي فرغت تحت الجدول تُوزَّع بالتساوي على صفوفه
        (زيادة ارتفاع فقط: لا خط ولا عرض أعمدة ولا محتوى)، بحد أعلى للصف حتى لا يتضخم. */
(function(){
  function mm(v){
    var d=document.createElement('div');
    d.style.cssText='position:absolute;visibility:hidden;width:1px;height:'+v+'mm';
    document.body.appendChild(d);
    var h=d.getBoundingClientRect().height; d.remove(); return h;
  }
  function fitClosing(){
    var itemPages=document.querySelectorAll('.page.items-page');
    var closing=document.querySelector('.closing');
    if(!itemPages.length || !closing) return;
    var last=itemPages[itemPages.length-1];
    var table=last.querySelector('table.items');
    if(!table || !table.tBodies.length) return;
    var rows=[].slice.call(table.tBodies[0].rows);
    rows.forEach(function(tr){ tr.style.height=''; });              // إعادة القياس من الارتفاع الطبيعي

    var H=mm(269);                 // ارتفاع المحتوى في الورقة: A4 ناقص هوامش الطباعة وحشوة الصفحة
    var SAFE_FIT=mm(2);            // هامش أمان لقرار «تتسع»
    var SAFE_FILL=mm(4);           // يُترك أسفل الجدول بعد التمديد حتى لا ينزل آخر صف إلى ورقة جديدة
    var MAX_ADD=mm(30);            // أقصى زيادة للصف الواحد

    var top=last.getBoundingClientRect().top+parseFloat(getComputedStyle(last).paddingTop);
    var avail=H-(table.getBoundingClientRect().bottom-top);         // الفراغ تحت جدول البنود
    if(avail<0) return;            // الجدول نفسه أطول من الورقة: يبقى السلوك الحالي كما هو
    var need=closing.getBoundingClientRect().height+parseFloat(getComputedStyle(closing).marginTop);
    var signPage=document.querySelector('.page.sign-page');

    if(need<=avail-SAFE_FIT){      // تتسع: مكانها تحت الجدول، ولا تمديد
      if(closing.parentNode!==last){ last.appendChild(closing); if(signPage) signPage.remove(); }
      return;
    }
    if(closing.parentNode===last){ // لا تتسع وهي الآن تحت الجدول: تنتقل كاملة إلى صفحة تالية بالترويسة نفسها
      signPage=document.createElement('div'); signPage.className='page sign-page';
      for(var n=last.firstElementChild; n && n!==table; n=n.nextElementSibling) signPage.appendChild(n.cloneNode(true));
      signPage.appendChild(closing);
      last.parentNode.insertBefore(signPage, last.nextSibling);
    }
    // توزيع الفراغ على صفوف جدول البنود: الزيادة نفسها لكل صف
    var extra=avail-SAFE_FILL;
    if(!rows.length || extra<=0) return;
    var add=Math.min(extra/rows.length, MAX_ADD);
    var hs=rows.map(function(tr){ return tr.getBoundingClientRect().height; });
    rows.forEach(function(tr,i){ tr.style.height=(hs[i]+add).toFixed(2)+'px'; });
  }
  function run(){ try{ fitClosing(); }catch(e){} }
  if(document.fonts && document.fonts.ready){ document.fonts.ready.then(run); }   // بعد تحميل الخط: القياس بالخط النهائي
  window.addEventListener('load', run);
})();
</script>

<?php
function head_block($H){ ?>
  <div class="head">
    <div class="r"><?= e($H['ministry']) ?><?php if($H['line2']!==''): ?><br><?= e($H['line2']) ?><?php endif; ?></div>
    <div class="c"><img src="<?= e($H['logo']) ?>" alt="logo"></div>
    <div class="l"><?= e($H['contractor']) ?><?php if($H['cline2']!==''): ?><br><?= e($H['cline2']) ?><?php endif; ?></div>
  </div>
  <div class="contract-box">
    <?= e($H['clabel']) ?> ( <?= e($H['contract']['contract_no']??'') ?> )
    <div class="name"><?= e($H['contract']['contract_name']??'') ?></div>
  </div>
<?php }

/* كتلة الإجماليات والتواقيع — تعريف واحد يُستدعى في آخر صفحة بنود أو في صفحة مستقلة */
function closing_block($C){ ?>
  <div class="closing">
    <table class="totals<?= $C['small'] ? ' amt-sm' : '' ?>">
      <tr>
        <td class="lbl sign-col"><?= e($C['sign1']) ?></td>
        <td class="lbl sign-col"><?= e($C['sign2']) ?></td>
        <td class="lbl sign-col"><?= e($C['sign3']) ?></td>
        <td class="lbl amt-label">الإجمالي قبل الخصم</td>
        <td class="num"><?= fmt($C['totalBefore']) ?></td>
      </tr>
      <?php $span = count($C['amtRows']); $first = $C['amtRows'][0]; ?>
      <tr>
        <td rowspan="<?= $span ?>" class="sg-box">
          <table class="sg-split">
            <tr><td class="sg-lbl">الإسم</td><td class="sg-space"><?= e($C['sup1']) ?></td></tr>
            <tr><td class="sg-lbl">التوقيع</td><td class="sg-space"></td></tr>
          </table>
        </td>
        <td rowspan="<?= $span ?>" class="sg-box">
          <table class="sg-split">
            <tr><td class="sg-lbl">التوقيع</td><td class="sg-space"></td></tr>
            <tr><td class="sg-lbl">الختم</td><td class="sg-space"></td></tr>
          </table>
        </td>
        <td rowspan="<?= $span ?>" class="sg-box">
          <table class="sg-split">
            <tr><td class="sg-lbl">التوقيع</td><td class="sg-space"></td></tr>
            <tr><td class="sg-lbl">الختم</td><td class="sg-space"></td></tr>
          </table>
        </td>
        <td class="lbl amt-label"><?= e($first[0]) ?></td>
        <td class="num <?= $first[2] ?>"><?= e($first[1]) ?></td>
      </tr>
      <?php foreach (array_slice($C['amtRows'], 1) as $ar): ?>
      <tr>
        <td class="lbl amt-label"><?= e($ar[0]) ?></td>
        <td class="num <?= $ar[2] ?>"><?= e($ar[1]) ?></td>
      </tr>
      <?php endforeach; ?>
      <tr>
        <td colspan="3" class="lbl rep-row"><?= e($C['repRow']) ?></td>
        <td colspan="2" style="border:none"></td>
      </tr>
      <tr>
        <td colspan="3" class="sg-box">
          <table class="sg-split">
            <tr><td class="sg-lbl rep-lbl">الإسم</td><td class="sg-space"></td></tr>
            <tr><td class="sg-lbl rep-lbl">التوقيع</td><td class="sg-space"></td></tr>
          </table>
        </td>
        <td colspan="2" style="border:none"></td>
      </tr>
    </table>

    <!-- التوقيعان السفليان جنباً إلى جنب بلا حدود -->
    <table class="bottom-sign">
      <tr>
        <td class="rep-col">
          <div class="mgr-label"><?= e($C['bottomRight']) ?></div>
          <div class="mgr-space"></div>
        </td>
        <td class="mgr-col">
          <div class="mgr-label"><?= e($C['bottomLeft']) ?></div>
          <div class="mgr-space"></div>
        </td>
      </tr>
    </table>
  </div>
<?php }

function info_block($doc,$dept,$site,$sup1,$DOC_TITLE,$NO_FIELD){ ?>
  <div class="doc-no"><?= e($DOC_TITLE) ?> رقم <span><?= (int)$doc[$NO_FIELD] ?></span></div>
  <table class="info">
    <tr>
      <td class="lbl">اسم الإدارة</td><td><?= e($dept) ?></td>
      <td class="lbl">نوع العمل</td><td><?= e($doc['work_type']) ?></td>
      <td class="lbl">مدة التنفيذ</td><td><?= (int)$doc['exec_days'] ?> يوم</td>
    </tr>
    <tr>
      <td class="lbl">موقع العمل</td><td><?= e($site) ?></td>
      <td class="lbl">تاريخ بدء العمل</td><td><?= fmt_date($doc['start_date']) ?></td>
      <td class="lbl">تاريخ انتهاء العمل</td><td><?= fmt_date($doc['end_date']) ?></td>
    </tr>
    <tr>
      <td class="lbl">اسم المشرف</td><td><?= e($sup1) ?></td>
      <td class="lbl">فترة الضمان</td><td><?= e($doc['warranty_months']) ?></td>
      <td class="lbl">مدة التأخير</td><td><?= (int)$doc['delay_days'] ?></td>
    </tr>
  </table>
<?php }
?>

<?php
// ===== تقسيم ذكي: كل صفحة تُملأ حسب "وزن" البنود (الوصف الطويل يأخذ مساحة أكبر) =====
// الصفحة الأولى ترويستها أكبر (صندوق العقد + المعلومات) فتسع وزناً أقل.
$CAP_FIRST = 38;   // سعة الصفحة الأولى (وحدات وزن)
$CAP_REST  = 48;   // سعة الصفحات التالية
$CAP_LAST_EXTRA = 24 + ($extraPct > 0 ? 2 : 0); // وزن كتلة التواقيع (مقاس فعلياً: 395px ≈ 24 وحدة) يُخصم من سعة آخر صفحة

// احسب وزن كل بند: سطر أساسي + سطر لكل ~55 حرف في الوصف
$weights = [];
foreach($items as $it){
  $len = mb_strlen($it['item_details'] ?? '', 'UTF-8');
  $lines = max(1, (int)ceil($len / 55));
  $weights[] = $lines + 0.4;   // 0.4 هامش للحشوة
}

// --- دالة التوزيع: تملأ الصفحات حسب سعة معطاة ---
function pack_pages($weights, $capFirst, $capRest){
  $pages = []; $cur = []; $curW = 0; $cap = $capFirst;
  for($k=0; $k<count($weights); $k++){
    $w = $weights[$k];
    if($curW + $w > $cap && count($cur) > 0){
      $pages[] = $cur; $cur = []; $curW = 0; $cap = $capRest;
    }
    $cur[] = $k; $curW += $w;
  }
  if(count($cur) > 0) $pages[] = $cur;
  if(count($pages) === 0) $pages[] = [];
  return $pages;
}
// --- هل تتسع كتلة التواقيع في آخر صفحة من توزيع ما؟ ---
function sign_fits($pages, $weights, $capFirst, $capRest, $signW){
  $li = count($pages) - 1;
  $lw = 0; foreach($pages[$li] as $k){ $lw += $weights[$k]; }
  $lc = ($li === 0) ? $capFirst : $capRest;
  return ($lw <= $lc - $signW);
}

// المحاولة 1: ملء متتابع (أقصى استغلال للصفحات)
$pagesArr = pack_pages($weights, $CAP_FIRST, $CAP_REST);
$signOnNewPage = !sign_fits($pagesArr, $weights, $CAP_FIRST, $CAP_REST, $CAP_LAST_EXTRA);

// المحاولة 2 (تلقائية): إن تيتّمت كتلة التواقيع في صفحة شبه فارغة،
// وزّع البنود بشكل متوازن على نفس عدد الصفحات لإفساح مكان لها.
if($signOnNewPage && count($pagesArr) > 1){
  $totalW = array_sum($weights);
  $nPages = count($pagesArr);
  // سعة متوازنة: الوزن الكلي + كتلة التواقيع موزّعاً على نفس عدد الصفحات
  $balanced = ($totalW + $CAP_LAST_EXTRA) / $nPages;
  $bFirst = min($CAP_FIRST, max(8, $balanced));
  $bRest  = min($CAP_REST,  max(8, $balanced));
  $try = pack_pages($weights, $bFirst, $bRest);
  // اقبل التوزيع المتوازن فقط إن لم يزد عدد الصفحات وأتاح مكاناً للتواقيع
  if(count($try) <= $nPages && sign_fits($try, $weights, $bFirst, $bRest, $CAP_LAST_EXTRA)){
    $pagesArr = $try;
    $signOnNewPage = false;
  }
}

$pagesCount = count($pagesArr) + ($signOnNewPage ? 1 : 0);

for ($p = 0; $p < count($pagesArr); $p++):
  $sliceIdx = $pagesArr[$p];
  $isLastItemsPage = ($p === count($pagesArr) - 1);
  $showSignHere = $isLastItemsPage && !$signOnNewPage;
?>
<div class="page items-page">
  <?php head_block($HEAD); ?>
  <?php info_block($doc,$dept,$site,$sup1,$DOC_TITLE,$NO_FIELD); ?>

  <table class="items">
    <thead>
      <tr>
        <th style="width:34px">م</th><th style="width:70px">رقم البند</th><th>نص البند</th>
        <th style="width:50px">الوحدة</th><th style="width:55px">الكمية</th>
        <th style="width:85px">سعر الوحدة قبل الخصم</th><th style="width:90px">المبلغ الإجمالي قبل الخصم</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach($sliceIdx as $k): $it = $items[$k]; $n = $k + 1; ?>
      <tr>
        <td class="c"><?= $n ?></td>
        <td class="c"><?= e($it['item_no']) ?></td>
        <td><?= e($it['item_details']) ?></td>
        <td class="c"><?= e($it['unit']) ?></td>
        <td class="c"><?= fmt($it['qty']) ?></td>
        <td class="c"><?= fmt($it['unit_price']) ?></td>
        <td class="c"><?= fmt($it['total_before']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <?php if($showSignHere) closing_block($CLOSE); /* كتلة الإجماليات والتواقيع — آخر صفحة فقط */ ?>
</div>
<?php endfor; ?>

<?php if($signOnNewPage): ?>
<!-- صفحة منفصلة لكتلة التواقيع (مع الترويسة الثابتة) -->
<div class="page sign-page">
  <?php head_block($HEAD); ?>
  <?php info_block($doc,$dept,$site,$sup1,$DOC_TITLE,$NO_FIELD); ?>
  <?php closing_block($CLOSE); ?>
</div>
<?php endif; ?>

<?php if(!empty($WITH_HANDOVER)): ?>
<?php
  // نصوص المحضر من الإعدادات؛ الرموز تُستبدل بقيم المستند
  $hoVars = [
    '{تاريخ_البدء}'     => fmt_date($doc['start_date']),
    '{تاريخ_الانتهاء}'  => fmt_date($doc['end_date']),
    '{رقم_المستند}'     => (string)(int)$doc[$NO_FIELD],
  ];
  $hoNote1 = strtr($PS['handover_note1'], $hoVars);
  $hoNote2 = strtr($PS['handover_note2'], $hoVars);
  $hoEnt   = [$PS['handover_entity1'], $PS['handover_entity2'], $PS['handover_entity3']];
?>
<!-- ===== محضر استلام الأعمال (أمر العمل فقط) ===== -->
<div class="page">
  <?php head_block($HEAD); ?>
  <div class="doc-no"><?= e($PS['handover_title']) ?> <span><?= (int)$doc[$NO_FIELD] ?></span></div>
  <p class="ho-note"><?= e($hoNote1) ?></p>
  <table class="ho-table">
    <thead><tr><th style="width:35px">م</th><th style="width:34%">الاسم</th><th style="width:34%">الصفة</th><th>الجهة التابع لها</th></tr></thead>
    <tbody>
      <?php foreach($hoEnt as $i => $ent): ?>
      <tr style="height:50px"><td><?= $i+1 ?></td><td></td><td></td><td><?= e($ent) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <p class="ho-note"><?= e($hoNote2) ?></p>
  <table class="ho-table" style="margin-top:14px">
    <thead>
      <tr><th colspan="3" style="font-size:14px">التوقيع</th></tr>
      <tr><?php foreach($hoEnt as $ent): ?><th style="width:33.33%"><?= e($ent) ?></th><?php endforeach; ?></tr>
    </thead>
    <tbody>
      <tr style="height:120px"><td></td><td></td><td></td></tr>
    </tbody>
  </table>
</div>
<?php endif; ?>

</body>
</html>

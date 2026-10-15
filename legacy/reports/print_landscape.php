<?php
/**
 * شكل «البلدية» (A4 أفقي) — على نموذج «عرض التقرير»:
 *   1) صفحة غلاف (تُلغى من «الترويسة والتذييل»)
 *   2) صفحات البنود: الترويسة كاملة + الجدول + التذييل (ملاحظات وتواقيع) في كل صفحة،
 *      مع «صفحة X من N» وقيمة المستند قبل وبعد النسبة
 *   3) محضر الاستلام (أمر العمل فقط) بنفس الترويسة
 *
 * يُضمَّن من print_template.php بعد تجهيز متغيرات المستند ($doc, $items, $PS, ...).
 * توزيع البنود على الصفحات يتم في المتصفح بقياس ارتفاع كل صف فعلياً،
 * فلا يفيض الجدول عن صفحته مهما طال وصف البند.
 */
$area = '';
if ($doc['site_id'] && has_site_area()) {
    $area = (string) q_val("SELECT area FROM worksites WHERE id=?", [$doc['site_id']]);
}
$logo2     = print_logo2_url();
// الرقم مع رمز نوع العمل (43/م)؛ بدون نوع برمز تُستعمل «لاحقة رقم المستند» الثابتة من الإعدادات
$wtCode    = work_types_enabled() ? trim((string)($doc['work_type_code'] ?? '')) : '';
$docNo     = $wtCode !== '' ? doc_no_label($doc[$NO_FIELD], $wtCode) : (int)$doc[$NO_FIELD] . $PS['l_no_suffix'];
$workType  = trim((string)($doc['work_type'] ?? ''));
$workDesc  = trim((string)($doc['work_desc'] ?? ''));
$fine      = (float)$doc['delay_fine'];
$noDisc    = $disc <= 0 && $extraPct > 0;   // منطقة بلا خصم: «نسبة الخصم : لا يُطبق»
/* صندوق القيمة: الإجمالي، ثم النسبة الإضافية والخصم بالترتيب المحدد في الإعدادات، ثم الغرامة والمستحق */
$vrows = [[$PS['l_value_label'], $totalBefore]];
if ($extraPct > 0 && $TOT['before']) {
    $vrows[] = [$extraText, $extraAmount];
    $vrows[] = [$PS['l_value_after_label'], $TOT['net']];
} elseif ($extraPct > 0) {
    if ($disc > 0) $vrows[] = [$PS['l_value_after_label'], $totalBefore - $discAmount];
    $vrows[] = [$extraText, $extraAmount];
    if ($fine <= 0) $vrows[] = ['المبلغ المستحق', $dueAmount];
} else {
    $vrows[] = [$PS['l_value_after_label'], $TOT['net']];
}
if ($fine > 0) { $vrows[] = ['غرامة التأخير', $fine]; $vrows[] = ['المبلغ المستحق', $dueAmount]; }
$execText  = strtr($PS['l_exec_text'], ['{المدة}' => (int)$doc['exec_days']]);
$notes     = ps_lines('l_notes');
$signs     = ps_lines('l_sign_titles');
$cur       = $PS['l_currency'];

/* سطر «عنوان : قيمة» — لا يُطبع إن كان العنوان أو القيمة فارغاً.
   $ltr للقيم الرقمية مثل «-53 %» حتى تُعرض بنفس ترتيبها المكتوب. */
function L_kv($label, $value, $ltr = false) {
    if (trim((string)$label) === '' || trim((string)$value) === '') return;
    echo '<div class="kv"><span class="k">' . e($label) . '</span><span class="sep">:</span><span class="v"' . ($ltr ? ' dir="ltr"' : '') . '>' . e($value) . '</span></div>';
}

/* الترويسة — $mode: 'items' لصفحات البنود، 'handover' لمحضر الاستلام */
function L_head($V, $mode = 'items') {
    $PS = $V['PS']; ?>
  <div class="lh">
    <div class="lh-top">
      <div class="lh-r">
        <div class="cname"><?= e($V['contract']['contract_name'] ?? '') ?></div>
        <?php L_kv($PS['l_row1_label'], $PS['l_row1_value']); ?>
        <?php L_kv($PS['l_contract_label'], $V['contract']['contract_no'] ?? ''); ?>
        <?php L_kv($PS['l_contractor_label'], $V['contractor']); ?>
      </div>
      <div class="lh-c">
        <img class="logo1" src="<?= e($V['logo']) ?>" alt="">
        <?php if ($PS['l_center_line'] !== ''): ?><div class="cline"><?= e($PS['l_center_line']) ?></div><?php endif; ?>
        <?php if ($mode === 'items'): ?>
          <div class="dno"><?= e($V['DOC_TITLE']) ?> رقم : <b><?= e($V['docNo']) ?></b></div>
        <?php endif; ?>
      </div>
      <div class="lh-l">
        <div class="pg-row">
          <?php if ($mode === 'items' && $PS['l_page_label'] !== ''): ?>
            <div class="pg"><span class="k"><?= e($PS['l_page_label']) ?> :</span> <span class="pg-no"></span></div>
          <?php else: ?><div></div><?php endif; ?>
          <?php if ($V['logo2']): ?><img class="logo2" src="<?= e($V['logo2']) ?>" alt=""><?php endif; ?>
        </div>
        <?php if ($mode === 'items'): ?>
        <div class="vbox">
          <?php foreach ($V['vrows'] as [$lbl, $amt]): ?>
          <div class="vr"><span><?= e($lbl) ?></span><b><?= fmt($amt) ?> <?= e($V['cur']) ?></b></div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($mode === 'items'): ?>
    <div class="lh-grid">
      <div class="g1"><?php L_kv($PS['l_discount_label'], $V['noDisc'] ? 'لا يُطبق' : ($V['discLabel'] !== '0' ? '-' . $V['discLabel'] . ' %' : '0 %'), !$V['noDisc']); ?></div>
      <div class="g2-4"><?php L_kv($PS['l_exec_label'], $V['execText']); ?></div>
      <div class="g1"><?php L_kv($PS['l_row5_label'], $PS['l_row5_value']); ?></div>
      <div><?php L_kv($PS['l_area_label'], $V['area']); ?></div>
      <div><?php L_kv($PS['l_site_label'], $V['site']); ?></div>
      <div><?php L_kv($PS['l_dept_label'], $V['dept']); ?></div>
      <?php if ($V['workType'] !== '' || $V['workDesc'] !== ''): ?>
      <div class="g1"><?php L_kv($PS['l_wtype_label'], $V['workType']); ?></div>
      <div class="g2-4"><?php L_kv($PS['l_wdesc_label'], $V['workDesc']); ?></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
<?php }

/* التذييل — يتكرر أسفل كل صفحة بنود */
function L_foot($V) {
    $PS = $V['PS']; $n = count($V['signs']); ?>
  <div class="lf">
    <?php if ($V['notes']): ?>
      <?php if ($PS['l_notes_title'] !== ''): ?><div class="notes-t"><?= e($PS['l_notes_title']) ?></div><?php endif; ?>
      <?php foreach ($V['notes'] as $i => $nt): ?><div class="note"><?= $i + 1 ?>- <?= e($nt) ?></div><?php endforeach; ?>
    <?php endif; ?>
    <?php if ($n): ?>
    <div class="signs">
      <?php foreach ($V['signs'] as $i => $s): ?>
        <div class="sg">
          <div class="sg-top"><?= $i === $n - 1 ? e($PS['l_approve_label']) : '' ?></div>
          <div class="sg-t"><?= e($s) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="sign-space"></div>
    <div class="bottom-row">
      <div><?= e($PS['l_contractor_sign']) ?></div>
      <div><?= e($PS['l_receive_date']) ?></div>
      <div></div>
    </div>
  </div>
<?php }

$V = compact('PS', 'contract', 'contractor', 'logo', 'logo2', 'DOC_TITLE', 'docNo', 'totalBefore',
             'vrows', 'noDisc', 'workType', 'workDesc', 'fine', 'dueAmount', 'cur', 'discLabel', 'execText', 'area', 'site', 'dept',
             'notes', 'signs');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title><?= e($DOC_TITLE) ?> رقم <?= e($docNo) ?></title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  html,body{-webkit-print-color-adjust:exact;print-color-adjust:exact}
  body{font-family:Arial,Tahoma,sans-serif;direction:rtl;color:#000;background:#9aa0aa;font-size:12px}

  /* صفحة A4 أفقية بقياس ثابت — نفس القياس على الشاشة وفي الطباعة */
  .lp{width:297mm;height:209.5mm;padding:7mm 8mm 6mm;margin:12px auto;background:#fff;
      display:flex;flex-direction:column;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.25)}
  .lp + .lp{break-before:page}

  /* ===== الترويسة ===== */
  .lh{flex:none;border-bottom:3px solid #000;padding-bottom:5px;margin-bottom:6px}
  .lh-top{display:flex;align-items:flex-start;gap:4mm}
  .lh-r{width:38%;font-weight:700;font-size:12.5px}
  .lh-c{width:28%;text-align:center;display:flex;flex-direction:column;align-items:center;gap:3px}
  .lh-l{width:34%;display:flex;flex-direction:column;gap:4px}
  .cname{font-weight:700;font-size:12.5px;line-height:1.55;margin-bottom:4px}
  .kv{display:flex;align-items:baseline;line-height:1.75;font-weight:700;font-size:12.5px}
  .kv .k{white-space:nowrap}
  .kv .sep{margin:0 6px}
  .lh-r .kv .k,.lh-grid .g1 .kv .k{min-width:21mm}
  .logo1{height:15mm;max-width:60mm;object-fit:contain}
  .cline{font-weight:700;font-size:12.5px}
  .dno{font-weight:700;font-size:13px;margin-top:6px}
  .dno b{font-size:21px;font-weight:800;margin-inline-start:4px}
  .pg-row{display:flex;justify-content:space-between;align-items:flex-start;min-height:6mm}
  .pg{font-weight:700;font-size:12.5px;padding-top:2mm}
  .pg-no{font-size:11.5px;font-weight:400;color:#333}
  .logo2{height:17mm;max-width:24mm;object-fit:contain}
  .vbox{border:2px dashed #222}
  .vr{display:flex;justify-content:space-between;align-items:center;padding:5px 14px;font-weight:700;font-size:13px}
  .vr + .vr{border-top:2px dashed #222}
  .vr b{font-size:15px;min-width:42%}
  .lh-grid{display:grid;grid-template-columns:27% 22% 23% 28%;margin-top:4px}
  .lh-grid .g2-4{grid-column:2 / span 3}

  /* ===== الجدول ===== */
  .lbody{flex:1 1 auto;min-height:0;overflow:hidden}
  table.lt{width:100%;border-collapse:collapse;border:1.5px solid #000}
  table.lt th{background:#e3e3e3;border:1px solid #000;padding:5px 4px;font-size:13px;font-weight:700}
  table.lt td{border:1px solid #000;padding:7px 6px;font-size:12px;text-align:center;vertical-align:middle;font-weight:700}
  table.lt td.det{text-align:right;color:#1c2fa8;line-height:1.55}
  table.lt td.price{color:#c0141b}
  table.lt td.empty{color:#888;padding:16px}

  /* ===== التذييل ===== */
  .lf{flex:none;padding-top:6px}
  .notes-t{font-weight:700;font-size:12.5px;margin-bottom:2px}
  .note{font-weight:700;font-size:12px;line-height:1.9}
  .signs{display:flex;justify-content:space-between;align-items:flex-end;margin-top:4px;gap:6px}
  .sg{text-align:center;flex:1 1 0}
  .sg-top{font-weight:800;font-size:14px;min-height:18px}
  .sg-t{font-weight:700;font-size:12px;white-space:nowrap}
  .sign-space{height:13mm}
  .bottom-row{display:grid;grid-template-columns:1fr 1fr 1fr;text-align:center;font-weight:700;font-size:12.5px}

  /* ===== الغلاف ===== */
  .cover .cv{flex:1;border:3px solid #000;display:flex;flex-direction:column;align-items:center;justify-content:space-evenly;
       text-align:center;padding:6mm 10mm}
  .cv-logo{height:20mm;max-width:90mm;object-fit:contain}
  .cv-l{font-weight:700;font-size:30px}
  .cv-no{font-weight:800;font-size:46px;color:#1414b8}
  .cv-c{font-weight:700;font-size:30px;color:#b3141b}

  /* ===== محضر الاستلام ===== */
  .ho{flex:1;display:flex;flex-direction:column}
  .ho-title{text-align:center;font-weight:800;font-size:19px;margin:6mm 0 3mm}
  .ho-title span{display:inline-block;background:#3a4a5e;color:#fff;padding:1px 16px;border-radius:7px;margin-inline-start:6px}
  .ho-note{margin:3mm 0;font-size:13.5px;line-height:2;font-weight:700}
  .ho-table{width:100%;border-collapse:collapse;margin-top:2mm}
  .ho-table td,.ho-table th{border:1px solid #000;padding:7px;font-size:13px;text-align:center}
  .ho-table th{background:#e3e3e3;font-weight:700}

  .toolbar{position:fixed;top:12px;left:12px;z-index:99;display:flex;gap:8px}
  .toolbar button,.toolbar a{background:#1a1a1a;color:#fff;border:0;padding:11px 22px;border-radius:9px;cursor:pointer;
        font-family:inherit;font-size:14px;font-weight:700;box-shadow:0 3px 10px rgba(0,0,0,.3);text-decoration:none}

  @page{size:A4 landscape;margin:0}
  @media print{
    html,body{background:#fff}
    .toolbar{display:none}
    /* الصفحة تملأ مساحة الورقة المتاحة فعلاً. الأصل هوامش صفر، لكن الطابعة أو خيار «الهوامش» في نافذة الطباعة قد يفرض هوامش،
       فتصغر مساحة الورقة عن مقاس الصفحة الثابت وتنقسم الصفحة (الترويسة في ورقة ورأس الجدول في أخرى والباقي يختفي). لذلك:
       - هامش لا يتجاوز حشوة الصفحة: تُنقص الحشوة بمقداره، فيبقى المحتوى بمقاسه ومكانه على الورقة كما في المعاينة.
       - هامش أكبر من الحشوة: تُصغَّر الصفحة كلها بنسبة واحدة (zoom) لتدخل في الورقة بلا إعادة توزيع للسطور. */
    .lp{margin:0;box-shadow:none;
        --mx:calc((297mm - 100vw) / 2); --my:calc((210mm - 100vh) / 2);
        width:max(100vw, 281mm); height:max(100vh - 0.5mm, 196.5mm);
        padding:max(0mm, 7mm - var(--my)) max(0mm, 8mm - var(--mx)) max(0mm, 6mm - var(--my));
        zoom:min(1, 100vw / 281.5mm, 100vh / 197.5mm)}
  }
</style>
</head>
<body>
<div class="toolbar">
  <button onclick="window.print()">🖨️ طباعة</button>
</div>

<?php if ($PS['l_cover'] === '1'): ?>
<!-- ===== صفحة الغلاف ===== -->
<div class="lp cover">
  <div class="cv">
    <img class="cv-logo" src="<?= e($logo) ?>" alt="">
    <?php if ($PS['l_cover_line1'] !== ''): ?><div class="cv-l"><?= e($PS['l_cover_line1']) ?></div><?php endif; ?>
    <?php if ($PS['l_cover_line2'] !== ''): ?><div class="cv-l"><?= e($PS['l_cover_line2']) ?></div><?php endif; ?>
    <div class="cv-no"><?= e($DOC_TITLE) ?> رقم: ( <?= e($docNo) ?> )</div>
    <?php if ($area !== ''): ?><div class="cv-l"><?= e($PS['l_area_label']) ?>: <?= e($area) ?></div><?php endif; ?>
    <?php if ($site): ?><div class="cv-l"><?= e($PS['l_cover_site_label']) ?>: <?= e($site) ?></div><?php endif; ?>
    <?php if ($dept): ?><div class="cv-l"><?= e($PS['l_dept_label']) ?>: <?= e($dept) ?></div><?php endif; ?>
    <?php if (!empty($contract['contract_no'])): ?><div class="cv-l"><?= e($PS['l_contract_label']) ?>: <?= e($contract['contract_no']) ?></div><?php endif; ?>
    <?php if ($contractor !== ''): ?><div class="cv-c"><?= e($contractor) ?></div><?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- ===== صفحة البنود (تُنسخ في المتصفح لكل صفحة إضافية) ===== -->
<div class="lp items-page">
  <?php L_head($V, 'items'); ?>
  <div class="lbody">
    <table class="lt">
      <thead><tr>
        <th style="width:9mm">م</th><th style="width:20mm">رقم البند</th><th>بيان الأعمال</th>
        <th style="width:18mm">الوحدة</th><th style="width:25mm">سعر العقد</th><th style="width:23mm">الكمية</th>
        <th style="width:48mm">ملاحظات</th>
      </tr></thead>
      <tbody>
      <?php if (!$items): ?>
        <tr><td colspan="7" class="empty">لا توجد بنود</td></tr>
      <?php else: foreach ($items as $k => $it): ?>
        <tr>
          <td><?= $k + 1 ?></td>
          <td><?= e($it['item_no']) ?></td>
          <td class="det" dir="auto"><?= e($it['item_details']) ?></td>
          <td><?= e($it['unit']) ?></td>
          <td class="price"><?= fmt($it['unit_price']) ?></td>
          <td><?= fmt($it['qty']) ?></td>
          <td></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php L_foot($V); ?>
</div>

<?php if (!empty($WITH_HANDOVER)):
  $hoVars = [
    '{تاريخ_البدء}'    => fmt_date($doc['start_date']),
    '{تاريخ_الانتهاء}' => fmt_date($doc['end_date']),
    '{رقم_المستند}'    => $docNo,
  ];
  $hoEnt = [$PS['handover_entity1'], $PS['handover_entity2'], $PS['handover_entity3']];
?>
<!-- ===== محضر استلام الأعمال (أمر العمل فقط) ===== -->
<div class="lp">
  <?php L_head($V, 'handover'); ?>
  <div class="ho">
    <div class="ho-title"><?= e($PS['handover_title']) ?> <span><?= e($docNo) ?></span></div>
    <p class="ho-note"><?= e(strtr($PS['handover_note1'], $hoVars)) ?></p>
    <table class="ho-table">
      <thead><tr><th style="width:12mm">م</th><th style="width:34%">الاسم</th><th style="width:34%">الصفة</th><th>الجهة التابع لها</th></tr></thead>
      <tbody>
        <?php foreach ($hoEnt as $i => $ent): ?>
        <tr style="height:11mm"><td><?= $i + 1 ?></td><td></td><td></td><td><?= e($ent) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p class="ho-note"><?= e(strtr($PS['handover_note2'], $hoVars)) ?></p>
    <table class="ho-table">
      <thead>
        <tr><th colspan="3">التوقيع</th></tr>
        <tr><?php foreach ($hoEnt as $ent): ?><th style="width:33.33%"><?= e($ent) ?></th><?php endforeach; ?></tr>
      </thead>
      <tbody><tr style="height:24mm"><td></td><td></td><td></td></tr></tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<script>
/* توزيع البنود على الصفحات: يُضاف كل صف لآخر صفحة، فإن فاض الجدول عن مساحته
   يُنقل الصف لصفحة جديدة (نسخة من نفس الترويسة والتذييل). ثم تُرقّم الصفحات. */
(function(){
  function paginate(){
    var first = document.querySelector('.items-page');
    var rows = Array.prototype.slice.call(first.querySelectorAll('table.lt tbody tr'));
    var proto = first.cloneNode(true);
    proto.querySelector('table.lt tbody').innerHTML = '';
    first.querySelector('table.lt tbody').innerHTML = '';
    var page = first;
    function overflows(p){ var b = p.querySelector('.lbody'); return b.scrollHeight > b.clientHeight + 1; }
    rows.forEach(function(tr){
      var tb = page.querySelector('table.lt tbody');
      tb.appendChild(tr);
      if (overflows(page) && tb.rows.length > 1) {
        tb.removeChild(tr);
        var np = proto.cloneNode(true);
        page.parentNode.insertBefore(np, page.nextSibling);
        page = np;
        page.querySelector('table.lt tbody').appendChild(tr);
      }
    });
    var all = document.querySelectorAll('.items-page');
    for (var i = 0; i < all.length; i++) {
      var no = all[i].querySelector('.pg-no');
      if (no) no.textContent = 'صفحة ' + (i + 1) + ' من ' + all.length;
    }
  }
  // بعد تحميل الشعارات والخطوط حتى تكون ارتفاعات الترويسة نهائية قبل القياس
  window.addEventListener('load', paginate);
})();
</script>
</body>
</html>

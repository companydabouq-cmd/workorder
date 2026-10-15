<?php
/**
 * شكل «المالية» — دفعة إنجاز (A4 طولي) — على نموذج ورقة وزارة المالية:
 *   ترويسة في كل صفحة: يمين الجهة والعقد والمتعهد، وسط الشعار، يسار رقم المستند ونوع العمل
 *   وقيمته ومدته وتاريخ بدئه والغرامة.
 *   الجدول: الموقع | البند | البند الرئيسي | رقمه | وصف الأعمال | عدد | سعر | وحدة | إجمالي
 *   آخر صفحة: عدد البنود، المبلغ بالحروف، الإجماليات، التواقيع، ملاحظات المهندس، تاريخ الاستلام.
 *
 * يُضمَّن من print_template.php بعد تجهيز متغيرات المستند ($doc, $items, $PS, ...).
 * توزيع البنود على الصفحات يتم في المتصفح بقياس ارتفاع كل صف فعلياً.
 */

/** عدد صحيح بالكلمات العربية (حتى المليارات) */
function ar_words_int($n) {
    $ones = ['', 'واحد', 'اثنان', 'ثلاثة', 'أربعة', 'خمسة', 'ستة', 'سبعة', 'ثمانية', 'تسعة', 'عشرة',
             'أحد عشر', 'اثنا عشر', 'ثلاثة عشر', 'أربعة عشر', 'خمسة عشر', 'ستة عشر', 'سبعة عشر', 'ثمانية عشر', 'تسعة عشر'];
    $tens = ['', '', 'عشرون', 'ثلاثون', 'أربعون', 'خمسون', 'ستون', 'سبعون', 'ثمانون', 'تسعون'];
    $hund = ['', 'مائة', 'مائتان', 'ثلاثمائة', 'أربعمائة', 'خمسمائة', 'ستمائة', 'سبعمائة', 'ثمانمائة', 'تسعمائة'];
    $below1000 = function ($n) use ($ones, $tens, $hund) {
        $p = [];
        $h = intdiv($n, 100); $r = $n % 100;
        if ($h) $p[] = $hund[$h];
        if ($r) $p[] = $r < 20 ? $ones[$r] : (($r % 10) ? $ones[$r % 10] . ' و' . $tens[intdiv($r, 10)] : $tens[intdiv($r, 10)]);
        return implode(' و', $p);
    };
    if ($n == 0) return 'صفر';
    $parts = [];
    foreach ([[1000000000, 'مليار', 'ملياران', 'مليارات'], [1000000, 'مليون', 'مليونان', 'ملايين'], [1000, 'ألف', 'ألفان', 'آلاف']] as [$v, $one, $two, $plural]) {
        $c = intdiv($n, $v); $n %= $v;
        if (!$c) continue;
        if ($c == 1)      $parts[] = $one;
        elseif ($c == 2)  $parts[] = $two;
        elseif ($c <= 10) $parts[] = $below1000($c) . ' ' . $plural;
        else              $parts[] = $below1000($c) . ' ' . $one;
    }
    if ($n) $parts[] = $below1000($n);
    return implode(' و', $parts);
}

/** مبلغ بالدينار الكويتي كتابةً: «فقط ... دينار كويتي و ... فلس لا غير» */
function kwd_words($amount) {
    $neg = $amount < 0;
    $amount = round(abs($amount), 3);
    $d = (int) floor($amount);
    $f = (int) round(($amount - $d) * 1000);
    if ($f >= 1000) { $d++; $f -= 1000; }
    $s = 'فقط ' . ($neg ? 'سالب ' : '') . ar_words_int($d) . ' دينار كويتي';
    if ($f) $s .= ' و' . ar_words_int($f) . ' فلس';
    return $s . ' لا غير';
}

/** رقم بلا أصفار زائدة (45 / 22.5) — للعدد والسعر كما في الورقة */
function p_num($n) {
    return rtrim(rtrim(number_format((float)$n, 3, '.', ''), '0'), '.');
}
/** تاريخ بصيغة 26/4/2026 */
function p_date($d) {
    $t = $d ? strtotime($d) : false;
    return $t ? date('j/n/Y', $t) : '';
}

$hasMain   = has_item_main();
$docTitle  = trim($PS['p_doc_title']) !== '' ? trim($PS['p_doc_title']) : $DOC_TITLE;
$docNo     = (int)$doc[$NO_FIELD];
$afterDisc = $TOT['net'];   // قيمة الدفعة: بعد الخصم والنسبة الإضافية، قبل الغرامة
$fine      = (float)$doc['delay_fine'];
/* سطرا الخصم والنسبة الإضافية بالترتيب المحدد في الإعدادات */
$discRow   = [$PS['p_discount_label'], $discLabel . '%', $discAmount];
$midRows   = $extraPct <= 0 ? [$discRow]
           : ($disc <= 0 ? [[$extraText, '', $extraAmount]]   // منطقة بلا خصم: النسبة الإضافية فقط
           : ($TOT['before'] ? [[$extraText, '', $extraAmount], $discRow] : [$discRow, [$extraText, '', $extraAmount]]));
$signs     = ps_lines('p_sign_titles');
$cur       = $PS['p_cur_short'];
$money     = $PS['p_money_word'];
$centerLogo = ($PS['p_center_logo'] === 'second' && print_logo2_url()) ? print_logo2_url() : $logo;
$rightLines = array_values(array_filter([$ministry, $PS['head_right_line2'], $PS['p_right_line3']],
                                        function ($l) { return trim((string)$l) !== ''; }));
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title><?= e($docTitle) ?> رقم <?= $docNo ?></title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  html,body{-webkit-print-color-adjust:exact;print-color-adjust:exact}
  body{font-family:Arial,Tahoma,sans-serif;direction:rtl;color:#000;background:#9aa0aa;font-size:12px}

  /* صفحة A4 طولية بقياس ثابت — نفس القياس على الشاشة وفي الطباعة */
  .pp{width:210mm;height:296.5mm;padding:12mm 9mm 9mm;margin:12px auto;background:#fff;
      display:flex;flex-direction:column;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.25)}
  .pp + .pp{break-before:page}

  /* ===== الترويسة ===== */
  .ph{flex:none;display:flex;align-items:flex-start;gap:3mm;margin-bottom:5mm}
  .ph-r{width:42%;font-weight:700;font-size:11.5px;line-height:1.75}
  .ph-c{width:18%;text-align:center;padding-top:3mm}
  .ph-c img{height:25mm;max-width:100%;object-fit:contain}
  .ph-l{width:40%;padding-top:1mm}
  .ph-l table{border-collapse:collapse;margin-inline-start:auto}
  .ph-l td{padding:1px 5px;font-weight:700;font-size:12px;white-space:nowrap;vertical-align:top}
  .ph-l td.v{text-align:center;min-width:24mm}
  .ph-l .wt td{padding-bottom:3px}

  /* ===== الجدول ===== */
  .pbody{flex:1 1 auto;min-height:0;overflow:hidden}
  table.pt{width:100%;border-collapse:collapse;border:1.5px solid #000}
  table.pt th{background:#6f9fd8;border:1px solid #000;padding:6px 3px;font-size:12px;font-weight:700;line-height:1.3}
  table.pt td{border:1px solid #000;padding:5px 4px;font-size:11.5px;text-align:center;vertical-align:middle;line-height:1.45}
  table.pt td.det{text-align:right}
  table.pt td.empty{color:#888;padding:14px}

  /* ===== الختام (آخر صفحة) ===== */
  .pc{padding-top:5mm}
  .pc-sum{display:flex;gap:6mm;align-items:flex-start}
  .pc-r{width:44%;font-weight:700;font-size:12px;line-height:2.1}
  .pc-r .k{display:inline-block;min-width:24mm}
  .pc-l{flex:1}
  .pc-l table{border-collapse:collapse;margin-inline-start:auto}
  .pc-l td{padding:1px 6px;font-weight:700;font-size:12px;white-space:nowrap}
  .pc-l td.v{text-align:center;min-width:26mm}
  .pc-l tr.due td{border-top:1.5px solid #000}
  .signs{display:flex;justify-content:space-around;margin-top:11mm;font-weight:700;font-size:12.5px;text-align:center}
  .sign-space{height:20mm}
  .pc-bottom{display:flex;justify-content:space-between;align-items:baseline;font-weight:700;font-size:12px}
  .pc-bottom .notes{text-decoration:underline}
  .pc-bottom .rcv b{margin-inline-start:8mm}

  .toolbar{position:fixed;top:12px;left:12px;z-index:99;display:flex;gap:8px}
  .toolbar button{background:#1a1a1a;color:#fff;border:0;padding:11px 22px;border-radius:9px;cursor:pointer;
        font-family:inherit;font-size:14px;font-weight:700;box-shadow:0 3px 10px rgba(0,0,0,.3)}

  @page{size:A4 portrait;margin:0}
  @media print{
    html,body{background:#fff}
    .toolbar{display:none}
    /* الصفحة تملأ مساحة الورقة المتاحة فعلاً. الأصل هوامش صفر، لكن الطابعة أو خيار «الهوامش» في نافذة الطباعة قد يفرض هوامش،
       فتصغر مساحة الورقة عن مقاس الصفحة الثابت وتنقسم الصفحة (الترويسة في ورقة ورأس الجدول في أخرى والباقي يختفي). لذلك:
       - هامش لا يتجاوز حشوة الصفحة: تُنقص الحشوة بمقداره، فيبقى المحتوى بمقاسه ومكانه على الورقة كما في المعاينة.
       - هامش أكبر من الحشوة: تُصغَّر الصفحة كلها بنسبة واحدة (zoom) لتدخل في الورقة بلا إعادة توزيع للسطور. */
    .pp{margin:0;box-shadow:none;
        --mx:calc((210mm - 100vw) / 2); --my:calc((297mm - 100vh) / 2);
        width:max(100vw, 192mm); height:max(100vh - 0.5mm, 275.5mm);
        padding:max(0mm, 12mm - var(--my)) max(0mm, 9mm - var(--mx)) max(0mm, 9mm - var(--my));
        zoom:min(1, 100vw / 192.5mm, 100vh / 276.5mm)}
  }
</style>
</head>
<body>
<div class="toolbar"><button onclick="window.print()">🖨️ طباعة</button></div>

<!-- ===== صفحة (تُنسخ في المتصفح لكل صفحة إضافية) ===== -->
<div class="pp items-page">
  <div class="ph">
    <div class="ph-r">
      <?php foreach ($rightLines as $l): ?><div><?= e($l) ?></div><?php endforeach; ?>
      <?php if (!empty($contract['contract_no'])): ?><div><?= e($PS['p_contract_label']) ?> <?= e($contract['contract_no']) ?></div><?php endif; ?>
      <?php if (!empty($contract['contract_name'])): ?><div><?= e($contract['contract_name']) ?></div><?php endif; ?>
      <?php if ($contractor !== ''): ?><div><?= e($PS['p_contractor_label']) ?> : <?= e($contractor) ?></div><?php endif; ?>
    </div>
    <div class="ph-c"><img src="<?= e($centerLogo) ?>" alt=""></div>
    <div class="ph-l">
      <table>
        <tr><td><?= e($docTitle) ?> رقم</td><td class="v"><?= $docNo ?></td><td></td></tr>
        <?php if (trim((string)$doc['work_type']) !== ''): ?>
        <tr class="wt"><td colspan="3"><?= e($doc['work_type']) ?></td></tr>
        <?php endif; ?>
        <?php if (trim((string)($doc['work_desc'] ?? '')) !== ''): ?>
        <tr class="wt"><td colspan="3"><?= e($doc['work_desc']) ?></td></tr>
        <?php endif; ?>
        <tr><td><?= e($PS['p_value_label']) ?></td><td class="v"><?= fmt($afterDisc) ?></td><td><?= e($money) ?></td></tr>
        <tr><td><?= e($PS['p_exec_label']) ?></td><td class="v"><?= (int)$doc['exec_days'] ?></td><td><?= e($PS['p_days_word']) ?></td></tr>
        <tr><td><?= e($PS['p_start_label']) ?></td><td class="v"><?= p_date($doc['start_date']) ?></td><td></td></tr>
        <tr><td><?= e($PS['p_fine_label']) ?></td><td class="v"><?= p_num($fine) ?></td><td><?= e($money) ?></td></tr>
      </table>
    </div>
  </div>

  <div class="pbody">
    <table class="pt">
      <thead><tr>
        <th style="width:13%">الموقع</th><th style="width:8%">البند</th><th style="width:10%">البند الرئيسي</th>
        <th style="width:6%">رقم البند الرئيسي</th><th>وصف الأعمال</th><th style="width:6%">عدد</th>
        <th style="width:7%">سعر</th><th style="width:8%">وحدة</th><th style="width:9%">إجمالي</th>
      </tr></thead>
      <tbody>
      <?php if (!$items): ?>
        <tr><td colspan="9" class="empty">لا توجد بنود</td></tr>
      <?php else: foreach ($items as $it): ?>
        <tr>
          <td><?= e($site) ?></td>
          <td><?= e($it['item_no']) ?></td>
          <td><?= e($hasMain ? ($it['main_name'] ?? '') : '') ?></td>
          <td><?= e($hasMain ? ($it['main_no'] ?? '') : '') ?></td>
          <td class="det" dir="auto"><?= e($it['item_details']) ?></td>
          <td><?= p_num($it['qty']) ?></td>
          <td><?= p_num($it['unit_price']) ?></td>
          <td><?= e($it['unit']) ?></td>
          <td><?= fmt($it['total_before']) ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ===== الختام: يُنقل في المتصفح أسفل جدول آخر صفحة (أو لصفحة جديدة إن لم يتّسع) ===== -->
<div class="pc" id="closing">
  <div class="pc-sum">
    <div class="pc-r">
      <div><span class="k"><?= e($PS['p_count_label']) ?></span> <?= count($items) ?></div>
      <div><span class="k"><?= e($PS['p_words_label']) ?></span> <?= e(kwd_words($dueAmount)) ?></div>
    </div>
    <div class="pc-l">
      <table>
        <tr><td><?= e($PS['p_total_label']) ?></td><td class="v"><?= fmt($totalBefore) ?></td><td><?= e($cur) ?></td></tr>
        <?php foreach ($midRows as [$lbl, $pct, $amt]): ?>
        <tr><td><?= e($lbl) ?><?php if ($pct !== ''): ?> <span dir="ltr"><?= e($pct) ?></span><?php endif; ?></td><td class="v"><?= fmt($amt) ?></td><td><?= e($cur) ?></td></tr>
        <?php endforeach; ?>
        <tr><td><?= e($PS['p_fine_total_label']) ?></td><td class="v"><?= fmt($fine) ?></td><td><?= e($cur) ?></td></tr>
        <tr class="due"><td><?= e($PS['p_due_label']) ?></td><td class="v"><?= fmt($dueAmount) ?></td><td><?= e($cur) ?></td></tr>
      </table>
    </div>
  </div>
  <?php if ($signs): ?>
  <div class="signs"><?php foreach ($signs as $s): ?><div><?= e($s) ?></div><?php endforeach; ?></div>
  <?php endif; ?>
  <div class="sign-space"></div>
  <div class="pc-bottom">
    <div class="notes"><?= e($PS['p_notes_label']) ?></div>
    <div class="rcv"><?= e($PS['p_receive_label']) ?> <b><?= p_date($doc['end_date']) ?></b></div>
  </div>
</div>

<script>
/* توزيع البنود على الصفحات (نفس الترويسة في كل صفحة)، ثم وضع الختام أسفل آخر جدول،
   فإن لم يتّسع يُنقل لصفحة جديدة بنفس الترويسة. */
(function(){
  function paginate(){
    var first = document.querySelector('.items-page');
    var closing = document.getElementById('closing');
    var rows = Array.prototype.slice.call(first.querySelectorAll('table.pt tbody tr'));
    var proto = first.cloneNode(true);
    proto.querySelector('table.pt tbody').innerHTML = '';
    first.querySelector('table.pt tbody').innerHTML = '';
    var page = first;
    function overflows(p){ var b = p.querySelector('.pbody'); return b.scrollHeight > b.clientHeight + 1; }
    function newPage(){
      var np = proto.cloneNode(true);
      page.parentNode.insertBefore(np, page.nextSibling);
      page = np; return np;
    }
    rows.forEach(function(tr){
      var tb = page.querySelector('table.pt tbody');
      tb.appendChild(tr);
      if (overflows(page) && tb.rows.length > 1) { tb.removeChild(tr); newPage().querySelector('table.pt tbody').appendChild(tr); }
    });
    page.querySelector('.pbody').appendChild(closing);
    if (overflows(page)) {
      var np = newPage();
      var t = np.querySelector('table.pt'); t.parentNode.removeChild(t);
      np.querySelector('.pbody').appendChild(closing);
    }
  }
  window.addEventListener('load', paginate);
})();
</script>
</body>
</html>

<?php
/**
 * صفحة «الترويسة والتذييل» — كل نص ثابت يظهر في الطباعة يُحرَّر من هنا.
 * نسخ البرنامج لمشروع آخر: غيّر النصوص هنا وارفع الشعار، بلا لمس الكود.
 */
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$PAGE_KEY = 'printset'; $PAGE_TITLE = 'الترويسة والتذييل';

$D = print_defaults();
$logoError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // استعادة الافتراضي
    if (($_POST['action'] ?? '') === 'reset') {
        print_settings_reset();
        flash_set('ok', 'أُعيدت كل النصوص إلى الافتراضي (الشعار لم يُمسّ).');
        header('Location: print_settings.php'); exit;
    }

    // حذف الشعار المرفوع
    if (($_POST['action'] ?? '') === 'drop_logo') {
        if ($p = print_logo_file()) store_delete($p);
        print_settings_save(['logo_path' => '']);
        flash_set('ok', 'حُذف الشعار المرفوع.');
        header('Location: print_settings.php'); exit;
    }

    // حذف الشعار الثاني (يسار الترويسة العرضية)
    if (($_POST['action'] ?? '') === 'drop_logo2') {
        if ($p = print_logo2_file()) store_delete($p);
        print_settings_save(['logo2_path' => '']);
        flash_set('ok', 'حُذف الشعار الثاني.');
        header('Location: print_settings.php'); exit;
    }

    // 1) الشعاران
    $up = print_logo_upload('logo_file');
    if (is_string($up) && strpos($up, 'assets/') === 0) {
        print_settings_save(['logo_path' => $up]);
    } elseif (is_string($up)) {
        $logoError = $up;   // رسالة خطأ — تُعرض ولا تمنع حفظ باقي النصوص
    }
    $up2 = print_logo_upload('logo2_file', 'logo2-custom');
    if (is_string($up2) && strpos($up2, 'assets/') === 0) {
        print_settings_save(['logo2_path' => $up2]);
    } elseif (is_string($up2)) {
        $logoError = $logoError !== '' ? $logoError . ' / ' . $up2 : $up2;
    }

    // 2) اسم الجهة واسم المقاول يُحفظان في مكانهما الأصلي (نفس الحقل الذي تراه صفحاتهما).
    //    يُكتب الحقل فقط إن أُرسل فعلاً، حتى لا يمحو طلبٌ ناقص اسماً محفوظاً.
    if (array_key_exists('ministry_name', $_POST)) {
        $ministryName = trim((string) $_POST['ministry_name']);
        $m = get_ministry();
        if (!empty($m['id'])) q("UPDATE ministry_settings SET ministry_name=? WHERE id=?", [$ministryName, $m['id']]);
        else                  q("INSERT INTO ministry_settings (ministry_name) VALUES (?)", [$ministryName]);
    }

    if (array_key_exists('contractor_name', $_POST)) {
        $contractorName = trim((string) $_POST['contractor_name']);
        $c = get_contractor();
        if (!empty($c['id'])) q("UPDATE contractor_settings SET contractor_name=? WHERE id=?", [$contractorName, $c['id']]);
        else                  q("INSERT INTO contractor_settings (contractor_name, discount_percent) VALUES (?,?)", [$contractorName, 0]);
    }

    // 3) باقي النصوص
    $pairs = [];
    foreach ($D as $k => $v) {
        if ($k === 'logo_path' || $k === 'logo2_path') continue;
        if (array_key_exists($k, $_POST)) $pairs[$k] = trim((string) $_POST[$k]);
    }
    try {
        print_settings_save($pairs);
    } catch (Exception $ex) {
        // أشهر سبب: مستخدم قاعدة البيانات بلا صلاحية إنشاء جدول print_settings
        flash_set('er', 'تعذّر حفظ النصوص: ' . $ex->getMessage());
        header('Location: print_settings.php'); exit;
    }

    flash_set($logoError ? 'er' : 'ok',
        $logoError ? ('حُفظت النصوص، لكن الشعار لم يُرفع: ' . $logoError)
                   : ('تم حفظ' . (isset($pairs['print_layout'], print_layouts()[$pairs['print_layout']]) ? ' نوع المشروع «' . print_layouts()[$pairs['print_layout']][0] . '» و' : ' ') . 'نصوص الترويسة والتذييل.'));
    header('Location: print_settings.php'); exit;
}

$S = print_settings_all();
// نوع المشروع «مختار» إن كان محفوظاً فعلاً في القاعدة (لا القيمة الافتراضية)
try { $typeChosen = (int) q_val("SELECT COUNT(*) FROM print_settings WHERE setting_key='print_layout'") > 0; }
catch (Exception $ex) { $typeChosen = false; }
$ministryName   = get_ministry()['ministry_name'] ?? '';
$contractorName = get_contractor()['contractor_name'] ?? '';

/**
 * خانة نص مع زر استرجاع القيمة الافتراضية.
 * النص الإرشادي داخل الخانة تعليمة فقط («أدخل ...») — بلا أمثلة من أي مشروع.
 */
function fld($key, $label, $S, $D, $hint = '', $rows = 0, $ph = null) {
    $val = $S[$key] ?? '';
    $def = $D[$key] ?? '';
    $ph  = $ph !== null ? $ph : 'أدخل ' . $label;
    echo '<div class="field">';
    echo '<label>' . e($label);
    if ($def !== '') {
        echo ' <button type="button" class="reset-one" data-target="' . e($key) . '" data-default="' . e($def) . '" title="استرجاع النص الافتراضي">↺</button>';
    }
    echo '</label>';
    if ($rows > 0) {
        echo '<textarea name="' . e($key) . '" id="f_' . e($key) . '" rows="' . (int) $rows . '" placeholder="' . e($ph) . '">' . e($val) . '</textarea>';
    } else {
        echo '<input name="' . e($key) . '" id="f_' . e($key) . '" value="' . e($val) . '" placeholder="' . e($ph) . '">';
    }
    if ($hint !== '') echo '<div class="fhint">' . $hint . '</div>';
    echo '</div>';
}

require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div class="page-title"><span class="ic">🖨️</span> الترويسة والتذييل</div></div>
<?= flash_get() ?>

<style>
  .fhint{font-size:11.5px;color:#7a8398;margin-top:5px;line-height:1.7}
  .reset-one{background:#eef1f6;border:0;color:#55607a;border-radius:6px;cursor:pointer;
    font-size:12px;padding:1px 7px;margin-inline-start:6px;font-family:inherit}
  .reset-one:hover{background:#dfe4ec;color:#1a1a1a}
  .sec{font-weight:800;font-size:15px;margin:0 0 4px;display:flex;align-items:center;gap:8px}
  .sec-note{font-size:12px;color:#7a8398;margin-bottom:14px;line-height:1.8}
  hr.sep{border:0;border-top:1px solid #e5e9f0;margin:22px 0}
  /* معاينة حيّة لترويسة الطباعة */
  .pv{background:#fff;border:1px solid #d8dee9;border-radius:10px;padding:14px 16px;margin-bottom:6px}
  .pv .head{display:flex;justify-content:space-between;align-items:center;border-bottom:2.5px solid #1a1a1a;padding-bottom:9px}
  .pv .head .r{font-weight:800;font-size:13px;line-height:1.8;color:#111}
  .pv .head .l{font-weight:800;font-size:11.5px;text-align:left;line-height:1.6;color:#111;max-width:230px}
  .pv .head .c img{width:60px;height:60px;object-fit:contain}
  .pv .cbox{border:1.5px solid #1a1a1a;margin:9px 0 0;padding:7px;text-align:center;font-weight:800;
    font-size:12px;background:#f6f7f9;border-radius:5px;color:#111}
  .logo-row{display:flex;align-items:center;gap:14px;flex-wrap:wrap}
  .logo-row img{width:64px;height:64px;object-fit:contain;background:#fff;border:1px solid #e0e5ee;border-radius:10px;padding:4px}
  .layout-pick label{display:block;padding:10px 12px;border:1px solid #e3e8f0;border-radius:10px;margin-bottom:8px;cursor:pointer;line-height:1.8}
  .layout-pick input{margin-inline-end:6px}
</style>

<form method="post" enctype="multipart/form-data">
  <?= mq_csrf_field() ?>

  <div class="panel" style="margin-bottom:18px">
    <div class="sec">📐 نوع المشروع</div>
    <div class="sec-note"><b>اختر نوع المشروع أولاً</b>، فتظهر تحته ترويسته وتذييله فقط. يُطبَّق على طباعة الحصر المبدئي وحصر العمل وأمر العمل،
      ويتبعه تنسيق جدول الكميات (مع «<?= e(print_layouts()['payment'][0]) ?>» يظهر البند الرئيسي) ورمز نوع الأعمال مع رقم المستند.</div>
    <div class="layout-pick">
      <?php $LAYOUTS = print_layouts();
            // النوع المختار: المحفوظ فعلاً فقط — برنامج جديد لم يُختر نوعه بعد لا تظهر فيه الترويسة حتى يُختار
            $curLayout = ($typeChosen && isset($LAYOUTS[$S['print_layout']])) ? $S['print_layout'] : '';
            foreach ($LAYOUTS as $key => [$name, $desc]): ?>
      <label><input type="radio" name="print_layout" value="<?= e($key) ?>" <?= $curLayout === $key ? 'checked' : '' ?>>
        <b><?= e($name) ?></b> — <?= e($desc) ?>
        <span class="muted">(جدول الكميات: <?= in_array($key, boq_main_layouts(), true) ? 'مع البند الرئيسي' : 'التنسيق الأصلي' ?>)</span></label>
      <?php endforeach; ?>
    </div>
    <div id="ptype_hint" class="flash er" style="margin:10px 0 0;display:none">اختر نوع المشروع أعلاه لتظهر ترويسته وتذييله.</div>
  </div>

  <div class="panel" style="margin-bottom:18px" data-types="classic">
    <div class="sec">👁️ معاينة الترويسة</div>
    <div class="sec-note">تتغيّر فوراً أثناء الكتابة — هذا شكلها أعلى كل صفحة مطبوعة.</div>
    <div class="pv">
      <div class="head">
        <div class="r"><div id="pv1"></div><div id="pv2"></div></div>
        <div class="c"><img src="<?= e(print_logo_url()) ?>" alt="logo"></div>
        <div class="l"><div id="pv3"></div><div id="pv4"></div></div>
      </div>
      <div class="cbox"><span id="pv5"></span> ( <?= e(get_contract()['contract_no'] ?? '—') ?> )</div>
    </div>
  </div>

  <div class="panel" style="margin-bottom:18px" data-types="all">
    <div class="sec">🏛️ الترويسة</div>
    <div class="sec-note"><span data-types="classic">الجهة على اليمين، الشعار في الوسط، المقاول على اليسار. اترك «السطر الثاني» فارغاً ليختفي.</span><span data-types="payment">الجهة (السطر الأول والثاني) تُطبع يمين الترويسة، واسم المقاول بعد عنوان المتعهد.</span><span data-types="landscape">اسم الجهة يظهر في لوحة البيانات والتقارير، واسم المقاول يُطبع في الترويسة والغلاف.</span></div>
    <div class="grid2">
      <div class="field">
        <label>الجهة — السطر الأول</label>
        <input name="ministry_name" id="f_ministry_name" value="<?= e($ministryName) ?>" placeholder="أدخل اسم الجهة">
        <div class="fhint">نفس الحقل الموجود في صفحة «اسم الوزارة» — تعديله هنا يعدّله هناك. اتركه فارغاً ليختفي السطر.</div>
      </div>
      <div data-types="classic payment"><?php fld('head_right_line2', 'الجهة — السطر الثاني', $S, $D,
          'يظهر تحت اسم الجهة مباشرة. اتركه فارغاً ليختفي السطر.', 0, 'أدخل السطر الثاني للجهة'); ?></div>
      <div class="field">
        <label>المقاول — السطر الأول</label>
        <input name="contractor_name" id="f_contractor_name" value="<?= e($contractorName) ?>" placeholder="أدخل اسم المقاول">
        <div class="fhint">نفس الحقل الموجود في صفحة «اسم المقاول ونسبة الخصم» — نسبة الخصم تبقى هناك.</div>
      </div>
      <div data-types="classic"><?php fld('head_left_line2', 'المقاول — السطر الثاني', $S, $D,
          'اختياري — يظهر تحت اسم المقاول. اتركه فارغاً ليختفي السطر.', 0, 'أدخل السطر الثاني للمقاول'); ?></div>
    </div>
    <div class="grid2">
      <div data-types="classic"><?php fld('contract_label', 'كلمة صندوق العقد', $S, $D,
          'الكلمة التي تسبق رقم العقد داخل الصندوق. رقم العقد واسمه يأتيان من صفحة «بيانات العقد».', 0, 'أدخل كلمة صندوق العقد'); ?></div>
      <?php fld('summary_dept_line', 'سطر الجهة في التقارير المجمّعة', $S, $D,
          'في تقارير الملخص فقط: يظهر اسم الإدارة إن كانت كل المستندات لإدارة واحدة، وإلا يظهر هذا النص.', 0, 'أدخل سطر الجهة للتقارير'); ?>
    </div>
  </div>

  <div class="panel" style="margin-bottom:18px" data-types="all">
    <div class="sec">🖼️ الشعار</div>
    <div class="sec-note">يظهر وسط ترويسة كل مستند مطبوع وكل تقرير. PNG أو JPG أو WEBP، حتى 4 ميجابايت.</div>
    <div class="logo-row">
      <img src="<?= e(print_logo_url()) ?>" alt="الشعار الحالي">
      <div class="field" style="margin:0;flex:1;min-width:240px">
        <label>رفع شعار جديد</label>
        <input type="file" name="logo_file" accept="image/png,image/jpeg,image/webp">
        <div class="fhint">
          <?php if (has_logo()): ?>يوجد شعار حالياً — الرفع يستبدله.<?php else: ?>لا يوجد شعار بعد — يظهر شعار مبدئي في الطباعة.<?php endif; ?>
        </div>
      </div>
    </div>
    <div class="logo-row" style="margin-top:14px" data-types="landscape payment">
      <?php if (print_logo2_url()): ?><img src="<?= e(print_logo2_url()) ?>" alt="الشعار الثاني"><?php endif; ?>
      <div class="field" style="margin:0;flex:1;min-width:240px">
        <label>الشعار الثاني</label>
        <input type="file" name="logo2_file" accept="image/png,image/jpeg,image/webp">
        <div class="fhint">اختياري (مثل شعار الدولة) — يظهر أعلى يسار كل صفحة في «البلدية»، ووسط الترويسة في «المالية».</div>
      </div>
    </div>
  </div>

  <div class="panel" style="margin-bottom:18px" data-types="landscape">
    <div class="sec">🧾 «البلدية» — الترويسة</div>
    <div class="sec-note">تتكرر أعلى كل صفحة. يمين: اسم العقد (من «بيانات العقد») ثم الأسطر أدناه. الوسط: الشعار ورقم المستند.
      يسار: رقم الصفحة وصندوق القيمة. السطر ذو العنوان أو القيمة الفارغة لا يُطبع.</div>
    <div class="grid4">
      <?php fld('l_row1_label', 'سطر يمين أول — العنوان', $S, $D); ?>
      <?php fld('l_row1_value', 'سطر يمين أول — القيمة', $S, $D); ?>
      <?php fld('l_row5_label', 'سطر يمين أخير — العنوان', $S, $D); ?>
      <?php fld('l_row5_value', 'سطر يمين أخير — القيمة', $S, $D); ?>
    </div>
    <div class="grid3">
      <?php fld('l_contract_label', 'عنوان رقم العقد', $S, $D, 'يُستعمل أيضاً في الغلاف.'); ?>
      <?php fld('l_contractor_label', 'عنوان اسم المقاول', $S, $D); ?>
      <?php fld('l_discount_label', 'عنوان نسبة الخصم', $S, $D); ?>
    </div>
    <div class="grid2">
      <?php fld('l_center_line', 'سطر الوسط (تحت الشعار)', $S, $D); ?>
      <?php fld('l_no_suffix', 'لاحقة رقم المستند', $S, $D, 'تُستعمل فقط للمستند الذي ليس له نوع عمل برمز — رمز النوع من صفحة «أنواع الأعمال» يُكتب تلقائياً مثل 43/م.', 0, 'بدون لاحقة'); ?>
    </div>
    <div class="grid4">
      <?php fld('l_exec_label', 'عنوان مدة التنفيذ', $S, $D); ?>
      <div style="grid-column:span 3"><?php fld('l_exec_text', 'نص مدة التنفيذ', $S, $D, 'الرمز <b>{المدة}</b> يُستبدل بعدد أيام التنفيذ من المستند.'); ?></div>
    </div>
    <div class="grid4">
      <?php fld('l_area_label', 'عنوان المنطقة', $S, $D, 'قيمتها من حقل «المنطقة» في صفحة مواقع العمل.'); ?>
      <?php fld('l_site_label', 'عنوان موقع العمل', $S, $D); ?>
      <?php fld('l_dept_label', 'عنوان الإدارة', $S, $D); ?>
      <?php fld('l_page_label', 'عنوان رقم الصفحة', $S, $D); ?>
    </div>
    <div class="grid4">
      <?php fld('l_wtype_label', 'عنوان نوع العمل', $S, $D); ?>
      <?php fld('l_wdesc_label', 'عنوان بيان الأعمال', $S, $D); ?>
    </div>
    <div class="grid3">
      <?php fld('l_value_label', 'صندوق القيمة — قبل الخصم', $S, $D); ?>
      <?php fld('l_value_after_label', 'صندوق القيمة — بعد الخصم', $S, $D); ?>
      <?php fld('l_currency', 'العملة', $S, $D); ?>
    </div>
  </div>

  <div class="panel" style="margin-bottom:18px" data-types="landscape">
    <div class="sec">🧾 «البلدية» — التذييل</div>
    <div class="sec-note">يتكرر أسفل كل صفحة. كل سطر في الخانتين الكبيرتين = ملاحظة مرقّمة أو مسمى توقيع.</div>
    <?php fld('l_notes_title', 'عنوان الملاحظات', $S, $D); ?>
    <?php fld('l_notes', 'الملاحظات (سطر لكل ملاحظة)', $S, $D, 'تُرقّم تلقائياً 1- 2- 3-. اتركها فارغة لإخفاء الملاحظات.', 4); ?>
    <?php fld('l_sign_titles', 'مسميات التواقيع (سطر لكل مسمى، من اليمين لليسار)', $S, $D, '', 7); ?>
    <div class="grid3">
      <?php fld('l_approve_label', 'كلمة الاعتماد (فوق آخر توقيع)', $S, $D); ?>
      <?php fld('l_contractor_sign', 'توقيع المقاول', $S, $D); ?>
      <?php fld('l_receive_date', 'تاريخ الاستلام', $S, $D); ?>
    </div>
  </div>

  <div class="panel" style="margin-bottom:18px" data-types="payment">
    <div class="sec">🧾 «المالية» — الترويسة والتذييل (دفعة الإنجاز)</div>
    <div class="sec-note">يمين الترويسة: «الجهة — السطر الأول والثاني» من قسم الترويسة أعلاه، ثم السطر الثالث، ثم رقم العقد واسمه والمتعهد.
      «البند الرئيسي» ورقمه يأتيان من جدول الكميات (يُربطان عند استيراد ملف Excel).</div>
    <div class="grid3">
      <?php fld('p_right_line3', 'يمين — السطر الثالث', $S, $D); ?>
      <?php fld('p_contract_label', 'عنوان رقم العقد', $S, $D); ?>
      <?php fld('p_contractor_label', 'عنوان المتعهد', $S, $D); ?>
    </div>
    <div class="grid3">
      <div class="field">
        <label>شعار الوسط</label>
        <select name="p_center_logo">
          <option value="second" <?= $S['p_center_logo'] === 'second' ? 'selected' : '' ?>>الشعار الثاني (مثل شعار الدولة)</option>
          <option value="main" <?= $S['p_center_logo'] === 'main' ? 'selected' : '' ?>>الشعار الرئيسي</option>
        </select>
        <div class="fhint">إن لم يُرفع شعار ثانٍ يُستعمل الرئيسي.</div>
      </div>
      <?php fld('p_doc_title', 'اسم المستند في الطباعة', $S, $D, 'اختياري، مثل <b>دفعة الإنجاز</b>. فارغ = اسم المستند نفسه (حصر عمل / أمر عمل).', 0, 'اسم المستند نفسه'); ?>
      <?php fld('p_value_label', 'عنوان قيمة المستند', $S, $D, 'القيمة بعد خصم النسبة.'); ?>
    </div>
    <div class="grid4">
      <?php fld('p_exec_label', 'عنوان المدة', $S, $D); ?>
      <?php fld('p_start_label', 'عنوان تاريخ البدء', $S, $D); ?>
      <?php fld('p_fine_label', 'عنوان الغرامة', $S, $D); ?>
      <?php fld('p_money_word', 'كلمة العملة (الترويسة)', $S, $D); ?>
    </div>
    <div class="grid4">
      <?php fld('p_count_label', 'عنوان عدد البنود', $S, $D); ?>
      <?php fld('p_words_label', 'عنوان المبلغ بالحروف', $S, $D, 'يُكتب المبلغ المستحق بالحروف تلقائياً.'); ?>
      <?php fld('p_days_word', 'كلمة الأيام', $S, $D); ?>
      <?php fld('p_cur_short', 'العملة (الإجماليات)', $S, $D); ?>
    </div>
    <div class="grid4">
      <?php fld('p_total_label', 'الإجمالي قبل الخصم', $S, $D); ?>
      <?php fld('p_discount_label', 'الخصم', $S, $D, 'تُكتب بعده النسبة تلقائياً.'); ?>
      <?php fld('p_fine_total_label', 'إجمالي الغرامة', $S, $D); ?>
      <?php fld('p_due_label', 'المبلغ المستحق', $S, $D); ?>
    </div>
    <?php fld('p_sign_titles', 'مسميات التواقيع (سطر لكل مسمى، من اليمين لليسار)', $S, $D, '', 4); ?>
    <div class="grid2">
      <?php fld('p_notes_label', 'ملاحظات المهندس', $S, $D); ?>
      <?php fld('p_receive_label', 'تاريخ الاستلام', $S, $D, 'يُطبع بعده تاريخ انتهاء العمل من المستند.'); ?>
    </div>
  </div>

  <div class="panel" style="margin-bottom:18px" data-types="landscape">
    <div class="sec">📕 «البلدية» — الغلاف</div>
    <div class="sec-note">الغلاف: الشعار، السطران أدناه، رقم المستند، ثم المنطقة وموقع العمل والإدارة ورقم العقد واسم المقاول.</div>
    <input type="hidden" name="l_cover" value="0">
    <label style="display:block;margin-bottom:12px"><input type="checkbox" name="l_cover" value="1" <?= $S['l_cover'] === '1' ? 'checked' : '' ?>>
      طباعة صفحة غلاف قبل صفحات البنود</label>
    <div class="grid3">
      <?php fld('l_cover_line1', 'الغلاف — السطر الأول', $S, $D); ?>
      <?php fld('l_cover_line2', 'الغلاف — السطر الثاني', $S, $D); ?>
      <?php fld('l_cover_site_label', 'الغلاف — عنوان موقع العمل', $S, $D); ?>
    </div>
  </div>

  <div class="panel" style="margin-bottom:18px" data-types="classic">
    <div class="sec">✍️ «الإعاقة + الشئون» — التذييل (كتلة التواقيع)</div>
    <div class="sec-note">تظهر أسفل آخر صفحة في الحصر المبدئي وحصر العمل وأمر العمل.</div>
    <div class="grid3">
      <?php fld('sign1_title', 'المسمى الأول', $S, $D, 'يُطبع تحته اسم المهندس المشرف تلقائياً.'); ?>
      <?php fld('sign2_title', 'المسمى الثاني', $S, $D); ?>
      <?php fld('sign3_title', 'المسمى الثالث', $S, $D); ?>
    </div>
    <?php fld('rep_row_label', 'صف مندوب المتعهد', $S, $D, '', 0, 'أدخل نص صف المندوب'); ?>
    <div class="grid2">
      <?php fld('bottom_right_label', 'التوقيع السفلي — يمين', $S, $D, '', 0, 'أدخل التوقيع السفلي الأيمن'); ?>
      <?php fld('bottom_left_label', 'التوقيع السفلي — يسار', $S, $D, '', 0, 'أدخل التوقيع السفلي الأيسر'); ?>
    </div>
  </div>

  <div class="panel" style="margin-bottom:18px" data-types="classic landscape">
    <div class="sec">📋 محضر الاستلام</div>
    <div class="sec-note">صفحة إضافية تُطبع مع أمر العمل فقط.</div>
    <?php fld('handover_title', 'عنوان المحضر', $S, $D, 'يُطبع بعده رقم أمر العمل تلقائياً.'); ?>
    <?php fld('handover_note1', 'الفقرة الأولى', $S, $D,
        'الرموز التي تُستبدل بالقيم الفعلية: <b>{تاريخ_البدء}</b> و <b>{تاريخ_الانتهاء}</b> و <b>{رقم_المستند}</b>.', 3,
        'أدخل نص الفقرة الأولى'); ?>
    <div class="grid3">
      <?php fld('handover_entity1', 'الجهة الأولى', $S, $D); ?>
      <?php fld('handover_entity2', 'الجهة الثانية', $S, $D); ?>
      <?php fld('handover_entity3', 'الجهة الثالثة', $S, $D); ?>
    </div>
    <?php fld('handover_note2', 'الفقرة الثانية', $S, $D, '', 3, 'أدخل نص الفقرة الثانية'); ?>
  </div>

  <div class="panel" style="margin-bottom:18px" data-types="all">
    <div class="sec">🧭 اسم النظام على الشاشة</div>
    <div class="sec-note">يظهر أعلى القائمة الجانبية وفي عنوان تبويب المتصفح وصفحة الدخول.</div>
    <?php fld('app_title', 'اسم النظام', $S, $D); ?>
  </div>

  <button class="btn btn-primary" data-types="all">💾 حفظ نوع المشروع وكل النصوص</button>
</form>

<hr class="sep">
<form method="post" onsubmit="return confirm('إعادة كل نصوص الترويسة والتذييل إلى الافتراضي؟ الشعار لن يُمسّ.');">
  <?= mq_csrf_field() ?>
  <input type="hidden" name="action" value="reset">
  <button class="btn btn-soft">↺ استعادة كل النصوص الافتراضية</button>
</form>
<?php if (print_logo_file()): ?>
<form method="post" style="margin-top:10px" onsubmit="return confirm('حذف الشعار المرفوع؟');">
  <?= mq_csrf_field() ?>
  <input type="hidden" name="action" value="drop_logo">
  <button class="btn btn-soft">🗑️ حذف الشعار المرفوع</button>
</form>
<?php endif; ?>
<?php if (print_logo2_file()): ?>
<form method="post" style="margin-top:10px" onsubmit="return confirm('حذف الشعار الثاني؟');">
  <?= mq_csrf_field() ?>
  <input type="hidden" name="action" value="drop_logo2">
  <button class="btn btn-soft">🗑️ حذف الشعار الثاني</button>
</form>
<?php endif; ?>

<script>
// نوع المشروع أولاً: تظهر أقسام الترويسة والتذييل الخاصة بالنوع المختار فقط (الخانات المخفية تبقى محفوظة كما هي)
(function(){
  var radios=document.querySelectorAll('input[name="print_layout"]');
  function show(t){
    document.querySelectorAll('[data-types]').forEach(function(el){
      var ts=el.getAttribute('data-types').split(' ');
      el.style.display=(t && (ts.indexOf('all')>=0 || ts.indexOf(t)>=0)) ? '' : 'none';
    });
    document.getElementById('ptype_hint').style.display=t ? 'none' : '';
  }
  var cur='';
  radios.forEach(function(r){ if(r.checked) cur=r.value; r.addEventListener('change', function(){ show(r.value); }); });
  show(cur);
})();

// زر ↺ يعيد النص الافتراضي إلى الخانة (لا يُحفظ حتى تضغط حفظ)
document.querySelectorAll('.reset-one').forEach(function(b){
  b.addEventListener('click', function(){
    var el = document.getElementById('f_' + b.dataset.target);
    if (el) { el.value = b.dataset.default; el.dispatchEvent(new Event('input')); el.focus(); }
  });
});

// معاينة حيّة للترويسة
(function(){
  var map = { pv1:'ministry_name', pv2:'head_right_line2', pv3:'contractor_name', pv4:'head_left_line2', pv5:'contract_label' };
  function sync(){
    for (var out in map) {
      var src = document.getElementById('f_' + map[out]);
      var dst = document.getElementById(out);
        if (src && dst) {
        dst.textContent = src.value;
        if (out !== 'pv5') dst.style.display = src.value.trim() === '' ? 'none' : '';
      }
    }
  }
  for (var out in map) {
    var src = document.getElementById('f_' + map[out]);
    if (src) src.addEventListener('input', sync);
  }
  sync();
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>

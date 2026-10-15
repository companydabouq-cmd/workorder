<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$PAGE_KEY='boq'; $PAGE_TITLE='جداول الكميات';

$msg=''; $preview=null;
$hasMain = has_item_main();   // عمودا البند الرئيسي (رقمه واسمه) — لطباعة «دفعة الإنجاز»

// تنسيق الجدول يتبع الترويسة المختارة: «المالية» مع البند الرئيسي، والبقية بالتنسيق الأصلي (بلا بند رئيسي)
$mainFmt = boq_main_format();
$fmtName = boq_format_name();
$hasKey  = has_boq_key();   // مفتاح البحث: يطابق رقم البند بأي ترتيب كُتب به (8/1-أ = أ-8/1)

// طريقة اختيار البند: هل يكتب المستخدم رقم البند ثم الباب (8/1) أم الباب ثم البند (1/8)
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['act']??'')==='order') {
    csrf_check();
    $ORD = boq_orders();
    $t = (string)($_POST['type_order'] ?? '');
    $f = (string)($_POST['file_order'] ?? 'auto');
    setting_set('boq_file_order', isset($ORD[$f]) ? $f : 'auto');
    setting_set('boq_type_order', isset($ORD[$t]) ? $t : '');
    flash_set('ok', 'تم حفظ طريقة اختيار البند: ' . ($ORD[$t] ?? 'كما في الجدول') . '.');
    header('Location: boq.php'); exit;
}

/**
 * تخمين ربط الأعمدة من صف العناوين في الملف (البند، البند الرئيسي، رقم البند الرئيسي،
 * وصف الأعمال، الوحدة، السعر...). يبحث في أول 6 صفوف عن الصف الأكثر تطابقاً.
 * يرجع [رقم صف العناوين (من 0)، [الحقل => رقم العمود]] — أو [0, null] إن لم يتعرّف
 * على رقم البند والوصف والسعر معاً (فيبقى الترتيب الافتراضي القديم).
 */
function boq_guess_columns(array $rows) {
    $norm = function ($s) {
        $s = str_replace(["\xD9\x80", ' ', "\xc2\xa0", "\t", "\n", "\r"], '', (string)$s);   // تطويل ومسافات
        $s = str_replace(['أ', 'إ', 'آ'], 'ا', $s);
        return str_replace('ة', 'ه', $s);
    };
    $best = null; $bestIdx = 0;
    foreach (array_slice($rows, 0, 6) as $ri => $row) {
        $map = [];
        foreach ($row as $ci => $cell) {
            $h = $norm($cell);
            if ($h === '' || mb_strlen($h) > 40) continue;          // خلية طويلة = بيانات لا عنوان
            $has = function ($w) use ($h) { return mb_strpos($h, $w) !== false; };
            if ($has('رئيسي'))                                              $key = $has('رقم') ? 'mno' : 'mname';
            elseif ($has('وصف') || $has('تفاصيل') || $has('بيان') || $has('نصالبند')) $key = 'det';
            elseif ($has('وحده') && !$has('سعر'))                           $key = 'unit';   // «الوحدة» لا «سعر الوحدة»
            elseif ($has('سعر'))                                            $key = $has('بعد') ? 'after' : 'before';
            elseif ($has('بند'))                                            $key = 'no';
            else continue;
            if (!isset($map[$key])) $map[$key] = $ci;
        }
        if (count($map) > count($best ?? [])) { $best = $map; $bestIdx = $ri; }
    }
    if ($best && isset($best['no'], $best['det'], $best['before'])) return [$bestIdx, $best];
    return [0, null];
}

// خطوة 1: رفع الملف وعرض معاينة لربط الأعمدة
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['act']??'')==='upload') {
    csrf_check();
    if (!empty($_FILES['file']) && $_FILES['file']['error']===UPLOAD_ERR_OK) {
        $tmp=$_FILES['file']['tmp_name'];
        $ext=strtolower(pathinfo($_FILES['file']['name'],PATHINFO_EXTENSION));
        $rows=[];
        if ($ext==='csv') {
            // Excel العربي يحفظ «CSV (Comma delimited)» بترميز Windows-1256 لا UTF-8:
            // نحوّله وإلا ضاعت كل النصوص العربية واستُورد جدول بلا أوصاف
            $raw=file_get_contents($tmp);
            $raw=preg_replace('/^\xEF\xBB\xBF/', '', $raw);   // علامة BOM في بداية ملف UTF-8
            if (!mb_check_encoding($raw,'UTF-8')) {
                $conv=function_exists('iconv') ? @iconv('CP1256','UTF-8//IGNORE',$raw) : false;
                if ($conv!==false) $raw=$conv;
            }
            $fh=fopen('php://temp','r+'); fwrite($fh,$raw); rewind($fh);
            // كشف الفاصل
            $first=fgets($fh); rewind($fh);
            $delim = (substr_count($first,';')>substr_count($first,',')) ? ';' : ',';
            while(($r=fgetcsv($fh,0,$delim))!==false){ $rows[]=$r; }
            fclose($fh);
        } elseif ($ext==='xlsx') {
            require_once __DIR__.'/../vendor/SimpleXLSX.php';
            $x=SimpleXLSX::parse($tmp);
            if($x===false){ $msg='<div class="flash er">خطأ في قراءة الملف: '.e(SimpleXLSX::$lastError).'</div>'; }
            else { $rows=$x->rows(); }
        } else {
            $msg='<div class="flash er">صيغة غير مدعومة. ارفع ملف .xlsx أو .csv</div>';
        }
        if ($rows) {
            // احفظ مؤقتاً في الجلسة (أول 5000 صف كحد)
            $_SESSION['boq_rows']=array_slice($rows,0,6000);
            $preview=array_slice($rows,0,6);
        }
    } else {
        $msg='<div class="flash er">لم يتم اختيار ملف</div>';
    }
}

// خطوة 2: تأكيد الربط والاستيراد
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['act']??'')==='import') {
    csrf_check();
    $rows=$_SESSION['boq_rows']??[];
    $cNo=(int)$_POST['col_no']; $cDet=(int)$_POST['col_det']; $cUnit=(int)$_POST['col_unit'];
    $cBefore=(int)$_POST['col_before']; $cAfter=(int)$_POST['col_after'];
    $cMno=(int)($_POST['col_mno']??-1); $cMname=(int)($_POST['col_mname']??-1);
    if (!$mainFmt) { $cMno=-1; $cMname=-1; }   // التنسيق الأصلي: لا بند رئيسي
    $skipHeader = !empty($_POST['skip_header']);
    $replace = !empty($_POST['replace']);
    $mergeSub = !empty($_POST['merge_sub']); // دمج البنود الرئيسية مع التفاصيل الأبجدية

    // تنظيف النصوص من المسافات غير الكاسرة والأحرف الخفية
    $cleanText = function($v){
        if ($v===null) return '';
        $s = (string)$v;
        // استبدال المسافات غير الكاسرة والأحرف الخفية بمسافة عادية
        $s = str_replace(["\xc2\xa0","\xe2\x80\x8c","\xe2\x80\x8f","\xe2\x80\x8e","\xe2\x80\x8b"], ' ', $s);
        // دمج المسافات المتعددة (ترجع null إن لم يكن النص UTF-8 صالحاً، مثل CSV بترميز Windows-1256)
        $s = preg_replace('/\s+/u', ' ', $s);
        return trim($s ?? '');
    };
    // استخراج الحرف الأبجدي من بداية التفصيل (أ ب ج ...)
    $arLetter = function($det){
        if (preg_match('/^([\x{0621}-\x{064A}])\s*[\.\-\)]/u', $det, $m)) return $m[1];
        return '';
    };
    $toNum = function($v){ return (float)str_replace([',',' '], '', (string)$v); };

    // لا بيانات في الجلسة (إعادة إرسال النموذج بعد استيراد سابق، أو انتهاء الجلسة):
    // لا نلمس الجدول، وإلا مسح «الاستبدال» كل البنود واستورد صفراً
    if (!$rows) {
        flash_set('er', 'لا توجد بيانات للاستيراد — أعد رفع الملف. لم يتغيّر جدول الكميات.');
        header('Location: boq.php'); exit;
    }

    $pdo=db();
    if ($pdo->inTransaction()) $pdo->rollBack(); // نظافة احترازية
    $pdo->beginTransaction();
    try {
    // الحذف داخل المعاملة: إن فشل الاستيراد يُسترجع جدول الكميات القديم كما هو
    if ($replace) $pdo->exec("DELETE FROM boq_items");
    $insCols = 'item_no,item_details,unit,price_before,price_after' . ($hasMain ? ',main_no,main_name' : '') . ($hasKey ? ',item_key' : '');
    $st=$pdo->prepare("INSERT INTO boq_items ($insCols) VALUES (" . implode(',', array_fill(0, substr_count($insCols, ',') + 1, '?')) . ")");
    $count=0; $i=0;
    $curMainNo=''; $curMainDet='';
    $lastMno=''; $lastMname='';   // البند الرئيسي يُكتب غالباً مرة واحدة أعلى مجموعته (خلايا مدمجة) فيُسحب لما تحته
    $ins=function($no,$det,$unit,$pb,$pa) use ($st,$hasMain,$hasKey,&$lastMno,&$lastMname){
        $v=[mb_substr($no,0,250),$det,$unit,$pb,$pa];
        if ($hasMain) { $v[]=$lastMno!==''?mb_substr($lastMno,0,50):null; $v[]=$lastMname!==''?mb_substr($lastMname,0,255):null; }
        if ($hasKey) $v[]=boq_item_key($no);
        $st->execute($v);
    };
    $skipRows = max(1, (int)($_POST['skip_rows'] ?? 1));   // صف العناوين وما فوقه (عناوين الملف العلوية)
    $pendMainNo=''; $pendMainDet='';                         // آخر بند بلا سعر: بنوده الفرعية المرقّمة تأخذ وصفه
    // هل $no بند فرعي للبند $p؟ (2 ← أ/2 أو 2/أ): نفس الرقم مع جزء إضافي غير رقمي (حرف)
    $isSubOf = function($no, $p){
        if ($p==='' || $no===$p) return false;
        foreach (['/','-'] as $sep) {
            $pl=mb_strlen($sep.$p);
            if (mb_substr($no, -$pl)===$sep.$p) $rest=mb_substr($no, 0, mb_strlen($no)-$pl);
            elseif (mb_strpos($no, $p.$sep)===0) $rest=mb_substr($no, $pl);
            else continue;
            if (preg_match('/^[^\d\/\-\s]{1,3}$/u', trim($rest))) return true;
        }
        return false;
    };
    foreach ($rows as $r) {
        $i++;
        if ($skipHeader && $i<=$skipRows) continue;
        $no  = $cleanText($r[$cNo]??'');
        $det = $cleanText($r[$cDet]??'');
        $unit= $cleanText($r[$cUnit]??'');
        $rawBefore = $r[$cBefore]??'';
        $hasPrice = ($rawBefore!==null && trim((string)$rawBefore)!=='');
        $pb = $toNum($rawBefore);
        $pa = ($cAfter>=0 && isset($r[$cAfter]) && trim((string)$r[$cAfter])!=='') ? $toNum($r[$cAfter]) : $pb;

        if ($cMno>=0)   { $m=$cleanText($r[$cMno]??'');   if ($m!=='') $lastMno=$m; }
        if ($cMname>=0) { $m=$cleanText($r[$cMname]??''); if ($m!=='') $lastMname=$m; }

        if ($no==='' && $det==='') continue;

        if (!$mergeSub) {
            // وضع بسيط: استورد كل صف كما هو (مع حد أمان للطول)
            if ($no==='' ) continue;
            $ins($no,$det,$unit,$pb,$pa); $count++;
            continue;
        }

        // وضع الدمج (مناسب لملفك): بند رئيسي + تفاصيل أبجدية
        if ($no!=='') {
            $curMainNo=$no; $curMainDet=$det;
            if ($hasPrice) {
                // بند له سعر. إن كان فرعياً مرقّماً لبند بلا سعر قبله (2 ثم أ/2، ب/2) يُسبق وصفه بوصف ذلك البند
                $fullDet=$det;
                if ($isSubOf($no, $pendMainNo)) {
                    $fullDet=$pendMainDet.(preg_match('/[:\-]\s*$/u', $pendMainDet) ? ' ' : ' - ').$det;
                } else {
                    $pendMainNo=''; $pendMainDet='';   // بند مستقل: انتهت مجموعة البند السابق
                }
                $ins($no,$fullDet,$unit,$pb,$pa); $count++;
            } else {
                // بلا سعر: بند رئيسي، ننتظر تفاصيله (أبجدية بلا رقم، أو فرعية مرقّمة)
                $pendMainNo=$no; $pendMainDet=$det;
            }
        } elseif ($det!=='') {
            // تفصيل فرعي تابع للبند الرئيسي الحالي
            $lt=$arLetter($det);
            $combo=$curMainNo.($lt!==''?' - '.$lt:'');
            $fullDet=($curMainDet!==''?$curMainDet.' - ':'').$det;
            if ($combo==='') continue;
            $ins($combo,$fullDet,$unit,$pb,$pa); $count++;
        }
    }
    if ($count === 0) {
        // ربط أعمدة خاطئ أو ترميز غير UTF-8: لا تستبدل الجدول بلا شيء
        $pdo->rollBack();
        flash_set('er', 'لم يُستورد أي بند — تحقق من ربط الأعمدة، أو احفظ ملف CSV بترميز UTF-8 (أو استخدم xlsx). لم يتغيّر جدول الكميات.');
        header('Location: boq.php'); exit;
    }
    $pdo->commit();
    setting_set('boq_file_order_auto', '');   // جدول جديد: يُعاد اكتشاف ترتيب أرقامه (بند/باب أو باب/بند)
    unset($_SESSION['boq_rows']);
    flash_set('ok',"تم استيراد $count بند بنجاح");
    } catch (Exception $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash_set('er','خطأ في الاستيراد: '.$ex->getMessage());
    }
    header('Location: boq.php'); exit;
}

// ترقيم صفحات للعرض (للتعامل مع آلاف البنود)
$per=20;
$page=max(1,(int)($_GET['p']??1));
$search=trim($_GET['s']??'');
$where=''; $params=[];
if ($search!==''){
    $where="WHERE item_no LIKE ? OR item_details LIKE ?"; $params=["%$search%","%$search%"];
    if ($hasKey) { $where.=" OR item_key LIKE ?"; $params[]='%'.boq_query_key($search).'%'; }
}
$total=(int)q_val("SELECT COUNT(*) FROM boq_items $where",$params);
$pages=max(1,ceil($total/$per));
$page=min($page,$pages);
$off=($page-1)*$per;
$items=q_all("SELECT * FROM boq_items $where ORDER BY id ASC LIMIT $per OFFSET $off",$params);

// طريقة اختيار البند: ترتيب الجدول (المحدَّد أو المكتشَف) وطريقة كتابة المستخدم
$ORD        = boq_orders();
$ordFileSet = setting_get('boq_file_order', 'auto'); if (!isset($ORD[$ordFileSet])) $ordFileSet = 'auto';
$ordFile    = boq_file_order();
$ordFileAuto= $ordFileSet === 'auto' ? $ordFile : boq_detect_order();
$ordType    = boq_type_order();

require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div class="page-title"><span class="ic">📋</span> جداول الكميات (البنود والأسعار)</div>
  <button class="btn btn-primary" onclick="openModal('up')">⬆️ إضافة / استيراد جدول كميات</button>
</div>
<?= flash_get() ?><?= $msg ?>

<?php
  // مثال من الجدول نفسه (بند فيه رقمان مختلفان، ويفضَّل أن يكون له فرع) — وإلا مثال ثابت
  $exItem = '8'; $exBab = '1'; $exSub = 'أ';
  if ($hasKey) {
      foreach (q_all("SELECT item_no, item_key FROM boq_items WHERE item_key REGEXP '^[0-9]+/[0-9]+' ORDER BY (item_key LIKE '%-%') DESC, id LIMIT 300") as $r) {
          if (!preg_match('/^(\d+)\/(\d+)(?:-(.+))?$/u', $r['item_key'], $m) || $m[1] === $m[2]) continue;
          [$exItem, $exBab] = $ordFile === 'bab_item' ? [$m[2], $m[1]] : [$m[1], $m[2]];
          $exSub = '';
          if (isset($m[3])) $exSub = preg_match('/\p{L}+/u', (string)$r['item_no'], $l) ? $l[0] : $m[3];
          break;
      }
  }
  $exTail = $exSub !== '' ? '-' . $exSub : '';
  $exWords = 'البند ' . $exItem . ' من الباب ' . $exBab . ($exSub !== '' ? '، الفرع ' . $exSub : '');
?>
<div class="panel" style="margin-bottom:14px">
  <form method="post" action="boq.php">
    <?= mq_csrf_field() ?><input type="hidden" name="act" value="order">
    <div class="field" style="margin-bottom:8px">
      <label>طريقة اختيار البند</label>
      <p class="muted" style="margin:0 0 8px">بأي ترتيب تكتب رقم البند عند اختياره في الحصر المبدئي وحصر العمل وأمر العمل، وفي البحث هنا؟
        مثال من الجدول: <b><?= e($exWords) ?></b>.</p>
      <label class="opt" style="display:block;margin-bottom:6px"><input type="radio" name="type_order" value="item_bab" <?= $ordType === 'item_bab' ? 'checked' : '' ?>>
        <b>رقم البند ثم رقم الباب</b><?= $exSub !== '' ? ' ثم الفرع' : '' ?> — تكتب <b><?= e($exItem) ?></b> ثم <b><?= e($exBab) ?></b><?= $exSub !== '' ? ' ثم <b>'.e($exSub).'</b>' : '' ?>
        <span class="muted">(<bdi dir="ltr"><?= e($exItem . '/' . $exBab . $exTail) ?></bdi>)</span></label>
      <label class="opt" style="display:block"><input type="radio" name="type_order" value="bab_item" <?= $ordType === 'bab_item' ? 'checked' : '' ?>>
        <b>رقم الباب ثم رقم البند</b><?= $exSub !== '' ? ' ثم الفرع' : '' ?> — تكتب <b><?= e($exBab) ?></b> ثم <b><?= e($exItem) ?></b><?= $exSub !== '' ? ' ثم <b>'.e($exSub).'</b>' : '' ?>
        <span class="muted">(<bdi dir="ltr"><?= e($exBab . '/' . $exItem . $exTail) ?></bdi>)</span></label>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
      <span class="muted">الأرقام في الجدول المرفوع مكتوبة:</span>
      <select name="file_order" style="width:auto;min-width:260px">
        <option value="auto" <?= $ordFileSet === 'auto' ? 'selected' : '' ?>>تلقائي — <?= e($ORD[$ordFileAuto]) ?></option>
        <?php foreach ($ORD as $k => $nm): ?>
        <option value="<?= e($k) ?>" <?= $ordFileSet === $k ? 'selected' : '' ?>><?= e($nm) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-primary btn-sm">💾 حفظ الطريقة</button>
    </div>
    <p class="muted" style="margin:8px 0 0">رقم البند يبقى محفوظاً ومطبوعاً كما هو في الجدول؛ هذا الاختيار لطريقة الكتابة عند البحث والاختيار فقط.
      والفرع يُقبل في أول الرقم أو آخره.</p>
  </form>
</div>

<div class="panel">
  <p class="muted" style="margin:0 0 12px">تنسيق الجدول يتبع نوع المشروع <b>«<?= e($fmtName) ?>»</b>:
    <?= $mainFmt ? 'مع البند الرئيسي (رقمه واسمه).' : 'رقم البند، التفاصيل، الوحدة، السعر قبل وبعد الخصم.' ?>
    لتغييره غيّر نوع المشروع من صفحة <a href="print_settings.php"><b>«الترويسة والتذييل»</b></a>.</p>
  <div class="table-tools">
    <form method="get" style="display:flex;gap:8px">
      <input class="search-box" name="s" value="<?= e($search) ?>" placeholder="بحث برقم البند أو التفاصيل...">
      <button class="btn btn-soft btn-sm">بحث</button>
    </form>
    <div class="muted">إجمالي البنود: <b><?= number_format($total) ?></b></div>
  </div>
  <div style="overflow-x:auto">
  <table class="tbl">
    <thead><tr><?php if($mainFmt): ?><th>البند الرئيسي</th><?php endif; ?><th>رقم البند</th><th>تفاصيل البند</th><th>الوحدة</th><th>السعر قبل الخصم</th><th>السعر بعد الخصم</th></tr></thead>
    <tbody>
    <?php if(!$items): ?><tr><td colspan="<?= $mainFmt?6:5 ?>" class="empty"><?= $search!=='' ? 'لا توجد بنود مطابقة للبحث.' : 'لا توجد بنود. ارفع ملف Excel لاستيرادها.' ?></td></tr>
    <?php else: foreach($items as $it): ?>
      <tr><?php if($mainFmt): ?><td><?= e(trim(($it['main_no']??'').' '.($it['main_name']??''))) ?></td><?php endif; ?><td><?= e($it['item_no']) ?></td><td><?= e($it['item_details']) ?></td><td><?= e($it['unit']) ?></td>
      <td><?= fmt($it['price_before']) ?></td><td><?= fmt($it['price_after']) ?></td></tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
  </div>
  <?php if($pages>1): ?>
  <div style="display:flex;gap:6px;justify-content:center;margin-top:16px;flex-wrap:wrap">
    <?php
      $qs=$search!=='' ? '&s='.urlencode($search) : '';
      if($page>1) echo '<a class="btn btn-soft btn-sm" href="?p='.($page-1).$qs.'">السابق</a>';
      $start=max(1,$page-3); $end=min($pages,$page+3);
      if($start>1) echo '<span class="muted" style="padding:6px">1 ...</span>';
      for($i=$start;$i<=$end;$i++){
        $cls=$i===$page?'btn-primary':'btn-soft';
        echo '<a class="btn '.$cls.' btn-sm" href="?p='.$i.$qs.'">'.$i.'</a>';
      }
      if($end<$pages) echo '<span class="muted" style="padding:6px">... '.$pages.'</span>';
      if($page<$pages) echo '<a class="btn btn-soft btn-sm" href="?p='.($page+1).$qs.'">التالي</a>';
    ?>
  </div>
  <?php endif; ?>
</div>

<!-- نافذة الرفع -->
<div class="modal-bg" id="up"><div class="modal" style="max-width:620px">
  <div class="modal-head">استيراد جدول كميات من Excel</div>
  <form method="post" action="boq.php" enctype="multipart/form-data"><div class="modal-body">
    <?= mq_csrf_field() ?><input type="hidden" name="act" value="upload">
    <div class="field"><label>اختر ملف Excel (.xlsx) أو CSV <span class="req">*</span></label>
      <input type="file" name="file" accept=".xlsx,.csv" required></div>
    <p class="muted">بعد الرفع ستظهر معاينة لتربط الأعمدة (رقم البند، التفاصيل، الوحدة، السعر) — أكثر أماناً مع أي ترتيب أعمدة.</p>
  </div><div class="modal-foot">
    <button class="btn btn-primary">⬆️ رفع ومعاينة</button>
    <button type="button" class="btn btn-warn" onclick="closeModal('up')">إغلاق</button>
  </div></form>
</div></div>

<?php if($preview): // نافذة ربط الأعمدة بعد الرفع
  $ncols=0; foreach($preview as $pr){ $ncols=max($ncols,count($pr)); }
  // تخمين الربط من عناوين الملف؛ إن لم تُعرف العناوين يبقى الترتيب الافتراضي القديم
  [$hdrIdx, $G] = boq_guess_columns($preview);
  $colNames = $G ? array_map(function($c){ return trim(preg_replace('/s+/u', ' ', (string)$c)); }, $preview[$hdrIdx]) : [];
?>
<div class="modal-bg show" id="map"><div class="modal" style="max-width:900px">
  <div class="modal-head">ربط الأعمدة ومعاينة البيانات</div>
  <form method="post" action="boq.php"><div class="modal-body">
    <?= mq_csrf_field() ?><input type="hidden" name="act" value="import">
    <input type="hidden" name="skip_rows" value="<?= (int)$hdrIdx + 1 ?>">
    <?php if ($G): ?>
      <div class="flash ok" style="margin-bottom:12px">✓ تم التعرّف على أعمدة الملف تلقائياً من عناوينه (الصف <?= (int)$hdrIdx + 1 ?>) — راجع الربط أدناه ثم اضغط «استيراد البنود».</div>
    <?php else: ?>
      <div class="flash er" style="margin-bottom:12px">لم أتعرّف على عناوين الأعمدة في الملف — حدّد يدوياً أي عمود يقابل كل حقل.</div>
    <?php endif; ?>
    <p class="muted">معاينة أول صفوف الملف. حدّد أي عمود يقابل كل حقل:</p>
    <div style="overflow-x:auto;margin-bottom:16px">
      <table class="tbl"><thead><tr>
        <?php for($i=0;$i<$ncols;$i++) echo '<th>عمود '.($i+1).'</th>'; ?>
      </tr></thead><tbody>
      <?php foreach($preview as $pr): ?>
        <tr><?php for($i=0;$i<$ncols;$i++) echo '<td>'.e($pr[$i]??'').'</td>'; ?></tr>
      <?php endforeach; ?>
      </tbody></table>
    </div>
    <?php
      function colsel($name,$ncols,$def,$allowNone=false){
        global $colNames;   // عناوين الملف (إن عُرفت) تُكتب بجانب رقم العمود
        $o='<select name="'.$name.'" class="">';
        if($allowNone) $o.='<option value="-1">- لا يوجد -</option>';
        for($i=0;$i<$ncols;$i++){
          $s=$i===$def?' selected':'';
          $nm=(isset($colNames[$i]) && $colNames[$i]!=='') ? ' — '.e(mb_substr($colNames[$i],0,30)) : '';
          $o.='<option value="'.$i.'"'.$s.'>عمود '.($i+1).$nm.'</option>';
        }
        return $o.'</select>';
      }
    ?>
    <div class="grid3">
      <div class="field"><label>رقم البند</label><?= colsel('col_no',$ncols,$G['no'] ?? 0) ?></div>
      <div class="field"><label>تفاصيل البند</label><?= colsel('col_det',$ncols,$G['det'] ?? 1) ?></div>
      <div class="field"><label>الوحدة</label><?= colsel('col_unit',$ncols,$G ? ($G['unit'] ?? -1) : 2, (bool)$G) ?></div>
    </div>
    <div class="grid2">
      <div class="field"><label>السعر قبل الخصم</label><?= colsel('col_before',$ncols,$G['before'] ?? 3) ?></div>
      <div class="field"><label>السعر بعد الخصم</label><?= colsel('col_after',$ncols,$G ? ($G['after'] ?? -1) : 4,true) ?></div>
    </div>
    <?php if($mainFmt): // حقلا البند الرئيسي من تنسيق «المالية» فقط ?>
    <div class="grid2">
      <div class="field"><label>رقم البند الرئيسي (اختياري)</label><?= colsel('col_mno',$ncols,$G['mno'] ?? -1,true) ?></div>
      <div class="field"><label>اسم البند الرئيسي (اختياري)</label><?= colsel('col_mname',$ncols,$G['mname'] ?? -1,true) ?></div>
    </div>
    <p class="muted" style="margin-top:-6px">مثل: 1 / أعمال تكييف الهواء، 2 / قطع الغيار — يُطبعان في شكل «المالية». الخانة الفارغة تأخذ قيمة الصف الذي فوقها.</p>
    <?php endif; ?>
    <div class="field"><label><input type="checkbox" name="skip_header" checked> <?= $hdrIdx > 0 ? 'تجاهل صف العناوين وما فوقه (أول '.((int)$hdrIdx+1).' صفوف)' : 'الصف الأول عناوين (تجاهله)' ?></label></div>
    <div class="field"><label><input type="checkbox" name="merge_sub" checked> دمج البنود الرئيسية مع تفاصيلها (أ، ب، ج)</label>
      <p class="muted" style="margin-top:4px">البند الذي بلا سعر (مثل 2) لا يُستورد وحده؛ وصفه يُضاف إلى أول وصف كل بند فرعي تحته (2/أ، 2/ب) فيظهر الوصف كاملاً في الطباعة. والتفاصيل الأبجدية التي بلا رقم يُنشأ لها بند مثل «1/1 - أ».</p></div>
    <div class="field"><label><input type="checkbox" name="replace" checked> استبدال كل البنود الحالية بالملف الجديد</label></div>
  </div><div class="modal-foot">
    <button class="btn btn-green">✓ استيراد البنود</button>
    <a class="btn btn-warn" href="boq.php">إلغاء</a>
  </div></form>
</div></div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>

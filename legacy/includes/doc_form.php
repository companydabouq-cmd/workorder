<?php
/**
 * نموذج مشترك لحصر العمل وأمر العمل
 * المتغيرات المطلوبة قبل التضمين:
 *   $DOC          : 'survey' أو 'order'
 *   $DOC_TITLE    : 'حصر عمل' أو 'أمر عمل'
 *   $TBL_MASTER   : 'work_surveys' أو 'work_orders'
 *   $TBL_ITEMS    : 'work_survey_items' أو 'work_order_items'
 *   $SEQ_KEY      : 'work_survey' أو 'work_order'
 *   $NO_FIELD     : 'survey_no' أو 'order_no'
 *   $LIST_PAGE    : صفحة القائمة للعودة إليها
 *   $PAGE_KEY     : مفتاح القائمة الجانبية
 */
require_once __DIR__ . '/../includes/functions.php';
require_staff();

$edit_id = (int)($_GET['id'] ?? 0);
$hasMain = has_item_main();   // البند الرئيسي (رقمه واسمه) يُنسخ مع كل بند — لطباعة «دفعة الإنجاز»
$hasExtra = has_extra();      // النسبة الإضافية لمنطقة الموقع (قبل المعاملة: إضافة الأعمدة تُنهي أي معاملة)
$hasMode  = has_price_mode(); // طريقة تسعير البند: سعر الجدول أو يومي ÷ 365 (قبل المعاملة أيضاً)
// نوع العمل: قائمة من أنواع أعمال العقد (المختارة في «بيانات العقد») — مع كل الترويسات.
// رمز النوع مع الرقم (54/ت) وخانة «بيان الأعمال»: مع ترويستي «البلدية» و«المالية» فقط.
$hasWT    = work_types_enabled();                         // الرمز وبيان الأعمال
$workTypes = has_work_types() ? contract_work_types() : [];   // بلا أنواع مختارة: خانة نص كما في الأصل
// البنود الرئيسية في جدول الكميات: لحصر البحث في نافذة «إضافة بند» (أرقام البنود تتكرر بين البنود الرئيسية)
// (البند الرئيسي جزء من تنسيق «المالية» فقط: مع الترويسات الأخرى تبقى النافذة بتنسيقها الأصلي)
$boqTotal = (int)q_val("SELECT COUNT(*) FROM boq_items");
$boqMains = boq_main_format() ? q_all("SELECT main_no, main_name, COUNT(*) n FROM boq_items
                              WHERE main_name IS NOT NULL AND main_name<>''
                              GROUP BY main_no, main_name ORDER BY MIN(id)") : [];
$disc = discount_percent();
$__contract = get_contract();
$dailyFine = (float)($__contract['daily_delay_fine'] ?? 0);

// قوائم منسدلة
$depts   = q_all("SELECT id,dept_name FROM departments ORDER BY dept_name");
$sites   = q_all("SELECT id,site_name,dept_id FROM worksites ORDER BY site_name");
// المواقع التي لها نسبة إضافية: {site_id: {pct, area, nodisc}} — nodisc = لا تُطبق نسبة الخصم في منطقتها
$siteExtra = sites_extra();
$mEmps   = q_all("SELECT id,emp_name FROM ministry_employees ORDER BY emp_name");
$cEmps   = q_all("SELECT id,emp_name FROM contractor_employees ORDER BY emp_name");

// تحميل سجل للتعديل
$doc=null; $docItems=[];
if ($edit_id) {
    $doc = q_row("SELECT * FROM $TBL_MASTER WHERE id=?", [$edit_id]);
    if ($doc) $docItems = q_all("SELECT * FROM $TBL_ITEMS WHERE {$DOC}_id=? ORDER BY id ASC", [$edit_id]);
}

// حفظ
if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $id=(int)($_POST['id']??0);
    $fields=[
        'dept_id'=>$_POST['dept_id']?:null,'site_id'=>$_POST['site_id']?:null,
        'work_type'=>trim($_POST['work_type']??''),'supervisor1_id'=>$_POST['supervisor1_id']?:null,
        'supervisor2_id'=>$_POST['supervisor2_id']?:null,'start_date'=>$_POST['start_date']?:null,
        'exec_days'=>(int)($_POST['exec_days']??0),'end_date'=>$_POST['end_date']?:null,
        'warranty_months'=>trim($_POST['warranty_months']??''),'delay_days'=>(int)($_POST['delay_days']??0),
        'rep_contractor_id'=>$_POST['rep_contractor_id']?:null,'head_eng_id'=>$_POST['head_eng_id']?:null,
        'delay_fine'=>(float)($_POST['delay_fine']??0),'notes'=>trim($_POST['notes']??''),
    ];
    // نوع العمل من القائمة: يُحفظ اسمه (ورمزه مع «البلدية» و«المالية») — «keep» = نوع قديم مكتوب نصاً يبقى كما هو
    if ($workTypes || $hasWT) {
        if (array_key_exists('work_type_id', $_POST)) {
            $wtSel = (string)$_POST['work_type_id'];
            if ($wtSel === 'keep') {
                unset($fields['work_type']);
            } else {
                $wt = null;
                foreach ($workTypes as $t) if ((string)$t['id'] === $wtSel) $wt = $t;
                $fields['work_type']      = $wt ? $wt['type_name'] : '';
                if ($hasWT) $fields['work_type_code'] = ($wt && $wt['type_code'] !== '') ? $wt['type_code'] : null;
            }
        }
        if ($hasWT) $fields['work_desc'] = trim($_POST['work_desc'] ?? '');
    }
    // النسبة الإضافية ونسبة الخصم: نسخة وقت الإنشاء، وتُؤخذان من جديد فقط إن تغيّر موقع العمل عند التعديل.
    // منطقة «بدون خصم» (من الإعدادات): خصم المستند = 0 فيأخذ النسبة الإضافية فقط.
    $docDisc = $disc;
    if ($hasExtra) {
        $orig = $id ? q_row("SELECT site_id, discount_percent FROM $TBL_MASTER WHERE id=?", [$id]) : null;
        if (!$id || (string)$orig['site_id'] !== (string)$fields['site_id']) {
            $ex = $siteExtra[(int)$fields['site_id']] ?? null;
            $fields['extra_percent'] = $ex ? $ex['pct'] : 0;
            $fields['extra_area']    = $ex ? $ex['area'] : null;
            $noDisc = $ex && $ex['nodisc'];
            if (!$id) $docDisc = $noDisc ? 0 : $disc;
            else $fields['discount_percent'] = $noDisc ? 0 : ((float)$orig['discount_percent'] > 0 ? (float)$orig['discount_percent'] : $disc);
        }
    }
    // البنود: نقرأها من حقل JSON واحد (يتجاوز حد max_input_vars مهما زاد العدد)
    $itemsList = [];
    if (!empty($_POST['items_json'])) {
        $decoded = json_decode($_POST['items_json'], true);
        if (is_array($decoded)) {
            foreach ($decoded as $row) {
                $itemsList[] = [
                    'no'    => trim($row['no'] ?? ''),
                    'det'   => trim($row['det'] ?? ''),
                    'unit'  => trim($row['unit'] ?? ''),
                    'qty'   => (float)($row['qty'] ?? 0),
                    'price' => (float)($row['price'] ?? 0),
                    'mode'  => ($row['mode'] ?? '') === 'daily' ? 'daily' : '',
                    'mno'   => trim($row['mno'] ?? ''),
                    'mname' => trim($row['mname'] ?? ''),
                ];
            }
        }
    } else {
        // احتياطي: الطريقة القديمة (مصفوفات متوازية)
        $itNo=$_POST['it_no']??[]; $itDet=$_POST['it_det']??[]; $itUnit=$_POST['it_unit']??[];
        $itQty=$_POST['it_qty']??[]; $itPrice=$_POST['it_price']??[];
        $itMno=$_POST['it_mno']??[]; $itMname=$_POST['it_mname']??[];
        for($i=0;$i<count($itNo);$i++){
            $itemsList[] = [
                'no'=>trim($itNo[$i]), 'det'=>trim($itDet[$i]??''), 'unit'=>trim($itUnit[$i]??''),
                'qty'=>(float)($itQty[$i]??0), 'price'=>(float)($itPrice[$i]??0), 'mode'=>'',
                'mno'=>trim($itMno[$i]??''), 'mname'=>trim($itMname[$i]??''),
            ];
        }
    }

    $pdo=db(); $pdo->beginTransaction();
    try {
        if ($id) {
            // تعديل
            $set=[]; $vals=[];
            foreach($fields as $k=>$v){ $set[]="$k=?"; $vals[]=$v; }
            $vals[]=$id;
            q("UPDATE $TBL_MASTER SET ".implode(',',$set)." WHERE id=?", $vals);
            q("DELETE FROM $TBL_ITEMS WHERE {$DOC}_id=?", [$id]);
            $docId=$id;
        } else {
            // جديد - رقم تسلسلي
            $useGap=(int)($_POST['use_gap']??0);
            if ($useGap>0){ $no=$useGap; consume_gap($SEQ_KEY,$no); }
            else { $no=next_sequence($SEQ_KEY); }
            $cols=array_keys($fields); $ph=array_fill(0,count($cols),'?');
            $vals=array_values($fields);
            q("INSERT INTO $TBL_MASTER ($NO_FIELD,discount_percent,".implode(',',$cols).")
               VALUES (?,?,".implode(',',$ph).")", array_merge([$no,$docDisc],$vals));
            $docId=$pdo->lastInsertId();
        }
        // إدراج البنود — 'price' هو سعر البند في جدول الكميات، ويُحسب منه سعر الوحدة حسب طريقة التسعير
        $insCols = "{$DOC}_id,item_no,item_details,unit,qty,unit_price,total_before"
                 . ($hasMain ? ',main_no,main_name' : '') . ($hasMode ? ',price_mode,base_price' : '');
        $sti=$pdo->prepare("INSERT INTO $TBL_ITEMS ($insCols) VALUES (" . implode(',', array_fill(0, substr_count($insCols, ',') + 1, '?')) . ")");
        foreach($itemsList as $row){
            $no=$row['no']; if($no==='') continue;
            $qty=$row['qty']; $base=$row['price'];
            $mode = $hasMode ? $row['mode'] : '';
            [$unitPrice, $total] = item_price_total($qty, $base, $mode);
            $v=[$docId,$no,$row['det'],$row['unit'],$qty,$unitPrice,$total];
            if ($hasMain) { $v[]=$row['mno']!==''?$row['mno']:null; $v[]=$row['mname']!==''?$row['mname']:null; }
            if ($hasMode) { $v[]=$mode!==''?$mode:null; $v[]=$base; }
            $sti->execute($v);
        }
        $pdo->commit();
        flash_set('ok','تم حفظ '.$DOC_TITLE.' بنجاح');
        header('Location: '.$LIST_PAGE.'?saved='.$docId); exit;
    } catch(Exception $ex){
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash_set('er','خطأ: '.$ex->getMessage());
        header('Location: '.$_SERVER['REQUEST_URI']); exit;
    }
}

// رقم العرض (للجديد نعرض المتوقع)
$displayNo = $doc ? $doc[$NO_FIELD] : ((int)q_val("SELECT last_no FROM number_sequences WHERE seq_key=?", [$SEQ_KEY]) + 1);
$useGap = (int)($_GET['gap'] ?? 0);
if ($useGap>0 && !$doc) $displayNo=$useGap;

require __DIR__ . '/../includes/header.php';
?>
<?= flash_get() ?>
<form method="post" id="docForm" onsubmit="return prepareSubmit()">
  <?= mq_csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)($doc['id'] ?? 0) ?>">
  <?php if($useGap>0 && !$doc): ?><input type="hidden" name="use_gap" value="<?= $useGap ?>"><?php endif; ?>

  <!-- رأس النموذج -->
  <div class="doc-head">
    <div class="doc-title"><span>📝</span> <?= e($doc ? 'تعديل '.$DOC_TITLE : $DOC_TITLE.' جديد') ?>
      <span class="doc-no">رقم <?= e(doc_no_label($displayNo, $doc['work_type_code'] ?? '')) ?></span></div>
    <div class="grid4">
      <div class="field"><label>اسم الإدارة <span class="req">*</span></label>
        <select name="dept_id" required><option value="">- الإدارة -</option>
          <?= options($depts,'id','dept_name',$doc['dept_id']??'') ?></select></div>
      <div class="field"><label>موقع العمل <span class="req">*</span></label>
        <select name="site_id" required onchange="extraChanged()"><option value="">- موقع العمل -</option>
          <?= options($sites,'id','site_name',$doc['site_id']??'') ?></select></div>
      <div class="field"><label>نوع العمل</label>
        <?php if ($workTypes): $curWT = trim((string)($doc['work_type'] ?? '')); $matched = false;
              $oneWT = count($workTypes) === 1;   // عقد بنوع أعمال واحد: يُكتب مباشرة بلا اختيار ?>
        <select name="work_type_id" id="work_type_id" onchange="wtChanged()">
          <?php if (!$oneWT): ?><option value="" data-code="">- اختر -</option><?php endif; ?>
          <?php foreach ($workTypes as $wt): $sel = $doc ? ($curWT === $wt['type_name']) : $oneWT; if ($sel) $matched = true; ?>
            <option value="<?= (int)$wt['id'] ?>" data-code="<?= $hasWT ? e($wt['type_code']) : '' ?>" <?= $sel ? 'selected' : '' ?>><?= e($wt['type_name']) ?><?= ($hasWT && $wt['type_code'] !== '') ? ' ('.e($wt['type_code']).')' : '' ?></option>
          <?php endforeach; ?>
          <?php if ($doc && !$matched && $curWT !== ''): ?>
            <option value="keep" data-code="<?= $hasWT ? e($doc['work_type_code'] ?? '') : '' ?>" selected><?= e($curWT) ?> (محفوظ)</option>
          <?php endif; ?>
        </select>
        <?php else: ?>
        <input name="work_type" value="<?= e($doc['work_type'] ?? 'أعمال مدنية') ?>">
        <?php endif; ?></div>
      <?php if ($hasWT): ?>
      <div class="field"><label>بيان الأعمال</label>
        <input name="work_desc" value="<?= e($doc['work_desc'] ?? '') ?>" placeholder="مثل: صيانة، نقاط انترنت"></div>
      <?php endif; ?>
      <div class="field"><label>اسم المشرف الأول</label>
        <select name="supervisor1_id"><option value="">- اختر -</option>
          <?= options($mEmps,'id','emp_name',$doc['supervisor1_id']??'') ?></select></div>

      <div class="field"><label>اسم المشرف الثاني</label>
        <select name="supervisor2_id"><option value="">- اختر -</option>
          <?= options($mEmps,'id','emp_name',$doc['supervisor2_id']??'') ?></select></div>
      <div class="field"><label>تاريخ بدء العمل</label>
        <input type="date" name="start_date" id="start_date" value="<?= e($doc['start_date']??'') ?>"></div>
      <div class="field"><label>مدة التنفيذ بالأيام</label>
        <input type="number" name="exec_days" id="exec_days" value="<?= e($doc['exec_days']??0) ?>"></div>
      <div class="field"><label>تاريخ انتهاء العمل</label>
        <input type="date" name="end_date" id="end_date" value="<?= e($doc['end_date']??'') ?>"></div>

      <div class="field"><label>فترة الضمان</label>
        <input name="warranty_months" value="<?= e($doc['warranty_months']??'12 شهر') ?>"></div>
      <div class="field"><label>مدة التأخير بالأيام</label>
        <input type="number" name="delay_days" id="delay_days" value="<?= e($doc['delay_days']??0) ?>" oninput="calcFine()"></div>
      <div class="field"><label>ممثل المتعهد</label>
        <select name="rep_contractor_id"><option value="">- اختر -</option>
          <?= options($cEmps,'id','emp_name',$doc['rep_contractor_id']??'') ?></select></div>
      <div class="field"><label>رئيس قسم الشؤون الهندسية</label>
        <select name="head_eng_id"><option value="">- اختر -</option>
          <?= options($mEmps,'id','emp_name',$doc['head_eng_id']??'') ?></select></div>

      <div class="field"><label>غرامة التأخير (تُحسب تلقائياً)</label>
        <input type="number" step="0.001" name="delay_fine" id="delay_fine" value="<?= e($doc['delay_fine']??0) ?>" readonly style="background:#f0f2f5">
        <?php if (is_admin()): /* قيمة غرامة اليوم من بيانات العقد — لمدير النظام فقط */ ?>
        <p class="muted" style="margin-top:4px">غرامة اليوم الواحد من العقد: <b id="dailyFineLbl"><?= fmt($dailyFine) ?></b> دينار</p>
        <?php endif; ?></div>
      <div class="field" style="grid-column:span <?= $hasWT ? 2 : 3 ?>"><label>ملاحظات</label>
        <input name="notes" value="<?= e($doc['notes']??'') ?>"></div>
    </div>
  </div>

  <!-- قسم إضافة البنود -->
  <div class="items-box">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:4px;flex-wrap:wrap">
      <button type="button" class="btn btn-warn" style="padding:11px 26px" onclick="openItemModal()">➕ إضافة بند</button>
      <input id="tbl_filter" placeholder="🔍 ابحث في البنود المضافة (رقم أو وصف)..." autocomplete="off"
        style="flex:1;min-width:220px;max-width:420px;padding:11px 14px;border:2px solid #e3e8f0;border-radius:10px;font-size:14px;font-family:inherit"
        oninput="filterTable(this.value)">
    </div>

    <div id="extraInfo" style="display:none;margin-top:14px;padding:10px 14px;border-radius:10px;background:#fff7e6;border:1px solid #f3d59b;font-size:13.5px"></div>
    <div style="overflow-x:auto;margin-top:16px">
    <table class="tbl" id="itemsTbl">
      <thead><tr>
        <th>م</th><th>رقم البند</th><th>وصف البند</th><th>الوحدة</th><th>الكمية</th>
        <th>سعر الوحدة</th><th>المبلغ الإجمالي</th><th>المجموع بعد الخصم</th><th class="no-export">الإجراء</th>
      </tr></thead>
      <tbody id="itemsBody">
        <tr class="empty-row" id="noItems"><td colspan="9" class="empty">لا يوجد بنود مضافة</td></tr>
      </tbody>
      <tfoot>
        <tr style="font-weight:800;background:#f6f0e0">
          <td colspan="6">الإجمالي</td>
          <td id="sumBefore">0.000</td><td id="sumAfter">0.000</td><td class="no-export"></td>
        </tr>
      </tfoot>
    </table>
    </div>
  </div>

  <input type="hidden" name="items_json" id="items_json">
  <div style="text-align:center;margin-top:18px">
    <button class="btn btn-primary" style="padding:13px 50px">✓ حفظ البيانات</button>
    <a class="btn btn-soft" href="<?= e($LIST_PAGE) ?>">إلغاء</a>
  </div>
</form>

<!-- ===== النافذة المنبثقة لإضافة بند ===== -->
<div id="itemModal" class="item-modal-overlay" style="display:none">
  <div class="item-modal">
    <div class="item-modal-head">
      <span>➕ إضافة بند</span>
      <button type="button" class="item-modal-x" onclick="closeItemModal()">&times;</button>
    </div>
    <div class="item-modal-body">
      <?php if ($boqTotal === 0): ?>
      <div class="flash er" style="margin-bottom:12px">جدول الكميات فارغ — لم يُرفع بعد.
        <?= is_admin() ? 'ارفعه من صفحة «جداول الكميات».' : 'اطلب من مدير النظام رفعه.' ?></div>
      <?php endif; ?>
      <?php if ($boqMains): ?>
      <div class="im-field">
        <label>البند الرئيسي</label>
        <select id="item_main" onchange="mainChanged()">
          <option value="">- كل البنود الرئيسية (<?= (int)$boqTotal ?> بند) -</option>
          <?php foreach ($boqMains as $bm): ?>
            <option value="<?= e($bm['main_name']) ?>"><?= e(trim($bm['main_no'].' — '.$bm['main_name'], ' —')) ?> (<?= (int)$bm['n'] ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="im-row">
        <div class="im-field im-field-no">
          <label>رقم البند أو وصفه <span class="req">*</span></label>
          <input id="item_search" placeholder="اكتب رقم البند أو وصفه ثم Enter..." autocomplete="off">
          <input type="hidden" id="sel_no"><input type="hidden" id="sel_det">
          <input type="hidden" id="sel_unit"><input type="hidden" id="sel_price">
          <input type="hidden" id="sel_mno"><input type="hidden" id="sel_mname">
          <div id="suggest" class="menu-pop" style="display:none;max-height:220px;overflow-y:auto"></div>
        </div>
        <div class="im-field im-field-qty">
          <label>الكمية <span class="req">*</span></label>
          <input type="number" step="0.001" id="item_qty" placeholder="الكمية">
        </div>
      </div>
      <div id="im_status" class="im-status"></div>
      <div id="im_detail" class="im-detail" style="display:none"></div>
    </div>
    <div class="item-modal-foot">
      <button type="button" class="btn btn-warn" onclick="addItem()">➕ إضافة</button>
      <button type="button" class="btn btn-soft" onclick="closeItemModal()">✕ خروج</button>
    </div>
  </div>
</div>

<!-- حاوية الإشعارات المؤقتة -->
<div id="toastWrap" class="toast-wrap"></div>

<style>
.item-modal-overlay{position:fixed;inset:0;background:rgba(20,24,34,.45);
  z-index:9999;display:flex;align-items:flex-start;justify-content:center;padding-top:8vh;animation:imFade .18s ease}
@keyframes imFade{from{opacity:0}to{opacity:1}}
.item-modal{background:#fff;width:480px;max-width:94vw;border-radius:16px;overflow:hidden;
  box-shadow:0 24px 60px rgba(0,0,0,.35);animation:imPop .22s cubic-bezier(.2,.9,.3,1.3)}
@keyframes imPop{from{transform:translateY(-18px) scale(.97);opacity:0}to{transform:none;opacity:1}}
.item-modal-head{background:linear-gradient(135deg,#1b1f2a,#2f5bb5);color:#fff;padding:16px 22px;
  display:flex;justify-content:space-between;align-items:center;font-size:17px;font-weight:800}
.item-modal-x{background:rgba(255,255,255,.15);border:0;color:#fff;width:32px;height:32px;border-radius:9px;
  font-size:22px;line-height:1;cursor:pointer;transition:.15s}
.item-modal-x:hover{background:rgba(255,255,255,.3)}
.item-modal-body{padding:22px}
.im-row{display:flex;gap:12px}
.im-field-no{flex:1}
.im-field-qty{width:120px;flex:none}
.im-field{margin-bottom:16px}
.im-field label{display:block;font-weight:700;color:#2b3242;margin-bottom:7px;font-size:13.5px}
.im-field input{width:100%;padding:12px 14px;border:2px solid #e3e8f0;border-radius:10px;font-size:15px;
  transition:.15s;font-family:inherit;color:#1a1a1a;background:#fff}
.im-field input::placeholder{color:#9aa3b2}
.im-field input:focus{outline:none;border-color:#2f5bb5;box-shadow:0 0 0 3px rgba(47,91,181,.12)}
.im-field input.ok{border-color:#16a34a;background:#f0fdf4;color:#1a1a1a}
.im-field input.err{border-color:#dc2626;background:#fef2f2;color:#1a1a1a}
/* اختيار طريقة تسعير البند تحت سعر الوحدة */
.price-mode{display:block;margin:5px auto 0;padding:3px 6px;border:1px solid #cdd3df;border-radius:6px;
  font-size:12px;font-family:inherit;background:#f8fafc;color:#2b3242;cursor:pointer}
.price-mode:focus{outline:none;border-color:#2f5bb5}
/* إشعار مؤقت (toast) */
.toast-wrap{position:fixed;top:20px;left:50%;transform:translateX(-50%);z-index:11000;
  display:flex;flex-direction:column;gap:10px;align-items:center;pointer-events:none}
.toast{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;border-radius:12px;
  padding:13px 22px;font-weight:700;font-size:14px;box-shadow:0 8px 24px rgba(0,0,0,.15);
  animation:toastIn .3s ease, toastOut .5s ease 2.5s forwards}
@keyframes toastIn{from{transform:translateY(-20px);opacity:0}to{transform:none;opacity:1}}
@keyframes toastOut{to{transform:translateY(-20px);opacity:0}}
.im-status{margin-top:7px;font-size:13px;font-weight:700;min-height:18px}
.im-status.ok{color:#16a34a}
.im-status.err{color:#dc2626}
.im-detail{background:#f6f8fc;border:1px solid #e3e8f0;border-radius:10px;padding:11px 13px;
  margin-bottom:16px;font-size:13px;color:#475069;line-height:1.6}
.im-detail b{color:#2f5bb5}
.item-modal-foot{padding:14px 22px;background:#f8fafc;border-top:1px solid #eef1f6;
  display:flex;gap:10px;justify-content:flex-start}
.item-modal-foot .btn{padding:11px 28px}
.item-modal .menu-pop{position:relative;margin-top:6px;border:1px solid #e3e8f0;border-radius:10px;box-shadow:0 8px 20px rgba(0,0,0,.1);background:#fff}
.item-modal .menu-pop a{display:block;padding:10px 13px;cursor:pointer;font-size:13.5px;color:#1a1a1a;background:#fff;border-bottom:1px solid #eee}
.item-modal .menu-pop a:hover{background:#f0f4fb}
.item-modal .menu-pop .sug-main{display:block;font-size:11.5px;color:#7a8398;margin-top:2px}
.item-modal .menu-pop .sug-more{padding:8px 13px;font-size:12px;color:#7a8398;text-align:center;background:#f8fafc}
.im-field select{width:100%;padding:11px 12px;border:2px solid #e3e8f0;border-radius:10px;font-size:14px;font-family:inherit;color:#1a1a1a;background:#fff}
.im-field select:focus{outline:none;border-color:#2f5bb5}
.it-main{font-size:11px;color:#7a8398;font-weight:400;margin-top:2px}
</style>
<script>
// نسبة الخصم: نسخة المستند عند التعديل، والحالية للمستند الجديد
// رمز نوع العمل بجانب الرقم في رأس النموذج (مثل 50/م)
var DOC_NO = <?= json_encode((int)$displayNo) ?>;
function wtChanged(){
  var sel=document.getElementById('work_type_id'); if(!sel) return;
  var code=(sel.options[sel.selectedIndex].dataset.code||'').trim();
  var el=document.querySelector('.doc-head .doc-no');
  if(el) el.textContent='رقم '+DOC_NO+(code?'/'+code:'');
}
wtChanged();   // عقد بنوع أعمال واحد: النوع مختار من البداية فيظهر رمزه مع الرقم
var DISCOUNT = <?= json_encode($doc ? (float)$doc['discount_percent'] : $disc) ?>;
// النسبة الإضافية: من منطقة الموقع المختار، أو نسخة المستند إن بقي موقعه كما هو
var SITE_EXTRA = <?= json_encode((object)$siteExtra, JSON_UNESCAPED_UNICODE) ?>;
var ORIG_SITE  = <?= json_encode($doc ? (string)($doc['site_id'] ?? '') : null) ?>;
var ORIG_EXTRA = <?= json_encode($doc ? ['pct' => (float)($doc['extra_percent'] ?? 0), 'area' => (string)($doc['extra_area'] ?? '')] : null, JSON_UNESCAPED_UNICODE) ?>;
var EXTRA_BEFORE = <?= json_encode(ps('extra_position') !== 'after') ?>;
var CUR_DISC  = <?= json_encode($disc) ?>;                                            // نسبة الخصم الحالية من الإعدادات
var ORIG_DISC = <?= json_encode($doc ? (float)$doc['discount_percent'] : null) ?>;   // نسخة المستند عند التعديل
var EXTRA = 0;
// معامل الصافي لكل بند: النسبة الإضافية ثم الخصم (الناتج واحد قبل الخصم أو بعده)
function netFactor(){ return (1+EXTRA/100)*(1-DISCOUNT/100); }
function extraChanged(){
  var site=document.querySelector('select[name="site_id"]').value;
  var ex=(ORIG_SITE!==null && site===ORIG_SITE) ? ORIG_EXTRA : (SITE_EXTRA[site]||null);
  EXTRA = ex ? (parseFloat(ex.pct)||0) : 0;
  // نفس منطق الحفظ: الموقع الأصلي ← نسخة المستند؛ منطقة «بدون خصم» ← 0؛ غير ذلك ← نسبة المستند أو الحالية
  var same=(ORIG_SITE!==null && site===ORIG_SITE);
  if (same) DISCOUNT = ORIG_DISC;
  else if (ex && ex.nodisc) DISCOUNT = 0;
  else DISCOUNT = (ORIG_DISC!==null && ORIG_DISC>0) ? ORIG_DISC : CUR_DISC;
  var box=document.getElementById('extraInfo');
  if(EXTRA>0){
    box.innerHTML='📍 موقع العمل في منطقة لها <b>نسبة إضافية '+(+EXTRA.toFixed(3))+'%</b>'+(ex.area?' ('+esc(ex.area)+')':'')+
      (DISCOUNT>0 ? ' تُحسب '+(EXTRA_BEFORE?'قبل':'بعد')+' الخصم — «المجموع بعد الخصم» يشملها.'
                  : ' — <b>لا تُطبق نسبة الخصم</b> في هذه المنطقة: «المجموع بعد الخصم» = الإجمالي + النسبة الإضافية.');
    box.style.display='';
  } else box.style.display='none';
  document.querySelectorAll('#itemsBody input[name="it_qty[]"]').forEach(function(q){ rowChanged(q); });
  recalc();
}
var DAILY_FINE = <?= json_encode($dailyFine) ?>;
function calcFine(){
  var days = parseInt(document.getElementById('delay_days').value||0);
  var fine = days * DAILY_FINE;
  document.getElementById('delay_fine').value = (Math.round(fine*1000)/1000);
}
var rowIdx = 0;
var PRICE_MODE = <?= $hasMode ? 'true' : 'false' ?>;   // عمودا طريقة التسعير موجودان: يظهر اختيار «نفس سعر الجدول / يومي»

// === حساب تاريخ النهاية تلقائياً من البداية + المدة ===
function calcEnd(){
  var sd=document.getElementById('start_date').value, days=parseInt(document.getElementById('exec_days').value||0);
  if(sd && days>0){
    var d=new Date(sd); d.setDate(d.getDate()+days);
    document.getElementById('end_date').value=d.toISOString().slice(0,10);
  }
}
document.getElementById('start_date').addEventListener('change',calcEnd);
document.getElementById('exec_days').addEventListener('input',calcEnd);

// ===== النافذة المنبثقة =====
function openItemModal(){
  document.getElementById('itemModal').style.display='flex';
  resetModalFields();
  setTimeout(function(){ document.getElementById('item_search').focus(); },60);
}
function closeItemModal(){
  document.getElementById('itemModal').style.display='none';
  resetModalFields();
}
function resetModalFields(){
  document.getElementById('item_search').value='';
  document.getElementById('item_search').className='';
  document.getElementById('item_qty').value='';
  ['sel_no','sel_det','sel_unit','sel_price','sel_mno','sel_mname'].forEach(i=>document.getElementById(i).value='');
  var st=document.getElementById('im_status'); st.textContent=''; st.className='im-status';
  document.getElementById('im_detail').style.display='none';
  document.getElementById('suggest').style.display='none';
}
// إغلاق بالنقر خارج النافذة أو ESC
document.getElementById('itemModal').addEventListener('click',function(e){
  if(e.target===this) closeItemModal();
});
document.addEventListener('keydown',function(e){
  if(e.key==='Escape' && document.getElementById('itemModal').style.display==='flex') closeItemModal();
});

// === البحث الحي + التحقق الفوري ===
var searchTimer=null, validateTimer=null;
var searchEl=document.getElementById('item_search');
searchEl.addEventListener('input',function(){
  clearTimeout(searchTimer); clearTimeout(validateTimer);
  var q=this.value.trim();
  // أي تعديل يلغي الاختيار السابق
  document.getElementById('sel_no').value='';
  this.className='';
  document.getElementById('im_status').textContent='';
  document.getElementById('im_status').className='im-status';
  document.getElementById('im_detail').style.display='none';
  searchTimer=setTimeout(function(){ fetchItems(q); },200);
  // تحقق فوري إن بدا أنه رقم بند كامل
  validateTimer=setTimeout(function(){ validateExact(q); },350);
});
searchEl.addEventListener('focus',function(){ if(!this.value) fetchItems(''); });
// Enter في رقم البند: إن كان صحيحاً انتقل للكمية
searchEl.addEventListener('keydown',function(e){
  if(e.key==='Enter'){
    e.preventDefault();
    var q=this.value.trim();
    if(document.getElementById('sel_no').value){
      document.getElementById('item_qty').focus();
    } else {
      validateExact(q, true); // تحقق فوري؛ إن صح ينتقل للكمية
    }
  }
});

// البند الرئيسي المختار لحصر البحث ('' = الكل). يبقى مختاراً بين بند وآخر.
function curMain(){ var s=document.getElementById('item_main'); return s ? s.value : ''; }
function searchUrl(q, offset){
  return '<?= base_url('api/boq_search.php') ?>?q='+encodeURIComponent(q)+'&main='+encodeURIComponent(curMain())+'&offset='+(offset||0);
}
function mainChanged(){
  ['sel_no','sel_det','sel_unit','sel_price','sel_mno','sel_mname'].forEach(i=>document.getElementById(i).value='');
  var st=document.getElementById('im_status'); st.textContent=''; st.className='im-status';
  document.getElementById('im_detail').style.display='none';
  var inp=document.getElementById('item_search'); inp.className=''; inp.focus();
  fetchItems(inp.value.trim());
}

// قائمة البنود: 30 بنداً في كل مرة، والنزول لآخر القائمة يحمّل ما بعدها حتى آخر بند
var BOQ_TOTAL = <?= (int)$boqTotal ?>;
var MAIN_FMT = <?= boq_main_format() ? 'true' : 'false' ?>;   // تنسيق «المالية»: اسم البند الرئيسي يظهر تحت رقم البند
var sug = {q:'', offset:0, done:false, loading:false, seq:0};
function fetchItems(q, more){
  var box=document.getElementById('suggest');
  if(more){ if(sug.done || sug.loading) return; }
  else { sug.q=q; sug.offset=0; sug.done=false; }
  var mySeq=++sug.seq; sug.loading=true;
  fetch(searchUrl(sug.q, sug.offset))
    .then(r=>r.json()).then(function(rows){
      if(mySeq!==sug.seq) return;            // رد قديم لبحث سابق
      sug.loading=false;
      if(!more) box.innerHTML='';
      var hint=box.querySelector('.sug-more'); if(hint) hint.remove();
      if(!rows.length && !more){ box.style.display='none'; return; }
      var showMain=!curMain();
      rows.forEach(function(it){
        var a=document.createElement('a');
        a.innerHTML='<b>'+esc(it.item_no)+'</b> — '+esc((it.item_details||'').slice(0,60))+
          (showMain && it.main_name ? '<span class="sug-main">'+esc(it.main_name)+'</span>' : '');
        a.onclick=function(){ pickItem(it); };
        box.appendChild(a);
      });
      sug.offset+=rows.length;
      if(rows.length<30) sug.done=true;
      else {
        var h=document.createElement('div'); h.className='sug-more';
        h.textContent=(sug.q==='' && !curMain())
          ? 'يظهر '+sug.offset+' من '+BOQ_TOTAL+' بنداً — انزل لتحميل المزيد، أو اكتب للبحث'
          : 'انزل لتحميل المزيد…';
        box.appendChild(h);
      }
      box.style.display='block';
    }).catch(function(){ if(mySeq===sug.seq) sug.loading=false; });
}
document.getElementById('suggest').addEventListener('scroll',function(){
  if(this.scrollTop+this.clientHeight >= this.scrollHeight-40) fetchItems(sug.q, true);
});

// تحقق فوري من تطابق رقم البند تماماً
function validateExact(q, gotoQty){
  q=(q||'').trim();
  if(!q){ return; }
  var typed = q.split('—')[0].trim();
  fetch(searchUrl(typed, 0))
    .then(r=>r.json()).then(function(rows){
      if(document.getElementById('item_search').value.trim()!==q) return;   // تغيّر النص أثناء الانتظار
      var exact=null;
      // 1) تطابق رقم البند تماماً — الرقم قد يتكرر في أكثر من بند رئيسي، فلا يُختار تلقائياً إلا إن كان وحيداً
      //    (r.exact: نفس البند مهما كان ترتيب كتابته — تكتب 8/1-أ والجدول فيه أ-8/1)
      //    (مع «طريقة اختيار البند» المعكوسة يُعتمد حكم الخادم فقط: النص كما كُتب قد يطابق بنداً آخر)
      var sameNo=rows.filter(function(r){ return ('exact' in r) ? r.exact : String(r.item_no).trim()===typed; });
      if(sameNo.length===1) exact=sameNo[0];
      // الرقم المكتوب له بنود فرعية (8/1 ← 8/1-أ، 8/1-ب...) وليس بنداً بنفسه: اعرض فروعه ليختار
      var subs=rows.filter(function(r){ return r.sub; });
      if(!sameNo.length && subs.length){
        document.getElementById('sel_no').value='';
        document.getElementById('item_search').className='';
        var st1=document.getElementById('im_status');
        st1.textContent='البند '+typed+' له بنود فرعية — اختر الفرع من القائمة، أو أكمل الكتابة مثل: '+typed+'-أ';
        st1.className='im-status';
        document.getElementById('im_detail').style.display='none';
        if(gotoQty || document.getElementById('suggest').style.display==='none') fetchItems(typed);
        return;
      }
      if(sameNo.length>1){
        document.getElementById('sel_no').value='';
        var st0=document.getElementById('im_status');
        st0.textContent=MAIN_FMT
          ? 'الرقم '+typed+' موجود في أكثر من بند رئيسي — اختر من القائمة، أو حدّد البند الرئيسي أعلاه'
          : 'الرقم '+typed+' مكرر في الجدول لـ '+sameNo.length+' بنود مختلفة — اختر المقصود من القائمة';
        st0.className='im-status';
        document.getElementById('im_detail').style.display='none';
        if(gotoQty || document.getElementById('suggest').style.display==='none') fetchItems(typed);
        return;
      }
      // 2) تطابق الوصف تماماً
      if(!exact){
        for(var j=0;j<rows.length;j++){
          if(String(rows[j].item_details||'').trim()===typed){ exact=rows[j]; break; }
        }
      }
      // 3) نتيجة واحدة فقط → اعتبرها المقصودة
      if(!exact && rows.length===1){ exact=rows[0]; }

      var inp=document.getElementById('item_search');
      var st=document.getElementById('im_status');
      var dt=document.getElementById('im_detail');
      if(exact){
        document.getElementById('sel_no').value=exact.item_no;
        document.getElementById('sel_det').value=exact.item_details||'';
        document.getElementById('sel_unit').value=exact.unit||'';
        document.getElementById('sel_price').value=exact.price_before||0;
        document.getElementById('sel_mno').value=exact.main_no||'';
        document.getElementById('sel_mname').value=exact.main_name||'';
        inp.className='ok';
        // بند صحيح بنفسه، ويوجد بالرقم نفسه بنود بفروع (8/1 لوحده، و 8/1-أ، 8/1-ب... لبند آخر بالرقم نفسه في الجدول):
        // يُختار البند المكتوب كما هو، وتبقى القائمة ظاهرة ليختار أحد الفروع إن كان هو المقصود
        var hasSubs=subs.length>0 && exact.exact;
        st.textContent='✓ بند صحيح — '+exact.item_no+(hasSubs ? ' — ويوجد بالرقم نفسه بنود بفروع: إن أردت أحدها اختره من القائمة أو أكمل الكتابة مثل '+typed+'-أ' : '');
        st.className='im-status ok';
        dt.innerHTML=itemDetailHtml(exact);
        dt.style.display='block';
        if(!hasSubs || gotoQty) document.getElementById('suggest').style.display='none';
        if(gotoQty){ document.getElementById('item_qty').focus(); }
      } else if(rows.length>1){
        // عدة نتائج → اعرض القائمة ليختار
        document.getElementById('sel_no').value='';
        inp.className='';
        st.textContent='يوجد عدة بنود مطابقة — اختر من القائمة';
        st.className='im-status';
        dt.style.display='none';
        if(gotoQty) fetchItems(typed);
      } else {
        document.getElementById('sel_no').value='';
        inp.className='err';
        st.textContent='✗ البند غير موجود — يرجى التصحيح';
        st.className='im-status err';
        dt.style.display='none';
      }
    }).catch(function(){});
}

function pickItem(it){
  document.getElementById('item_search').value=it.item_no;
  document.getElementById('sel_no').value=it.item_no;
  document.getElementById('sel_det').value=it.item_details||'';
  document.getElementById('sel_unit').value=it.unit||'';
  document.getElementById('sel_price').value=it.price_before||0;
  document.getElementById('sel_mno').value=it.main_no||'';
  document.getElementById('sel_mname').value=it.main_name||'';
  document.getElementById('item_search').className='ok';
  var st=document.getElementById('im_status'); st.textContent='✓ بند صحيح'; st.className='im-status ok';
  var dt=document.getElementById('im_detail');
  dt.innerHTML=itemDetailHtml(it);
  dt.style.display='block';
  document.getElementById('suggest').style.display='none';
  document.getElementById('item_qty').focus();
}
// تفاصيل البند المختار (البند الرئيسي + الوصف + الوحدة + السعر)
function itemDetailHtml(it){
  return (it.main_name ? '<b>البند الرئيسي:</b> '+esc((it.main_no?it.main_no+' — ':'')+it.main_name)+'<br>' : '')+
    '<b>الوصف:</b> '+esc(it.item_details||'—')+'<br><b>الوحدة:</b> '+esc(it.unit||'—')+' &nbsp;|&nbsp; <b>السعر:</b> '+esc(it.price_before||0);
}
// Enter في الكمية: إضافة فورية
document.getElementById('item_qty').addEventListener('keydown',function(e){
  if(e.key==='Enter'){ e.preventDefault(); addItem(); }
});
document.addEventListener('click',function(e){
  if(!e.target.closest('#item_search') && !e.target.closest('#suggest')){
    var s=document.getElementById('suggest'); if(s) s.style.display='none';
  }
});

// === البحث عن صف بند موجود مسبقاً في الجدول ===
// البند = رقمه + بنده الرئيسي + وصفه (الرقم نفسه يتكرر تحت بنود رئيسية مختلفة، وبعض الجداول فيها
// الرقم نفسه لبندين مختلفين — فلا يُعدّ البند مكرراً إلا إن تطابق وصفه أيضاً)
function findExistingRow(no, mname, det){
  var rows=document.querySelectorAll('#itemsBody tr');
  for(var i=0;i<rows.length;i++){
    var inp=rows[i].querySelector('input[name="it_no[]"]');
    var mn=rows[i].querySelector('input[name="it_mname[]"]');
    var dt=rows[i].querySelector('input[name="it_det[]"]');
    if(inp && String(inp.value).trim()===String(no).trim() &&
       String(mn?mn.value:'').trim()===String(mname||'').trim() &&
       (det===undefined || !dt || String(dt.value).trim()===String(det||'').trim())) return rows[i];
  }
  return null;
}

// === تجهيز الإرسال: جمع كل البنود في JSON واحد (يتجاوز حد max_input_vars) ===
function prepareSubmit(){
  try{
    var rows=document.querySelectorAll('#itemsBody tr');
    var list=[];
    rows.forEach(function(tr){
      var noInp=tr.querySelector('input[name="it_no[]"], input[data-n="no"]');
      if(!noInp) return;
      var det=tr.querySelector('input[name="it_det[]"], input[data-n="det"]');
      var unit=tr.querySelector('input[name="it_unit[]"], input[data-n="unit"]');
      var qty=tr.querySelector('input[name="it_qty[]"], input[data-n="qty"]');
      var price=tr.querySelector('input[name="it_price[]"], input[data-n="price"]');
      var mno=tr.querySelector('input[name="it_mno[]"], input[data-n="mno"]');
      var mname=tr.querySelector('input[name="it_mname[]"], input[data-n="mname"]');
      var mode=tr.querySelector('select[name="it_mode[]"], select[data-n="mode"]');
      var no=(noInp.value||'').trim();
      if(no==='') return;
      list.push({
        no:no,
        det:det?det.value:'',
        unit:unit?unit.value:'',
        qty:qty?parseFloat(qty.value)||0:0,
        price:price?parseFloat(price.value)||0:0,
        mode:mode?mode.value:'',
        mno:mno?mno.value:'',
        mname:mname?mname.value:''
      });
    });
    document.getElementById('items_json').value=JSON.stringify(list);
    // أزل أسماء الحقول القديمة حتى لا تُرسل كمتغيرات منفصلة (تفادي حد max_input_vars)
    document.querySelectorAll('#itemsBody input[name^="it_"], #itemsBody select[name^="it_"]').forEach(function(inp){
      inp.setAttribute('data-n', inp.getAttribute('name').replace('it_','').replace('[]',''));
      inp.removeAttribute('name');
    });
    return true;
  }catch(err){
    // فشل الجمع؟ امنع الإرسال الناقص وأبلغ المستخدم
    alert('حدث خطأ أثناء تجهيز البنود للحفظ. أعد المحاولة.\n'+err.message);
    return false;
  }
}
// === إضافة بند للجدول ===
function addItem(){
  var no=document.getElementById('sel_no').value;
  var qty=parseFloat(document.getElementById('item_qty').value||0);
  if(!no){
    var st=document.getElementById('im_status');
    st.textContent='✗ يرجى إدخال رقم بند صحيح أولاً';
    st.className='im-status err';
    document.getElementById('item_search').focus();
    return;
  }
  if(!(qty>0)){
    document.getElementById('item_qty').focus();
    document.getElementById('item_qty').className='err';
    return;
  }
  var det=document.getElementById('sel_det').value;
  var unit=document.getElementById('sel_unit').value;
  var price=parseFloat(document.getElementById('sel_price').value||0);
  var mno=document.getElementById('sel_mno').value, mname=document.getElementById('sel_mname').value;

  // كشف التكرار → إشعار مؤقت فقط (دون أي تغيير على الكمية)
  var existing=findExistingRow(no, mname, det);
  if(existing){
    showToast('البند رقم ('+no+')'+(mname?' من «'+mname+'»':'')+' موجود مسبقاً');
    resetModalFields();
    document.getElementById('item_search').focus();
    return;
  }

  addRow(no,det,unit,qty,price,mno,mname);
  sortItems();
  // النافذة تبقى مفتوحة لإضافة بند آخر
  resetModalFields();
  document.getElementById('item_search').focus();
}

// إشعار مؤقت يختفي تدريجياً
function showToast(msg){
  var wrap=document.getElementById('toastWrap');
  var t=document.createElement('div'); t.className='toast'; t.textContent=msg;
  wrap.appendChild(t);
  setTimeout(function(){ if(t.parentNode) t.parentNode.removeChild(t); },3000);
}

// بحث/تصفية في جدول البنود المضافة (رقم أو وصف)
function filterTable(q){
  q=(q||'').trim().toLowerCase();
  var rows=document.querySelectorAll('#itemsBody tr');
  rows.forEach(function(tr){
    if(tr.id==='noItems'){ return; }
    var noInp=tr.querySelector('input[name="it_no[]"]');
    var detInp=tr.querySelector('input[name="it_det[]"]');
    if(!noInp){ return; }
    var no=(noInp.value||'').toLowerCase();
    var det=(detInp?detInp.value:'').toLowerCase();
    var cellDet=(tr.querySelector('.cell-det')?tr.querySelector('.cell-det').textContent:'').toLowerCase();
    var hit = !q || no.indexOf(q)>-1 || det.indexOf(q)>-1 || cellDet.indexOf(q)>-1;
    tr.style.display = hit ? '' : 'none';
  });
}
// === ترتيب البنود حسب رقم البند ===
function itemSortKey(no){
  // يفصل رقم البند (مثل 174/1 أو أ-5/1) إلى أجزاء للترتيب الطبيعي
  var s=String(no).trim();
  var nums=s.match(/\d+/g)||[];
  // المفتاح: أول رقم ثم باقي الأرقام
  return nums.map(function(x){ return String(x).padStart(8,'0'); }).join('-')+'|'+s;
}
function sortItems(){
  var body=document.getElementById('itemsBody');
  var rows=Array.prototype.slice.call(body.querySelectorAll('tr'));
  rows=rows.filter(function(tr){ return tr.querySelector('input[name="it_no[]"]'); });
  // بنود كل بند رئيسي معاً (برقم البند الرئيسي ثم اسمه)، وداخله حسب رقم البند
  function rowKey(tr){
    var mn=tr.querySelector('input[name="it_mno[]"]'), mm=tr.querySelector('input[name="it_mname[]"]');
    var mno=mn?mn.value.trim():'', mname=mm?mm.value.trim():'';
    var mainKey = (MAIN_FMT && mname) ? String(parseInt(mno,10)||0).padStart(8,'0')+'|'+mname : '';
    return mainKey+'||'+itemSortKey(tr.querySelector('input[name="it_no[]"]').value);
  }
  rows.sort(function(a,b){
    var ka=rowKey(a), kb=rowKey(b);
    return ka<kb?-1:(ka>kb?1:0);
  });
  rows.forEach(function(tr){ body.appendChild(tr); });
  recalc();
}
// طريقة تسعير البند: '' = نفس سعر جدول الكميات (الافتراضي)، 'daily' = سعر يومي (السعر ÷ 365) لبنود الصيانة السنوية.
// it_price[] يحمل دائماً سعر البند في جدول الكميات، ويُحسب منه سعر الوحدة والمبلغ هنا وفي الحفظ.
function unitPrice(base, mode){ return mode==='daily' ? Math.round(base/365*1000)/1000 : base; }
function lineTotal(qty, base, mode){ return mode==='daily' ? Math.round(qty*base/365*1000)/1000 : qty*base; }
function rowTotal(tr){
  var qty=parseFloat(tr.querySelector('input[name="it_qty[]"]').value)||0;
  var base=parseFloat(tr.querySelector('input[name="it_price[]"]').value)||0;
  var m=tr.querySelector('select[name="it_mode[]"]');
  return lineTotal(qty, base, m?m.value:'');
}
function modeChanged(sel){
  var tr=sel.closest('tr');
  var base=parseFloat(tr.querySelector('input[name="it_price[]"]').value)||0;
  tr.querySelector('.cell-price').textContent=fmt(unitPrice(base, sel.value));
  rowChanged(tr.querySelector('input[name="it_qty[]"]'));
}
function addRow(no,det,unit,qty,price,mno,mname,mode){
  rowIdx++;
  mode = mode==='daily' ? 'daily' : '';
  var total=lineTotal(qty,price,mode), after=total*netFactor();
  var noItems=document.getElementById('noItems'); if(noItems) noItems.remove();
  var tr=document.createElement('tr');
  tr.innerHTML=
    '<td class="rownum"></td>'+
    '<td>'+esc(no)+((MAIN_FMT && mname)?'<div class="it-main">'+esc(mname)+'</div>':'')+'<input type="hidden" name="it_no[]" value="'+esc(no)+'">'+
        '<input type="hidden" name="it_mno[]" value="'+esc(mno||'')+'"><input type="hidden" name="it_mname[]" value="'+esc(mname||'')+'"></td>'+
    '<td>'+esc(det)+'<input type="hidden" name="it_det[]" value="'+esc(det)+'"></td>'+
    '<td>'+esc(unit)+'<input type="hidden" name="it_unit[]" value="'+esc(unit)+'"></td>'+
    '<td><input type="number" step="0.001" min="0" name="it_qty[]" value="'+qty+'" '+
        'style="width:80px;text-align:center;padding:5px;border:1px solid #cdd3df;border-radius:6px" '+
        'oninput="rowChanged(this)"></td>'+
    '<td><span class="cell-price">'+fmt(unitPrice(price,mode))+'</span><input type="hidden" name="it_price[]" value="'+price+'">'+
        (PRICE_MODE ? '<select name="it_mode[]" class="price-mode" onchange="modeChanged(this)" title="طريقة تسعير البند">'+
          '<option value=""'+(mode===''?' selected':'')+'>نفس سعر الجدول</option>'+
          '<option value="daily"'+(mode==='daily'?' selected':'')+'>يومي (÷365)</option></select>' : '')+'</td>'+
    '<td class="cell-total">'+fmt(total)+'</td>'+
    '<td class="cell-after">'+fmt(after)+'</td>'+
    '<td class="no-export"><button type="button" class="icon-btn icon-del" onclick="delItemRow(this)">🗑️</button></td>';
  document.getElementById('itemsBody').appendChild(tr);
  recalc();
}
function delItemRow(btn){ btn.closest('tr').remove(); recalc(); }
// عند تغيير الكمية: أعد حساب صف هذا البند ثم الإجماليات
function rowChanged(input){
  var tr=input.closest('tr');
  var total=rowTotal(tr), after=total*netFactor();
  tr.querySelector('.cell-total').textContent=fmt(total);
  tr.querySelector('.cell-after').textContent=fmt(after);
  recalc();
}
function recalc(){
  var rows=document.querySelectorAll('#itemsBody tr'); var sb=0,sa=0,n=0;
  rows.forEach(function(tr){
    var q=tr.querySelector('input[name="it_qty[]"]'); if(!q) return;
    n++; tr.querySelector('.rownum').textContent=n;
    sb+=rowTotal(tr);
  });
  sa=sb*netFactor();
  document.getElementById('sumBefore').textContent=fmt(sb);
  document.getElementById('sumAfter').textContent=fmt(sa);
}
function fmt(n){ return (Math.round(n*1000)/1000).toLocaleString('en-US',{minimumFractionDigits:3,maximumFractionDigits:3}); }
function esc(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

extraChanged();
// تحميل بنود التعديل
<?php if($docItems): ?>
<?php foreach($docItems as $it):
  // البنود القديمة (قبل طريقة التسعير) بلا base_price: سعرها المحفوظ هو سعر الجدول
  $itMode = ($it['price_mode'] ?? '') === 'daily' ? 'daily' : '';
  $itBase = isset($it['base_price']) && $it['base_price'] !== null ? (float)$it['base_price'] : (float)$it['unit_price'];
?>
addRow(<?= json_encode($it['item_no'],JSON_UNESCAPED_UNICODE) ?>,<?= json_encode($it['item_details'],JSON_UNESCAPED_UNICODE) ?>,<?= json_encode($it['unit'],JSON_UNESCAPED_UNICODE) ?>,<?= (float)$it['qty'] ?>,<?= $itBase ?>,<?= json_encode($it['main_no'] ?? '',JSON_UNESCAPED_UNICODE) ?>,<?= json_encode($it['main_name'] ?? '',JSON_UNESCAPED_UNICODE) ?>,<?= json_encode($itMode) ?>);
<?php endforeach; ?>
sortItems();
<?php endif; ?>
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>

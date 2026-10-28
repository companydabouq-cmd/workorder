<?php
/**
 * قائمة مشتركة. متغيرات مطلوبة:
 *  $DOC, $DOC_TITLE, $TBL_MASTER, $TBL_ITEMS, $SEQ_KEY, $NO_FIELD,
 *  $FORM_PAGE, $PRINT_PAGE, $PAGE_KEY, $LIST_TITLE
 *  للتحويل (اختياري): $CONVERT = [
 *      'flag'        => اسم عمود "تم التحويل" في الجدول الحالي,
 *      'target_tbl'  => جدول الوجهة,
 *      'target_items'=> جدول بنود الوجهة,
 *      'target_seq'  => مفتاح تسلسل الوجهة,
 *      'target_no'   => اسم عمود الرقم في الوجهة,
 *      'target_doc'  => بادئة عمود البنود في الوجهة (order/survey),
 *      'src_col'     => عمود مصدر التحويل في الوجهة (source_survey_id/source_prelim_id),
 *      'col_label'   => عنوان عمود الحالة (مثل: حوّل لأمر عمل),
 *      'menu_label'  => نص زر التحويل (مثل: تحويل إلى أمر عمل),
 *      'success'     => نص النجاح (مثل: تم التحويل إلى أمر عمل رقم),
 *  ]
 */
require_once __DIR__ . '/../includes/functions.php';
require_login();
$CONVERT = $CONVERT ?? null;
$hasExtra = has_extra();   // النسبة الإضافية للمنطقة (نسخة في كل مستند) — قبل أي استعلام
$hasWT    = work_types_enabled();   // رمز نوع العمل مع الرقم (54/ت) وبيان الأعمال — مع «البلدية» و«المالية» فقط

// المشاهد ممنوع من أي عملية تعديل (حماية من الطلبات المزوّرة)
if (is_viewer() && $_SERVER['REQUEST_METHOD']==='POST') {
    header('Location: '.basename($_SERVER['PHP_SELF'])); exit;
}

// حذف (يسجّل فجوة)
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['act']??'')==='delete') {
    csrf_check();
    $id=(int)$_POST['id'];
    $row=q_row("SELECT $NO_FIELD AS no FROM $TBL_MASTER WHERE id=?", [$id]);
    if ($row) {
        q("DELETE FROM $TBL_MASTER WHERE id=?", [$id]);
        add_gap($SEQ_KEY, (int)$row['no']);
        flash_set('ok','تم الحذف. أصبح الرقم '.(int)$row['no'].' متاحاً للاسترداد.');
    }
    header('Location: '.basename($_SERVER['PHP_SELF'])); exit;
}

// تحويل (نسخة مستقلة، مرة واحدة)
if ($CONVERT && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['act']??'')==='convert') {
    csrf_check();
    $id=(int)$_POST['id'];
    $flag=$CONVERT['flag'];
    $s=q_row("SELECT * FROM $TBL_MASTER WHERE id=?", [$id]);
    if ($s && !$s[$flag]) {
        $items=q_all("SELECT * FROM $TBL_ITEMS WHERE {$DOC}_id=?", [$id]);
        $hasMain=has_item_main();   // قبل المعاملة: إضافة عمود تُنهي أي معاملة مفتوحة
        $hasMode=has_price_mode();  // طريقة التسعير (نفس سعر الجدول / يومي) تنتقل مع البند
        $pdo=db(); if($pdo->inTransaction())$pdo->rollBack(); $pdo->beginTransaction();
        try {
            $no=next_sequence($CONVERT['target_seq']);
            $tt=$CONVERT['target_tbl']; $tno=$CONVERT['target_no']; $srcCol=$CONVERT['src_col'];
            q("INSERT INTO $tt
               ($tno,$srcCol,dept_id,site_id,work_type,supervisor1_id,supervisor2_id,
                start_date,exec_days,end_date,warranty_months,delay_days,rep_contractor_id,head_eng_id,
                delay_fine,notes,discount_percent)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
              [$no,$id,$s['dept_id'],$s['site_id'],$s['work_type'],$s['supervisor1_id'],$s['supervisor2_id'],
               $s['start_date'],$s['exec_days'],$s['end_date'],$s['warranty_months'],$s['delay_days'],
               $s['rep_contractor_id'],$s['head_eng_id'],$s['delay_fine'],$s['notes'],$s['discount_percent']]);
            $newId=$pdo->lastInsertId();
            if (has_work_types()) {      // ويرث نوع العمل ورمزه وبيان الأعمال
                q("UPDATE $tt SET work_type_code=?, work_desc=? WHERE id=?", [$s['work_type_code'] ?? null, $s['work_desc'] ?? null, $newId]);
            }
            if ($hasExtra) {   // المستند الجديد يرث نسخة النسبة الإضافية كما هي
                q("UPDATE $tt SET extra_percent=?, extra_area=? WHERE id=?", [$s['extra_percent'] ?? 0, $s['extra_area'] ?? null, $newId]);
            }
            $tdoc=$CONVERT['target_doc']; $titems=$CONVERT['target_items'];
            $insCols = "{$tdoc}_id,item_no,item_details,unit,qty,unit_price,total_before"
                     . ($hasMain ? ',main_no,main_name' : '') . ($hasMode ? ',price_mode,base_price,maint_days' : '');
            $sti=$pdo->prepare("INSERT INTO $titems ($insCols) VALUES (" . implode(',', array_fill(0, substr_count($insCols, ',') + 1, '?')) . ")");
            foreach($items as $it){
                $v=[$newId,$it['item_no'],$it['item_details'],$it['unit'],$it['qty'],$it['unit_price'],$it['total_before']];
                if ($hasMain) { $v[]=$it['main_no']??null; $v[]=$it['main_name']??null; }
                if ($hasMode) { $v[]=$it['price_mode']??null; $v[]=$it['base_price']??null; $v[]=$it['maint_days']??null; }
                $sti->execute($v);
            }
            q("UPDATE $TBL_MASTER SET $flag=1 WHERE id=?", [$id]);
            $pdo->commit();
            flash_set('ok',$CONVERT['success'].' '.$no);
        } catch(Exception $ex){ if ($pdo->inTransaction()) $pdo->rollBack(); flash_set('er','خطأ في التحويل: '.$ex->getMessage()); }
    } else {
        flash_set('er','تم تحويل هذا المستند مسبقاً أو غير موجود');
    }
    header('Location: '.basename($_SERVER['PHP_SELF'])); exit;
}

// رفع نسخة ضوئية
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['act']??'')==='scan') {
    csrf_check();
    $id=(int)$_POST['id'];
    $path=upload_image('scan','scans');
    if ($path){ q("UPDATE $TBL_MASTER SET scan_file=? WHERE id=?", [$path,$id]); flash_set('ok','تم إرفاق النسخة الضوئية'); }
    else flash_set('er','تعذّر رفع الملف');
    header('Location: '.basename($_SERVER['PHP_SELF'])); exit;
}

// استرداد محذوف -> ينقل لصفحة النموذج برقم الفجوة
$gaps = gaps_list($SEQ_KEY);

// جلب القائمة مع مجموع كل مستند
$rows=q_all("SELECT m.*, d.dept_name, s.site_name,
             COALESCE((SELECT SUM(total_before) FROM $TBL_ITEMS WHERE {$DOC}_id=m.id),0) AS total_before
             FROM $TBL_MASTER m
             LEFT JOIN departments d ON d.id=m.dept_id
             LEFT JOIN worksites s ON s.id=m.site_id
             ORDER BY m.$NO_FIELD DESC");

require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div class="page-title"><span class="ic">📑</span> <?= e($LIST_TITLE ?? $DOC_TITLE) ?></div>
  <?php if (!is_viewer()): ?>
  <div style="display:flex;gap:8px">
    <a class="btn btn-primary" href="<?= e($FORM_PAGE) ?>">➕ <?= e($DOC_TITLE) ?> جديد</a>
    <button class="btn btn-recover" <?= $gaps?'':'disabled' ?> onclick="openModal('recover')">♻️ استرداد محذوف<?= $gaps?' ('.count($gaps).')':'' ?></button>
  </div>
  <?php endif; ?>
</div>
<?= flash_get() ?>
<div class="panel">
  <div class="table-tools">
    <div><button class="btn btn-soft btn-sm" onclick="exportTableCSV('tbl','<?= e($DOC_TITLE) ?>')">Excel</button></div>
    <input class="search-box" placeholder="بحث..." onkeyup="tableSearch(this,'tbl')">
  </div>
  <div style="overflow-x:auto">
  <table class="tbl" id="tbl">
    <thead><tr>
      <th>رقم <?= e($DOC_TITLE) ?></th><?php if ($hasWT): ?><th>نوع العمل</th><th>بيان العمل</th><?php endif; ?><th>الإدارة</th><th>موقع العمل</th>
      <th>تاريخ البداية</th><th>تاريخ النهاية</th><th>مدة التنفيذ</th><th>المجموع بعد الخصم</th>
      <?php if($CONVERT): ?><th><?= e($CONVERT['col_label']) ?></th><?php endif; ?>
      <th class="no-export">الإجراء</th>
    </tr></thead>
    <tbody>
    <?php $colspan = ($CONVERT?9:8) + ($hasWT?2:0); if(!$rows): ?><tr class="empty-row"><td colspan="<?= $colspan ?>" class="empty">لا توجد بيانات بعد</td></tr>
    <?php else: foreach($rows as $r):
      $after=doc_net($r['total_before'], (float)$r['discount_percent'], (float)($r['extra_percent'] ?? 0));
      $isConverted = $CONVERT ? !empty($r[$CONVERT['flag']]) : false; ?>
      <tr>
        <td style="white-space:nowrap"><?= e(doc_no_label($r[$NO_FIELD], $r['work_type_code'] ?? '')) ?></td>
        <?php if ($hasWT): ?><td><?= e($r['work_type'] ?? '') ?></td><td><?= e($r['work_desc'] ?? '') ?></td><?php endif; ?>
        <td><?= e($r['dept_name']??'—') ?></td>
        <td><?= e($r['site_name']??'—') ?></td>
        <td><?= fmt_date($r['start_date']) ?></td>
        <td><?= fmt_date($r['end_date']) ?></td>
        <td><?= (int)$r['exec_days'] ?></td>
        <td><?= fmt($after) ?></td>
        <?php if($CONVERT): ?>
          <td><?= $isConverted?'<span style="color:#36b3a0;font-weight:700">نعم</span>':'لا' ?></td>
        <?php endif; ?>
        <td class="no-export"><div class="act-btns">
          <?php if(is_viewer()): ?>
            <a class="btn btn-soft btn-sm" href="<?= e($PRINT_PAGE) ?>?id=<?= $r['id'] ?>" target="_blank">🖨️ طباعة</a>
          <?php else: ?>
          <button class="icon-btn icon-del" onclick="delRow(<?= $r['id'] ?>)">🗑️</button>
          <button class="icon-btn icon-more" onclick="toggleMenu(event,<?= $r['id'] ?>)">⋯</button>
          <div class="menu-pop" id="menu<?= $r['id'] ?>" style="display:none">
            <a class="m-print" href="<?= e($PRINT_PAGE) ?>?id=<?= $r['id'] ?>" target="_blank">🖨️ طباعة <?= e($DOC_TITLE) ?></a>
            <a class="m-edit" href="<?= e($FORM_PAGE) ?>?id=<?= $r['id'] ?>">✏️ تعديل <?= e($DOC_TITLE) ?></a>
            <?php if($CONVERT && !$isConverted): ?>
              <a class="m-conv" href="#" onclick="convertRow(<?= $r['id'] ?>);return false;">✅ <?= e($CONVERT['menu_label']) ?></a>
            <?php endif; ?>
            <a class="m-scan" href="#" onclick="scanRow(<?= $r['id'] ?>);return false;">📎 إرفاق نسخة ضوئية</a>
            <?php if($r['scan_file']): ?>
              <a class="m-scan" style="background:#5b86e5" href="<?= base_url(e($r['scan_file'])) ?>" target="_blank">👁️ عرض النسخة الضوئية</a>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
  </div>
</div>

<!-- استرداد محذوف -->
<div class="modal-bg" id="recover"><div class="modal" style="max-width:440px">
  <div class="modal-head">استرداد رقم محذوف</div>
  <div class="modal-body">
    <p class="muted">اختر رقماً محذوفاً لإنشاء <?= e($DOC_TITLE) ?> جديد يأخذ نفس الرقم:</p>
    <div style="display:flex;flex-wrap:wrap;gap:8px">
      <?php foreach($gaps as $g): ?>
        <a class="btn btn-recover btn-sm" href="<?= e($FORM_PAGE) ?>?gap=<?= $g ?>"><?= $g ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="modal-foot"><button type="button" class="btn btn-warn" onclick="closeModal('recover')">إغلاق</button></div>
</div></div>

<!-- نماذج خفية -->
<form method="post" id="delForm" style="display:none"><?= mq_csrf_field() ?>
  <input type="hidden" name="act" value="delete"><input type="hidden" name="id" id="del_id"></form>
<?php if($CONVERT): ?>
<form method="post" id="convForm" style="display:none"><?= mq_csrf_field() ?>
  <input type="hidden" name="act" value="convert"><input type="hidden" name="id" id="conv_id"></form>
<?php endif; ?>
<form method="post" id="scanForm" style="display:none" enctype="multipart/form-data"><?= mq_csrf_field() ?>
  <input type="hidden" name="act" value="scan"><input type="hidden" name="id" id="scan_id">
  <input type="file" name="scan" id="scan_file" accept="image/*,application/pdf" onchange="document.getElementById('scanForm').submit()"></form>

<script>
function delRow(id){ if(confirm('تأكيد الحذف؟ سيبقى الرقم محجوزاً للاسترداد.')){ document.getElementById('del_id').value=id; document.getElementById('delForm').submit(); } }
function convertRow(id){ if(confirm('<?= e($CONVERT['menu_label'] ?? 'تحويل') ?>؟ لا يمكن التحويل أكثر من مرة.')){ document.getElementById('conv_id').value=id; document.getElementById('convForm').submit(); } }
function scanRow(id){ document.getElementById('scan_id').value=id; document.getElementById('scan_file').click(); }
function toggleMenu(e,id){
  e.stopPropagation();
  document.querySelectorAll('.menu-pop').forEach(function(m){ if(m.id!=='menu'+id) m.style.display='none'; });
  var m=document.getElementById('menu'+id);
  m.style.display = m.style.display==='block'?'none':'block';
}
document.addEventListener('click',function(){ document.querySelectorAll('.menu-pop').forEach(m=>m.style.display='none'); });

// بعد الحفظ: اسأل هل يريد الطباعة أم الحفظ فقط
<?php
$savedId = (int)($_GET['saved'] ?? 0);
if ($savedId > 0):
?>
(function(){
  var id = <?= $savedId ?>;
  var printUrl = '<?= e($PRINT_PAGE) ?>?id=' + id;
  // نافذة خيار مخصّصة بدل confirm العادي
  var bg=document.createElement('div'); bg.className='modal-bg show';
  bg.innerHTML='<div class="modal" style="max-width:420px">'+
    '<div class="modal-head">✅ تم الحفظ بنجاح</div>'+
    '<div class="modal-body" style="text-align:center;font-size:15px;padding:22px">'+
      'تم حفظ <?= e($DOC_TITLE) ?> بنجاح.<br>هل تريد طباعته الآن؟'+
    '</div>'+
    '<div class="modal-foot" style="justify-content:center">'+
      '<button class="btn btn-primary" id="doPrintBtn">🖨️ نعم، اطبع الآن</button>'+
      '<button class="btn btn-soft" id="noPrintBtn">الحفظ فقط</button>'+
    '</div></div>';
  document.body.appendChild(bg);
  document.getElementById('doPrintBtn').onclick=function(){ window.open(printUrl,'_blank'); cleanUrl(); bg.remove(); };
  document.getElementById('noPrintBtn').onclick=function(){ cleanUrl(); bg.remove(); };
  function cleanUrl(){ if(history.replaceState){ history.replaceState(null,'',location.pathname); } }
})();
<?php endif; ?>
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>

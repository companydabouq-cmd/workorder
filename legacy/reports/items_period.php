<?php
require_once __DIR__ . '/../includes/functions.php';
require_staff();
$PAGE_KEY='rpt_period'; $PAGE_TITLE='حصر بنود خلال فترة';

$from=$_GET['from']??''; $to=$_GET['to']??''; $itemNo=trim($_GET['item_no']??'');
$result=null; $ran=false;

if ($itemNo!=='' || $from!=='' || $to!=='') {
    $ran=true;
    $w=["oi.item_no = ?"]; $p=[$itemNo];
    if($from!==''){ $w[]="o.start_date >= ?"; $p[]=$from; }
    if($to!==''){ $w[]="o.start_date <= ?"; $p[]=$to; }
    $where=implode(' AND ',$w);
    $result=q_all("SELECT o.order_no, s.site_name, o.start_date, o.end_date,
                          oi.item_details, oi.unit, oi.qty
                   FROM work_order_items oi
                   JOIN work_orders o ON o.id=oi.order_id
                   LEFT JOIN worksites s ON s.id=o.site_id
                   WHERE $where
                   ORDER BY o.order_no ASC", $p);
}

require __DIR__ . '/../includes/header.php';
$ministry=get_ministry()['ministry_name']; $contractor=get_contractor()['contractor_name'];
?>
<div class="page-head"><div class="page-title"><span class="ic">📈</span> حصر بنود خلال فترة</div></div>

<div class="panel" style="margin-bottom:18px">
  <form method="get" class="grid4" style="align-items:end">
    <div class="field"><label>من تاريخ</label><input type="date" name="from" value="<?= e($from) ?>"></div>
    <div class="field"><label>إلى تاريخ</label><input type="date" name="to" value="<?= e($to) ?>"></div>
    <div class="field"><label>رقم البند <span class="req">*</span></label><input name="item_no" value="<?= e($itemNo) ?>" required placeholder="أدخل رقم البند"></div>
    <div class="field"><button class="btn btn-primary">عرض الحصر</button></div>
  </form>
</div>

<?php if($ran && $itemNo!==''):
  $totalQty=0; foreach(($result??[]) as $r) $totalQty+=$r['qty'];
  $itemDetails = $result ? $result[0]['item_details'] : '';
?>
<div class="panel" id="rptArea">
  <div style="text-align:center;margin-bottom:14px">
    <div style="font-weight:800;font-size:16px">حصر البنود خلال الفترة</div>
    <div class="muted">من: <?= e($from?fmt_date($from):'البداية') ?> &nbsp; إلى: <?= e($to?fmt_date($to):'النهاية') ?></div>
  </div>
  <table class="tbl" style="margin-bottom:12px">
    <tr><td class="lbl" style="background:#eef1f7;font-weight:700;width:120px">رقم البند</td><td><?= e($itemNo) ?></td></tr>
    <tr><td class="lbl" style="background:#eef1f7;font-weight:700">تفاصيل البند</td><td><?= e($itemDetails) ?></td></tr>
  </table>
  <div style="overflow-x:auto">
  <table class="tbl">
    <thead><tr>
      <th>رقم أمر العمل</th><th>موقع العمل</th><th>تاريخ بدء العمل</th><th>تاريخ نهاية العمل</th><th>الكمية</th><th>الوحدة</th>
    </tr></thead>
    <tbody>
    <?php if(!$result): ?><tr><td colspan="6" class="empty">لا توجد نتائج لهذا البند في الفترة المحددة</td></tr>
    <?php else: foreach($result as $r): ?>
      <tr><td><?= (int)$r['order_no'] ?></td><td><?= e($r['site_name']??'—') ?></td>
      <td><?= fmt_date($r['start_date']) ?></td><td><?= fmt_date($r['end_date']) ?></td>
      <td><?= fmt($r['qty']) ?></td><td><?= e($r['unit']) ?></td></tr>
    <?php endforeach; endif; ?>
    </tbody>
    <tfoot>
      <tr style="font-weight:800;background:#f6f0e0">
        <td colspan="2">عدد أوامر العمل الإجمالي: <?= count($result) ?></td>
        <td colspan="2">إجمالي الكميات</td>
        <td colspan="2"><?= fmt($totalQty) ?></td>
      </tr>
    </tfoot>
  </table>
  </div>
  <div style="margin-top:16px"><button class="btn btn-soft" onclick="window.print()">🖨️ طباعة التقرير</button></div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>

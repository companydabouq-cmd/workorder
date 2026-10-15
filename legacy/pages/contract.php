<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$PAGE_KEY='contract'; $PAGE_TITLE='بيانات العقد';

$c = get_contract();
// أنواع أعمال العقد (مدني، كهرباء، تكييف، إنذار وحريق...): المختارة هنا هي التي تظهر في الحصر المبدئي
// وحصر العمل وأمر العمل — مع كل الترويسات.
$wtOn = has_work_types();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    if ($wtOn && !empty($_POST['wt_present'])) {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['wt'] ?? [])))));
        q("UPDATE work_types SET in_contract=0");
        if ($ids) q("UPDATE work_types SET in_contract=1 WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")", $ids);
    }
    $no    = trim($_POST['contract_no'] ?? '');
    $name  = trim($_POST['contract_name'] ?? '');
    $value = (float)($_POST['actual_value'] ?? 0);
    $dailyFine = (float)($_POST['daily_delay_fine'] ?? 0);
    $sd    = $_POST['start_date'] ?: null;
    $ed    = $_POST['end_date'] ?: null;
    if ($c) {
        q("UPDATE contracts SET contract_no=?,contract_name=?,actual_value=?,daily_delay_fine=?,start_date=?,end_date=? WHERE id=?",
          [$no,$name,$value,$dailyFine,$sd,$ed,$c['id']]);
    } else {
        q("INSERT INTO contracts (contract_no,contract_name,actual_value,daily_delay_fine,start_date,end_date,is_active) VALUES (?,?,?,?,?,?,1)",
          [$no,$name,$value,$dailyFine,$sd,$ed]);
    }
    flash_set('ok','تم حفظ بيانات العقد بنجاح');
    header('Location: contract.php'); exit;
}

require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div class="page-title"><span class="ic">📄</span> بيانات العقد</div></div>
<?= flash_get() ?>
<div class="panel" style="max-width:760px">
  <form method="post">
    <?= mq_csrf_field() ?>
    <div class="grid2">
      <div class="field">
        <label>رقم العقد <span class="req">*</span></label>
        <input name="contract_no" required value="<?= e($c['contract_no'] ?? '') ?>" placeholder="أدخل رقم العقد">
      </div>
      <div class="field">
        <label>القيمة الفعلية للعقد <span class="req">*</span></label>
        <input name="actual_value" type="number" step="0.001" required value="<?= e($c['actual_value'] ?? '') ?>">
      </div>
    </div>
    <div class="grid2">
      <div class="field">
        <label>غرامة التأخير لليوم الواحد (دينار)</label>
        <input name="daily_delay_fine" type="number" step="0.001" value="<?= e($c['daily_delay_fine'] ?? '0') ?>" placeholder="أدخل غرامة التأخير اليومية">
      </div>
    </div>
    <div class="field">
      <label>اسم العقد <span class="req">*</span></label>
      <textarea name="contract_name" required><?= e($c['contract_name'] ?? '') ?></textarea>
    </div>
    <div class="grid2">
      <div class="field">
        <label>تاريخ بداية العقد</label>
        <input name="start_date" type="date" value="<?= e($c['start_date'] ?? '') ?>">
      </div>
      <div class="field">
        <label>تاريخ نهاية العقد</label>
        <input name="end_date" type="date" value="<?= e($c['end_date'] ?? '') ?>">
      </div>
    </div>
    <?php if ($wtOn): $wtAll = q_all("SELECT id, type_name, type_code, in_contract FROM work_types ORDER BY id"); ?>
    <div class="field">
      <label>أنواع أعمال العقد</label>
      <input type="hidden" name="wt_present" value="1">
      <?php if (!$wtAll): ?>
        <p class="muted">لا توجد أنواع أعمال — أضفها من صفحة <a href="work_types.php"><b>«أنواع الأعمال»</b></a>.</p>
      <?php else: ?>
      <div style="display:flex;gap:18px;flex-wrap:wrap;align-items:center;padding:6px 0">
        <label class="opt"><input type="checkbox" id="wt_all" onchange="wtAll(this.checked)"> <b>الكل</b></label>
        <?php foreach ($wtAll as $t): ?>
        <label class="opt"><input type="checkbox" class="wt-one" name="wt[]" value="<?= (int)$t['id'] ?>" <?= $t['in_contract'] ? 'checked' : '' ?> onchange="wtSync()">
          <?= e($t['type_name']) ?><?= $t['type_code'] !== '' ? ' ('.e($t['type_code']).')' : '' ?></label>
        <?php endforeach; ?>
      </div>
      <p class="muted" style="margin-top:4px">اختر نوعاً واحداً أو أكثر أو «الكل». في الحصر المبدئي وحصر العمل وأمر العمل:
        إن اخترت نوعاً واحداً يُكتب مباشرة، وإن اخترت أكثر تظهر قائمة بالأنواع المختارة فقط.
        (لإضافة نوع أو تعديل رمزه: صفحة <a href="work_types.php"><b>«أنواع الأعمال»</b></a>.)</p>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <button class="btn btn-primary">💾 حفظ بيانات العقد</button>
  </form>
</div>
<?php if ($wtOn): ?>
<script>
function wtAll(on){ document.querySelectorAll('.wt-one').forEach(function(c){ c.checked=on; }); }
function wtSync(){ var a=document.querySelectorAll('.wt-one'), all=document.getElementById('wt_all'); if(!all) return;
  all.checked = a.length>0 && Array.prototype.every.call(a, function(c){ return c.checked; }); }
wtSync();
</script>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$PAGE_KEY='contractor'; $PAGE_TITLE='اسم المقاول ونسبة الخصم';

$c = get_contractor();
$cid = $c['id'] ?? null;
$hasExtra = has_extra();   // جدول المناطق ذات النسبة الإضافية (يُنشأ تلقائياً)

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $name = trim($_POST['contractor_name'] ?? '');
    $disc = (float)($_POST['discount_percent'] ?? 0);
    if ($cid) q("UPDATE contractor_settings SET contractor_name=?,discount_percent=? WHERE id=?", [$name,$disc,$cid]);
    else      q("INSERT INTO contractor_settings (contractor_name,discount_percent) VALUES (?,?)", [$name,$disc]);

    // المناطق ذات النسبة الإضافية — تُعالج فقط إن أُرسل قسمها (حتى لا يمحوها طلب ناقص)
    if ($hasExtra && !empty($_POST['ex_present'])) {
        $ids = $_POST['ex_id'] ?? []; $names = $_POST['ex_name'] ?? []; $pcts = $_POST['ex_pct'] ?? [];
        $discs = $_POST['ex_disc'] ?? [];   // تطبيق نسبة الخصم في المنطقة: 1 نعم، 0 لا
        $keep = [];
        foreach ($names as $i => $n) {
            $n = trim($n); $p = (float)($pcts[$i] ?? 0); $id = (int)($ids[$i] ?? 0);
            $ad = (($discs[$i] ?? '1') === '0') ? 0 : 1;
            if ($n === '') continue;
            if ($id) { q("UPDATE extra_areas SET area_name=?, percent=?, apply_discount=? WHERE id=?", [$n, $p, $ad, $id]); $keep[] = $id; }
            else     { q("INSERT INTO extra_areas (area_name, percent, apply_discount) VALUES (?,?,?)", [$n, $p, $ad]); $keep[] = (int)db()->lastInsertId(); }
        }
        // المحذوفة: يُلغى ربطها بمواقع العمل (المستندات السابقة تحتفظ بنسختها)
        foreach (array_column(q_all("SELECT id FROM extra_areas"), 'id') as $oldId) {
            if (in_array((int)$oldId, $keep, true)) continue;
            q("UPDATE worksites SET extra_area_id=NULL WHERE extra_area_id=?", [$oldId]);
            q("DELETE FROM extra_areas WHERE id=?", [$oldId]);
        }
        print_settings_save([
            'extra_position' => ($_POST['extra_position'] ?? '') === 'after' ? 'after' : 'before',
            'extra_label'    => trim((string)($_POST['extra_label'] ?? '')),
        ]);
    }
    flash_set('ok','تم حفظ بيانات المقاول');
    header('Location: contractor_settings.php'); exit;
}

$extraAreas = $hasExtra ? q_all("SELECT ea.*, (SELECT COUNT(*) FROM worksites w WHERE w.extra_area_id=ea.id) AS sites
                                 FROM extra_areas ea ORDER BY ea.id") : [];
$extraPos   = ps('extra_position');

require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div class="page-title"><span class="ic">⚙️</span> اسم المقاول ونسبة الخصم</div></div>
<?= flash_get() ?>
<form method="post">
<?= mq_csrf_field() ?>
<div class="panel" style="max-width:720px;margin-bottom:18px">
    <div class="field">
      <label>اسم المقاول (الشركة) <span class="req">*</span></label>
      <input name="contractor_name" required value="<?= e($c['contractor_name'] ?? '') ?>" placeholder="أدخل اسم المقاول">
    </div>
    <div class="field">
      <label>نسبة الخصم على العقد كاملاً (%) <span class="req">*</span></label>
      <input name="discount_percent" type="number" step="0.001" required value="<?= e($c['discount_percent'] ?? '0') ?>" placeholder="أدخل نسبة الخصم">
    </div>
  <p class="muted" style="margin-top:14px">تُطبّق نسبة الخصم تلقائياً على جميع البنود في كل حصورات العمل وأوامر العمل.</p>
  <p class="muted" style="margin-top:6px">
    اسم المقاول يظهر يسار ترويسة الطباعة. لإضافة سطر ثانٍ تحته أو تعديل باقي نصوص الترويسة
    ← <a href="<?= base_url('pages/print_settings.php') ?>"><b>صفحة «الترويسة والتذييل»</b></a>.
  </p>
</div>

<?php if ($hasExtra): ?>
<div class="panel" style="max-width:720px;margin-bottom:18px">
  <input type="hidden" name="ex_present" value="1">
  <div style="font-weight:800;font-size:15px;margin-bottom:4px">📍 النسب الإضافية للمناطق</div>
  <p class="muted" style="margin-bottom:12px">
    بعض مناطق المناقصة لها نسبة إضافية. اكتب كل منطقة ونسبتها مرة واحدة هنا، ثم عند إضافة موقع عمل في صفحة
    <a href="<?= base_url('pages/worksites.php') ?>"><b>مواقع العمل</b></a> يُسأل: هل يوجد نسبة إضافية؟ فتختار منطقتها.
    «تطبيق نسبة الخصم = لا» يعني أن مستندات هذه المنطقة تأخذ النسبة الإضافية فقط بلا خصم.
    المستند يحفظ النسبتين وقت إنشائه، فتغييرهما هنا لا يغيّر المستندات السابقة.
  </p>
  <table class="tbl" id="exTbl">
    <thead><tr><th>المنطقة</th><th style="width:130px">النسبة الإضافية (%)</th><th style="width:150px">تطبيق نسبة الخصم</th><th style="width:100px">مواقع مربوطة</th><th class="no-export" style="width:60px"></th></tr></thead>
    <tbody>
    <?php foreach ($extraAreas as $ea): ?>
      <tr>
        <td><input type="hidden" name="ex_id[]" value="<?= (int)$ea['id'] ?>">
            <input name="ex_name[]" value="<?= e($ea['area_name']) ?>" placeholder="اسم المنطقة" style="width:100%"></td>
        <td><input name="ex_pct[]" type="number" step="0.001" value="<?= e(pct_label($ea['percent'])) ?>" style="width:100%"></td>
        <td><select name="ex_disc[]" style="width:100%">
              <option value="1" <?= (int)$ea['apply_discount'] ? 'selected' : '' ?>>نعم — يُطبق الخصم</option>
              <option value="0" <?= (int)$ea['apply_discount'] ? '' : 'selected' ?>>لا — النسبة الإضافية فقط</option>
            </select></td>
        <td style="text-align:center"><?= (int)$ea['sites'] ?></td>
        <td><button type="button" class="icon-btn icon-del" onclick="delExRow(this, <?= (int)$ea['sites'] ?>)">🗑️</button></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <button type="button" class="btn btn-soft btn-sm" style="margin-top:10px" onclick="addExRow()">➕ إضافة منطقة</button>

  <div class="field" style="margin-top:18px">
    <label>هل تُحسب النسبة الإضافية قبل نسبة الخصم أم بعدها؟</label>
    <label style="font-weight:400;display:block"><input type="radio" name="extra_position" value="before" <?= $extraPos !== 'after' ? 'checked' : '' ?>>
      <b>قبل الخصم</b> — تُضاف النسبة على الإجمالي، ثم يُخصم الخصم من المجموع.</label>
    <label style="font-weight:400;display:block"><input type="radio" name="extra_position" value="after" <?= $extraPos === 'after' ? 'checked' : '' ?>>
      <b>بعد الخصم</b> — يُخصم الخصم من الإجمالي، ثم تُضاف النسبة على الباقي.</label>
    <p class="muted" style="margin-top:4px">المبلغ النهائي واحد في الحالتين؛ يتغيّر فقط ترتيب السطور في الطباعة وقيمة سطري النسبة والخصم.</p>
  </div>
  <div class="field">
    <label>نص سطر النسبة الإضافية في الطباعة</label>
    <input name="extra_label" value="<?= e(ps('extra_label')) ?>" placeholder="أدخل نص السطر">
    <p class="muted" style="margin-top:4px">الرمزان <b>{النسبة}</b> و<b>{المنطقة}</b> يُستبدلان بقيمتي المستند، مثل: «نسبة إضافية لمنطقة {المنطقة} {النسبة}%».</p>
  </div>
</div>
<?php endif; ?>

<button class="btn btn-primary">💾 حفظ</button>
</form>

<script>
function addExRow(){
  var tr=document.createElement('tr');
  tr.innerHTML='<td><input type="hidden" name="ex_id[]" value="0"><input name="ex_name[]" placeholder="اسم المنطقة" style="width:100%"></td>'+
    '<td><input name="ex_pct[]" type="number" step="0.001" placeholder="مثل 10" style="width:100%"></td>'+
    '<td><select name="ex_disc[]" style="width:100%"><option value="1">نعم — يُطبق الخصم</option><option value="0">لا — النسبة الإضافية فقط</option></select></td>'+
    '<td style="text-align:center">0</td>'+
    '<td><button type="button" class="icon-btn icon-del" onclick="delExRow(this,0)">🗑️</button></td>';
  document.querySelector('#exTbl tbody').appendChild(tr);
  tr.querySelector('input[name="ex_name[]"]').focus();
}
function delExRow(btn, sites){
  if(sites>0 && !confirm('هذه المنطقة مربوطة بـ '+sites+' موقع عمل — سيُلغى ربطها بعد الحفظ (المستندات السابقة لا تتغيّر). متابعة؟')) return;
  btn.closest('tr').remove();
}
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>

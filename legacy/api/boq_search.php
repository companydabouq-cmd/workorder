<?php
require_once __DIR__ . '/../includes/functions.php';
require_staff();   // المدير والمستخدم فقط — المشاهد لا يبحث في جدول الكميات
$q       = trim($_GET['q'] ?? '');
$main    = trim($_GET['main'] ?? '');                 // اسم البند الرئيسي (اختياري): يحصر البحث فيه
$offset  = max(0, (int)($_GET['offset'] ?? 0));       // القائمة تُحمَّل 30 بنداً في كل مرة
$hasMain = boq_main_format();                         // البند الرئيسي جزء من تنسيق «المالية» فقط (حسب الترويسة المختارة)
// مفتاح البحث: يطابق رقم البند بأي ترتيب كُتب به — المستخدم يكتب 8/1-أ والملف فيه أ-8/1.
// وإن كانت «طريقة اختيار البند» عكس ترتيب الجدول (يكتب الباب ثم البند والجدول بند/باب) يُعكس الرقمان.
$useKey  = $q !== '' && has_boq_key();
$key     = $useKey ? boq_query_key($q) : '';
$swapped = $useKey && boq_order_swapped();
$cols = 'id,item_no,item_details,unit,price_before' . ($hasMain ? ',main_no,main_name' : '') . ($key !== '' ? ',item_key' : '');

$w = []; $p = []; $op = [];
$order = 'id ASC';
if ($q !== '') {
    $typedKey = $useKey ? boq_item_key($q) : '';
    if ($swapped && preg_match('/^(\d+)\/?$/', $typedKey, $m) && strpos($typedKey, '/') !== false) {
        // كُتب رقم الباب فقط (مثل «1/») والجدول بند/باب: اعرض بنود هذا الباب بترتيب الجدول
        $w[] = '(item_key REGEXP ? OR item_details LIKE ?)';
        $p[] = '^[0-9]+/' . $m[1] . '(-|$)'; $p[] = "%$q%";
        $key = '';
    } elseif ($swapped && preg_match('/^(\d+)\/(\d+)(-.*)?$/u', $typedKey, $m)) {
        // رقم مكتوب بعكس ترتيب الجدول (الباب ثم البند): المطابقة بالمفتاح المعكوس فقط — النص كما كُتب قد يطابق بنداً آخر.
        // بلا فرع: بنود هذا الباب التي يبدأ رقمها بما كُتب (1/8 ← 8/1، 80/1، 81/1...). مع فرع: ما يبدأ بالمفتاح.
        if (($m[3] ?? '') === '') { $w[] = '(item_key REGEXP ? OR item_details LIKE ?)'; $p[] = '^' . $m[2] . '[0-9]*/' . $m[1] . '(-|$)'; }
        else                       { $w[] = '(item_key LIKE ? OR item_details LIKE ?)';   $p[] = "$key%"; }
        $p[] = "%$q%";
        $order = '(item_key = ?) DESC, (item_key LIKE ?) DESC, id ASC';
        $op = [$key, rtrim($key, '-') . '-%'];
    } else {
        $c = 'item_no LIKE ? OR item_details LIKE ?'; $p[] = "%$q%"; $p[] = "%$q%";
        if ($key !== '') { $c .= ' OR item_key LIKE ?'; $p[] = "%$key%"; }
        $w[] = "($c)";
        // الترتيب: البند المطابق تماماً، ثم بنوده الفرعية (8/1 ← 8/1-أ، 8/1-ب...)، ثم ما يبدأ به، ثم الباقي بترتيب الجدول
        // (أرقام البنود تتكرر بين البنود الرئيسية، فالمطابق قد يكون أكثر من بند)
        $order = '(item_no = ?) DESC, (item_no LIKE ?) DESC, id ASC'; $op = [$q, "$q%"];
        if ($key !== '') {
            $order = '(item_key = ?) DESC, (item_key LIKE ?) DESC, (item_key LIKE ?) DESC, ' . $order;
            $op = array_merge([$key, rtrim($key, '-') . '-%', "$key%"], $op);
        }
    }
}
if ($main !== '' && $hasMain)  { $w[] = 'main_name = ?'; $p[] = $main; }
$where = $w ? 'WHERE ' . implode(' AND ', $w) : '';

$rows = q_all("SELECT $cols FROM boq_items $where ORDER BY $order LIMIT 30 OFFSET $offset", array_merge($p, $op));
if ($useKey) {
    $base = rtrim($key, '-') . '-';
    foreach ($rows as &$r) {
        $k = (string)($r['item_key'] ?? '');
        $r['exact'] = ($key !== '' && $k === $key);                                 // هو البند المكتوب نفسه
        $r['sub']   = ($key !== '' && $k !== $key && strpos($k, $base) === 0);       // بند فرعي للرقم المكتوب
        unset($r['item_key']);
    }
    unset($r);
}
json_out($rows);

<?php
/**
 * دوال مساعدة عامة للنظام
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/session.php';

legacy_session_start();

/** هل المستخدم مسجّل دخول؟ */
function is_logged_in() {
    return !empty($_SESSION['user_id']);
}

/** فرض تسجيل الدخول - يُستدعى أعلى كل صفحة محمية.
 *  الدور يُقرأ من قاعدة البيانات في كل طلب: تغيير الصلاحية أو حذف المستخدم يسري فوراً بلا انتظار خروجه. */
function require_login() {
    if (!is_logged_in()) {
        header('Location: ' . base_url('login.php'));
        exit;
    }
    static $checked = false;
    if (!$checked) {
        $checked = true;
        $u = q_row("SELECT role, display_name FROM users WHERE id=?", [$_SESSION['user_id']]);
        if (!$u) {   // حُذف الحساب أثناء جلسته
            $_SESSION = []; session_destroy();
            header('Location: ' . base_url('login.php'));
            exit;
        }
        $_SESSION['role'] = $u['role'] ?: 'admin';
        $_SESSION['display_name'] = $u['display_name'];
    }
}

/* ===================================================================
 *  الأدوار:
 *    admin  مدير النظام — كل شيء.
 *    user   مستخدم — كل العمل اليومي (المستندات، التقارير، الإدارات، المواقع، الموظفين...)
 *           بلا بيانات العقد والمقاول: بيانات العقد، اسم المقاول ونسبة الخصم، الترويسة والتذييل،
 *           اسم الوزارة، جداول الكميات، المستخدمون.
 *    viewer مشاهد — يرى أوامر العمل ويطبعها فقط.
 * =================================================================== */
function role_labels() {
    return ['admin' => 'مدير النظام', 'user' => 'مستخدم', 'viewer' => 'مشاهد'];
}
/** دور المستخدم الحالي */
function user_role() {
    return $_SESSION['role'] ?? 'admin';
}
/** هل المستخدم الحالي مشاهد (مشاهدة فقط)؟ */
function is_viewer() {
    return user_role() === 'viewer';
}
/** هل المستخدم الحالي مدير النظام؟ */
function is_admin() {
    return user_role() === 'admin';
}
/** صفحات العمل اليومي: المدير والمستخدم — المشاهد يُوجّه لأوامر العمل */
function require_staff() {
    require_login();
    if (is_viewer()) {
        header('Location: ' . base_url('pages/work_orders.php'));
        exit;
    }
}
/** صفحات مدير النظام فقط (بيانات العقد والمقاول والترويسات وجدول الكميات والمستخدمون) */
function require_admin() {
    require_staff();
    if (!is_admin()) {
        flash_set('er', 'هذه الصفحة لمدير النظام فقط.');
        header('Location: ' . base_url('index.php'));
        exit;
    }
}

/** مسار أساسي للروابط - يكتشف مجلد المشروع تلقائياً */
function base_url($path = '') {
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    // التعامل مع الصفحات داخل مجلدات فرعية (pages/, api/)
    $dir = preg_replace('#/(pages|api|reports)$#', '', $dir);
    return ($dir === '' ? '' : $dir) . '/' . ltrim($path, '/');
}

/** تهريب آمن للإخراج (XSS) */
if (!function_exists('e')) {   // Laravel يعرّف e() بالسلوك نفسه
    function e($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

/** رمز CSRF */
function mq_csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function mq_csrf_field() {
    return '<input type="hidden" name="csrf" value="' . mq_csrf_token() . '">';
}
function csrf_check() {
    $t = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
    if (empty($t) || !hash_equals($_SESSION['csrf'] ?? '', $t)) {
        http_response_code(419);
        die('انتهت صلاحية الجلسة. أعد تحميل الصفحة.');
    }
}

/** رد JSON */
function json_out($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** تنسيق رقم بثلاث خانات عشرية */
function fmt($n) {
    return number_format((float)$n, 3, '.', '');
}

/** تنسيق تاريخ ميلادي YYYY/MM/DD */
function fmt_date($d) {
    if (!$d) return '';
    $t = strtotime($d);
    return $t ? date('Y/m/d', $t) : e($d);
}

/* ===================================================================
 *  منطق الترقيم التسلسلي + الفجوات + استرداد المحذوف
 * =================================================================== */

/** الرقم التالي = آخر رقم + 1 (لا يستخدم الفجوات تلقائياً)
 *  آمنة للاستدعاء سواء داخل معاملة قائمة أو خارجها */
function next_sequence($seq_key) {
    $pdo = db();
    $ownTx = !$pdo->inTransaction();   // ابدأ معاملة فقط إن لم تكن هناك واحدة
    if ($ownTx) $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT IGNORE INTO number_sequences (seq_key,last_no) VALUES (?,0)")
            ->execute([$seq_key]);
        $row = $pdo->query("SELECT last_no FROM number_sequences WHERE seq_key=" . $pdo->quote($seq_key) . " FOR UPDATE")->fetch();
        $next = ((int)$row['last_no']) + 1;
        $pdo->prepare("UPDATE number_sequences SET last_no=? WHERE seq_key=?")
            ->execute([$next, $seq_key]);
        if ($ownTx) $pdo->commit();
        return $next;
    } catch (Exception $ex) {
        if ($ownTx && $pdo->inTransaction()) $pdo->rollBack();
        throw $ex;
    }
}

/** تسجيل فجوة عند حذف سجل (الرقم يبقى محجوزاً للاسترداد) */
function add_gap($seq_key, $no) {
    db()->prepare("INSERT IGNORE INTO number_gaps (seq_key,gap_no) VALUES (?,?)")
        ->execute([$seq_key, $no]);
}

/** عدد الفجوات المتاحة في قسم معيّن */
function gaps_count($seq_key) {
    $st = db()->prepare("SELECT COUNT(*) c FROM number_gaps WHERE seq_key=?");
    $st->execute([$seq_key]);
    return (int)$st->fetch()['c'];
}

/** قائمة الفجوات (الأرقام المحذوفة) مرتبة تصاعدياً */
function gaps_list($seq_key) {
    $st = db()->prepare("SELECT gap_no FROM number_gaps WHERE seq_key=? ORDER BY gap_no ASC");
    $st->execute([$seq_key]);
    return array_column($st->fetchAll(), 'gap_no');
}

/** استهلاك فجوة (عند استرداد رقم محذوف) */
function consume_gap($seq_key, $no) {
    db()->prepare("DELETE FROM number_gaps WHERE seq_key=? AND gap_no=?")
        ->execute([$seq_key, $no]);
}

/* ===================================================================
 *  دوال جلب الإعدادات
 * =================================================================== */
/* تُقرأ مرة واحدة لكل طلب — تُستدعى من الترويسة وكتلة التواقيع في كل صفحة طباعة */
function get_ministry() {
    static $c = null;
    if ($c === null) {
        $r = db()->query("SELECT * FROM ministry_settings ORDER BY id DESC LIMIT 1")->fetch();
        $c = $r ?: ['ministry_name' => ''];
    }
    return $c;
}
function get_contractor() {
    static $c = null;
    if ($c === null) {
        $r = db()->query("SELECT * FROM contractor_settings ORDER BY id DESC LIMIT 1")->fetch();
        $c = $r ?: ['contractor_name' => '', 'discount_percent' => 0];
    }
    return $c;
}
function get_contract() {
    static $c = false;
    if ($c === false) {
        $r = db()->query("SELECT * FROM contracts WHERE is_active=1 ORDER BY id DESC LIMIT 1")->fetch();
        $c = $r ?: null;
    }
    return $c;
}
function discount_percent() {
    return (float) get_contractor()['discount_percent'];
}

/* ===================================================================
 *  دوال استعلام مختصرة
 * =================================================================== */
function q($sql, $params = []) {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}
function q_all($sql, $params = []) { return q($sql, $params)->fetchAll(); }
function q_row($sql, $params = []) { return q($sql, $params)->fetch(); }
function q_val($sql, $params = []) { $r = q($sql, $params)->fetch(); return $r ? array_values($r)[0] : null; }

/** يضيف عموداً لجدول قائم إن لم يكن موجوداً (ترحيل تلقائي — التحديث لا يحتاج install.php).
 *  يرجع true إن كان العمود موجوداً بعد المحاولة. */
function ensure_column($table, $column, $ddl) {
    static $done = [];
    $k = "$table.$column";
    if (!isset($done[$k])) {
        try {
            $has = db()->query("SHOW COLUMNS FROM `$table` LIKE " . db()->quote($column))->fetch();
            if (!$has) db()->exec("ALTER TABLE `$table` ADD COLUMN `$column` $ddl");
            $done[$k] = true;
        } catch (Exception $ex) {
            $done[$k] = false;   // لا صلاحية تعديل الجدول: تعمل الصفحات بدون العمود
        }
    }
    return $done[$k];
}

/** عمود «المنطقة» لمواقع العمل (يُطبع في الترويسة العرضية والغلاف) */
function has_site_area() {
    return ensure_column('worksites', 'area', "VARCHAR(255) DEFAULT NULL AFTER `site_name`");
}

/* ===================================================================
 *  النسب الإضافية للمناطق (من المناقصة: بعض المناطق لها نسبة إضافية)
 *  القائمة (المنطقة + نسبتها) تُكتب مرة واحدة في صفحة «اسم المقاول ونسبة الخصم»،
 *  وموقع العمل يُربط بإحداها، والمستند يحفظ نسخة منها وقت إنشائه (مثل نسبة الخصم).
 * =================================================================== */

/** جدول المناطق ونسبها + الأعمدة المرتبطة به (ترحيل تلقائي). يرجع true إن وُجد كل شيء. */
function has_extra() {
    static $ok = null;
    if ($ok === null) {
        try {
            db()->exec("CREATE TABLE IF NOT EXISTS `extra_areas` (
                `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
                `area_name`  VARCHAR(255)  NOT NULL,
                `percent`    DECIMAL(7,3)  NOT NULL DEFAULT 0,
                `apply_discount` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $ok = true;
        } catch (Exception $ex) {
            $ok = false;
        }
        // هل تُطبق نسبة الخصم في هذه المنطقة؟ لا ← المستند يأخذ النسبة الإضافية فقط
        $ok = $ok && ensure_column('extra_areas', 'apply_discount', "TINYINT(1) NOT NULL DEFAULT 1 AFTER `percent`");
        $ok = $ok && ensure_column('worksites', 'extra_area_id', "INT UNSIGNED DEFAULT NULL");
        foreach (['work_surveys', 'work_orders', 'prelim_surveys'] as $t) {
            $ok = ensure_column($t, 'extra_percent', "DECIMAL(7,3) NOT NULL DEFAULT 0") && $ok;
            $ok = ensure_column($t, 'extra_area', "VARCHAR(255) DEFAULT NULL") && $ok;
        }
    }
    return $ok;
}

/** النسبة الإضافية لكل موقع مربوط بمنطقة: {site_id: ['pct' => 10.0, 'area' => 'الوفرة', 'nodisc' => false]}
 *  nodisc = منطقة لا تُطبق فيها نسبة الخصم */
function sites_extra() {
    if (!has_extra()) return [];
    $map = [];
    foreach (q_all("SELECT w.id, e.percent, e.area_name, e.apply_discount FROM worksites w JOIN extra_areas e ON e.id = w.extra_area_id") as $r) {
        $map[(int)$r['id']] = ['pct' => (float)$r['percent'], 'area' => $r['area_name'], 'nodisc' => !(int)$r['apply_discount']];
    }
    return $map;
}

/** الصافي بعد النسبة الإضافية والخصم (قبل الغرامة).
 *  واحد سواء حُسبت النسبة قبل الخصم أو بعده (ضرب متتابع) — يختلف فقط أساس كل سطر في الطباعة. */
function doc_net($total, $discPct, $extraPct = 0) {
    return $total * (1 + $extraPct / 100) * (1 - $discPct / 100);
}

/** إجماليات المستند للطباعة حسب موقع النسبة الإضافية من الإعدادات:
 *  قبل الخصم: الإجمالي + النسبة، ثم الخصم على المجموع.  بعد الخصم: الإجمالي − الخصم، ثم النسبة على الباقي. */
function doc_totals($total, $discPct, $extraPct = 0, $fine = 0) {
    $before = ps('extra_position') !== 'after';
    if ($before) {
        $extra = $total * $extraPct / 100;
        $disc  = ($total + $extra) * $discPct / 100;
    } else {
        $disc  = $total * $discPct / 100;
        $extra = ($total - $disc) * $extraPct / 100;
    }
    $net = $total + $extra - $disc;
    return ['before' => $before, 'extra' => $extra, 'disc' => $disc, 'net' => $net, 'due' => $net - $fine];
}

/** نسبة بلا أصفار زائدة: 46.100 → 46.1 */
function pct_label($p) {
    return rtrim(rtrim(number_format((float)$p, 3, '.', ''), '0'), '.');
}

/** سطر النسبة الإضافية كما كتبه المستخدم في الإعدادات ({النسبة} و{المنطقة} تُستبدلان) */
function extra_line($pct, $area) {
    return strtr(ps('extra_label'), ['{النسبة}' => pct_label($pct), '{المنطقة}' => (string)$area]);
}

/* ===================================================================
 *  أنواع الأعمال (مدنية / كهرباء / تكييف ...) — لكل نوع رمز يُكتب مع رقم المستند: 54/ت
 * =================================================================== */

/** جدول أنواع الأعمال + أعمدة المستندات (ترحيل تلقائي). عند إنشائه أول مرة يُعبّأ بالأنواع المعتادة
 *  (مدنية، كهرباء، تكييف، إنذار وحريق). */
function has_work_types() {
    static $ok = null;
    if ($ok === null) {
        try {
            db()->exec("CREATE TABLE IF NOT EXISTS `work_types` (
                `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
                `type_name`  VARCHAR(255)  NOT NULL,
                `type_code`  VARCHAR(10)   NOT NULL DEFAULT '',
                `in_contract` TINYINT(1)   NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            if (!ensure_column('work_types', 'type_code', "VARCHAR(10) NOT NULL DEFAULT ''")) throw new Exception('work_types');
            // «ضمن أعمال العقد»: تُختار من صفحة «بيانات العقد» — الأنواع المختارة فقط تظهر في المستندات
            if (!ensure_column('work_types', 'in_contract', "TINYINT(1) NOT NULL DEFAULT 1")) throw new Exception('work_types');
            // أول مرة فقط: الأنواع المعتادة (تُعدّل أو تُحذف من صفحة «أنواع الأعمال»)
            if (!get_setting_flag('work_types_seeded')) {
                if ((int)q_val("SELECT COUNT(*) FROM work_types") === 0) {
                    $st = db()->prepare("INSERT INTO work_types (type_name, type_code) VALUES (?,?)");
                    foreach ([['اعمال مدنية', 'م'], ['اعمال كهرباء', 'ك'], ['اعمال التكييف', 'ت'], ['اعمال الإنذار والحريق', 'ح']] as $t) $st->execute($t);
                    set_setting_flag('work_types_fire_seeded');
                }
                set_setting_flag('work_types_seeded');
            }
            // قواعد عُبّئت سابقاً بالأنواع الثلاثة: يُضاف «الإنذار والحريق» مرة واحدة إن لم يكن موجوداً
            if (!get_setting_flag('work_types_fire_seeded')) {
                if ((int)q_val("SELECT COUNT(*) FROM work_types") > 0
                    && (int)q_val("SELECT COUNT(*) FROM work_types WHERE type_name LIKE '%حريق%'") === 0) {
                    q("INSERT INTO work_types (type_name, type_code) VALUES (?,?)", ['اعمال الإنذار والحريق', 'ح']);
                }
                set_setting_flag('work_types_fire_seeded');
            }
            $ok = true;
        } catch (Exception $ex) {
            $ok = false;
        }
        foreach (['work_surveys', 'work_orders', 'prelim_surveys'] as $t) {
            $ok = ensure_column($t, 'work_type_code', "VARCHAR(10) DEFAULT NULL AFTER `work_type`") && $ok;   // رمز النوع (نسخة)
            $ok = ensure_column($t, 'work_desc', "VARCHAR(255) DEFAULT NULL AFTER `work_type_code`") && $ok;    // بيان الأعمال
        }
    }
    return $ok;
}

/* نوع الأعمال كقائمة (مع رمز يُكتب بعد الرقم، وبيان الأعمال) يتبع الترويسة المختارة:
 *   «البلدية» و«المالية»   : قائمة من أنواع أعمال العقد.
 *   «الإعاقة + الشئون»     : كما في الأصل — «نوع العمل» خانة يكتبها المستخدم، والرقم بلا رمز. */
/** الترويسات التي يُستعمل معها «نوع الأعمال» كقائمة */
function work_type_layouts() {
    return ['landscape', 'payment'];
}
/** هل قائمة أنواع الأعمال مفعّلة؟ (حسب الترويسة المختارة) */
function work_types_enabled() {
    return in_array(ps('print_layout'), work_type_layouts(), true) && has_work_types();
}
/** أنواع أعمال العقد — المختارة في صفحة «بيانات العقد» — وهي التي تظهر في المستندات */
function contract_work_types() {
    return q_all("SELECT id, type_name, type_code FROM work_types WHERE in_contract=1 ORDER BY id");
}

/** علامة إعداد بسيطة في جدول print_settings (مفاتيح داخلية لا تظهر في الواجهة) */
function get_setting_flag($key) {
    try { return q_val("SELECT setting_value FROM print_settings WHERE setting_key=?", ['_' . $key]) === '1'; }
    catch (Exception $ex) { return false; }
}
function set_setting_flag($key) {
    print_settings_create_table();
    q("INSERT INTO print_settings (setting_key, setting_value) VALUES (?, '1')
       ON DUPLICATE KEY UPDATE setting_value='1'", ['_' . $key]);
}

/** رقم المستند مع رمز نوع العمل كما في موقع البلدية: 54/ت (بدون رمز: الرقم وحده) */
function doc_no_label($no, $code) {
    $code = work_types_enabled() ? trim((string)$code) : '';   // مع «الإعاقة + الشئون»: الرقم وحده كما في الأصل
    return (int)$no . ($code !== '' ? '/' . $code : '');
}

/** عمودا «البند الرئيسي» (رقمه واسمه) في جدول الكميات وبنود المستندات الثلاثة
 *  — يُطبعان في شكل «دفعة الإنجاز». يرجع true إن وُجدت كل الأعمدة. */
function has_item_main() {
    static $ok = null;
    if ($ok === null) {
        $ok = true;
        foreach (['boq_items', 'work_survey_items', 'work_order_items', 'prelim_survey_items'] as $t) {
            $ok = ensure_column($t, 'main_no',   "VARCHAR(50) DEFAULT NULL")  && $ok;
            $ok = ensure_column($t, 'main_name', "VARCHAR(255) DEFAULT NULL") && $ok;
        }
    }
    return $ok;
}

/* ===================================================================
 *  تنسيق جدول الكميات يتبع الترويسة المختارة في «الترويسة والتذييل».
 *  الجدول نفسه واحد يرفعه المستخدم؛ الذي يتغيّر هو شكله (أعمدته):
 *    «المالية»                      : مع البند الرئيسي (رقمه واسمه) — كجدول وزارة المالية.
 *    «الإعاقة + الشئون» و«البلدية» : التنسيق الأصلي — رقم البند، التفاصيل، الوحدة، السعر قبل وبعد الخصم.
 * =================================================================== */
/** الترويسات التي في تنسيق جدول كمياتها «البند الرئيسي» */
function boq_main_layouts() {
    return ['payment'];
}
/** هل تنسيق جدول الكميات الحالي فيه «البند الرئيسي»؟ (حسب الترويسة المختارة) */
function boq_main_format() {
    return has_item_main() && in_array(ps('print_layout'), boq_main_layouts(), true);
}
/** اسم الترويسة المختارة — يُكتب في صفحة جدول الكميات لبيان أي تنسيق معروض */
function boq_format_name() {
    $L = print_layouts();
    return $L[ps('print_layout')][0] ?? $L['landscape'][0];
}

/* ===================================================================
 *  مفتاح البحث عن رقم البند.
 *  المستخدم يكتب رقم البند ثم رقم الباب ثم الفرع: 8/1-أ ، وملفات الجداول تكتب الفرع أولاً (أ-8/1)
 *  أو بمسافات (8/1 - أ) أو بشرطة مائلة (أ/38). المفتاح شكل موحّد «الرقم-الفرع» بلا مسافات، فيطابق
 *  البحث أي كتابة. رقم البند نفسه يبقى محفوظاً ومطبوعاً كما جاء في الملف.
 * =================================================================== */
function boq_item_key($no) {
    $s = str_replace(["\xD9\x80", "\xC2\xA0", ' ', "\t"], '', (string)$no);       // تطويل ومسافات
    $s = str_replace(['–', '—', '−', '‐', '‑', '_'], '-', $s);                     // أشكال الشرطة
    $s = str_replace('\\', '/', $s);
    $s = str_replace(['أ', 'إ', 'آ', 'ٱ'], 'ا', $s);
    $s = strtr($s, ['٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
    if (preg_match('/^(\p{L}+)[\/\-](.+)$/u', $s, $m))       $s = $m[2] . '-' . $m[1];   // الفرع أولاً: أ-8/1 ، أ/38
    elseif (preg_match('/^(\d+)-(\d+\/.+)$/u', $s, $m))      $s = $m[2] . '-' . $m[1];   // فرع رقمي أولاً: 1-3/9
    elseif (preg_match('/^(.*\d)\/?(\p{L}+)$/u', $s, $m))    $s = $m[1] . '-' . $m[2];   // الفرع ملتصق أو بعد شرطة مائلة: 8/1أ ، 38/أ
    return mb_substr($s, 0, 120);
}
/** عمود مفتاح البحث في جدول الكميات — يُضاف ويُعبّأ تلقائياً للبنود الموجودة */
function has_boq_key() {
    static $ok = null;
    if ($ok === null) {
        $ok = ensure_column("boq_items", "item_key", "VARCHAR(120) DEFAULT NULL");
        if ($ok) {
            try {
                if (!get_setting_flag("boq_key_v1")) {          // أول مرة: فهرس + إعادة حساب الكل
                    try { db()->exec("ALTER TABLE `boq_items` ADD INDEX `idx_boq_itemkey` (`item_key`)"); } catch (Exception $ex) { /* موجود */ }
                    db()->exec("UPDATE boq_items SET item_key=NULL");
                    set_setting_flag("boq_key_v1");
                }
                $rows = q_all("SELECT id, item_no FROM boq_items WHERE item_key IS NULL");
                if ($rows) {
                    $st = db()->prepare("UPDATE boq_items SET item_key=? WHERE id=?");
                    foreach ($rows as $r) $st->execute([boq_item_key($r["item_no"]), $r["id"]]);
                }
            } catch (Exception $ex) {
                $ok = false;
            }
        }
    }
    return $ok;
}

/** إعداد داخلي بسيط في جدول print_settings (مفتاحه يبدأ بـ _ فلا تمسّه «استعادة الافتراضي») */
function setting_get($key, $default = '') {
    try { $v = q_val("SELECT setting_value FROM print_settings WHERE setting_key=?", ['_' . $key]); }
    catch (Exception $ex) { return $default; }
    return $v === null ? $default : (string)$v;
}
function setting_set($key, $value) {
    print_settings_create_table();
    q("INSERT INTO print_settings (setting_key, setting_value) VALUES (?,?)
       ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)", ['_' . $key, (string)$value]);
}

/* ===================================================================
 *  طريقة اختيار البند: ترتيب الرقمين في رقم البند.
 *    item_bab : رقم البند ثم رقم الباب  (8/1  = البند 8 من الباب 1)
 *    bab_item : رقم الباب ثم رقم البند  (1/8  = الباب 1، البند 8)
 *  - ترتيب الجدول المرفوع: يُكتشف تلقائياً من البنود، أو يحدده المدير من صفحة «جداول الكميات».
 *  - طريقة الاختيار: الترتيب الذي يكتب به المستخدم عند اختيار البند. إن خالف ترتيب الجدول
 *    يُعكس الرقمان عند البحث، فيجد البند نفسه. رقم البند المحفوظ والمطبوع لا يتغيّر.
 * =================================================================== */
function boq_orders() {
    return ['item_bab' => 'رقم البند ثم رقم الباب', 'bab_item' => 'رقم الباب ثم رقم البند'];
}
/** اكتشاف ترتيب الجدول: الرقم الذي يبقى ثابتاً بين الصفوف المتتالية هو رقم الباب */
function boq_detect_order() {
    if (!has_boq_key()) return 'item_bab';
    $first = $second = 0; $pa = $pb = null;
    foreach (q_all("SELECT item_key FROM boq_items WHERE item_key REGEXP '^[0-9]+/[0-9]+' ORDER BY id") as $r) {
        if (!preg_match('/^(\d+)\/(\d+)/', $r['item_key'], $m)) continue;
        if ($pa !== null) { if ($m[1] === $pa) $first++; if ($m[2] === $pb) $second++; }
        $pa = $m[1]; $pb = $m[2];
    }
    return $first > $second ? 'bab_item' : 'item_bab';
}
/** ترتيب الأرقام في الجدول المرفوع (ما حدّده المدير، وإلا المكتشَف تلقائياً) */
function boq_file_order() {
    static $v = null;
    if ($v === null) {
        $v = setting_get('boq_file_order', 'auto');
        if (!isset(boq_orders()[$v])) {
            $v = setting_get('boq_file_order_auto', '');
            if (!isset(boq_orders()[$v])) { $v = boq_detect_order(); setting_set('boq_file_order_auto', $v); }
        }
    }
    return $v;
}
/** طريقة اختيار البند (الترتيب الذي يكتب به المستخدم) — الافتراضي: كما في الجدول */
function boq_type_order() {
    $v = setting_get('boq_type_order', '');
    return isset(boq_orders()[$v]) ? $v : boq_file_order();
}
/** هل يكتب المستخدم بعكس ترتيب الجدول؟ */
function boq_order_swapped() {
    return boq_type_order() !== boq_file_order();
}
/** مفتاح البحث لما كتبه المستخدم — يُعكس الرقمان الأولان إن كانت طريقة الاختيار عكس ترتيب الجدول */
function boq_query_key($q) {
    $k = boq_item_key($q);
    if (boq_order_swapped()) $k = preg_replace('/^(\d+)\/(\d+)/', '$2/$1', $k);
    return $k;
}

/* رسائل الفلاش (تُخزّن في الجلسة وتظهر مرة واحدة) */
function flash_set($type, $msg) { $_SESSION['flash'] = ['t'=>$type, 'm'=>$msg]; }
function flash_get() {
    if (empty($_SESSION['flash'])) return '';
    $f = $_SESSION['flash']; unset($_SESSION['flash']);
    $cls = $f['t']==='ok' ? 'ok' : 'er';
    return '<div class="flash '.$cls.'">'.e($f['m']).'</div>';
}

/* خيارات قائمة منسدلة */
function options($rows, $value_key, $label_key, $selected = null) {
    $out = '';
    foreach ($rows as $r) {
        $sel = ((string)$selected === (string)$r[$value_key]) ? ' selected' : '';
        $out .= '<option value="'.e($r[$value_key]).'"'.$sel.'>'.e($r[$label_key]).'</option>';
    }
    return $out;
}

/* نصوص الترويسة والتذييل + الشعار (قابلة للتحرير من صفحة «الترويسة والتذييل») */
require_once __DIR__ . '/print_settings.php';

/* رفع ملف صورة وإرجاع المسار النسبي */
function upload_image($field, $subdir = 'employees') {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;
    $f = $_FILES[$field];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif','webp','pdf'];
    if (!in_array($ext, $allowed)) return null;
    if ($f['size'] > 8*1024*1024) return null; // 8MB
    if (!is_uploaded_file($f['tmp_name'])) return null;
    $name = $field . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $rel = 'assets/uploads/' . $subdir . '/' . $name;
    return store_put($rel, $f['tmp_name']) ? $rel : null;
}

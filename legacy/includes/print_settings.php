<?php
/**
 * نصوص الترويسة والتذييل — كل نص ثابت كان مكتوباً داخل الكود صار يُحرَّر
 * من صفحة «الترويسة والتذييل» (pages/print_settings.php).
 *
 * التخزين: جدول print_settings (مفتاح/قيمة). الجدول يُنشأ تلقائياً عند أول
 * استخدام، فلا حاجة لتشغيل install.php ولا لأي تعديل يدوي على قاعدة البيانات
 * عند التحديث من نسخة أقدم.
 *
 * القاعدة: المفتاح الموجود في الجدول يفوز دائماً — حتى لو كانت قيمته فارغة
 * (حتى تستطيع حذف سطر من الترويسة). الافتراضي يُستخدم فقط للمفاتيح غير المحفوظة.
 */

/** القيم الافتراضية = النصوص نفسها التي كانت مثبّتة في الكود قبل هذا التحديث */
function print_defaults() {
    return [
        /* هوية النظام (الشاشة) */
        'app_title'          => 'نظام أوامر العمل',
        'logo_path'          => '',

        /* ترويسة الطباعة */
        'head_right_line2'   => 'إدارة الخدمات العامة',
        'head_left_line2'    => '',
        'contract_label'     => 'عقد رقم',
        'summary_dept_line'  => 'الإدارة العامة',

        /* كتلة التواقيع */
        'sign1_title'        => 'جهاز الإشراف',
        'sign2_title'        => 'رئيس القسم',
        'sign3_title'        => 'الجهة المستفيدة',
        'rep_row_label'      => 'مندوب المتعهد ( استلمت نسخة للتنفيذ )',
        'bottom_right_label' => 'مراقب الخدمات الهندسية',
        'bottom_left_label'  => 'مدير إدارة الخدمات العامة',

        /* محضر الاستلام (أمر العمل فقط) */
        'handover_title'     => 'محضر استلام أعمال لأمر العمل رقم',
        'handover_note1'     => 'إنه في يوم {تاريخ_الانتهاء} قام السادة التالية أسماؤهم باستلام أعمال أمر العمل المذكور أعلاه والمؤرخ في {تاريخ_البدء}',
        'handover_note2'     => 'وبعد المعاينة وُجد أن المقاول قام بالانتهاء من جميع الأعمال المذكورة في أمر العمل وعليه تم التوقيع.',
        'handover_entity1'   => 'جهاز الإشراف',
        'handover_entity2'   => 'الجهة المستفيدة',
        'handover_entity3'   => 'المقاول',

        /* شكل الطباعة — أسماؤه في print_layouts() */
        'print_layout'       => 'landscape',

        /* النسبة الإضافية للمناطق (تُحرَّر من صفحة «اسم المقاول ونسبة الخصم») */
        'extra_position'     => 'before',   // before = قبل نسبة الخصم، after = بعدها
        'extra_label'        => 'نسبة إضافية للمنطقة {النسبة}%',
        'logo2_path'         => '',     // شعار يسار الترويسة العرضية (مثل شعار الدولة)

        /* الطباعة العرضية — ترويسة كل صفحة */
        'l_row1_label'       => 'المنطقة',
        'l_row1_value'       => 'الأحمدي',
        'l_contract_label'   => 'عقد رقم',
        'l_contractor_label' => 'السادة',
        'l_discount_label'   => 'نسبة الخصم',
        'l_row5_label'       => 'في محافظة',
        'l_row5_value'       => 'الأحمدي',
        'l_center_line'      => 'ادارة الإنشاءات - مراقبة المتابعة الإنشائية',
        'l_no_suffix'        => '',
        'l_exec_label'       => 'مدة التنفيذ',
        'l_exec_text'        => 'يرجى تنفيذ الأعمال المبينة أدناه خلال ( {المدة} ) يوم عمل اعتباراً من استلام أمر العمل',
        'l_wtype_label'      => 'نوع العمل',
        'l_wdesc_label'      => 'بيان الأعمال',
        'l_area_label'       => 'منطقة',
        'l_site_label'       => 'في مرفق / مبنى',
        'l_dept_label'       => 'إدارة',
        'l_page_label'       => 'رقم الصفحة',
        'l_value_label'      => 'قيمة أمر العمل',
        'l_value_after_label'=> 'قيمة أمر العمل بعد النسبة',
        'l_currency'         => 'د.ك.',

        /* الطباعة العرضية — تذييل كل صفحة (كل سطر = بند / توقيع) */
        'l_notes_title'      => 'ملاحظة :-',
        'l_notes'            => "في حالة رفض المقاول استلام العمل دون مبرر معقول تسري في مواجهته الجزاءات الواردة بالعقد.\n"
                              . "الكميات المعطاة تقريبية وليست نهائية وتتم المحاسبة على الكميات النهائية.\n"
                              . "تحتفظ إدارة الشئون المالية بأصل أمر العمل هذا بينما يزود بصورة ضوئية كل من : المقاول ، مدير العقد ، المهندس المشرف ، ملف العقد في إدارة الإنشاءات.",
        'l_sign_titles'      => "المهندس المشرف\nمدير العقد\nرئيس القسم المختص\nمراقب المتابعة الإنشائية للمحافظات\nرئيس قسم المحاسبة\nمدير إدارة الانشاءات\nنائب المدير العام لقطاع المشاريع",
        'l_approve_label'    => 'يعتمد ..',
        'l_contractor_sign'  => 'توقيع المقاول',
        'l_receive_date'     => 'تاريخ الاستلام :',

        /* الطباعة العرضية — صفحة الغلاف */
        'l_cover'            => '1',
        'l_cover_line1'      => 'إدارة الإنشاءات',
        'l_cover_line2'      => 'قسم محافظة الأحمدي',
        'l_cover_site_label' => 'مرفق أو مبنى',

        /* شكل «دفعة الإنجاز» (A4 طولي) — يمين الترويسة: الجهة وسطرها الثاني (من الترويسة العامة) ثم هذا السطر */
        'p_right_line3'      => 'مراقبة المباني والصيانة - قسم صيانة المباني',
        'p_contract_label'   => 'العقد المباشر رقم',
        'p_contractor_label' => 'المتعهد',
        'p_center_logo'      => 'second',   // second = الشعار الثاني (شعار الدولة)، main = الشعار الرئيسي
        'p_doc_title'        => '',         // فارغ = اسم المستند نفسه (حصر عمل / أمر عمل)
        'p_value_label'      => 'قيمة دفعة الإنجاز',
        'p_exec_label'       => 'مدة الإنجاز',
        'p_start_label'      => 'تاريخ بدء الإنجاز',
        'p_fine_label'       => 'غرامة التأخير',
        'p_money_word'       => 'دينار',
        'p_days_word'        => 'يوم',
        'p_count_label'      => 'عدد البنود',
        'p_words_label'      => 'المبلغ بالحروف',
        'p_total_label'      => 'المبلغ الاجمالي قبل الخصومات',
        'p_discount_label'   => 'خصم نسبة',
        'p_fine_total_label' => 'اجمالي قيمة غرامة التأخير',
        'p_due_label'        => 'المبلغ المستحق',
        'p_cur_short'        => 'د.ك',
        'p_sign_titles'      => "المتعهد\nالمهندس المشرف\nالمحاسبة\nرئيس قسم الصيانة",
        'p_notes_label'      => 'ملاحظات المهندس المختص :',
        'p_receive_label'    => 'تاريخ الاستلام',
    ];
}

/** أشكال الطباعة: المفتاح المحفوظ في print_layout => [الاسم، الوصف] — بنفس ترتيب العرض */
function print_layouts() {
    return [
        'classic'   => ['الإعاقة + الشئون', 'A4 طولي — الشكل الأصلي للبرنامج: كتلة الإجماليات والتواقيع في آخر صفحة.'],
        'landscape' => ['البلدية', 'A4 أفقي — غلاف، ثم في كل صفحة: الترويسة كاملة (اسم العقد ورقمه والمقاول ونسبة الخصم ورقم الصفحة وقيمة المستند) والتذييل (الملاحظات والتواقيع).'],
        'payment'   => ['المالية', 'A4 طولي — دفعة إنجاز: ترويسة الجهة والعقد في كل صفحة، جدول بالموقع والبند الرئيسي، ثم الإجماليات والمبلغ بالحروف والتواقيع.'],
    ];
}

/** نص متعدد الأسطر → مصفوفة أسطر غير فارغة (للملاحظات وعناوين التواقيع) */
function ps_lines($key) {
    return array_values(array_filter(array_map('trim', preg_split('/\R/u', ps($key))), 'strlen'));
}

/** إنشاء جدول الإعدادات (يرمي استثناءً عند الفشل — يُستدعى عند الحفظ) */
function print_settings_create_table() {
    db()->exec("CREATE TABLE IF NOT EXISTS `print_settings` (
        `setting_key`   VARCHAR(60) NOT NULL,
        `setting_value` TEXT        NOT NULL,
        PRIMARY KEY (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/** كل النصوص جاهزة للاستعمال (المحفوظ فوق الافتراضي) */
function print_settings_all() {
    static $cache = null;
    if ($cache !== null) return $cache;

    $cache = print_defaults();

    // ترحيل: من ضبط المسميات الثلاثة سابقاً في صفحة «اسم الوزارة» تبقى قيمه هي الافتراضي
    $m = get_ministry();
    foreach (['sign1_title' => 'sign1_title', 'sign2_title' => 'sign2_title', 'sign3_title' => 'third_title'] as $new => $old) {
        if (trim($m[$old] ?? '') !== '') $cache[$new] = $m[$old];
    }

    try {
        $rows = q_all("SELECT setting_key, setting_value FROM print_settings");
    } catch (Exception $ex) {
        // الجدول غير موجود بعد (تحديث من نسخة أقدم) — أنشئه وتابع بالافتراضي
        try { print_settings_create_table(); } catch (Exception $ex2) { /* لا صلاحية إنشاء: تعمل النسخة بالافتراضي */ }
        $rows = [];
    }
    foreach ($rows as $r) {
        $cache[$r['setting_key']] = (string) $r['setting_value'];
    }
    return $cache;
}

/** نص إعداد واحد */
function ps($key) {
    $all = print_settings_all();
    return $all[$key] ?? '';
}

/** حفظ مجموعة نصوص (المفاتيح غير المعروفة تُتجاهل) */
function print_settings_save(array $pairs) {
    print_settings_create_table();
    $known = print_defaults();
    $st = db()->prepare("INSERT INTO print_settings (setting_key, setting_value) VALUES (?,?)
                         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    foreach ($pairs as $k => $v) {
        if (!array_key_exists($k, $known)) continue;
        $st->execute([$k, (string) $v]);
    }
}

/** إعادة كل النصوص إلى الافتراضي (يمسح المحفوظ فقط).
 *  لا يمس الشعارين ولا شكل الطباعة المختار ولا إعدادات النسبة الإضافية (ليست نصوص طباعة). */
function print_settings_reset() {
    $keep = [];
    foreach (['logo_path', 'logo2_path', 'print_layout', 'extra_position', 'extra_label'] as $k) {
        $all = print_settings_all();
        if (array_key_exists($k, $all) && $all[$k] !== print_defaults()[$k]) $keep[$k] = $all[$k];
    }
    // المفاتيح التي تبدأ بـ _ علامات داخلية (مثل تعبئة أنواع الأعمال أول مرة) — لا تُمسح
    try { db()->exec("DELETE FROM print_settings WHERE setting_key NOT LIKE '\\_%'"); } catch (Exception $ex) { return; }
    if ($keep) print_settings_save($keep);
}

/** اسم النظام كما يظهر في الشاشة وعنوان المتصفح */
function app_title() {
    $t = trim(ps('app_title'));
    return $t !== '' ? $t : APP_NAME;
}

/**
 * مسار الشعار المرفوع (نسبي، داخل التخزين الدائم)، أو null.
 * المسار يحمل وقت الرفع في اسمه، فلا حاجة لسؤال التخزين في كل صفحة.
 */
function print_logo_file() {
    $p = ps('logo_path');
    return $p === '' ? null : $p;
}

/**
 * رابط الشعار للطباعة والشاشة.
 * الأولوية: المرفوع من الواجهة ← logo.png القديم في assets/img ← شعار مبدئي.
 */
function print_logo_url() {
    $p = print_logo_file();
    if ($p) return base_url($p);

    foreach (['logo.png', 'logo.jpg', 'logo.jpeg', 'logo.webp'] as $lf) {
        $f = __DIR__ . '/../assets/img/' . $lf;
        if (is_file($f)) return base_url('assets/img/' . $lf) . '?v=' . filemtime($f);
    }
    return base_url('assets/img/logo-placeholder.svg');
}

/** هل يوجد شعار حقيقي (مرفوع أو قديم)؟ — لتمييزه عن الشعار المبدئي */
function has_logo() {
    if (print_logo_file()) return true;
    foreach (['logo.png', 'logo.jpg', 'logo.jpeg', 'logo.webp'] as $lf) {
        if (is_file(__DIR__ . '/../assets/img/' . $lf)) return true;
    }
    return false;
}

/** مسار الشعار الثاني (يسار الترويسة العرضية)، أو null */
function print_logo2_file() {
    $p = ps('logo2_path');
    return $p === '' ? null : $p;
}

/** رابط الشعار الثاني، أو '' إن لم يُرفع (فلا يُطبع شيء مكانه) */
function print_logo2_url() {
    $p = print_logo2_file();
    return $p ? base_url($p) : '';
}

/**
 * رفع شعار جديد إلى التخزين الدائم باسم $base-<وقت>.<امتداد>
 * (logo-custom للرئيسي، logo2-custom للثاني) ويحذف الشعار السابق.
 * يرجع المسار النسبي عند النجاح، أو رسالة الخطأ نصاً، أو null إن لم يُرفع ملف.
 */
function print_logo_upload($field = 'logo_file', $base = 'logo-custom') {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return null;

    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) return 'تعذّر رفع الشعار (حجم الملف أكبر من حد الخادم).';

    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $allowed = ['png', 'jpg', 'jpeg', 'webp'];
    if (!in_array($ext, $allowed, true)) return 'صيغة الشعار يجب أن تكون PNG أو JPG أو WEBP.';
    if (!is_uploaded_file($f['tmp_name']) || getimagesize($f['tmp_name']) === false) return 'الملف المرفوع ليس صورة صالحة.';
    if ($f['size'] > 4 * 1024 * 1024) return 'حجم الشعار أكبر من 4 ميجابايت.';

    $rel = 'assets/img/' . $base . '-' . date('YmdHis') . '.' . $ext;
    if (!store_put($rel, $f['tmp_name'])) return 'تعذّر حفظ الشعار في التخزين — حاول مرة أخرى.';

    // امسح الشعار المرفوع سابقاً
    $old = ps($base === 'logo2-custom' ? 'logo2_path' : 'logo_path');
    if ($old !== '' && $old !== $rel) store_delete($old);

    return $rel;
}

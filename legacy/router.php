<?php
/**
 * يحوّل الطلب إلى صفحة البرنامج القديم داخل legacy/.
 *
 * يرجع المسار الكامل لملف PHP المطلوب تضمينه (يُضمَّن في public/index.php
 * في النطاق العام كما كان على الاستضافة العادية)، أو يخدم الملف الثابت/المرفق
 * وينهي الطلب، أو null ليعالجه Laravel (مثل /up).
 */

function legacy_route(string $legacyDir): ?string
{
    $path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    $rel  = ltrim($path, '/');

    if (str_contains($rel, '..') || str_contains($rel, "\0")) {
        http_response_code(400);
        exit;
    }

    if ($rel === 'up') {
        return null;
    }

    // المرفقات والشعارات المرفوعة — من التخزين الدائم
    if (str_starts_with($rel, 'assets/uploads/') || preg_match('#^assets/img/logo2?-custom[^/]*$#', $rel)) {
        legacy_serve_stored($legacyDir, $rel);
    }

    // الملفات الثابتة (CSS / صور / خطوط)
    if (str_starts_with($rel, 'assets/')) {
        legacy_serve_static($legacyDir, $rel);
    }

    if ($rel === '' || str_ends_with($rel, '/')) {
        $rel .= 'index.php';
    }

    $allowed = preg_match('#^(index|login|logout)\.php$#', $rel)
            || preg_match('#^(pages|reports|api)/[A-Za-z0-9_\-]+\.php$#', $rel);
    $file = $legacyDir . '/' . $rel;

    if (!$allowed || !is_file($file)) {
        http_response_code(404);
        exit('الصفحة غير موجودة');
    }

    legacy_guard_input_vars();

    // ما تتوقعه الصفحات القديمة: base_url() و basename($_SERVER['PHP_SELF'])
    $_SERVER['SCRIPT_NAME']     = '/' . $rel;
    $_SERVER['PHP_SELF']        = '/' . $rel;
    $_SERVER['SCRIPT_FILENAME'] = $file;
    chdir(dirname($file));

    return $file;
}

function legacy_serve_stored(string $legacyDir, string $rel): void
{
    // صور الموظفين والنسخ الضوئية لا تُعرض إلا لمستخدم مسجّل دخوله.
    // الشعار يُعرض للجميع لأنه يظهر في صفحة الدخول.
    require_once $legacyDir . '/includes/functions.php';
    if (str_starts_with($rel, 'assets/uploads/') && !is_logged_in()) {
        http_response_code(403);
        exit('يجب تسجيل الدخول');
    }
    session_write_close();
    store_stream($rel);
}

function legacy_serve_static(string $legacyDir, string $rel): void
{
    $types = [
        'css' => 'text/css; charset=utf-8', 'js' => 'application/javascript; charset=utf-8',
        'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'webp' => 'image/webp', 'gif' => 'image/gif', 'ico' => 'image/x-icon',
        'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf',
    ];
    $ext  = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
    $file = $legacyDir . '/' . $rel;

    if (!isset($types[$ext]) || !is_file($file)) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: ' . $types[$ext]);
    header('Content-Length: ' . filesize($file));
    header('Cache-Control: public, max-age=604800');
    readfile($file);
    exit;
}

/**
 * PHP يُسقط بصمت الحقول التي تزيد على max_input_vars (1000 افتراضياً).
 * في مستند كبير يعني ذلك حفظ جزء من البنود فقط — فنرفض الحفظ بدل خسارة البيانات.
 */
function legacy_guard_input_vars(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }
    $limit = (int) ini_get('max_input_vars');
    $count = 0;
    array_walk_recursive($_POST, function () use (&$count) { $count++; });
    if ($limit > 0 && $count >= $limit) {
        http_response_code(413);
        header('Content-Type: text/html; charset=utf-8');
        exit('<div dir="rtl" style="font-family:Tahoma;padding:30px">لم يُحفظ شيء: المستند يحوي حقولاً أكثر من الحد المسموح على الخادم ('.$limit.'). قسّم البنود على مستندين.</div>');
    }
}

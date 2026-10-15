<?php
/**
 * تخزين المرفقات (النسخ الضوئية، صور الموظفين، الشعارات).
 *
 * على Laravel Cloud يُحفظ كل شيء في Object Storage (القرص الافتراضي في Laravel)،
 * فلا تضيع الملفات عند إعادة النشر أو إعادة تشغيل الخادم.
 * المسار المحفوظ في القاعدة يبقى بالشكل القديم نفسه: assets/uploads/scans/xxx.pdf
 * ويُخدَم عبر public/index.php.
 */

use Illuminate\Support\Facades\Storage;

/** القرص المستخدم: القرص الافتراضي لـ Laravel (s3 على Cloud، local محلياً) */
function store_disk() {
    return Storage::disk();
}

/** حفظ ملف مؤقت (من الرفع) في المسار النسبي المعطى */
function store_put($relPath, $tmpFile) {
    $stream = fopen($tmpFile, 'rb');
    if (!$stream) return false;
    try {
        return store_disk()->put($relPath, $stream);
    } catch (Throwable $e) {
        error_log('store_put failed: ' . $e->getMessage());
        return false;
    } finally {
        if (is_resource($stream)) fclose($stream);
    }
}

function store_exists($relPath) {
    if ($relPath === '' || $relPath === null) return false;
    try {
        return store_disk()->exists($relPath);
    } catch (Throwable $e) {
        error_log('store_exists failed: ' . $e->getMessage());
        return false;
    }
}

function store_delete($relPath) {
    try {
        store_disk()->delete($relPath);
    } catch (Throwable $e) {
        error_log('store_delete failed: ' . $e->getMessage());
    }
}

/** وقت آخر تعديل (لبصمة الكاش في رابط الشعار) */
function store_mtime($relPath) {
    try {
        return store_disk()->lastModified($relPath);
    } catch (Throwable $e) {
        return 0;
    }
}

/** إرسال الملف إلى المتصفح ثم إنهاء الطلب */
function store_stream($relPath) {
    $disk = store_disk();
    if (!store_exists($relPath)) {
        http_response_code(404);
        exit('الملف غير موجود');
    }
    $ext = strtolower(pathinfo($relPath, PATHINFO_EXTENSION));
    $types = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'gif' => 'image/gif', 'webp' => 'image/webp', 'pdf' => 'application/pdf',
    ];
    header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=86400');
    $size = null;
    try { $size = $disk->size($relPath); } catch (Throwable $e) {}
    if ($size) header('Content-Length: ' . $size);
    $in = $disk->readStream($relPath);
    $out = fopen('php://output', 'wb');
    stream_copy_to_stream($in, $out);
    fclose($in);
    exit;
}

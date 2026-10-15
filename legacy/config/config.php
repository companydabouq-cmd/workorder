<?php
/**
 * إعدادات قاعدة البيانات
 * على Laravel Cloud تُقرأ القيم تلقائياً من متغيرات البيئة التي تحقنها المنصة
 * (DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD).
 * محلياً تُقرأ من ملف .env في جذر المشروع.
 */

/** قراءة متغير بيئة مع قيمة افتراضية */
function cfg_env($key, $default = null) {
    if (function_exists('env')) {
        $v = env($key);
        if ($v !== null) return $v;
    }
    $v = getenv($key);
    if ($v !== false) return $v;
    return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
}

define('DB_HOST', cfg_env('DB_HOST', '127.0.0.1'));
define('DB_PORT', cfg_env('DB_PORT', '3306'));
define('DB_NAME', cfg_env('DB_DATABASE', 'maqawel'));
define('DB_USER', cfg_env('DB_USERNAME', 'root'));
define('DB_PASS', (string) cfg_env('DB_PASSWORD', ''));

// اسم النظام (يظهر في العنوان)
define('APP_NAME', 'نظام إدارة أوامر العمل');

// المنطقة الزمنية
date_default_timezone_set('Asia/Kuwait');

/**
 * إنشاء اتصال PDO آمن
 */
function db() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $ca = cfg_env('MYSQL_ATTR_SSL_CA');
        if ($ca) $opts[PDO::MYSQL_ATTR_SSL_CA] = $ca;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("SET time_zone = '+03:00'");
        } catch (PDOException $e) {
            error_log('DB connection failed: ' . $e->getMessage());
            if (PHP_SAPI === 'cli') throw $e;
            http_response_code(500);
            die('فشل الاتصال بقاعدة البيانات.');
        }
    }
    return $pdo;
}

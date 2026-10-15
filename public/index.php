<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

// نظام أوامر العمل (legacy/) يخدم كل الصفحات. Laravel يوفّر له البيئة والتخزين فقط.
$app->instance('request', Request::capture());
$app->make(Kernel::class)->bootstrap();

require __DIR__.'/../legacy/router.php';

// الصفحات القديمة كُتبت لسلوك PHP العادي: التحذيرات تُسجَّل ولا توقف الصفحة.
restore_error_handler();
restore_exception_handler();
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

if ($__legacyFile = legacy_route(__DIR__.'/../legacy')) {
    // التضمين في النطاق العام كما على الاستضافة العادية
    require $__legacyFile;

    return;
}

$app->handleRequest(Request::capture());

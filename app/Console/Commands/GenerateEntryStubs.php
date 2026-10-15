<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * ينشئ ملفاً صغيراً في public/ لكل صفحة من صفحات البرنامج القديم.
 *
 * السبب: nginx على Laravel Cloud يحوّل طلب أي ملف .php غير موجود في public/
 * داخلياً إلى index.php كطلب GET، فتضيع بيانات النماذج (POST).
 * وجود الملف الحقيقي يجعل nginx يشغّله مباشرة ويحفظ الطلب كما هو.
 *
 * شغّله بعد إضافة أي صفحة جديدة في legacy/pages أو legacy/reports أو legacy/api.
 */
class GenerateEntryStubs extends Command
{
    protected $signature = 'maqawel:stubs';

    protected $description = 'إنشاء ملفات الدخول في public/ لصفحات legacy/';

    public function handle(): int
    {
        $legacy = base_path('legacy');
        $public = public_path();

        $entries = array_merge(
            ['login.php', 'logout.php'],
            ...array_map(
                fn ($dir) => array_map(fn ($f) => $dir.'/'.basename($f), glob("$legacy/$dir/*.php")),
                ['pages', 'reports', 'api']
            )
        );

        foreach ($entries as $rel) {
            $depth = substr_count($rel, '/');
            $front = $depth ? "dirname(__DIR__, $depth).'/index.php'" : "__DIR__.'/index.php'";
            $stub = "<?php\n\n// مولَّد بـ php artisan maqawel:stubs — الصفحة الفعلية في legacy/$rel\nrequire $front;\n";

            $target = "$public/$rel";
            if (! is_dir(dirname($target))) {
                mkdir(dirname($target), 0755, true);
            }
            file_put_contents($target, $stub);
        }

        $this->info('أُنشئ '.count($entries).' ملف دخول في public/.');

        return self::SUCCESS;
    }
}

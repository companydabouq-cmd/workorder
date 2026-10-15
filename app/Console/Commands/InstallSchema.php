<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PDO;

/**
 * يجهّز قاعدة البيانات للبرنامج — يُشغَّل تلقائياً مع كل نشر على Laravel Cloud.
 *
 * - يرفض العمل إن وجد في القاعدة جداول لمشروع آخر (حماية قاعدة دابوق مثلاً).
 * - ينفّذ legacy/database.sql (كل أوامره آمنة للتكرار: IF NOT EXISTS / INSERT IGNORE).
 * - ينشئ المستخدم admin مرة واحدة فقط إن لم يوجد مدير.
 */
class InstallSchema extends Command
{
    protected $signature = 'maqawel:install';

    protected $description = 'إنشاء جداول نظام أوامر العمل والمستخدم الافتراضي (آمن للتكرار)';

    public function handle(): int
    {
        require_once base_path('legacy/config/config.php');

        $sqlFile = base_path('legacy/database.sql');
        $sql = file_get_contents($sqlFile);

        preg_match_all('/CREATE TABLE IF NOT EXISTS `([^`]+)`/i', $sql, $m);
        $ours = array_merge($m[1], ['migrations']);

        $pdo = db();
        $existing = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $foreign = array_values(array_diff($existing, $ours));

        if ($foreign) {
            $this->error('توقف: قاعدة البيانات "'.DB_NAME.'" على '.DB_HOST.' فيها جداول لا تخص هذا البرنامج:');
            $this->error('  '.implode(', ', array_slice($foreign, 0, 15)).(count($foreign) > 15 ? ' ...' : ''));
            $this->error('غالباً هي قاعدة مشروع آخر. أنشئ قاعدة بيانات مستقلة لهذا البيئة ثم أعد النشر.');

            return self::FAILURE;
        }

        $pdo->exec($sql);
        $this->info('الجداول جاهزة ('.count($m[1]).' جدولاً).');

        $hasAdmin = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn() > 0;
        if (! $hasAdmin) {
            $password = (string) (env('ADMIN_INITIAL_PASSWORD') ?: 'admin123');
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $st = $pdo->prepare("SELECT id FROM users WHERE username='admin'");
            $st->execute();
            if ($st->fetch()) {
                $pdo->prepare("UPDATE users SET password_hash=?, role='admin' WHERE username='admin'")->execute([$hash]);
            } else {
                $pdo->prepare("INSERT INTO users (username,password_hash,display_name,role) VALUES ('admin',?,'مدير النظام','admin')")
                    ->execute([$hash]);
            }
            $this->warn('أُنشئ المستخدم admin — غيّر كلمة مروره بعد أول دخول.');
        }

        return self::SUCCESS;
    }
}

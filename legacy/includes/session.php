<?php
/**
 * جلسات الدخول محفوظة في قاعدة البيانات (جدول php_sessions)
 * لأن خادم Laravel Cloud قد يُعاد تشغيله أو يتوقف عند عدم الاستخدام
 * فتضيع ملفات الجلسات ويخرج المستخدمون.
 */

class DbSessionHandler implements SessionHandlerInterface {
    public function open($path, $name): bool { return true; }
    public function close(): bool { return true; }

    public function read($id): string|false {
        $st = db()->prepare("SELECT data FROM php_sessions WHERE id=?");
        $st->execute([$id]);
        $v = $st->fetchColumn();
        return $v === false ? '' : $v;
    }

    public function write($id, $data): bool {
        db()->prepare("REPLACE INTO php_sessions (id, data, updated_at) VALUES (?, ?, ?)")
            ->execute([$id, $data, time()]);
        return true;
    }

    public function destroy($id): bool {
        db()->prepare("DELETE FROM php_sessions WHERE id=?")->execute([$id]);
        return true;
    }

    public function gc($max_lifetime): int|false {
        $st = db()->prepare("DELETE FROM php_sessions WHERE updated_at < ?");
        $st->execute([time() - $max_lifetime]);
        return $st->rowCount();
    }
}

function legacy_session_start() {
    if (session_status() !== PHP_SESSION_NONE) return;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    ini_set('session.gc_maxlifetime', '43200');   // 12 ساعة
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('maqawel_session');
    session_set_save_handler(new DbSessionHandler(), true);
    session_start();
}

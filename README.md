# نظام إدارة أوامر العمل — نسخة Laravel Cloud

البرنامج الأصلي (PHP عادي) موجود كما هو في مجلد `legacy/`. Laravel هنا غلاف
فقط يتيح تشغيله على Laravel Cloud، ويوفّر له متغيرات البيئة والتخزين الدائم.

## كيف يعمل

| الجزء | المكان |
|---|---|
| صفحات البرنامج | `legacy/` (pages, reports, api, includes) |
| توجيه الطلبات | `public/index.php` ← `legacy/router.php` |
| إنشاء الجداول | `php artisan maqawel:install` (`app/Console/Commands/InstallSchema.php`) |
| المرفقات والشعارات | القرص الافتراضي لـ Laravel (Object Storage على Cloud) — `legacy/includes/storage.php` |
| جلسات الدخول | جدول `php_sessions` في قاعدة البيانات — `legacy/includes/session.php` |

ما تغيّر عن النسخة الأصلية:
- `config/config.php` يقرأ بيانات القاعدة من متغيرات البيئة.
- رفع الصور والنسخ الضوئية والشعار يذهب إلى التخزين الدائم بدل مجلد `assets/`.
  المسارات المحفوظة في القاعدة بقيت بالشكل نفسه (`assets/uploads/...`).
- المرفقات (صور الموظفين، النسخ الضوئية) لا تُفتح إلا لمستخدم مسجّل دخوله.
- `csrf_token()`/`csrf_field()` صارتا `mq_csrf_token()`/`mq_csrf_field()` لأن Laravel يعرّف الاسمين.
- حُذف `install.php` — التثبيت يتم تلقائياً مع كل نشر.

## الإعداد على Laravel Cloud (مرة واحدة)

1. **قاعدة بيانات مستقلة** — لا تستخدم قاعدة مشروع دابوق.
   أنشئ MySQL جديدة في منطقة التطبيق نفسها (US East – Virginia) واربطها بهذه البيئة.
   أمر التثبيت يرفض العمل إن وجد في القاعدة جداول لمشروع آخر.
2. **التخزين الدائم** — أنشئ Bucket من Object Storage واربطه بالبيئة،
   وفعّل خيار جعله القرص الافتراضي (Default disk) حتى يصبح `FILESYSTEM_DISK` مضبوطاً عليه.
   اجعل الـ Bucket خاصاً (Private) — الملفات تُعرض عبر البرنامج بعد التحقق من الدخول.
3. **أوامر النشر** (Settings ← Deployments ← Deploy commands): استبدل `php artisan migrate --force` بـ:
   ```
   php artisan maqawel:install
   ```
4. اختياري: متغير البيئة `ADMIN_INITIAL_PASSWORD` لكلمة مرور المدير الأولى
   (بدونه تكون `admin123` — غيّرها بعد أول دخول).
5. اضغط **Deploy**، ثم ادخل بالمستخدم `admin`.

## التشغيل محلياً

```
composer install
cp .env.example .env
php artisan key:generate
php artisan maqawel:install
php artisan serve
```
قاعدة البيانات المحلية: `maqawel` على MySQL (XAMPP). المرفقات محلياً تُحفظ في `storage/app/private/`.

## حدود معروفة

- `max_input_vars` على الخادم 1000 حقل افتراضياً. المستند الذي يتجاوزها يُرفض حفظه برسالة واضحة
  (بدل أن يُحفظ ناقصاً بصمت). إن ظهرت الرسالة فالحل تقسيم البنود أو رفع الحد من دعم Laravel Cloud.

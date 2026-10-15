-- =====================================================================
--  نظام إدارة أوامر العمل والمقاولات
--  مخطط قاعدة البيانات الكامل - MySQL / MariaDB
--  charset: utf8mb4 (لدعم العربية والرموز)
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+03:00';
SET foreign_key_checks = 0;

-- ---------------------------------------------------------------------
-- 1) المستخدم (مستخدم واحد لتسجيل الدخول)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(100)  NOT NULL,
  `password_hash` VARCHAR(255)  NOT NULL,
  `display_name`  VARCHAR(150)  DEFAULT NULL,
  `role`          VARCHAR(20)   NOT NULL DEFAULT 'admin',
  `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2) بيانات العقد (صفحة منفصلة) - يُتوقع سجل واحد فعّال
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contracts` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `contract_no`   VARCHAR(100)  NOT NULL,            -- رقم العقد مثل 11/2021-2022
  `contract_name` TEXT          NOT NULL,            -- اسم العقد
  `actual_value`  DECIMAL(18,3) NOT NULL DEFAULT 0,  -- القيمة الفعلية للعقد
  `daily_delay_fine` DECIMAL(18,3) NOT NULL DEFAULT 0, -- غرامة التأخير لليوم الواحد
  `start_date`    DATE          DEFAULT NULL,         -- بداية العقد
  `end_date`      DATE          DEFAULT NULL,         -- نهاية العقد
  `is_active`     TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3) إعدادات الوزارة (اسم الوزارة - قيمة واحدة)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ministry_settings` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ministry_name` VARCHAR(255)  NOT NULL,            -- اسم الوزارة
  `sign1_title`   VARCHAR(150)  NOT NULL DEFAULT 'جهاز الإشراف',
  `sign2_title`   VARCHAR(150)  NOT NULL DEFAULT 'رئيس القسم',
  `third_title`   VARCHAR(150)  NOT NULL DEFAULT '',
  `third_name`    VARCHAR(150)  NOT NULL DEFAULT '',
  `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4) إعدادات المقاول (اسم المقاول + نسبة الخصم - قيمة واحدة)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contractor_settings` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `contractor_name`  VARCHAR(255)  NOT NULL,         -- اسم المقاول (الشركة)
  `discount_percent` DECIMAL(7,3)  NOT NULL DEFAULT 0, -- نسبة الخصم على العقد كاملاً (مثل 46.100)
  `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4ج) أنواع الأعمال — لكل نوع رمز يُكتب مع رقم المستند (54/ت)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `work_types` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `type_name`  VARCHAR(255)  NOT NULL,             -- نوع العمل
  `type_code`  VARCHAR(10)   NOT NULL DEFAULT '',  -- رمز نوع العمل
  `in_contract` TINYINT(1)   NOT NULL DEFAULT 1,   -- ضمن أعمال العقد (تُختار من «بيانات العقد»)
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4أ) المناطق ذات النسبة الإضافية (من المناقصة) — تُكتب مرة واحدة في صفحة
--     «اسم المقاول ونسبة الخصم»، وموقع العمل يُربط بإحداها
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `extra_areas` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `area_name`  VARCHAR(255)  NOT NULL,             -- المنطقة
  `percent`    DECIMAL(7,3)  NOT NULL DEFAULT 0,   -- النسبة الإضافية (مثل 10 أو 15)
  `apply_discount` TINYINT(1) NOT NULL DEFAULT 1,  -- تطبيق نسبة الخصم في المنطقة؟ 0 = النسبة الإضافية فقط
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4ب) نصوص الترويسة والتذييل (مفتاح/قيمة)
--     تُحرَّر من صفحة «الترويسة والتذييل». الجدول يُنشأ تلقائياً من الكود
--     أيضاً، فالتحديث من نسخة أقدم لا يحتاج تشغيل install.php.
--     المفتاح الموجود هنا يفوز على الافتراضي حتى لو كانت قيمته فارغة.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `print_settings` (
  `setting_key`   VARCHAR(60) NOT NULL,
  `setting_value` TEXT        NOT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5) الإدارات (تابعة للعقد)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `departments` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dept_code`   VARCHAR(100)  DEFAULT NULL,          -- رمز الإدارة
  `dept_name`   VARCHAR(255)  NOT NULL,              -- اسم الإدارة
  `contract_id` INT UNSIGNED  DEFAULT NULL,          -- العقد التابع له
  `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dept_contract` (`contract_id`),
  CONSTRAINT `fk_dept_contract` FOREIGN KEY (`contract_id`)
      REFERENCES `contracts`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6) مواقع العمل (تابعة للإدارة)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `worksites` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_code`   VARCHAR(100)  DEFAULT NULL,          -- رمز موقع العمل
  `site_name`   VARCHAR(255)  NOT NULL,              -- مكان / موقع العمل
  `area`        VARCHAR(255)  DEFAULT NULL,          -- المنطقة (تُطبع في الترويسة والغلاف)
  `extra_area_id` INT UNSIGNED DEFAULT NULL,         -- منطقة النسبة الإضافية (extra_areas) إن وُجدت
  `dept_id`    INT UNSIGNED  NOT NULL,              -- الإدارة التابع لها
  `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_site_dept` (`dept_id`),
  CONSTRAINT `fk_site_dept` FOREIGN KEY (`dept_id`)
      REFERENCES `departments`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7) المسميات الوظيفية للوزارة (تابعة للعقد)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ministry_job_titles` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title_name`  VARCHAR(255)  NOT NULL,              -- المسمى الوظيفي
  `contract_id` INT UNSIGNED  DEFAULT NULL,          -- العقد التابع له
  `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mjt_contract` (`contract_id`),
  CONSTRAINT `fk_mjt_contract` FOREIGN KEY (`contract_id`)
      REFERENCES `contracts`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8) موظفو الوزارة (تابعون للمسمى الوظيفي للوزارة)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ministry_employees` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `emp_name`      VARCHAR(255)  NOT NULL,            -- اسم الموظف
  `job_number`    VARCHAR(100)  DEFAULT NULL,        -- الرقم الوظيفي
  `civil_id`      VARCHAR(100)  DEFAULT NULL,        -- الرقم المدني
  `phone`         VARCHAR(50)   DEFAULT NULL,        -- رقم التلفون
  `job_title_id`  INT UNSIGNED  DEFAULT NULL,        -- المسمى الوظيفي (للوزارة)
  `entity`        VARCHAR(255)  DEFAULT NULL,        -- الجهة التابع لها
  `contract_id`   INT UNSIGNED  DEFAULT NULL,        -- العقد
  `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_me_title` (`job_title_id`),
  KEY `idx_me_contract` (`contract_id`),
  CONSTRAINT `fk_me_title` FOREIGN KEY (`job_title_id`)
      REFERENCES `ministry_job_titles`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_me_contract` FOREIGN KEY (`contract_id`)
      REFERENCES `contracts`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9) المسميات الوظيفية للمقاول (بدون عقد)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contractor_job_titles` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title_name` VARCHAR(255)  NOT NULL,               -- المسمى الوظيفي
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 10) موظفو المقاول (تابعون للمسمى الوظيفي للمقاول + صور)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contractor_employees` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `emp_name`      VARCHAR(255)  NOT NULL,            -- اسم الموظف
  `job_number`    VARCHAR(100)  DEFAULT NULL,        -- الرقم الوظيفي
  `civil_id`      VARCHAR(100)  DEFAULT NULL,        -- الرقم المدني
  `phone`         VARCHAR(50)   DEFAULT NULL,        -- رقم التلفون
  `job_title_id`  INT UNSIGNED  DEFAULT NULL,        -- المسمى الوظيفي (للمقاول)
  `img_civil`     VARCHAR(255)  DEFAULT NULL,        -- صورة المدنية
  `img_passport`  VARCHAR(255)  DEFAULT NULL,        -- صورة الجواز
  `img_experience` VARCHAR(255) DEFAULT NULL,        -- صورة الخبرة
  `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ce_title` (`job_title_id`),
  CONSTRAINT `fk_ce_title` FOREIGN KEY (`job_title_id`)
      REFERENCES `contractor_job_titles`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 11) جداول الكميات (البنود والأسعار) - تُرفع من Excel
--     رقم البند نصي لأنه قد يحتوي حروفاً عربية (مثل أ/1/106)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `boq_items` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `item_no`        VARCHAR(100)  NOT NULL,           -- رقم البند (نصي)
  `item_details`   TEXT          NOT NULL,           -- تفاصيل / نص البند
  `unit`           VARCHAR(50)   DEFAULT NULL,        -- الوحدة
  `price_before`   DECIMAL(18,3) NOT NULL DEFAULT 0, -- السعر قبل الإضافة/الخصم
  `price_after`    DECIMAL(18,3) NOT NULL DEFAULT 0, -- السعر بعد الإضافة/الخصم
  `main_no`        VARCHAR(50)   DEFAULT NULL,       -- رقم البند الرئيسي (مثل 1)
  `main_name`      VARCHAR(255)  DEFAULT NULL,       -- اسم البند الرئيسي (مثل أعمال تكييف الهواء)
  `item_key`       VARCHAR(120)  DEFAULT NULL,       -- مفتاح البحث: رقم البند بشكل موحّد (أ-8/1 ← 8/1-ا)
  `created_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_boq_itemno` (`item_no`),
  KEY `idx_boq_itemkey` (`item_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 12) جدول الترقيم التسلسلي مع دعم الفجوات/الاسترداد
--     لكل قسم تسلسله الخاص. عند الحذف يبقى الرقم محجوزاً كفجوة
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `number_sequences` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `seq_key`   VARCHAR(50)  NOT NULL,                 -- اسم القسم: work_survey / work_order ...
  `last_no`   INT UNSIGNED NOT NULL DEFAULT 0,       -- آخر رقم تم استخدامه
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_seqkey` (`seq_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- فجوات الأرقام المحذوفة (لزر استرداد المحذوف)
CREATE TABLE IF NOT EXISTS `number_gaps` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `seq_key`   VARCHAR(50)  NOT NULL,                 -- اسم القسم
  `gap_no`    INT UNSIGNED NOT NULL,                 -- الرقم المحذوف (الفجوة)
  `created_at` TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_seq_gap` (`seq_key`,`gap_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 13) حصر العمل (رأس المستند) - work_survey
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `work_surveys` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `survey_no`        INT UNSIGNED  NOT NULL,         -- الرقم التسلسلي (ثابت، قد يحوي فجوات)
  `dept_id`          INT UNSIGNED  DEFAULT NULL,     -- اسم الإدارة
  `site_id`          INT UNSIGNED  DEFAULT NULL,     -- موقع العمل
  `work_type`        VARCHAR(255)  DEFAULT NULL,     -- نوع العمل
  `work_type_code`   VARCHAR(10)   DEFAULT NULL,     -- رمز نوع العمل (نسخة) يُكتب مع الرقم: 54/ت
  `work_desc`        VARCHAR(255)  DEFAULT NULL,     -- بيان الأعمال
  `supervisor1_id`   INT UNSIGNED  DEFAULT NULL,     -- اسم المشرف الأول (موظف وزارة)
  `supervisor2_id`   INT UNSIGNED  DEFAULT NULL,     -- اسم المشرف الثاني
  `start_date`       DATE          DEFAULT NULL,     -- تاريخ بدء العمل
  `exec_days`        INT           DEFAULT 0,        -- مدة التنفيذ بالأيام
  `end_date`         DATE          DEFAULT NULL,     -- تاريخ انتهاء العمل
  `warranty_months`  VARCHAR(50)   DEFAULT NULL,     -- فترة الضمان
  `delay_days`       INT           DEFAULT 0,        -- مدة التأخير بالأيام
  `rep_contractor_id` INT UNSIGNED DEFAULT NULL,     -- ممثل المتعهد (موظف مقاول)
  `head_eng_id`      INT UNSIGNED  DEFAULT NULL,     -- رئيس قسم الشؤون الهندسية
  `delay_fine`       DECIMAL(18,3) DEFAULT 0,        -- غرامة تأخير
  `notes`            TEXT          DEFAULT NULL,     -- ملاحظات
  `discount_percent` DECIMAL(7,3)  NOT NULL DEFAULT 0, -- نسخة من نسبة الخصم وقت الإنشاء (snapshot)
  `extra_percent`    DECIMAL(7,3)  NOT NULL DEFAULT 0, -- نسخة من النسبة الإضافية لمنطقة الموقع وقت الإنشاء
  `extra_area`       VARCHAR(255)  DEFAULT NULL,     -- اسم منطقة النسبة الإضافية
  `converted_to_order` TINYINT(1)  NOT NULL DEFAULT 0, -- هل حُوّل لأمر عمل؟ (مرة واحدة)
  `source_prelim_id` INT UNSIGNED  DEFAULT NULL,     -- مصدر التحويل من المبدئي (مرجعي)
  `scan_file`        VARCHAR(255)  DEFAULT NULL,     -- نسخة ضوئية مرفقة
  `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_survey_no` (`survey_no`),
  KEY `idx_ws_dept` (`dept_id`),
  KEY `idx_ws_site` (`site_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- بنود حصر العمل (التفاصيل) - snapshot من boq وقت الإضافة
CREATE TABLE IF NOT EXISTS `work_survey_items` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `survey_id`    INT UNSIGNED  NOT NULL,
  `item_no`      VARCHAR(100)  NOT NULL,             -- رقم البند
  `item_details` TEXT          NOT NULL,             -- وصف البند
  `unit`         VARCHAR(50)   DEFAULT NULL,         -- الوحدة
  `qty`          DECIMAL(18,3) NOT NULL DEFAULT 0,   -- الكمية
  `unit_price`   DECIMAL(18,3) NOT NULL DEFAULT 0,   -- سعر الوحدة (قبل الخصم)
  `total_before` DECIMAL(18,3) NOT NULL DEFAULT 0,   -- المبلغ الإجمالي = سعر الوحدة × الكمية
  `main_no`      VARCHAR(50)   DEFAULT NULL,         -- رقم البند الرئيسي (من جدول الكميات)
  `main_name`    VARCHAR(255)  DEFAULT NULL,         -- اسم البند الرئيسي
  PRIMARY KEY (`id`),
  KEY `idx_wsi_survey` (`survey_id`),
  CONSTRAINT `fk_wsi_survey` FOREIGN KEY (`survey_id`)
      REFERENCES `work_surveys`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 14) أمر العمل (رأس المستند) - work_order
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `work_orders` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_no`         INT UNSIGNED  NOT NULL,         -- الرقم التسلسلي (ثابت، قد يحوي فجوات)
  `source_survey_id` INT UNSIGNED  DEFAULT NULL,     -- مصدر التحويل (إن وُجد) - مرجعي فقط
  `dept_id`          INT UNSIGNED  DEFAULT NULL,
  `site_id`          INT UNSIGNED  DEFAULT NULL,
  `work_type`        VARCHAR(255)  DEFAULT NULL,
  `work_type_code`   VARCHAR(10)   DEFAULT NULL,     -- رمز نوع العمل (نسخة) يُكتب مع الرقم: 54/ت
  `work_desc`        VARCHAR(255)  DEFAULT NULL,     -- بيان الأعمال
  `supervisor1_id`   INT UNSIGNED  DEFAULT NULL,
  `supervisor2_id`   INT UNSIGNED  DEFAULT NULL,
  `start_date`       DATE          DEFAULT NULL,
  `exec_days`        INT           DEFAULT 0,
  `end_date`         DATE          DEFAULT NULL,
  `warranty_months`  VARCHAR(50)   DEFAULT NULL,
  `delay_days`       INT           DEFAULT 0,
  `rep_contractor_id` INT UNSIGNED DEFAULT NULL,
  `head_eng_id`      INT UNSIGNED  DEFAULT NULL,
  `delay_fine`       DECIMAL(18,3) DEFAULT 0,
  `notes`            TEXT          DEFAULT NULL,
  `discount_percent` DECIMAL(7,3)  NOT NULL DEFAULT 0, -- snapshot لنسبة الخصم
  `extra_percent`    DECIMAL(7,3)  NOT NULL DEFAULT 0, -- نسخة من النسبة الإضافية لمنطقة الموقع وقت الإنشاء
  `extra_area`       VARCHAR(255)  DEFAULT NULL,     -- اسم منطقة النسبة الإضافية
  `scan_file`        VARCHAR(255)  DEFAULT NULL,
  `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_order_no` (`order_no`),
  KEY `idx_wo_dept` (`dept_id`),
  KEY `idx_wo_site` (`site_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- بنود أمر العمل (snapshot)
CREATE TABLE IF NOT EXISTS `work_order_items` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`     INT UNSIGNED  NOT NULL,
  `item_no`      VARCHAR(100)  NOT NULL,
  `item_details` TEXT          NOT NULL,
  `unit`         VARCHAR(50)   DEFAULT NULL,
  `qty`          DECIMAL(18,3) NOT NULL DEFAULT 0,
  `unit_price`   DECIMAL(18,3) NOT NULL DEFAULT 0,
  `total_before` DECIMAL(18,3) NOT NULL DEFAULT 0,
  `main_no`      VARCHAR(50)   DEFAULT NULL,         -- رقم البند الرئيسي (من جدول الكميات)
  `main_name`    VARCHAR(255)  DEFAULT NULL,         -- اسم البند الرئيسي
  PRIMARY KEY (`id`),
  KEY `idx_woi_order` (`order_id`),
  CONSTRAINT `fk_woi_order` FOREIGN KEY (`order_id`)
      REFERENCES `work_orders`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;

-- ---------------------------------------------------------------------
--  حصر العمل المبدئي (رأس + بنود)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `prelim_surveys` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `prelim_no`        INT UNSIGNED  NOT NULL,
  `dept_id`          INT UNSIGNED  DEFAULT NULL,
  `site_id`          INT UNSIGNED  DEFAULT NULL,
  `work_type`        VARCHAR(255)  DEFAULT NULL,
  `work_type_code`   VARCHAR(10)   DEFAULT NULL,     -- رمز نوع العمل (نسخة) يُكتب مع الرقم: 54/ت
  `work_desc`        VARCHAR(255)  DEFAULT NULL,     -- بيان الأعمال
  `supervisor1_id`   INT UNSIGNED  DEFAULT NULL,
  `supervisor2_id`   INT UNSIGNED  DEFAULT NULL,
  `start_date`       DATE          DEFAULT NULL,
  `exec_days`        INT           DEFAULT 0,
  `end_date`         DATE          DEFAULT NULL,
  `warranty_months`  VARCHAR(50)   DEFAULT NULL,
  `delay_days`       INT           DEFAULT 0,
  `rep_contractor_id` INT UNSIGNED DEFAULT NULL,
  `head_eng_id`      INT UNSIGNED  DEFAULT NULL,
  `delay_fine`       DECIMAL(18,3) DEFAULT 0,
  `notes`            TEXT          DEFAULT NULL,
  `discount_percent` DECIMAL(7,3)  NOT NULL DEFAULT 0,
  `extra_percent`    DECIMAL(7,3)  NOT NULL DEFAULT 0, -- نسخة من النسبة الإضافية لمنطقة الموقع وقت الإنشاء
  `extra_area`       VARCHAR(255)  DEFAULT NULL,     -- اسم منطقة النسبة الإضافية
  `converted_to_survey` TINYINT(1) NOT NULL DEFAULT 0,
  `scan_file`        VARCHAR(255)  DEFAULT NULL,
  `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_prelim_no` (`prelim_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `prelim_survey_items` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `prelim_id`    INT UNSIGNED  NOT NULL,
  `item_no`      VARCHAR(255)  NOT NULL,
  `item_details` TEXT          NOT NULL,
  `unit`         VARCHAR(50)   DEFAULT NULL,
  `qty`          DECIMAL(18,3) NOT NULL DEFAULT 0,
  `unit_price`   DECIMAL(18,3) NOT NULL DEFAULT 0,
  `total_before` DECIMAL(18,3) NOT NULL DEFAULT 0,
  `main_no`      VARCHAR(50)   DEFAULT NULL,         -- رقم البند الرئيسي (من جدول الكميات)
  `main_name`    VARCHAR(255)  DEFAULT NULL,         -- اسم البند الرئيسي
  PRIMARY KEY (`id`),
  KEY `idx_psi_prelim` (`prelim_id`),
  CONSTRAINT `fk_psi_prelim` FOREIGN KEY (`prelim_id`)
      REFERENCES `prelim_surveys`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  بيانات أولية
-- =====================================================================

-- المستخدم يُنشأ عبر صفحة install.php (تولّد الهاش على خادمك مباشرة)
-- الافتراضي بعد التثبيت:  admin / admin123

-- تهيئة تسلسلات الترقيم
-- (INSERT IGNORE و WHERE NOT EXISTS: تنفيذ الملف مرتين — استيراد يدوي ثم install.php — لا يكرّر ولا يفشل)
INSERT IGNORE INTO `number_sequences` (`seq_key`, `last_no`) VALUES
('prelim_survey', 0),
('work_survey', 0),
('work_order', 0);

-- سجل افتراضي فارغ لإعدادات الوزارة/المقاول/العقد (تُحرّر لاحقاً من الواجهة)
INSERT INTO `ministry_settings` (`ministry_name`)
  SELECT '' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ministry_settings`);
INSERT INTO `contractor_settings` (`contractor_name`, `discount_percent`)
  SELECT '', 0 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `contractor_settings`);

-- جلسات الدخول (بدل ملفات الجلسات — تبقى بعد إعادة تشغيل الخادم)
CREATE TABLE IF NOT EXISTS `php_sessions` (
  `id`         VARCHAR(128) NOT NULL,
  `data`       MEDIUMBLOB   NOT NULL,
  `updated_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_updated` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

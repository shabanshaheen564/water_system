# نظام إدارة المياه ونظم المعلومات الجغرافية

نظام مؤسسي لإدارة بيانات **بلدية دير البلح — دائرة المياه والصرف الصحي، قسم نظم المعلومات الجغرافية**. يوفّر النظام إدارة مركزية لمجموعات البيانات والحقول والسجلات والمعالم المكانية، مع خريطة GIS وصلاحيات مؤسسية.

## التقنيات

- Laravel 12
- PHP 8.2+
- PostgreSQL + PostGIS
- Laravel Sanctum
- Spatie Laravel Permission
- Blade + Tailwind CSS v4 + Vite
- Leaflet 1.9.4
- IBM Plex Sans Arabic محلياً عبر npm

## الوظائف الحالية

- المصادقة وتسجيل الدخول وتسجيل الخروج
- الأدوار والصلاحيات
- إدارة المستخدمين
- إدارة مجموعات البيانات والحقول والسجلات
- إدارة المعالم المكانية
- استيراد البيانات
- العلاقات بين مجموعات البيانات
- لوحة تحكم إحصائية
- خريطة GIS تفاعلية باستخدام Leaflet
- واجهة عربية RTL موجهة للاستخدام المؤسسي

## المتطلبات

- PHP 8.2 أو أحدث
- Composer
- Node.js و npm
- PostgreSQL 17 أو إصدار متوافق
- PostGIS

## التشغيل المحلي

```bash
git clone https://github.com/shabanshaheen564/water_system.git
cd water_system
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
npm run build
php artisan serve
```

بعد ذلك افتح عنوان الخادم المحلي الذي يعرضه Laravel.

## إعداد قاعدة البيانات

اضبط قيم PostgreSQL في ملف `.env`، بما في ذلك:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=water_gis
DB_USERNAME=postgres
DB_PASSWORD=
```

تأكد من تفعيل PostGIS في قاعدة البيانات قبل تشغيل البيانات المكانية.

## حساب مالك النظام

يقرأ Seeder كلمة مرور مالك النظام من:

```env
SYSTEM_OWNER_PASSWORD=
```

لا تضع كلمة مرور حقيقية داخل ملفات Git أو الكود المصدري.

## البناء والاختبار

```bash
npm run build
php artisan test
```

## ملاحظات التطوير

- لا يتم تغيير أسماء الأدوار والصلاحيات المخزنة في قاعدة البيانات عند ترجمة الواجهة.
- القيم التقنية مثل أسماء الحقول والمعرفات والإحداثيات وSRID والبريد الإلكتروني تُعرض باتجاه LTR عند الحاجة.
- قاعدة البيانات والمخططات والهندسة المكانية يجب أن تبقى متوافقة مع PostgreSQL/PostGIS.

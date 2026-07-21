# ملف تسليم للمبرمج (Shared Hosting)

## اسم النظام

**Bassir AI Recruitment System**  
Powered by Bassir Technology

## نسخة الرفع المعتمدة

استخدم فقط ملف ZIP المخصص للتسليم النهائي للمبرمج (موجود بجانب المشروع في المجلد الرئيسي).

## خطوات الرفع (cPanel / Plesk / DirectAdmin)

1. ارفع مجلد المشروع خارج `public_html` (مثال: `/home/account/bassir`).
2. اجعل Document Root يشير إلى مجلد `public` داخل المشروع.
3. إذا تعذر تغيير Document Root:
   - انقل محتويات `public/` إلى `public_html/`
   - وعدل مسارات `index.php` لتشير إلى المجلد الخاص بالتطبيق.

## أوامر التنفيذ بعد الرفع

الحزمة النهائية تحتوي على `composer.lock` و`vendor` لتقليل مشاكل shared hosting. إذا كان `vendor/autoload.php` موجودا بعد فك الضغط، لا تحتاج لتشغيل Composer إلا إذا أردت إعادة بناء الاعتمادات.

عند الحاجة لإعادة تثبيت الاعتمادات:

```bash
composer install --no-dev --optimize-autoloader
```

إذا طلب Composer امتداد `gd` بسبب `phpoffice/phpword`، فعّل `gd` من لوحة الاستضافة. كحل مؤقت فقط:

```bash
composer install --no-dev --optimize-autoloader --ignore-platform-req=ext-gd
```

ثم نفذ:

```bash
cp .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan bassir:create-owner --username=yahya --email=owner@example.com --name="Bassir Owner" --company="Bassir Technology"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php scripts/preflight.php
```

لتشغيل الاختبارات على staging، ثبت dev dependencies مؤقتا:

```bash
composer install --optimize-autoloader
php artisan test
composer install --no-dev --optimize-autoloader
```

## بيانات البيئة المهمة (.env)

- إعدادات قاعدة البيانات (DB_HOST / DB_DATABASE / DB_USERNAME / DB_PASSWORD)
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://your-domain.com`

## مفاتيح التكامل (من لوحة Integrations أو .env)

- OpenAI
- Google Custom Search API Key + CSE ID
- Bing Search API Key
- SerpAPI Key (اختياري لكنه موصى به)
- Agency Feed URL/Token (عند وجود تكامل)
- OCR key للـ CV الممسوح (اختياري)

## تفعيل التكاملات والتحقق (البند ٢)

```bash
./scripts/apply-integrations.sh
php scripts/integration-check.php
```

اختبار اتصال فعلي (اختياري):

```bash
RUN_REMOTE_INTEGRATION_TESTS=true php scripts/integration-check.php
```

## العمليات اليومية (Backup/Monitoring) (البند ٣)

```bash
./scripts/ops-backup.sh
./scripts/ops-health-monitor.sh
INSTALL_CRON=true ./scripts/ops-install-cron.sh
```

## اختبار Smoke النهائي (البند ٤)

```bash
SMOKE_USERNAME=yahya \
SMOKE_PASSWORD='YourRealPassword' \
RUN_INTEGRATION_CHECK=true \
./scripts/smoke-test-suite.sh
```

## فحوصات قبول سريعة بعد الرفع

1. تسجيل الدخول يعمل.
2. Dashboard يعمل.
3. رفع CV وإنشاء مرشح يعمل.
4. AI Search يعمل ويظهر النتائج.
5. Import نتيجة من AI Search إلى المرشحين يعمل.
6. Manual LinkedIn import يعمل (بدون scraping).
7. إنشاء Job وتشغيل AI matching يعمل.
8. صفحة AI Matching (`/matching`) تعرض المقارنات وأسئلة المقابلة.
9. صفحة Pipeline (`/applications`) تعمل.
10. إنشاء application بين Candidate وJob يعمل.
11. تحديث مرحلة Pipeline يحفظ history وaudit log.
12. صفحة AI Ranking لكل وظيفة تعمل.
13. صفحة Candidate Job Matches تعمل.
14. Search Assistant يعمل بطلبات طبيعية.
15. Talent Pools تعمل حفظ/حذف مرشح.
16. Candidate Comparison تعمل لعدد 2-5 مرشحين.
17. الداشبورد يعرض Time-to-Hire KPIs.
18. التبديل بين EN/AR يعمل ويغير اتجاه الواجهة RTL/LTR.
19. Export التقارير CSV يعمل.

## ملاحظات التوافق والالتزام

- ممنوع أي Scraping غير نظامي لمنصات محمية.
- LinkedIn في النظام **manual import only**.
- يجب حفظ حالة موافقة المرشح قبل التواصل معه.

## اعتماد HR/UAT قبل Go Live (البند ٥)

- راجع تقرير فحص ما قبل الإرسال: `docs/PRE_RELEASE_SECURITY_QA_2026_06_06_AR.md`
- تعبئة واعتماد الملف: `docs/HR_UAT_SIGNOFF_AR.md`
- إرفاق سجلات التنفيذ:
  - `storage/logs/target-cutover-*.log`
  - `storage/logs/smoke-test-suite-*.log`
  - مخرجات `php scripts/integration-check.php`
  - `storage/logs/ops-backup-*.log`

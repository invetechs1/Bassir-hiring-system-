# سياق مراجعة ChatGPT للنظام

## اسم النظام

**Bassir AI Recruitment System**  
Powered by Bassir Technology

## الهدف من الحزمة

هذه الحزمة مخصصة لرفعها إلى ChatGPT أو أي مراجع تقني لفهم بنية النظام، مراجعة التصميم، اقتراح تحسينات، وكتابة ملاحظات تطوير يمكن إرسالها لاحقًا إلى Codex لتنفيذ الكود.

## التقنية المستخدمة

- Backend + Frontend: Laravel 12 / PHP
- UI: Blade Templates + CSS داخلي
- Database: MySQL / MariaDB
- Auth: Laravel Session Auth
- Mobile API: Bearer Token API تحت `/api/mobile/*`
- Deployment Target: Shared Hosting / cPanel / Plesk / DirectAdmin
- AI/Search Integrations:
  - OpenAI API
  - Google Custom Search API
  - Bing Search API
  - SerpAPI
  - Agency feeds when legally allowed
  - Manual LinkedIn URL import only

## مبدأ الامتثال

النظام لا يحتوي على scraping غير نظامي، ولا يتجاوز حماية Google أو LinkedIn. مصادر البحث المسموحة فقط:

- APIs رسمية
- Google Custom Search API
- Bing Search API
- SerpAPI
- Job board / agency feeds عند وجود إذن
- رفع CV يدويًا
- CSV/Excel import
- LinkedIn manual import فقط بدون scraping

## أهم الملفات

- `composer.json`: اعتماد Laravel 12 والحزم الأساسية.
- `.env.example`: قالب إعدادات البيئة.
- `routes/web.php`: صفحات الويب ولوحة الإدارة.
- `routes/api.php`: واجهات API للموبايل.
- `app/Http/Controllers`: Controllers الخاصة بالموديولات.
- `app/Services`: منطق AI, CV parsing, scoring, salary, search providers.
- `app/Models`: نماذج قاعدة البيانات.
- `database/migrations`: بنية الجداول.
- `database/seeders/DatabaseSeeder.php`: بيانات البداية وحساب المالك.
- `resources/views`: واجهات Blade.
- `public/.htaccess`: إعداد Apache rewrite.
- `scripts/preflight.php`: فحص الاستضافة قبل التشغيل.
- `scripts/target-cutover.sh`: تشغيل نهائي على السيرفر.
- `scripts/smoke-test-suite.sh`: اختبارات smoke.
- `scripts/apply-integrations.sh`: تفعيل مفاتيح التكامل.
- `scripts/integration-check.php`: فحص التكاملات.
- `docs/FINAL_QA_CHECKLIST.md`: قائمة فحص قبل الإطلاق.
- `docs/TARGET_SERVER_CUTOVER.md`: خطوات cutover.
- `docs/PRODUCTION_OPERATIONS_RUNBOOK.md`: تشغيل backups/monitoring/integrations.

## الموديولات الحالية

- Login / Logout
- Dashboard
- AI Search and CV Sourcing
- Candidate CRM
- CV Upload and Parsing
- Candidate Import CSV/XLS/XLSX
- Jobs / Job Requisitions
- AI Matching
- Specializations
- Interviews
- Salary Benchmarks
- Integrations
- Reports
- User Management
- Audit Logs
- Settings
- Mobile API
- SaaS tenant/company foundation
- Recruitment Pipeline / Candidate Applications
- AI Candidate Ranking
- Candidate-to-Job Matching
- Search Assistant
- Talent Pools
- Candidate Comparison
- Candidate Quality Score
- Health endpoint
- Backup and monitoring scripts

## آخر إصلاحات مهمة تمت

- ترقية `composer.json` من Laravel 11 إلى Laravel 12 بسبب حظر Composer لإصدارات Laravel 11 المتأثرة بتحذيرات أمنية.
- ضبط صلاحية `public/.htaccess` إلى `644`.
- إضافة `GET /login` لتجنب خطأ 405 عند فتح صفحة login مباشرة.
- إعادة ترتيب routes الخاصة بـ:
  - `jobs/create` قبل `jobs/{job}`
  - `candidates/create` قبل `candidates/{candidate}`
- استبدال Closure routes في `routes/api.php` بـ Controllers حقيقية لتجهيز `route:cache`.
- إضافة صفحات أخطاء branded لـ `403`, `404`, `405`.
- توسيع smoke tests لتشمل login web و-links الأساسية بعد تسجيل الدخول.
- إضافة `scripts/qa-server-suite.sh` لتشغيل QA على السيرفر.
- تحسين preflight لفحص `vendor/autoload.php`, صلاحية `.htaccess`, وإعدادات proxy HTTPS.
- تحسين إظهار أزرار/روابط الواجهة بناءً على permissions بدل roles حيث أمكن.
- إضافة company/tenant foundation وعزل البيانات حسب الشركة في أهم الجداول.
- إضافة Pipeline module للربط بين المرشح والوظيفة وتحديث مراحل التوظيف.
- إضافة PHPUnit scaffolding واختبارات أساسية.
- تقييد Integrations وSystem Settings على `SUPER_ADMIN`.
- إضافة ميزات تسريع اختيار المرشحين وتقليل وقت البحث:
  - ranking لكل job
  - job matches لكل candidate
  - red flags
  - interview questions
  - talent pools
  - comparison
  - recruiter AI feedback

## المطلوب من ChatGPT عند المراجعة

يرجى مراجعة النظام من هذه الزوايا:

1. Architecture Review:
   - هل بنية Laravel مناسبة لنظام SaaS HR Tech؟
   - هل توزيع Controllers/Services/Models واضح؟
   - ما التحسينات المقترحة للتوسع لاحقًا؟

2. UI/UX Review:
   - مراجعة تصميم لوحة الإدارة.
   - اقتراح تحسينات للـ dashboard, AI Search, Candidate CRM, Jobs.
   - اقتراح تصميم enterprise-grade أكثر احترافية.

3. Backend Review:
   - مراجعة الـ routes.
   - مراجعة validation/security.
   - مراجعة database schema.
   - اقتراح تحسينات للـ AI scoring/search/parsing.

4. Security Review:
   - مراجعة auth/RBAC.
   - مراجعة upload security.
   - مراجعة secrets/API keys.
   - مراجعة shared hosting risks.

5. Production Readiness:
   - ما النواقص قبل Go Live؟
   - ما الاختبارات الضرورية؟
   - ما التشغيل المطلوب على shared hosting؟

6. Code Tasks For Codex:
   - رجاءً اكتب قائمة مهام واضحة بصيغة:
     - Priority
     - File/module
     - Problem
     - Suggested implementation

## ملاحظات تشغيل مهمة

بعد رفع النظام على السيرفر:

```bash
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php scripts/preflight.php
```

ثم يجب ضبط:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com/rec/public
APP_FORCE_HTTPS=true
SESSION_SECURE_COOKIE=true
```

## ملاحظة مهمة للمراجع

الحزمة لا تحتوي على مجلد `vendor` ولا ملف `.env` الحقيقي. هذا مقصود. يتم إنشاء `vendor` عبر Composer على السيرفر، ويتم إنشاء `.env` من `.env.example`.

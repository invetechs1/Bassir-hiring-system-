# تسليم ميزات تسريع اختيار المرشحين بالذكاء الاصطناعي

التاريخ: 2026-06-06

هذا الملف يوضح آخر إضافات مرحلة AI Candidate Selection & Talent Search Acceleration داخل نظام Bassir AI Recruitment System.

## الميزات المضافة

- محرك ترتيب المرشحين لكل وظيفة مع تقسيم النتائج إلى 80%+ و60-79% ونتائج ضعيفة.
- مطابقة الوظيفة مع المرشحين الموجودين تلقائيا عند إنشاء الوظيفة.
- مطابقة المرشح مع الوظائف المناسبة بعد رفع أو تحديث السيرة الذاتية.
- تحسين CV Parser لاستخراج بيانات منظمة إضافية مثل الشركة الحالية، المدينة، المجال، سنوات الخبرة، فترة الإشعار، والشركات السابقة.
- صفحة AI Candidate Ranking للوظيفة مع فلاتر، تحميل CV، قرار المجند، ملاحظات، shortlist، رفض، وجدولة مقابلة.
- مساعد بحث HR بلغة طبيعية للبحث داخل قاعدة المواهب.
- مؤشرات Time-to-Hire في لوحة التحكم.
- Talent Pool module لتجميع المرشحين حسب الفئة.
- Candidate Quality Score طويل المدى.
- توليد أسئلة مقابلة حسب الوظيفة والمرشح والمخاطر.
- Red Flag Detection مع عرض تحذيري فقط بدون رفض تلقائي.
- Recruiter Feedback Loop على توصيات AI.
- Candidate Comparison Tool لمقارنة 2 إلى 5 مرشحين.

## أهم الملفات الجديدة

- `database/migrations/2026_06_06_000009_add_ai_selection_acceleration_features.php`
- `app/Services/AiCandidateRankingService.php`
- `app/Services/CandidateQualityService.php`
- `app/Services/TalentSearchAssistantService.php`
- `app/Http/Controllers/JobRankingController.php`
- `app/Http/Controllers/CandidateJobMatchController.php`
- `app/Http/Controllers/SearchAssistantController.php`
- `app/Http/Controllers/TalentPoolController.php`
- `app/Http/Controllers/CandidateComparisonController.php`
- `app/Models/TalentPool.php`
- `app/Models/AiRecommendationFeedback.php`
- `resources/views/rankings/job.blade.php`
- `resources/views/candidate-matches/show.blade.php`
- `resources/views/search-assistant/index.blade.php`
- `resources/views/talent-pools/index.blade.php`
- `resources/views/comparisons/candidates.blade.php`
- `tests/Unit/CandidateQualityServiceTest.php`
- `composer.lock`

## أهم الملفات المحدثة

- `app/Services/CandidateScoringService.php`
- `app/Services/CvParserService.php`
- `app/Services/AiInsightsService.php`
- `app/Http/Controllers/JobController.php`
- `app/Http/Controllers/CandidateController.php`
- `app/Http/Controllers/CvUploadController.php`
- `app/Http/Controllers/CandidateImportController.php`
- `app/Http/Controllers/DashboardController.php`
- `app/Http/Controllers/InterviewController.php`
- `app/Models/Candidate.php`
- `app/Models/CandidateScore.php`
- `app/Models/CandidateApplication.php`
- `app/Models/Job.php`
- `app/Models/Company.php`
- `routes/web.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/dashboard/index.blade.php`
- `resources/views/jobs/index.blade.php`
- `resources/views/jobs/show.blade.php`
- `resources/views/candidates/index.blade.php`
- `resources/views/candidates/show.blade.php`
- `resources/views/candidates/create.blade.php`
- `resources/views/interviews/create.blade.php`
- `database/seeders/DatabaseSeeder.php`
- `README.md`
- `docs/API_REFERENCE.md`
- `docs/FINAL_QA_CHECKLIST.md`
- `docs/PROGRAMMER_UPLOAD_HANDOFF_AR.md`
- `docs/CHATGPT_REVIEW_CONTEXT_AR.md`
- `QA_RESULTS.md`
- `CHANGELOG_FIXES.md`

## فحوصات تم تنفيذها محليا

- تم توليد `composer.lock`.
- تم تجهيز `vendor` كنسخة production بدون dev packages داخل الحزمة النهائية.
- تم فحص `composer.json` كـ JSON صالح.
- تم فحص `phpunit.xml` باستخدام `xmllint`.
- تم فحص سكربتات `scripts/*.sh` باستخدام `bash -n`.
- تم تشغيل `php artisan route:list --except-vendor` بنجاح داخل Docker.
- تم تشغيل `php artisan migrate:fresh --seed` بنجاح على SQLite مؤقتة داخل Docker.
- تم تشغيل `php artisan test` بنجاح: 9 tests passed, 36 assertions.
- تم التأكد من عدم وجود `.env` أو `node_modules` أو ملفات log داخل مجلد المشروع قبل التغليف.

## فحوصات يجب تنفيذها على جهاز المبرمج أو السيرفر

الحزمة النهائية تحتوي على `composer.lock` و`vendor`. إذا رفع المبرمج الحزمة كما هي، يمكنه الانتقال مباشرة إلى إعداد `.env` وتشغيل أوامر Laravel.

إذا أراد المبرمج إعادة تثبيت الاعتمادات على السيرفر، يشغل:

```bash
composer install --no-dev --optimize-autoloader
```

إذا ظهرت رسالة عن امتداد `gd` بسبب `phpoffice/phpword`، الأفضل تفعيل امتداد `gd` من لوحة الاستضافة. كحل مؤقت فقط:

```bash
composer install --no-dev --optimize-autoloader --ignore-platform-req=ext-gd
```

بعدها:

```bash
php artisan key:generate
php artisan migrate:fresh --seed
php artisan optimize:clear
php scripts/preflight.php
```

لتشغيل الاختبارات على staging، يجب تثبيت dev dependencies أولا:

```bash
composer install --optimize-autoloader
php artisan test
composer install --no-dev --optimize-autoloader
```

وفي production بعد ضبط `.env`:

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## حدود مهمة

- لا توجد أي آلية scraping غير قانونية. البحث الخارجي يجب أن يتم عبر APIs قانونية فقط مثل Google Custom Search API أو SerpAPI أو Bing API أو LinkedIn official/manual import.
- مساعد البحث الطبيعي الحالي يعمل كبحث ذكي داخلي deterministic داخل قاعدة المرشحين. يمكن ترقيته لاحقا لاستخدام OpenAI لاستخراج فلاتر أكثر تقدما.
- جودة مخرجات AI تعتمد على اكتمال بيانات المرشح والوظيفة وعلى إعداد مفاتيح AI في `.env`.
- يجب عدم استخدام حسابات demo في production إلا بعد تغيير كلمة المرور أو تعطيلها.

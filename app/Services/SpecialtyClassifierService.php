<?php

namespace App\Services;

use Illuminate\Support\Str;

class SpecialtyClassifierService
{
    /**
     * Ordered list of specialties. Each entry:
     *   slug: URL/folder slug
     *   name: English label
     *   name_ar: Arabic label
     *   keywords: array of case-insensitive words / phrases (EN + AR) that vote for this specialty.
     *
     * These names must match the platform's actual Specialization taxonomy (see
     * DatabaseSeeder / the Specializations admin page) exactly — CV Bank groups candidates by
     * their `specialization` field, so a classifier name that doesn't exist anywhere else in
     * the app means every candidate classified into it silently lands in "Unclassified" on the
     * CV Bank page instead. Order matters only as a tie-breaker (first defined wins on equal
     * scores). Add or reorder specialties freely; the classifier picks the highest keyword-hit
     * score.
     */
    private const SPECIALTIES = [
        [
            'slug' => 'civil-engineers',
            'name' => 'Civil Engineers',
            'name_ar' => 'مهندسون مدنيون',
            'keywords' => [
                'civil engineer', 'civil engineering', 'concrete design', 'reinforced concrete',
                'مهندس مدني', 'هندسة مدنية',
                'ETABS', 'SAP2000', 'STAAD',
            ],
        ],
        [
            'slug' => 'structural-engineers',
            'name' => 'Structural Engineers',
            'name_ar' => 'مهندسون إنشائيون',
            'keywords' => [
                'structural engineer', 'structural engineering', 'structural design', 'structural analysis',
                'مهندس إنشائي', 'هندسة إنشائية',
                'ETABS', 'SAP2000', 'STAAD Pro', 'SAFE',
            ],
        ],
        [
            'slug' => 'architects',
            'name' => 'Architects',
            'name_ar' => 'مهندسون معماريون',
            'keywords' => [
                'architect', 'architecture', 'architectural design', 'urban planner',
                'مهندس معماري', 'عمارة',
                'Revit', 'SketchUp', 'Rhino', 'Lumion', '3ds Max',
            ],
        ],
        [
            'slug' => 'electrical-engineers',
            'name' => 'Electrical Engineers',
            'name_ar' => 'مهندسون كهربائيون',
            'keywords' => [
                'electrical engineer', 'electrical engineering', 'power systems',
                'high voltage', 'low voltage', 'substation',
                'مهندس كهرباء', 'هندسة كهربائية', 'مهندس كهربائي',
                'ETAP', 'DIgSILENT',
            ],
        ],
        [
            'slug' => 'mechanical-engineers',
            'name' => 'Mechanical Engineers',
            'name_ar' => 'مهندسون ميكانيكيون',
            'keywords' => [
                'mechanical engineer', 'mechanical engineering', 'thermodynamics',
                'مهندس ميكانيكي', 'هندسة ميكانيكية', 'ميكانيكا',
                'SolidWorks', 'AutoCAD Mechanical', 'CATIA',
            ],
        ],
        [
            'slug' => 'mep-engineers',
            'name' => 'MEP Engineers',
            'name_ar' => 'مهندسو الكهروميكانيك',
            'keywords' => [
                'MEP engineer', 'MEP coordinator', 'HVAC engineer', 'plumbing engineer', 'piping engineer',
                'مهندس كهروميكانيك', 'تكييف',
                'Hap', 'Revit MEP',
            ],
        ],
        [
            'slug' => 'interior-designers',
            'name' => 'Interior Designers',
            'name_ar' => 'مصممون داخليون',
            'keywords' => [
                'interior designer', 'interior design', 'interior architect',
                'مصمم داخلي', 'تصميم داخلي',
                '3ds Max', 'Lumion', 'SketchUp',
            ],
        ],
        [
            'slug' => 'quantity-surveyors',
            'name' => 'Quantity Surveyors',
            'name_ar' => 'مساحو الكميات',
            'keywords' => [
                'quantity surveyor', 'QS engineer', 'cost estimator', 'cost engineer', 'BOQ',
                'bill of quantities', 'contracts management',
                'مسّاح كميات', 'مهندس تكاليف',
                'CostX', 'Candy',
            ],
        ],
        [
            'slug' => 'planning-engineers',
            'name' => 'Planning Engineers',
            'name_ar' => 'مهندسو التخطيط',
            'keywords' => [
                'planning engineer', 'project planner', 'scheduling engineer', 'project controls',
                'مهندس تخطيط', 'مخطط مشاريع',
                'Primavera P6', 'Primavera', 'MS Project',
            ],
        ],
        [
            'slug' => 'project-managers',
            'name' => 'Project Managers',
            'name_ar' => 'مديرو المشاريع',
            'keywords' => [
                'project manager', 'programme manager', 'construction manager', 'PMO',
                'مدير مشروع', 'إدارة مشاريع',
                'PMP', 'PRINCE2',
            ],
        ],
        [
            'slug' => 'site-engineers',
            'name' => 'Site Engineers',
            'name_ar' => 'مهندسو الموقع',
            'keywords' => [
                'site engineer', 'site supervisor', 'field engineer', 'construction supervisor',
                'مهندس موقع', 'مشرف موقع',
            ],
        ],
        [
            'slug' => 'hse-engineers',
            'name' => 'HSE Engineers',
            'name_ar' => 'مهندسو السلامة والصحة والبيئة',
            'keywords' => [
                'HSE engineer', 'HSE officer', 'health safety environment', 'safety engineer',
                'safety officer', 'NEBOSH', 'OSHA',
                'مهندس سلامة', 'أخصائي السلامة',
            ],
        ],
        [
            'slug' => 'qaqc-engineers',
            'name' => 'QA/QC Engineers',
            'name_ar' => 'مهندسو ضمان وجودة الجودة',
            'keywords' => [
                'QA/QC engineer', 'quality engineer', 'quality assurance', 'quality control',
                'inspection engineer', 'ISO 9001',
                'مهندس جودة', 'ضبط الجودة',
            ],
        ],
        [
            'slug' => 'bim-engineers',
            'name' => 'BIM Engineers',
            'name_ar' => 'مهندسو نمذجة معلومات البناء',
            'keywords' => [
                'BIM engineer', 'BIM coordinator', 'BIM manager', 'BIM modeler', 'clash detection',
                'مهندس BIM', 'نمذجة معلومات البناء',
                'Revit', 'Navisworks', 'BIM 360',
            ],
        ],
        [
            'slug' => 'infrastructure-engineers',
            'name' => 'Infrastructure Engineers',
            'name_ar' => 'مهندسو البنية التحتية',
            'keywords' => [
                'infrastructure engineer', 'utilities engineer', 'infrastructure design',
                'مهندس بنية تحتية',
            ],
        ],
        [
            'slug' => 'roads-engineers',
            'name' => 'Roads Engineers',
            'name_ar' => 'مهندسو الطرق',
            'keywords' => [
                'roads engineer', 'road engineer', 'highway engineer', 'highway design', 'pavement design',
                'مهندس طرق', 'هندسة طرق',
            ],
        ],
        [
            'slug' => 'geotechnical-engineers',
            'name' => 'Geotechnical Engineers',
            'name_ar' => 'مهندسو الجيوتقنية',
            'keywords' => [
                'geotechnical engineer', 'geotechnical engineering', 'geotechnical', 'soil investigation',
                'foundation engineering', 'مهندس جيوتقني', 'هندسة جيوتقنية',
            ],
        ],
        [
            'slug' => 'facade-engineers',
            'name' => 'Facade Engineers',
            'name_ar' => 'مهندسو الواجهات',
            'keywords' => [
                'facade engineer', 'facade design', 'curtain wall engineer', 'cladding engineer',
                'مهندس واجهات',
            ],
        ],
        [
            'slug' => 'fire-fighting-engineers',
            'name' => 'Fire Fighting Engineers',
            'name_ar' => 'مهندسو مكافحة الحريق',
            'keywords' => [
                'fire fighting engineer', 'fire protection engineer', 'fire alarm engineer',
                'fire suppression', 'NFPA',
                'مهندس مكافحة حريق', 'مكافحة الحرائق',
            ],
        ],
        [
            'slug' => 'low-current-engineers',
            'name' => 'Low Current Engineers',
            'name_ar' => 'مهندسو التيار المنخفض',
            'keywords' => [
                'low current engineer', 'ELV engineer', 'extra low voltage', 'CCTV engineer',
                'access control engineer', 'BMS engineer',
                'مهندس تيار منخفض',
            ],
        ],
        [
            'slug' => 'software-developers',
            'name' => 'Software Developers',
            'name_ar' => 'مطورو البرمجيات',
            'keywords' => [
                'software developer', 'software engineer', 'full stack', 'backend developer',
                'frontend developer', 'web developer', 'mobile developer',
                'مطور برمجيات', 'مطور ويب',
                'React', 'Vue', 'Angular', 'Laravel', 'Django', 'Node.js', 'Python', 'Java',
                'PHP', 'Kotlin', 'Swift', 'Flutter', 'REST API', 'GraphQL',
            ],
        ],
        [
            'slug' => 'ui-ux-designers',
            'name' => 'UI/UX Designers',
            'name_ar' => 'مصممو واجهات وتجربة المستخدم',
            'keywords' => [
                'UI designer', 'UX designer', 'UI/UX', 'product designer', 'interaction designer',
                'user research', 'wireframing',
                'مصمم واجهات', 'تجربة المستخدم',
                'Figma', 'Adobe XD', 'Sketch',
            ],
        ],
        [
            'slug' => 'data-analysts',
            'name' => 'Data Analysts',
            'name_ar' => 'محللو البيانات',
            'keywords' => [
                'data analyst', 'business intelligence', 'BI analyst', 'data visualization',
                'reporting analyst',
                'محلل بيانات',
                'Power BI', 'Tableau', 'SQL', 'Excel', 'Python pandas',
            ],
        ],
        [
            'slug' => 'accountants',
            'name' => 'Accountants',
            'name_ar' => 'محاسبون',
            'keywords' => [
                'accountant', 'accounting', 'financial analyst', 'auditor', 'audit',
                'bookkeeper', 'controller', 'finance manager',
                'محاسب', 'محاسبة', 'مدقق', 'مالية',
                'IFRS', 'GAAP', 'SAP FICO', 'QuickBooks', 'Xero', 'ZATCA',
            ],
        ],
        [
            'slug' => 'procurement-specialists',
            'name' => 'Procurement Specialists',
            'name_ar' => 'أخصائيو المشتريات',
            'keywords' => [
                'procurement specialist', 'procurement officer', 'purchasing manager',
                'buyer', 'sourcing specialist', 'vendor management',
                'مشتريات', 'أخصائي مشتريات',
            ],
        ],
        [
            'slug' => 'logistics-fleet-specialists',
            'name' => 'Logistics/Fleet Specialists',
            'name_ar' => 'أخصائيو اللوجستيات والأسطول',
            'keywords' => [
                'logistics specialist', 'logistics coordinator', 'fleet manager', 'fleet coordinator',
                'warehouse manager', 'supply chain', 'dispatch coordinator',
                'لوجستيات', 'إدارة الأسطول', 'سلاسل الإمداد',
            ],
        ],
        [
            'slug' => 'hr-specialists',
            'name' => 'HR Specialists',
            'name_ar' => 'أخصائيو الموارد البشرية',
            'keywords' => [
                'HR specialist', 'human resources', 'HR generalist', 'HR business partner',
                'recruiter', 'talent acquisition', 'people operations', 'payroll specialist',
                'موارد بشرية', 'مسؤول توظيف', 'أخصائي موارد بشرية',
            ],
        ],
    ];

    public const UNCLASSIFIED_SLUG = 'unclassified';

    public const UNCLASSIFIED_NAME = 'Unclassified';

    /**
     * Classify a CV/parsed profile into a specialty.
     *
     * @param  array{title?:?string, skills?:array<string>, experience?:array<string>, raw_text?:?string, summary?:?string}  $parsed
     * @return array{slug:string, name:string, name_ar:string, confidence:int}
     */
    public function classify(array $parsed): array
    {
        $haystack = mb_strtolower($this->normalize(implode(' ', [
            (string) ($parsed['title'] ?? ''),
            (string) ($parsed['current_job_title'] ?? ''),
            (string) ($parsed['summary'] ?? ''),
            (string) ($parsed['raw_text'] ?? ''),
            implode(' ', (array) ($parsed['skills'] ?? [])),
            implode(' ', (array) ($parsed['experience'] ?? [])),
        ])));

        if ($haystack === '') {
            return $this->unclassified();
        }

        $best = null;
        $bestScore = 0;
        foreach (self::SPECIALTIES as $spec) {
            $score = 0;
            foreach ($spec['keywords'] as $keyword) {
                $needle = mb_strtolower($this->normalize($keyword));
                if ($needle === '') {
                    continue;
                }
                $score += mb_substr_count($haystack, $needle);
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $spec;
            }
        }

        if ($best === null) {
            return $this->unclassified();
        }

        return [
            'slug' => $best['slug'],
            'name' => $best['name'],
            'name_ar' => $best['name_ar'],
            'confidence' => $bestScore,
        ];
    }

    /**
     * @return array<int, array{slug:string, name:string, name_ar:string}>
     */
    public function all(): array
    {
        $list = array_map(
            fn (array $spec) => ['slug' => $spec['slug'], 'name' => $spec['name'], 'name_ar' => $spec['name_ar']],
            self::SPECIALTIES
        );
        $list[] = ['slug' => self::UNCLASSIFIED_SLUG, 'name' => self::UNCLASSIFIED_NAME, 'name_ar' => 'غير مصنف'];

        return $list;
    }

    public function findBySlug(string $slug): ?array
    {
        foreach ($this->all() as $spec) {
            if ($spec['slug'] === $slug) {
                return $spec;
            }
        }

        return null;
    }

    public function slugOf(string $specialtyName): string
    {
        $needle = mb_strtolower(trim($specialtyName));
        foreach (self::SPECIALTIES as $spec) {
            if (mb_strtolower($spec['name']) === $needle || mb_strtolower($spec['name_ar']) === $needle) {
                return $spec['slug'];
            }
        }

        return Str::slug($specialtyName) ?: self::UNCLASSIFIED_SLUG;
    }

    private function unclassified(): array
    {
        return [
            'slug' => self::UNCLASSIFIED_SLUG,
            'name' => self::UNCLASSIFIED_NAME,
            'name_ar' => 'غير مصنف',
            'confidence' => 0,
        ];
    }

    private function normalize(string $text): string
    {
        // Collapse whitespace and strip control chars for cleaner substring matching.
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}

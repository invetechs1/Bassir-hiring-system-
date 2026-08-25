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
     * Order matters only as a tie-breaker (first defined wins on equal scores).
     * Add or reorder specialties freely; the classifier picks the highest keyword-hit score.
     */
    private const SPECIALTIES = [
        [
            'slug' => 'civil-engineer',
            'name' => 'Civil Engineer',
            'name_ar' => 'مهندس مدني',
            'keywords' => [
                'civil engineer', 'civil engineering', 'structural engineer', 'site engineer',
                'road engineer', 'highway engineer', 'geotechnical', 'reinforced concrete',
                'concrete design', 'quantity surveyor', 'construction manager',
                'مهندس مدني', 'هندسة مدنية', 'مهندس إنشائي', 'مهندس موقع', 'مسّاح كميات',
                'ETABS', 'SAP2000', 'STAAD', 'Primavera P6',
            ],
        ],
        [
            'slug' => 'mechanical-engineer',
            'name' => 'Mechanical Engineer',
            'name_ar' => 'مهندس ميكانيكي',
            'keywords' => [
                'mechanical engineer', 'mechanical engineering', 'HVAC engineer', 'MEP engineer',
                'plumbing engineer', 'piping engineer', 'thermodynamics', 'refrigeration',
                'مهندس ميكانيكي', 'هندسة ميكانيكية', 'تكييف', 'ميكانيكا',
                'SolidWorks', 'AutoCAD Mechanical', 'CATIA',
            ],
        ],
        [
            'slug' => 'electrical-engineer',
            'name' => 'Electrical Engineer',
            'name_ar' => 'مهندس كهربائي',
            'keywords' => [
                'electrical engineer', 'electrical engineering', 'power systems',
                'high voltage', 'low voltage', 'substation', 'PLC', 'SCADA',
                'مهندس كهرباء', 'هندسة كهربائية', 'مهندس كهربائي',
                'ETAP', 'DIgSILENT',
            ],
        ],
        [
            'slug' => 'architect',
            'name' => 'Architect',
            'name_ar' => 'مهندس معماري',
            'keywords' => [
                'architect', 'architecture', 'architectural design', 'urban planner',
                'interior designer', 'BIM Manager', 'مهندس معماري', 'عمارة', 'تصميم داخلي',
                'Revit', 'SketchUp', 'Rhino', 'Lumion', '3ds Max',
            ],
        ],
        [
            'slug' => 'software-engineer',
            'name' => 'Software Engineer',
            'name_ar' => 'مهندس برمجيات',
            'keywords' => [
                'software engineer', 'software developer', 'full stack', 'backend developer',
                'frontend developer', 'web developer', 'mobile developer',
                'مهندس برمجيات', 'مطور برمجيات', 'مطور ويب',
                'React', 'Vue', 'Angular', 'Laravel', 'Django', 'Node.js', 'Python', 'Java',
                'PHP', 'Kotlin', 'Swift', 'Flutter', 'REST API', 'GraphQL',
            ],
        ],
        [
            'slug' => 'data-scientist',
            'name' => 'Data Scientist',
            'name_ar' => 'عالم بيانات',
            'keywords' => [
                'data scientist', 'data science', 'machine learning', 'deep learning',
                'data engineer', 'AI engineer', 'data analyst',
                'عالم بيانات', 'محلل بيانات', 'ذكاء اصطناعي', 'تعلم آلي',
                'TensorFlow', 'PyTorch', 'scikit-learn', 'Pandas', 'NumPy',
                'Power BI', 'Tableau', 'Spark',
            ],
        ],
        [
            'slug' => 'devops-engineer',
            'name' => 'DevOps / Cloud Engineer',
            'name_ar' => 'مهندس ديفوبس/سحابة',
            'keywords' => [
                'devops', 'DevOps engineer', 'SRE', 'site reliability', 'cloud engineer',
                'platform engineer', 'infrastructure engineer',
                'مهندس ديفوبس', 'مهندس سحابة',
                'Kubernetes', 'Docker', 'AWS', 'Azure', 'GCP', 'Terraform', 'Ansible', 'Jenkins',
            ],
        ],
        [
            'slug' => 'network-engineer',
            'name' => 'Network / Security Engineer',
            'name_ar' => 'مهندس شبكات/أمن',
            'keywords' => [
                'network engineer', 'network administrator', 'security engineer',
                'cybersecurity', 'information security', 'SOC analyst',
                'مهندس شبكات', 'أمن سيبراني', 'أمن المعلومات',
                'CCNA', 'CCNP', 'CISSP', 'firewall', 'Palo Alto', 'Fortinet',
            ],
        ],
        [
            'slug' => 'accountant',
            'name' => 'Accountant / Finance',
            'name_ar' => 'محاسب/مالية',
            'keywords' => [
                'accountant', 'accounting', 'finance manager', 'financial analyst',
                'auditor', 'audit', 'bookkeeper', 'CFO', 'controller',
                'محاسب', 'محاسبة', 'مدقق', 'مالية', 'محلل مالي',
                'IFRS', 'GAAP', 'SAP FICO', 'QuickBooks', 'Xero',
            ],
        ],
        [
            'slug' => 'hr',
            'name' => 'Human Resources',
            'name_ar' => 'موارد بشرية',
            'keywords' => [
                'human resources', 'HR manager', 'HR generalist', 'HR business partner',
                'recruiter', 'talent acquisition', 'people operations', 'payroll specialist',
                'موارد بشرية', 'مسؤول توظيف', 'أخصائي موارد بشرية',
            ],
        ],
        [
            'slug' => 'sales',
            'name' => 'Sales / Business Development',
            'name_ar' => 'مبيعات/تطوير أعمال',
            'keywords' => [
                'sales manager', 'sales executive', 'account manager', 'account executive',
                'business development', 'BDM', 'key account',
                'مدير مبيعات', 'مندوب مبيعات', 'تطوير أعمال',
            ],
        ],
        [
            'slug' => 'marketing',
            'name' => 'Marketing',
            'name_ar' => 'تسويق',
            'keywords' => [
                'marketing manager', 'digital marketing', 'SEO specialist', 'content marketer',
                'brand manager', 'social media',
                'تسويق', 'تسويق رقمي', 'مدير تسويق', 'وسائل التواصل الاجتماعي',
            ],
        ],
        [
            'slug' => 'operations',
            'name' => 'Operations / Supply Chain',
            'name_ar' => 'عمليات/سلاسل إمداد',
            'keywords' => [
                'operations manager', 'supply chain', 'logistics', 'procurement',
                'warehouse manager', 'inventory',
                'عمليات', 'سلاسل الإمداد', 'مشتريات', 'لوجستيات', 'مخازن',
            ],
        ],
        [
            'slug' => 'healthcare',
            'name' => 'Healthcare / Medical',
            'name_ar' => 'الرعاية الصحية',
            'keywords' => [
                'nurse', 'physician', 'doctor', 'pharmacist', 'medical officer',
                'radiographer', 'physiotherapist', 'dentist',
                'ممرض', 'ممرضة', 'طبيب', 'صيدلي', 'أخصائي علاج طبيعي',
            ],
        ],
        [
            'slug' => 'education',
            'name' => 'Education / Training',
            'name_ar' => 'تعليم/تدريب',
            'keywords' => [
                'teacher', 'instructor', 'lecturer', 'professor', 'trainer',
                'curriculum designer', 'academic coordinator',
                'معلم', 'معلمة', 'أستاذ', 'مدرب', 'منسق أكاديمي',
            ],
        ],
        [
            'slug' => 'legal',
            'name' => 'Legal',
            'name_ar' => 'قانوني',
            'keywords' => [
                'lawyer', 'attorney', 'legal counsel', 'paralegal', 'compliance officer',
                'محامي', 'مستشار قانوني', 'الامتثال',
            ],
        ],
        [
            'slug' => 'design-creative',
            'name' => 'Design / Creative',
            'name_ar' => 'تصميم/إبداع',
            'keywords' => [
                'graphic designer', 'UI designer', 'UX designer', 'product designer',
                'motion designer', 'video editor',
                'مصمم جرافيك', 'مصمم واجهات',
                'Figma', 'Adobe Photoshop', 'Adobe Illustrator', 'InDesign',
            ],
        ],
        [
            'slug' => 'project-management',
            'name' => 'Project Management',
            'name_ar' => 'إدارة المشاريع',
            'keywords' => [
                'project manager', 'programme manager', 'PMO', 'scrum master', 'agile coach',
                'مدير مشروع', 'إدارة مشاريع',
                'PMP', 'PRINCE2',
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

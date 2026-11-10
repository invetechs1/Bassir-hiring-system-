<?php

namespace App\Services;

/**
 * Single source of truth for skills/tools/certifications the platform recognises.
 *
 * Used by the CV parser (to extract skills from CV text), the candidate search (to expand
 * "p6" into "Primavera P6"), and the web sourcing scorer. Keeping one list means a skill
 * a recruiter searches for is the same skill the parser was able to find in the CV.
 */
class SkillCatalog
{
    /** Canonical name => aliases (matched case-insensitively, whole-word). */
    private const SKILLS = [
        // Design / BIM / CAD
        'AutoCAD' => ['autocad', 'auto cad'],
        'Revit' => ['revit', 'autodesk revit'],
        'Revit MEP' => ['revit mep'],
        'Navisworks' => ['navisworks', 'navis works'],
        'BIM' => ['bim', 'building information modeling', 'building information modelling'],
        'BIM 360' => ['bim 360', 'bim360', 'autodesk construction cloud', 'acc docs'],
        'BIM Coordination' => ['bim coordination', 'bim coordinator'],
        'Clash Detection' => ['clash detection', 'clash resolution'],
        'Civil 3D' => ['civil 3d', 'civil3d'],
        'MicroStation' => ['microstation', 'micro station'],
        'ArchiCAD' => ['archicad'],
        'SketchUp' => ['sketchup', 'sketch up'],
        'Lumion' => ['lumion'],
        '3ds Max' => ['3ds max', '3dsmax', '3d max'],
        'Rhino' => ['rhino', 'rhinoceros'],
        'Dynamo' => ['dynamo'],
        'Grasshopper' => ['grasshopper'],
        'Tekla' => ['tekla'],
        'SolidWorks' => ['solidworks', 'solid works'],
        'CATIA' => ['catia'],
        'Photoshop' => ['photoshop'],
        'Illustrator' => ['illustrator'],
        'InDesign' => ['indesign'],
        // Structural / analysis
        'ETABS' => ['etabs'],
        'SAP2000' => ['sap2000', 'sap 2000'],
        'SAFE' => ['csi safe'],
        'STAAD Pro' => ['staad pro', 'staad.pro', 'staad'],
        'Robot Structural' => ['robot structural', 'autodesk robot'],
        'ETAP' => ['etap'],
        'DIALux' => ['dialux'],
        'HAP' => ['carrier hap'],
        // Planning / project controls
        'Primavera P6' => ['primavera p6', 'primavera', 'oracle primavera', 'p6'],
        'MS Project' => ['ms project', 'microsoft project', 'msp'],
        'Project Planning' => ['project planning', 'project scheduling'],
        'Earned Value' => ['earned value', 'evm'],
        'Cost Control' => ['cost control', 'cost controlling'],
        'Cost Estimation' => ['cost estimation', 'cost estimating', 'cost estimator'],
        'Quantity Surveying' => ['quantity surveying', 'quantity surveyor'],
        'BOQ' => ['boq', 'bill of quantities', 'bills of quantities'],
        'CostX' => ['costx'],
        'FIDIC' => ['fidic'],
        'Contract Management' => ['contract management', 'contracts management', 'contract administration'],
        'Claims Management' => ['claims management', 'delay analysis'],
        'Risk Management' => ['risk management', 'risk assessment'],
        // Quality / safety / certifications
        'QA/QC' => ['qa/qc', 'qaqc', 'qa-qc', 'qa & qc', 'qa and qc'],
        'Quality Assurance' => ['quality assurance'],
        'Quality Control' => ['quality control'],
        'HSE' => ['hse', 'health and safety', 'health & safety', 'ehs'],
        'NEBOSH' => ['nebosh'],
        'OSHA' => ['osha'],
        'IOSH' => ['iosh'],
        'ISO 9001' => ['iso 9001', 'iso9001'],
        'ISO 45001' => ['iso 45001', 'iso45001', 'ohsas 18001'],
        'ISO 14001' => ['iso 14001', 'iso14001'],
        'PMP' => ['pmp', 'project management professional'],
        'PRINCE2' => ['prince2', 'prince 2'],
        'PMI-SP' => ['pmi-sp'],
        'LEED' => ['leed'],
        'CCNA' => ['ccna'],
        'CISSP' => ['cissp'],
        'CPA' => ['cpa'],
        'ACCA' => ['acca'],
        'CMA' => ['cma'],
        'SHRM' => ['shrm'],
        'CIPD' => ['cipd'],
        // MEP / site disciplines
        'HVAC' => ['hvac'],
        'Plumbing' => ['plumbing'],
        'Fire Fighting' => ['fire fighting', 'firefighting', 'fire protection'],
        'NFPA' => ['nfpa'],
        'Fire Alarm' => ['fire alarm'],
        'Low Current' => ['low current', 'elv', 'extra low voltage'],
        'BMS' => ['bms', 'building management system'],
        'CCTV' => ['cctv'],
        'Piping' => ['piping'],
        'Concrete Design' => ['concrete design', 'reinforced concrete'],
        'Structural Design' => ['structural design', 'structural analysis'],
        'Geotechnical' => ['geotechnical'],
        'Surveying' => ['land surveying', 'site surveying', 'total station'],
        'Site Supervision' => ['site supervision', 'site supervisor'],
        'Value Engineering' => ['value engineering'],
        'Procurement' => ['procurement'],
        'Supply Chain' => ['supply chain'],
        'Logistics' => ['logistics'],
        'Warehouse Management' => ['warehouse management'],
        'Fleet Management' => ['fleet management'],
        // Software / IT
        'Excel' => ['excel', 'ms excel', 'microsoft excel'],
        'Power BI' => ['power bi', 'powerbi'],
        'Tableau' => ['tableau'],
        'SQL' => ['sql', 'mysql', 'postgresql', 'postgres', 'mssql', 'sql server'],
        'Python' => ['python'],
        'JavaScript' => ['javascript', 'ecmascript'],
        'TypeScript' => ['typescript'],
        'React' => ['react', 'react.js', 'reactjs'],
        'Vue' => ['vue', 'vue.js', 'vuejs'],
        'Angular' => ['angular', 'angularjs'],
        'Node.js' => ['node.js', 'nodejs', 'node js'],
        'PHP' => ['php'],
        'Laravel' => ['laravel'],
        'Java' => ['java'],
        'C#' => ['c#', 'csharp'],
        '.NET' => ['.net', 'dotnet', 'asp.net'],
        'Django' => ['django'],
        'Flutter' => ['flutter'],
        'Kotlin' => ['kotlin'],
        'Swift' => ['swift'],
        'Docker' => ['docker'],
        'Kubernetes' => ['kubernetes', 'k8s'],
        'AWS' => ['aws', 'amazon web services'],
        'Azure' => ['azure'],
        'Git' => ['git', 'github', 'gitlab'],
        'REST API' => ['rest api', 'restful', 'rest apis'],
        'GraphQL' => ['graphql'],
        'Figma' => ['figma'],
        'Adobe XD' => ['adobe xd'],
        'UI/UX' => ['ui/ux', 'ux design', 'ui design', 'user experience'],
        // Finance / HR / ERP
        'SAP' => ['sap erp', 'sap fico', 'sap s/4hana', 'sap hana', 'sap'],
        'Oracle' => ['oracle ebs', 'oracle erp', 'oracle fusion'],
        'ERPNext' => ['erpnext'],
        'Odoo' => ['odoo'],
        'QuickBooks' => ['quickbooks'],
        'Xero' => ['xero'],
        'IFRS' => ['ifrs'],
        'GAAP' => ['gaap'],
        'ZATCA' => ['zatca', 'fatoorah', 'e-invoicing'],
        'Payroll' => ['payroll'],
        'Recruitment' => ['recruitment', 'talent acquisition', 'sourcing candidates'],
        'Budgeting' => ['budgeting', 'budget preparation'],
        'Auditing' => ['auditing', 'internal audit'],
    ];

    /**
     * Aliases that are also ordinary English words or lowercase abbreviations ("react to change",
     * "swift response", "cpa" in a URL). These only count when written Capitalised or UPPERCASE.
     */
    private const CASE_SENSITIVE_ALIASES = [
        'react', 'swift', 'dynamo', 'rhino', 'grasshopper', 'azure', 'msp', 'evm', 'cma', 'cpa', 'ehs', 'elv',
    ];

    /** @var array<string, string>|null alias (lowercase) => canonical */
    private static ?array $aliasIndex = null;

    /**
     * Find every catalogued skill mentioned in the text.
     *
     * @return array<int, string> canonical skill names, in catalogue order
     */
    public function extract(string $text): array
    {
        if (trim($text) === '') {
            return [];
        }

        $found = [];
        foreach (self::SKILLS as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                if ($this->mentions($text, $alias)) {
                    $found[] = $canonical;
                    break;
                }
            }
        }

        return $found;
    }

    /** Canonical skill name for an alias ("p6" -> "Primavera P6"), or null when unknown. */
    public function canonical(string $term): ?string
    {
        return $this->aliasIndex()[mb_strtolower(trim($term))] ?? null;
    }

    /**
     * All spellings that mean the same skill as the given term, including the term itself.
     * Used to widen a search so "p6" also finds CVs that say "Primavera".
     *
     * @return array<int, string>
     */
    public function synonyms(string $term): array
    {
        $term = trim($term);
        $canonical = $this->canonical($term);
        if ($canonical === null) {
            return [$term];
        }

        // Single-letter or very short aliases ("p6", "msp", "bim") are only safe to expand when
        // the user typed them; we never expand a long phrase down into a 2-letter alias.
        $aliases = array_filter(self::SKILLS[$canonical], fn ($alias) => mb_strlen($alias) >= 3 || mb_strtolower($alias) === mb_strtolower($term));

        return array_values(array_unique(array_merge([$term, $canonical], $aliases)));
    }

    private function mentions(string $text, string $alias): bool
    {
        if (! in_array(mb_strtolower($alias), self::CASE_SENSITIVE_ALIASES, true)) {
            return $this->containsTerm($text, $alias);
        }
        $capitalised = preg_quote(mb_strtoupper(mb_substr($alias, 0, 1)).mb_substr($alias, 1), '/');
        $upper = preg_quote(mb_strtoupper($alias), '/');

        return preg_match('/(?<![\p{L}\p{N}])(?:'.$capitalised.'|'.$upper.')(?![\p{L}\p{N}])/u', $text) === 1;
    }

    /** Whole-word, unicode-safe, case-insensitive containment (so "Java" never matches "JavaScript"). */
    public function containsTerm(string $haystack, string $needle): bool
    {
        $needle = trim($needle);
        if ($needle === '') {
            return false;
        }

        return preg_match('/(?<![\p{L}\p{N}])'.preg_quote($needle, '/').'(?![\p{L}\p{N}])/iu', $haystack) === 1;
    }

    /** @return array<string, string> */
    private function aliasIndex(): array
    {
        if (self::$aliasIndex === null) {
            $index = [];
            foreach (self::SKILLS as $canonical => $aliases) {
                $index[mb_strtolower($canonical)] = $canonical;
                foreach ($aliases as $alias) {
                    // First definition wins, so a short ambiguous alias never steals a longer skill's name.
                    $index[mb_strtolower($alias)] ??= $canonical;
                }
            }
            self::$aliasIndex = $index;
        }

        return self::$aliasIndex;
    }
}

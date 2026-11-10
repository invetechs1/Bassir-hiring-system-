<?php

namespace App\Services;

use App\Support\PersonName;
use Illuminate\Http\UploadedFile;
use Smalot\PdfParser\Parser;
use Throwable;
use ZipArchive;

class CvParserService
{
    private const CITIES = [
        'Riyadh', 'Jeddah', 'Dammam', 'Khobar', 'Al Khobar', 'Mecca', 'Makkah', 'Medina', 'Madinah', 'Tabuk', 'Abha',
        'Jubail', 'Yanbu', 'Taif', 'Dhahran', 'Hail', 'Jazan', 'Najran', 'Buraidah', 'Khamis Mushait', 'Neom',
        'Dubai', 'Abu Dhabi', 'Sharjah', 'Doha', 'Kuwait City', 'Manama', 'Muscat', 'Amman', 'Cairo', 'Alexandria',
        'Karachi', 'Lahore', 'Islamabad', 'Mumbai', 'Delhi', 'Manila',
    ];

    private const COUNTRIES = [
        'Saudi Arabia' => ['saudi arabia', 'ksa', 'kingdom of saudi arabia'],
        'United Arab Emirates' => ['united arab emirates', 'uae'],
        'Qatar' => ['qatar'],
        'Kuwait' => ['kuwait'],
        'Bahrain' => ['bahrain'],
        'Oman' => ['oman'],
        'Jordan' => ['jordan'],
        'Egypt' => ['egypt'],
        'Pakistan' => ['pakistan'],
        'India' => ['india'],
        'Philippines' => ['philippines'],
        'Lebanon' => ['lebanon'],
        'Sudan' => ['sudan'],
    ];

    private const CITY_COUNTRY = [
        'Riyadh' => 'Saudi Arabia', 'Jeddah' => 'Saudi Arabia', 'Dammam' => 'Saudi Arabia', 'Khobar' => 'Saudi Arabia',
        'Al Khobar' => 'Saudi Arabia', 'Mecca' => 'Saudi Arabia', 'Makkah' => 'Saudi Arabia', 'Medina' => 'Saudi Arabia',
        'Madinah' => 'Saudi Arabia', 'Tabuk' => 'Saudi Arabia', 'Abha' => 'Saudi Arabia', 'Jubail' => 'Saudi Arabia',
        'Yanbu' => 'Saudi Arabia', 'Taif' => 'Saudi Arabia', 'Dhahran' => 'Saudi Arabia', 'Hail' => 'Saudi Arabia',
        'Jazan' => 'Saudi Arabia', 'Najran' => 'Saudi Arabia', 'Buraidah' => 'Saudi Arabia', 'Khamis Mushait' => 'Saudi Arabia',
        'Neom' => 'Saudi Arabia', 'Dubai' => 'United Arab Emirates', 'Abu Dhabi' => 'United Arab Emirates',
        'Sharjah' => 'United Arab Emirates', 'Doha' => 'Qatar', 'Kuwait City' => 'Kuwait', 'Manama' => 'Bahrain',
        'Muscat' => 'Oman', 'Amman' => 'Jordan', 'Cairo' => 'Egypt', 'Alexandria' => 'Egypt', 'Karachi' => 'Pakistan',
        'Lahore' => 'Pakistan', 'Islamabad' => 'Pakistan', 'Mumbai' => 'India', 'Delhi' => 'India', 'Manila' => 'Philippines',
    ];

    private const ARABIC_CITIES = [
        'الرياض' => 'Riyadh', 'جدة' => 'Jeddah', 'الدمام' => 'Dammam', 'الخبر' => 'Khobar', 'مكة' => 'Mecca',
        'المدينة المنورة' => 'Medina', 'الجبيل' => 'Jubail', 'ينبع' => 'Yanbu', 'الطائف' => 'Taif', 'أبها' => 'Abha',
        'تبوك' => 'Tabuk', 'دبي' => 'Dubai', 'أبوظبي' => 'Abu Dhabi', 'الدوحة' => 'Doha', 'القاهرة' => 'Cairo',
    ];

    private const NATIONALITIES = [
        'Saudi', 'Egyptian', 'Indian', 'Pakistani', 'Jordanian', 'Sudanese', 'Lebanese', 'Filipino', 'Syrian',
        'Yemeni', 'Palestinian', 'Bangladeshi', 'Sri Lankan', 'Nepalese', 'Iraqi', 'Tunisian', 'Moroccan', 'Emirati',
        'Kuwaiti', 'Qatari', 'Bahraini', 'Omani', 'British', 'American', 'Turkish',
    ];

    private const LANGUAGES = [
        'Arabic' => ['arabic', 'العربية'],
        'English' => ['english', 'الإنجليزية', 'الانجليزية'],
        'French' => ['french'],
        'Urdu' => ['urdu'],
        'Hindi' => ['hindi'],
        'Tagalog' => ['tagalog', 'filipino'],
        'Bengali' => ['bengali', 'bangla'],
        'Tamil' => ['tamil'],
        'Malayalam' => ['malayalam'],
        'Turkish' => ['turkish'],
        'German' => ['german'],
        'Spanish' => ['spanish'],
        'Chinese' => ['chinese', 'mandarin'],
    ];

    private const TITLE_WORDS = 'engineer|developer|manager|architect|designer|analyst|specialist|accountant|supervisor|'
        .'coordinator|officer|surveyor|planner|technician|consultant|director|estimator|draftsman|drafter|administrator|'
        .'foreman|inspector|controller|buyer|programmer|recruiter|lead|modeler|modeller|scheduler|auditor|'
        .'مهندس|مدير|محاسب|مصمم|مشرف';

    public function __construct(
        private readonly OcrService $ocr,
        private readonly SkillCatalog $skills
    ) {
    }

    public function parse(UploadedFile|string $file): array
    {
        $text = $this->sanitizeUtf8($this->extractText($file));
        $lines = collect(preg_split('/\R/u', $text) ?: [])
            ->map(fn ($line) => trim(preg_replace('/[ \t]+/u', ' ', $line) ?? $line))
            ->filter()
            ->values();
        $lineList = $lines->all();

        preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $email);
        $email = $email[0] ?? null;

        $experienceEntries = $this->experienceEntries($lineList);
        $companies = $this->companiesFrom($lineList, $experienceEntries);
        $city = $this->cityFrom($text);
        $country = $this->countryFrom($text, $city);

        $titleLines = $lines->filter(fn ($line) => $this->isTitleLine($line))->values();
        $skills = $this->skills->extract($text);

        return [
            'name' => $this->guessName($lineList, $email),
            'email' => $email,
            'phone' => $this->phoneFrom($text),
            'location' => trim(implode(', ', array_filter([$city, $country]))) ?: null,
            'country' => $country,
            'city' => $city,
            'nationality' => $this->nationalityFrom($text),
            'current_job_title' => $this->currentTitle($lineList),
            'years_experience' => $this->yearsOfExperience($text, $lineList),
            'current_company' => $companies['current'],
            'previous_companies' => $companies['previous'],
            'education' => $lines->filter(fn ($line) => preg_match('/\b(bachelor|master|degree|university|diploma|b\.?sc|m\.?sc|ph\.?d|mba)\b/iu', $line))->take(8)->values()->all(),
            'experience' => $titleLines->take(12)->all(),
            'skills' => $skills,
            'languages' => $this->languagesFrom($text),
            'certifications' => $lines->filter(fn ($line) => $this->isCertificationLine($line))->take(8)->values()->all(),
            'industry' => $this->industryFrom($text),
            'expected_salary' => $this->salaryFrom($text),
            'notice_period' => $this->noticePeriodFrom($text),
            'summary' => mb_substr(trim(preg_replace('/\s+/u', ' ', $text) ?? $text), 0, 900),
            'experience_entries' => $experienceEntries,
            'education_entries' => $this->educationEntries($lineList),
            'certification_entries' => $this->certificationEntries($lineList),
            'raw_text' => $text,
        ];
    }

    /**
     * Extract plain text from a CV on disk. Public so the re-index command can rebuild
     * searchable text for CVs that were stored before full-text search existed.
     */
    public function extractText(UploadedFile|string $file): string
    {
        $path = is_string($file) ? $file : (string) $file->getRealPath();
        if ($path === '' || ! is_file($path)) {
            return '';
        }
        $extension = strtolower(is_string($file) ? pathinfo($path, PATHINFO_EXTENSION) : $file->getClientOriginalExtension());

        try {
            $text = match ($this->detectKind($path, $extension)) {
                'pdf' => $this->extractPdf($path),
                'docx' => $this->extractDocx($path),
                'doc' => $this->extractLegacyDoc($path),
                'image' => '',
                default => (string) file_get_contents($path),
            };
        } catch (Throwable) {
            $text = '';
        }

        // Scanned PDFs and photographed CVs have no text layer — hand them to OCR when it's configured.
        if (mb_strlen(trim($text)) < 120 && in_array($this->detectKind($path, $extension), ['pdf', 'image'], true)) {
            $upload = $file instanceof UploadedFile ? $file : new UploadedFile($path, basename($path), null, null, true);
            $text = $this->ocr->extract($upload) ?: $text;
        }

        return $this->sanitizeUtf8($text);
    }

    /** Sniff the real format from the file header — a ".doc" that is really a .docx is common. */
    private function detectKind(string $path, string $extension): string
    {
        $head = (string) @file_get_contents($path, false, null, 0, 8);
        if (str_starts_with($head, '%PDF')) {
            return 'pdf';
        }
        if (str_starts_with($head, "PK\x03\x04")) {
            return 'docx';
        }
        if (str_starts_with($head, "\xD0\xCF\x11\xE0")) {
            return 'doc';
        }

        // A file named .pdf/.doc/.docx whose bytes are plain readable text is just text.
        if (in_array($extension, ['pdf', 'doc', 'docx'], true)) {
            $sample = (string) @file_get_contents($path, false, null, 0, 512);
            // Drop the last bytes so a multi-byte character cut at the sample boundary isn't mistaken for binary.
            $whole = strlen($sample) >= 512 ? substr($sample, 0, -3) : $sample;
            if ($whole !== '' && mb_check_encoding($whole, 'UTF-8') && ! preg_match('/[\x00-\x08\x0E-\x1F]/', $whole)) {
                return 'text';
            }
        }

        return match ($extension) {
            'pdf' => 'pdf',
            'docx' => 'docx',
            'doc' => 'doc',
            'jpg', 'jpeg', 'png' => 'image',
            default => 'text',
        };
    }

    private function extractPdf(string $path): string
    {
        return (new Parser())->parseFile($path)->getText();
    }

    /**
     * Read the .docx XML directly. PhpWord's element walk silently skips text inside runs,
     * tables, text boxes and headers — which is where most CV templates keep the candidate's
     * name, contact line and skills table — so a typical designed CV came back near-empty.
     */
    private function extractDocx(string $path): string
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }

        $headers = $body = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (! preg_match('#^word/(header\d*|footer\d*|document|footnotes|endnotes)\.xml$#', $name, $match)) {
                continue;
            }
            $xml = (string) $zip->getFromIndex($i);
            if ($xml === '') {
                continue;
            }
            if (str_starts_with($match[1], 'header')) {
                $headers[] = $xml;
            } else {
                $body[$match[1] === 'document' ? 0 : 1][] = $xml;
            }
        }
        $zip->close();

        $chunks = array_merge($headers, $body[0] ?? [], $body[1] ?? []);
        $lines = [];
        foreach ($chunks as $xml) {
            $xml = preg_replace(['#<w:tab\s*/>#', '#<w:(?:br|cr)[^>]*/>#', '#</w:p>#', '#</w:tr>#', '#</w:tc>#'], ["\t", "\n", "\n", "\n", ' '], $xml) ?? $xml;
            $plain = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
            foreach (preg_split('/\R/u', $plain) ?: [] as $line) {
                $line = trim($line);
                // Word repeats text-box content in mc:Fallback — drop consecutive duplicates.
                if ($line !== '' && $line !== end($lines)) {
                    $lines[] = $line;
                }
            }
        }

        return implode("\n", $lines);
    }

    /** Best effort for the old binary .doc format: pull readable runs out of the OLE stream. */
    private function extractLegacyDoc(string $path): string
    {
        $raw = (string) file_get_contents($path);
        $runs = [];
        if (preg_match_all('/(?:[\x20-\x7E\xA0-\xFF]\x00){6,}/', $raw, $utf16)) {
            foreach ($utf16[0] as $run) {
                $runs[] = (string) mb_convert_encoding($run, 'UTF-8', 'UTF-16LE');
            }
        }
        if (preg_match_all('/[\x20-\x7E]{8,}/', $raw, $ascii)) {
            $runs = array_merge($runs, $ascii[0]);
        }
        $noise = '/root entry|microsoft|normal\.dot|summaryinformation|msworddoc|word\.document|times new roman|arial|calibri|courier new|default paragraph|table normal|no list|bjbj/i';
        $lines = array_values(array_filter(array_map('trim', $runs), fn ($line) => $line !== '' && ! preg_match($noise, $line)));

        return implode("\n", array_values(array_unique($lines)));
    }

    /**
     * PDF/Word extraction can produce invalid UTF-8 byte sequences from a corrupted,
     * scanned, or oddly-encoded source file — MySQL's strict mode rejects those outright
     * (SQLSTATE 22007) rather than storing them, crashing whatever saves this text.
     */
    private function sanitizeUtf8(string $text): string
    {
        if ($text === '' || mb_check_encoding($text, 'UTF-8')) {
            return str_replace("\0", '', $text);
        }
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $text);

        return $clean !== false ? str_replace("\0", '', $clean) : '';
    }

    // ---------------------------------------------------------------- identity

    private function guessName(array $lines, ?string $email): ?string
    {
        $top = array_slice($lines, 0, 15);
        foreach ($top as $line) {
            if (preg_match('/^(?:full\s+)?name\s*[:\-–]\s*(.+)$/iu', $line, $match) && PersonName::looksLikeName($match[1])) {
                return PersonName::tidy($match[1]);
            }
        }
        foreach ($top as $line) {
            if (PersonName::looksLikeName($line)) {
                return PersonName::tidy($line);
            }
        }

        // Last resort: john.smith@mail.com -> "John Smith" (better than "Imported Candidate").
        if ($email !== null) {
            $local = preg_replace('/\d+/', '', explode('@', $email)[0]) ?? '';
            $parts = array_values(array_filter(preg_split('/[._\-]+/', $local) ?: [], fn ($p) => preg_match('/^\p{L}{2,}$/u', $p)));
            if (count($parts) >= 2 && count($parts) <= 4) {
                return implode(' ', array_map(fn ($p) => mb_convert_case($p, MB_CASE_TITLE), $parts));
            }
        }

        return null;
    }

    private function phoneFrom(string $text): ?string
    {
        if (! preg_match_all('/(?<![\d\w])(\+|00)?\d[\d\s().\-]{7,18}\d(?![\d\w])/u', $text, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $best = null;
        foreach ($matches[0] as [$candidate, $offset]) {
            $digits = preg_replace('/\D/', '', $candidate) ?? '';
            if (strlen($digits) < 9 || strlen($digits) > 15) {
                continue;
            }
            // "2015 - 2020", "03/2018 - 06/2021" and other date ranges are not phone numbers.
            if (preg_match('/^(19|20)\d{2}\D+(19|20)\d{2}$/', trim($candidate)) || preg_match('/(0?[1-9]|1[0-2])[\/.\-](19|20)\d{2}/', $candidate)) {
                continue;
            }
            $before = mb_strtolower(mb_substr($text, max(0, $offset - 24), min(24, $offset)));
            $labelled = preg_match('/(phone|mobile|mob|tel|cell|whatsapp|contact|جوال|هاتف|موبايل)\s*[:\-#]?\s*$/iu', $before) === 1;
            if ($labelled) {
                return trim(preg_replace('/\s+/', ' ', $candidate) ?? $candidate);
            }
            $best ??= trim(preg_replace('/\s+/', ' ', $candidate) ?? $candidate);
        }

        return $best;
    }

    private function nationalityFrom(string $text): ?string
    {
        if (preg_match('/nationality\s*[:\-–]\s*([\p{L} ]{3,25})/iu', $text, $match)) {
            return mb_convert_case(implode(' ', array_slice(preg_split('/\s+/u', trim($match[1])) ?: [], 0, 2)), MB_CASE_TITLE);
        }
        // "Saudi Aramco", "Saudi Electricity Company" are employers, not nationality.
        $notEmployer = '(?!\s+(?:arabia|aramco|electricity|telecom|basic|airlines|binladin|oger|german|hospital|ministry|national|company|co\b|group|bank|arabian|council|authority|commission|standards|railways|ports|industries|contractors))';
        foreach (self::NATIONALITIES as $nationality) {
            if (preg_match('/(?<![\p{L}])'.preg_quote($nationality, '/').'(?![\p{L}])'.$notEmployer.'/iu', $text)) {
                return $nationality;
            }
        }

        return null;
    }

    private function cityFrom(string $text): ?string
    {
        // Prefer the header of the CV (where the candidate's own address lives) over a project site mentioned later.
        $head = mb_substr($text, 0, 800);
        foreach ([$head, $text] as $scope) {
            foreach (self::ARABIC_CITIES as $arabic => $city) {
                if (mb_strpos($scope, $arabic) !== false) {
                    return $city;
                }
            }
            foreach (self::CITIES as $city) {
                if (preg_match('/(?<![\p{L}])'.preg_quote($city, '/').'(?![\p{L}])/iu', $scope)) {
                    return match ($city) {
                        'Makkah' => 'Mecca',
                        'Madinah' => 'Medina',
                        default => $city,
                    };
                }
            }
        }

        return null;
    }

    private function countryFrom(string $text, ?string $city): ?string
    {
        if (mb_strpos(mb_substr($text, 0, 1200), 'السعودية') !== false) {
            return 'Saudi Arabia';
        }
        foreach (self::COUNTRIES as $country => $aliases) {
            foreach ($aliases as $alias) {
                if (preg_match('/(?<![\p{L}])'.preg_quote($alias, '/').'(?![\p{L}])/iu', mb_substr($text, 0, 1200))) {
                    return $country;
                }
            }
        }

        return $city !== null ? (self::CITY_COUNTRY[$city] ?? null) : null;
    }

    private function languagesFrom(string $text): array
    {
        $found = [];
        foreach (self::LANGUAGES as $language => $aliases) {
            foreach ($aliases as $alias) {
                if (preg_match('/(?<![\p{L}])'.preg_quote($alias, '/').'(?![\p{L}])/iu', $text)) {
                    $found[] = $language;
                    break;
                }
            }
        }

        return $found;
    }

    // ------------------------------------------------------------- experience

    private function isTitleLine(string $line): bool
    {
        return mb_strlen($line) <= 120 && preg_match('/(?<![\p{L}])(?:'.self::TITLE_WORDS.')s?(?![\p{L}])/iu', $line) === 1;
    }

    /**
     * The headline title: a short title-like line near the top of the CV, not a sentence
     * from the middle of a job description that happens to contain the word "engineer".
     */
    private function currentTitle(array $lines): ?string
    {
        foreach (array_slice($lines, 0, 14) as $line) {
            if ($this->isTitleLine($line) && mb_strlen($line) <= 70 && str_word_count($line) <= 9 && ! preg_match('/@|\d{4}/', $line)) {
                return $line;
            }
        }
        foreach ($lines as $line) {
            if ($this->isTitleLine($line) && mb_strlen($line) <= 70 && str_word_count($line) <= 9 && ! preg_match('/@|\d{4}/', $line)) {
                return $line;
            }
        }
        foreach ($lines as $line) {
            if ($this->isTitleLine($line)) {
                return mb_substr($line, 0, 120);
            }
        }

        return null;
    }

    private const MONTHS = 'jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:t(?:ember)?)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?';

    /** @return array{start:?string,end:?string,current:bool,start_year:int,end_year:int}|null */
    private function dateRange(string $text): ?array
    {
        $month = '(?:('.self::MONTHS.')\.?,?\s+)?';
        $pattern = '/'.$month.'((?:19|20)\d{2})\s*(?:-|–|—|to|until|till)\s*(?:'
            .'(?:('.self::MONTHS.')\.?,?\s+)?((?:19|20)\d{2})|(present|current|now|ongoing|till date|to date|date))/iu';
        if (! preg_match($pattern, $text, $m)) {
            return null;
        }

        $startYear = (int) $m[2];
        $current = ! empty($m[5]);
        $endYear = $current ? (int) date('Y') : (int) $m[4];
        if ($endYear < $startYear || $startYear < 1970) {
            return null;
        }
        $monthNumber = fn (string $name): int => (int) date('n', strtotime(substr($name, 0, 3).' 1 2000') ?: 0) ?: 1;

        return [
            'start' => sprintf('%04d-%02d-01', $startYear, $m[1] !== '' ? $monthNumber($m[1]) : 1),
            'end' => $current ? null : sprintf('%04d-%02d-01', $endYear, ! empty($m[3]) ? $monthNumber($m[3]) : 12),
            'current' => $current,
            'start_year' => $startYear,
            'end_year' => $endYear,
        ];
    }

    private function yearsOfExperience(string $text, array $lines): int
    {
        $stated = [];
        $patterns = [
            '/(\d{1,2})\s*\+?\s*(?:years?|yrs?)\s*(?:of\s+)?(?:[a-z\-]+\s+){0,3}?experience/iu',
            '/experience\s*(?:of|:|-)?\s*(?:over|more than|about|around|approximately)?\s*(\d{1,2})\s*\+?\s*(?:years?|yrs?)/iu',
            '/(\d{1,2})\s*\+?\s*(?:years?|yrs?)\s*(?:\'|’)?\s*(?:in|as)\s+(?:the\s+)?[a-z]/iu',
            '/خبرة\s*(?:أكثر من|اكثر من)?\s*(\d{1,2})\s*(?:سنوات|سنة|عام)/u',
            '/(\d{1,2})\s*(?:سنوات|سنة)\s*(?:من\s*)?خبرة/u',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] as $years) {
                    if ((int) $years > 0 && (int) $years <= 45) {
                        $stated[] = (int) $years;
                    }
                }
            }
        }
        if ($stated) {
            // A CV states total experience in the summary and per-role durations below it — the largest figure is the total.
            return max($stated);
        }

        // No explicit statement: add up the non-overlapping employment date ranges (education ranges excluded).
        $intervals = [];
        $education = '/\b(bachelor|master|university|college|diploma|degree|school|b\.?sc|m\.?sc|ph\.?d|certificate|course|training)\b/iu';
        foreach (array_values($lines) as $index => $line) {
            // A date range on its own line right under a degree/university line belongs to the education entry.
            if (preg_match($education, $line) || ($index > 0 && preg_match($education, $lines[$index - 1]))) {
                continue;
            }
            if ($range = $this->dateRange($line)) {
                $intervals[] = [$range['start_year'], $range['end_year']];
            }
        }
        if ($intervals) {
            usort($intervals, fn ($a, $b) => $a[0] <=> $b[0]);
            $total = 0;
            [$start, $end] = $intervals[0];
            foreach (array_slice($intervals, 1) as [$s, $e]) {
                if ($s <= $end) {
                    $end = max($end, $e);
                } else {
                    $total += $end - $start;
                    [$start, $end] = [$s, $e];
                }
            }
            $total += $end - $start;
            if ($total > 0 && $total <= 45) {
                return $total;
            }
        }

        foreach ($lines as $line) {
            if (preg_match('/(\d{1,2})\+?\s*(?:years|year|yrs|سنوات|سنة)/iu', $line, $match) && (int) $match[1] <= 45) {
                return (int) $match[1];
            }
        }

        return 0;
    }

    /**
     * Build structured job entries. The company comes from "Title at Company" / "Title - Company"
     * on the same line or from a company-looking neighbouring line — never a placeholder that
     * would later be shown to a recruiter as if it were real data.
     */
    private function experienceEntries(array $lines): array
    {
        $items = [];
        $companyHint = '/\b(co\b|company|llc|ltd|limited|group|contracting|construction|consult\w*|engineering|holding|corporation|corp|est\b|establishment|international|enterprises|industries|aramco|trading|developers|architects|projects|solutions|technologies|systems|services|authority|ministry)\b/iu';

        foreach ($lines as $i => $line) {
            if (! $this->isTitleLine($line) || mb_strlen($line) > 110 || preg_match('/^(?:\W*)(?:responsib|dut(?:y|ies)|manag(?:ed|ing) |prepar|coordinat|review|ensur|supervis)/iu', $line)) {
                continue;
            }

            $title = $line;
            $company = null;
            if (preg_match('/^(.{3,70}?)\s+(?:at|@|\||[-–—])\s+(.{3,70})$/iu', $line, $m) && $this->isTitleLine($m[1])) {
                [$title, $company] = [trim($m[1]), trim($m[2], " \t.,-")];
            } elseif (preg_match('/^(.{3,70}?),\s+(.{3,70})$/u', $line, $m) && $this->isTitleLine($m[1]) && preg_match($companyHint, $m[2])) {
                [$title, $company] = [trim($m[1]), trim($m[2], " \t.,-")];
            }
            if ($company !== null && preg_match('/^(?:\(?\s*(?:19|20)\d{2}|present|current)/i', $company)) {
                $company = null;
            }
            $range = $this->dateRange($line);
            foreach ([$i + 1, $i + 2, $i - 1] as $neighbour) {
                if (! isset($lines[$neighbour])) {
                    continue;
                }
                $range ??= $this->dateRange($lines[$neighbour]);
                if ($company === null && mb_strlen($lines[$neighbour]) <= 80 && preg_match($companyHint, $lines[$neighbour]) && ! $this->isTitleLine($lines[$neighbour])) {
                    $company = trim($lines[$neighbour], " \t.,-");
                }
            }
            // Strip the date range from the title/company so it doesn't pollute either.
            $dateRange = '/\(?\b(?:'.self::MONTHS.')?\.?\s*(?:19|20)\d{2}\s*(?:-|–|—|to)\s*(?:(?:19|20)\d{2}|present|current|now)\b\)?/iu';
            $title = trim(preg_replace($dateRange, '', $title) ?? $title, " \t|,-–");
            if ($company !== null) {
                $company = trim(preg_replace($dateRange, '', $company) ?? $company, " \t|,-–.");
            }

            // A bare title with no employer and no dates is the CV headline or a skills line, not a job.
            if ($company === null && $range === null) {
                continue;
            }

            $items[] = [
                'title' => mb_substr($title, 0, 120),
                'company' => mb_substr($company ?: 'Not specified', 0, 190),
                'location' => null,
                'start_date' => $range['start'] ?? null,
                'end_date' => $range['end'] ?? null,
                'is_current' => (bool) ($range['current'] ?? preg_match('/\b(present|current)\b/i', $line)),
                'summary' => mb_substr($line, 0, 220),
                'sort_order' => count($items),
            ];
            if (count($items) >= 8) {
                break;
            }
        }

        return $items;
    }

    private function companiesFrom(array $lines, array $entries): array
    {
        $companies = [];
        $current = null;
        foreach ($entries as $entry) {
            if ($entry['company'] === 'Not specified') {
                continue;
            }
            $companies[] = $entry['company'];
            if ($current === null && $entry['is_current']) {
                $current = $entry['company'];
            }
        }
        foreach ($lines as $line) {
            if (preg_match('/^(?:current\s+)?(?:company|employer)\s*[:\-–]\s*([A-Za-z0-9 &.,\'-]{3,80})/iu', $line, $match)) {
                $companies[] = trim($match[1], " \t.,-");
            }
        }
        $companies = array_values(array_unique(array_filter($companies)));
        $current ??= $companies[0] ?? null;

        return [
            'current' => $current,
            'previous' => array_slice(array_values(array_filter($companies, fn ($c) => $c !== $current)), 0, 8),
        ];
    }

    // -------------------------------------------------------------- education

    private function educationEntries(array $lines): array
    {
        $items = [];
        foreach ($lines as $line) {
            if (! preg_match('/\b(bachelor|master|phd|ph\.d|diploma|university|college|degree|b\.?sc|m\.?sc|mba|b\.?eng|institute)\b/iu', $line)) {
                continue;
            }
            $institution = $this->capture('/([A-Z][\p{L}&\' .\-]{2,60}?(?:University|College|Institute|Academy)[\p{L}&\' .\-]{0,40})/u', $line) ?? $line;
            $degree = $this->capture('/(bachelor[^,.;\n]*|master[^,.;\n]*|ph\.?d[^,.;\n]*|diploma[^,.;\n]*|b\.?sc\.?[^,.;\n]*|m\.?sc\.?[^,.;\n]*|mba|b\.?eng\.?[^,.;\n]*)/iu', $line);
            $items[] = [
                'institution' => mb_substr(trim($institution, " \t.,-|"), 0, 140),
                'degree' => $degree,
                'field_of_study' => $degree !== null ? $this->capture('/\bin\s+([\p{L} &\/]{3,60})$/iu', $degree) : null,
                'level' => null,
                'start_year' => null,
                'end_year' => $this->yearFrom($line),
                'grade' => null,
            ];
            if (count($items) >= 4) {
                break;
            }
        }

        return $items;
    }

    private function isCertificationLine(string $line): bool
    {
        return mb_strlen($line) <= 160
            && preg_match('/(?<![\p{L}])(pmp|nebosh|leed|aws|ccna|cissp|prince2|iosh|osha|certificate|certified|certification|accredited)(?![\p{L}])/iu', $line) === 1;
    }

    private function certificationEntries(array $lines): array
    {
        $items = [];
        foreach ($lines as $line) {
            if (! $this->isCertificationLine($line)) {
                continue;
            }
            $items[] = [
                'name' => mb_substr($line, 0, 140),
                'issuer' => null,
                'issue_date' => null,
                'expiry_date' => null,
                'credential_id' => null,
                'url' => null,
            ];
            if (count($items) >= 6) {
                break;
            }
        }

        return $items;
    }

    private function yearFrom(string $text): ?int
    {
        if (preg_match('/\b(19|20)\d{2}\b/', $text, $match)) {
            return (int) $match[0];
        }

        return null;
    }

    private function capture(string $pattern, string $text): ?string
    {
        if (preg_match($pattern, $text, $match)) {
            return trim($match[1]);
        }

        return null;
    }

    // ------------------------------------------------------------------ other

    private function salaryFrom(string $text): ?int
    {
        if (preg_match('/(\d{1,3}(?:,\d{3})+|\d{4,6})\s?(?:sar|riyals?|usd|aed|sr)\b/i', $text, $match)
            || preg_match('/\b(?:sar|usd|aed|sr)\s?(\d{1,3}(?:,\d{3})+|\d{4,6})/i', $text, $match)) {
            return (int) str_replace(',', '', $match[1]);
        }

        return null;
    }

    private function industryFrom(string $text): ?string
    {
        $map = [
            'Construction' => ['construction', 'contracting', 'site', 'bim', 'mep', 'civil', 'structural', 'infrastructure'],
            'Technology' => ['software', 'developer', 'react', 'python', 'data', 'web', 'mobile app', 'devops'],
            'Finance' => ['accountant', 'finance', 'audit', 'accounting', 'ifrs', 'treasury'],
            'Logistics' => ['logistics', 'fleet', 'warehouse', 'supply chain', 'shipping'],
            'Oil and Gas' => ['oil', 'gas', 'aramco', 'petrochemical', 'refinery'],
            'Human Resources' => ['recruitment', 'payroll', 'human resources', 'talent acquisition'],
        ];
        $best = null;
        $bestScore = 0;
        foreach ($map as $industry => $terms) {
            $score = 0;
            foreach ($terms as $term) {
                $score += preg_match_all('/(?<![\p{L}])'.preg_quote($term, '/').'(?![\p{L}])/iu', $text);
            }
            if ($score > $bestScore) {
                [$best, $bestScore] = [$industry, $score];
            }
        }

        return $best;
    }

    private function noticePeriodFrom(string $text): ?string
    {
        if (preg_match('/notice\s*period[:\s\-]*([^\r\n]+)/i', $text, $match)) {
            return trim(mb_substr($match[1], 0, 80));
        }
        if (preg_match('/\b(immediate|available now)\b/i', $text, $match)) {
            return ucfirst(strtolower($match[1]));
        }
        if (preg_match('/\b(15|30|45|60|90)\s*days\b/i', $text, $match)) {
            return $match[1].' days';
        }

        return null;
    }
}

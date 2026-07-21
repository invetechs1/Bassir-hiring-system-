<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpWord\IOFactory;
use Smalot\PdfParser\Parser;

class CvParserService
{
    public function __construct(private readonly OcrService $ocr)
    {
    }

    public function parse(UploadedFile|string $file): array
    {
        $text = is_string($file)
            ? ((file_exists($file) ? file_get_contents($file) : '') ?: '')
            : $this->extractText($file);
        $text = (string) $text;

        preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $email);
        preg_match('/(\+?\d[\d\s().-]{7,}\d)/', $text, $phone);
        preg_match('/(saudi|egyptian|indian|pakistani|jordanian|sudanese|lebanese|filipino)/i', $text, $nationality);
        preg_match('/(\d{4,6})\s?(sar|riyal|usd|aed)/i', $text, $salary);
        preg_match('/(\d{1,2})\+?\s*(years|year|yrs|سنوات|سنة)/i', $text, $years);
        preg_match('/notice\s*period[:\s-]*([^\r\n]+)/i', $text, $notice);

        $lines = collect(preg_split('/\R/', $text))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values();

        $skillsDictionary = ['AutoCAD', 'Revit', 'Primavera P6', 'MS Project', 'ETABS', 'SAP2000', 'Excel', 'ERPNext', 'Python', 'React', 'Node.js', 'BIM 360'];
        $skills = collect($skillsDictionary)->filter(fn ($skill) => preg_match('/\b'.preg_quote($skill, '/').'\b/i', $text))->values()->all();
        $languages = collect(['Arabic', 'English', 'French', 'Urdu', 'Hindi'])->filter(fn ($language) => preg_match('/\b'.$language.'\b/i', $text))->values()->all();
        $experienceLines = $lines->filter(fn ($line) => preg_match('/engineer|developer|manager|designer|analyst|specialist|accountant|procurement|logistics/i', $line))->take(12)->values();
        $educationLines = $lines->filter(fn ($line) => preg_match('/bachelor|master|degree|university|diploma/i', $line))->take(8)->values();
        $certificationLines = $lines->filter(fn ($line) => preg_match('/pmp|nebosh|leed|aws|certified|certificate/i', $line))->take(8)->values();
        $city = $this->cityFrom($text);
        $currentTitle = $experienceLines->first();
        $companies = $this->companiesFrom($lines->all());

        return [
            'name' => $lines->first(fn ($line) => ! str_contains($line, '@') && strlen($line) <= 80),
            'email' => $email[0] ?? null,
            'phone' => isset($phone[0]) ? preg_replace('/\s+/', ' ', $phone[0]) : null,
            'location' => $lines->first(fn ($line) => preg_match('/riyadh|jeddah|khobar|dammam|ksa|saudi/i', $line)),
            'city' => $city,
            'nationality' => isset($nationality[0]) ? ucfirst(strtolower($nationality[0])) : null,
            'current_job_title' => $currentTitle,
            'years_experience' => isset($years[1]) ? (int) $years[1] : $this->yearsFromLines($lines->all()),
            'current_company' => $companies[0] ?? null,
            'previous_companies' => array_slice($companies, 1, 8),
            'education' => $educationLines->all(),
            'experience' => $experienceLines->all(),
            'skills' => $skills,
            'languages' => $languages,
            'certifications' => $certificationLines->all(),
            'industry' => $this->industryFrom($text),
            'expected_salary' => isset($salary[1]) ? (int) $salary[1] : null,
            'notice_period' => isset($notice[1]) ? trim(mb_substr($notice[1], 0, 80)) : $this->noticeFrom($text),
            'summary' => mb_substr(trim(preg_replace('/\s+/', ' ', $text)), 0, 900),
            'experience_entries' => $this->experienceEntries($lines->all()),
            'education_entries' => $this->educationEntries($lines->all()),
            'certification_entries' => $this->certificationEntries($lines->all()),
            'raw_text' => $text,
        ];
    }

    private function extractText(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $text = match ($extension) {
            'pdf' => (new Parser())->parseFile($file->getRealPath())->getText(),
            'docx', 'doc' => $this->extractWord($file->getRealPath()),
            'jpg', 'jpeg', 'png' => $this->ocr->extract($file),
            default => file_get_contents($file->getRealPath()) ?: '',
        };

        if ($extension === 'pdf' && mb_strlen(trim((string) $text)) < 120) {
            $text = $this->ocr->extract($file) ?: $text;
        }

        return (string) $text;
    }

    private function extractWord(string $path): string
    {
        $document = IOFactory::load($path);
        $text = '';
        foreach ($document->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $text .= $element->getText().' ';
                }
            }
        }
        return $text;
    }

    private function experienceEntries(array $lines): array
    {
        $items = [];
        $index = 0;
        foreach ($lines as $line) {
            if (! preg_match('/(engineer|manager|designer|developer|analyst|specialist)/i', $line)) {
                continue;
            }
            $items[] = [
                'title' => mb_substr($line, 0, 120),
                'company' => 'Imported Company',
                'location' => null,
                'start_date' => null,
                'end_date' => null,
                'is_current' => str_contains(strtolower($line), 'present'),
                'summary' => mb_substr($line, 0, 220),
                'sort_order' => $index++,
            ];
            if (count($items) >= 6) {
                break;
            }
        }
        return $items;
    }

    private function educationEntries(array $lines): array
    {
        $items = [];
        foreach ($lines as $line) {
            if (! preg_match('/(bachelor|master|phd|diploma|university|college|degree)/i', $line)) {
                continue;
            }
            $items[] = [
                'institution' => mb_substr($line, 0, 140),
                'degree' => $this->capture('/(bachelor[^,.;]*|master[^,.;]*|phd[^,.;]*|diploma[^,.;]*)/i', $line),
                'field_of_study' => null,
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

    private function certificationEntries(array $lines): array
    {
        $items = [];
        foreach ($lines as $line) {
            if (! preg_match('/(pmp|nebosh|leed|aws|ccna|certificate|certified)/i', $line)) {
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

    private function cityFrom(string $text): ?string
    {
        foreach (['Riyadh', 'Jeddah', 'Dammam', 'Khobar', 'Mecca', 'Medina', 'Tabuk', 'Abha'] as $city) {
            if (preg_match('/\b'.preg_quote($city, '/').'\b/i', $text)) {
                return $city;
            }
        }

        return null;
    }

    private function yearsFromLines(array $lines): int
    {
        foreach ($lines as $line) {
            if (preg_match('/(\d{1,2})\+?\s*(years|year|yrs|سنوات|سنة)/i', $line, $match)) {
                return (int) $match[1];
            }
        }

        return 0;
    }

    private function companiesFrom(array $lines): array
    {
        $companies = [];
        foreach ($lines as $line) {
            if (preg_match('/(?:at|company|employer)[:\s-]+([A-Za-z0-9 &.,-]{3,80})/i', $line, $match)) {
                $companies[] = trim($match[1], " \t\n\r\0\x0B.,-");
            }
        }

        return array_values(array_unique(array_filter($companies)));
    }

    private function industryFrom(string $text): ?string
    {
        $map = [
            'Construction' => ['construction', 'contracting', 'site', 'bim', 'mep'],
            'Technology' => ['software', 'developer', 'react', 'python', 'data'],
            'Finance' => ['accountant', 'finance', 'audit'],
            'Logistics' => ['logistics', 'fleet', 'warehouse'],
            'Oil and Gas' => ['oil', 'gas', 'aramco'],
        ];
        $lower = strtolower($text);
        foreach ($map as $industry => $terms) {
            foreach ($terms as $term) {
                if (str_contains($lower, $term)) {
                    return $industry;
                }
            }
        }

        return null;
    }

    private function noticeFrom(string $text): ?string
    {
        if (preg_match('/\b(immediate|available now)\b/i', $text, $match)) {
            return ucfirst(strtolower($match[1]));
        }
        if (preg_match('/\b(15|30|45|60|90)\s*days\b/i', $text, $match)) {
            return $match[1].' days';
        }

        return null;
    }
}

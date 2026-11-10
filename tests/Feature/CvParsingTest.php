<?php

namespace Tests\Feature;

use App\Services\CvParserService;
use App\Services\SkillCatalog;
use App\Services\SpecialtyClassifierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class CvParsingTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<int, string> $paragraphs */
    private function makeDocx(array $paragraphs, string $name = 'cv.docx'): string
    {
        // Mimic a designed CV template: every line is split across two runs and the whole body sits in a table
        // cell, which is exactly what PhpWord's element walk used to skip.
        $body = '';
        foreach ($paragraphs as $line) {
            $half = intdiv(strlen($line), 2);
            $body .= '<w:p><w:r><w:t>'.htmlspecialchars(substr($line, 0, $half)).'</w:t></w:r><w:r><w:t>'.htmlspecialchars(substr($line, $half)).'</w:t></w:r></w:p>';
        }
        $xml = '<?xml version="1.0"?><w:document xmlns:w="x"><w:body><w:tbl><w:tr><w:tc>'.$body.'</w:tc></w:tr></w:tbl></w:body></w:document>';

        $path = sys_get_temp_dir().'/'.uniqid('cv_', true).'_'.$name;
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('word/document.xml', $xml);
        $zip->addFromString('[Content_Types].xml', '<Types/>');
        $zip->close();

        return $path;
    }

    private function sampleLines(): array
    {
        return [
            'AHMED AL-HARBI',
            'Senior BIM Engineer',
            'Mobile: +966 55 123 4567 | ahmed.harbi@example.com | Riyadh, Saudi Arabia',
            'Nationality: Egyptian',
            'BIM Engineer with 9+ years of experience in Revit, Navisworks and BIM 360 coordination on Saudi Aramco and giga-projects. Fluent in Arabic and English.',
            'Senior BIM Engineer - Saudi Binladin Group',
            'Jan 2019 - Present',
            'BIM Engineer at Dar Al Handasah Consultants',
            '2014 - 2018',
            'Bachelor of Science in Civil Engineering, King Saud University, 2013',
            'PMP certified, 2020',
            'Skills: Revit, Navisworks, AutoCAD, Primavera P6, Excel. Able to react quickly to change.',
        ];
    }

    public function test_docx_text_is_fully_extracted_even_from_tables_and_split_runs(): void
    {
        $parsed = app(CvParserService::class)->parse($this->makeDocx($this->sampleLines()));

        $this->assertStringContainsString('Navisworks', $parsed['raw_text']);
        $this->assertStringContainsString('Dar Al Handasah', $parsed['raw_text']);
    }

    public function test_core_fields_are_parsed_correctly(): void
    {
        $parsed = app(CvParserService::class)->parse($this->makeDocx($this->sampleLines()));

        $this->assertSame('Ahmed Al-Harbi', $parsed['name']);
        $this->assertSame('ahmed.harbi@example.com', $parsed['email']);
        $this->assertSame('+966 55 123 4567', $parsed['phone']);
        $this->assertSame('Riyadh', $parsed['city']);
        // The old parser stored the whole text line as the country.
        $this->assertSame('Saudi Arabia', $parsed['country']);
        $this->assertSame('Senior BIM Engineer', $parsed['current_job_title']);
        $this->assertSame(9, $parsed['years_experience']);
        // "Saudi Aramco"/"Saudi Binladin Group" are employers; the CV states the nationality.
        $this->assertSame('Egyptian', $parsed['nationality']);
        $this->assertSame(['Arabic', 'English'], $parsed['languages']);
    }

    public function test_skills_beyond_the_old_twelve_item_dictionary_are_found(): void
    {
        $parsed = app(CvParserService::class)->parse($this->makeDocx($this->sampleLines()));

        foreach (['Revit', 'Navisworks', 'BIM 360', 'Primavera P6', 'PMP', 'AutoCAD'] as $skill) {
            $this->assertContains($skill, $parsed['skills']);
        }
        // The ordinary verb "react" is not the React framework.
        $this->assertNotContains('React', $parsed['skills']);
    }

    public function test_structured_experience_has_real_companies_and_dates_not_placeholders(): void
    {
        $parsed = app(CvParserService::class)->parse($this->makeDocx($this->sampleLines()));

        $companies = array_column($parsed['experience_entries'], 'company');
        $this->assertContains('Saudi Binladin Group', $companies);
        $this->assertContains('Dar Al Handasah Consultants', $companies);
        $this->assertNotContains('Imported Company', $companies);

        $current = collect($parsed['experience_entries'])->firstWhere('company', 'Saudi Binladin Group');
        $this->assertTrue($current['is_current']);
        $this->assertSame('2019-01-01', $current['start_date']);
        $this->assertSame('Saudi Binladin Group', $parsed['current_company']);
    }

    public function test_a_docx_with_a_doc_extension_is_still_read_properly(): void
    {
        $docx = $this->makeDocx($this->sampleLines());
        $renamed = dirname($docx).'/'.uniqid('renamed_', true).'.doc';
        copy($docx, $renamed);

        $parsed = app(CvParserService::class)->parse($renamed);

        $this->assertSame('ahmed.harbi@example.com', $parsed['email']);
    }

    public function test_years_are_summed_from_date_ranges_when_not_stated_and_education_is_ignored(): void
    {
        $lines = [
            'Sara Khalid',
            'Civil Engineer',
            'Site Engineer at Nesma Construction',
            '2015 - 2019',
            'Project Engineer at El Seif Engineering',
            '2019 - 2023',
            'B.Sc. Civil Engineering, Cairo University',
            '2005 - 2010',
            'sara.khalid@example.com',
        ];

        $parsed = app(CvParserService::class)->parse($this->makeDocx($lines));

        $this->assertSame(8, $parsed['years_experience']);
    }

    public function test_email_username_is_used_when_no_name_line_exists(): void
    {
        $parsed = app(CvParserService::class)->parse($this->makeDocx([
            'Curriculum Vitae',
            'Mechanical Engineer',
            'Contact: omar.farouk@example.com',
        ]));

        $this->assertSame('Omar Farouk', $parsed['name']);
    }

    public function test_skill_catalog_matches_whole_words_only(): void
    {
        $catalog = new SkillCatalog();

        $this->assertSame(['JavaScript'], $catalog->extract('Strong JavaScript developer'));
        $this->assertNotContains('Java', $catalog->extract('Strong JavaScript developer'));
        $this->assertContains('Primavera P6', $catalog->extract('Planned with p6 and MS Project'));
        $this->assertContains('MS Project', $catalog->extract('Planned with p6 and MS Project'));
        $this->assertSame('Primavera P6', $catalog->canonical('primavera'));
    }

    public function test_classifier_no_longer_routes_cvs_by_substrings(): void
    {
        $classifier = app(SpecialtyClassifierService::class);

        // "excellent" contains "Excel", "safety" contains "SAFE": neither is evidence of an analyst / structural engineer.
        $hse = $classifier->classify([
            'title' => 'HSE Officer',
            'raw_text' => 'Excellent record in site safety. NEBOSH certified. Conducted safety inductions and toolbox talks.',
        ]);
        $this->assertSame('hse-engineers', $hse['slug']);

        // The headline title outweighs a passing body mention of another trade.
        $bim = $classifier->classify([
            'current_job_title' => 'Senior BIM Engineer',
            'skills' => ['Revit', 'Navisworks'],
            'raw_text' => 'Coordinated with the architect and the structural engineer on weekly clash detection.',
        ]);
        $this->assertSame('bim-engineers', $bim['slug']);
    }
}

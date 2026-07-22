<?php

namespace Tests\Unit;

use App\Models\Candidate;
use App\Models\CandidateCertification;
use App\Models\CandidateDocument;
use App\Models\CandidateEducation;
use App\Models\CandidateLanguage;
use App\Models\CandidateSkill;
use App\Services\CandidateQualityService;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class CandidateQualityServiceTest extends TestCase
{
    public function test_quality_score_uses_completeness_and_profile_strength(): void
    {
        $candidate = new Candidate([
            'full_name' => 'Aisha Al-Fahad',
            'email' => 'aisha@example.com',
            'title' => 'Senior BIM Engineer',
            'specialization' => 'BIM Engineers',
            'city' => 'Riyadh',
            'years_experience' => 8,
            'expected_salary' => 21000,
            'status' => 'SHORTLISTED',
            'recruiter_rating' => 85,
        ]);
        $candidate->setRelation('skills', new Collection([
            new CandidateSkill(['name' => 'Revit']),
            new CandidateSkill(['name' => 'Navisworks']),
            new CandidateSkill(['name' => 'BIM 360']),
            new CandidateSkill(['name' => 'QA/QC']),
            new CandidateSkill(['name' => 'Primavera P6']),
        ]));
        $candidate->setRelation('languages', new Collection([
            new CandidateLanguage(['name' => 'English']),
            new CandidateLanguage(['name' => 'Arabic']),
        ]));
        $candidate->setRelation('education', new Collection([
            new CandidateEducation(['degree' => 'Bachelor']),
            new CandidateEducation(['degree' => 'Diploma']),
        ]));
        $candidate->setRelation('certifications', new Collection([
            new CandidateCertification(['name' => 'PMP']),
            new CandidateCertification(['name' => 'Autodesk Certified Professional']),
            new CandidateCertification(['name' => 'LEED Green Associate']),
        ]));
        $candidate->setRelation('experience', new Collection());
        $candidate->setRelation('documents', new Collection([new CandidateDocument(['file_name' => 'cv.pdf'])]));
        $candidate->setRelation('interviews', new Collection());
        $candidate->setRelation('scores', new Collection());

        $result = (new CandidateQualityService())->calculate($candidate);

        $this->assertGreaterThan(70, $result['quality_score']);
        $this->assertGreaterThanOrEqual(80, $result['cv_completeness_score']);
        $this->assertArrayHasKey('skills_strength', $result['quality_factors']);
    }
}

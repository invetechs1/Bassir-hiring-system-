<?php

namespace Database\Seeders;

use App\Models\Candidate;
use App\Models\CandidateApplication;
use App\Models\Company;
use App\Models\Job;
use App\Models\PipelineStageHistory;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Specialization;
use App\Models\TalentPool;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::firstOrCreate(
            ['slug' => 'bassir-demo'],
            [
                'name' => 'Bassir Demo Company',
                'status' => 'ACTIVE',
                'default_locale' => 'en',
                'default_currency' => 'SAR',
                'subscription_status' => 'INTERNAL',
            ]
        );

        $roles = collect(['SUPER_ADMIN', 'COMPANY_ADMIN', 'HR_MANAGER', 'RECRUITER', 'HIRING_MANAGER', 'INTERVIEWER', 'CANDIDATE', 'VIEWER', 'AUDITOR'])
            ->mapWithKeys(fn ($role) => [$role => Role::firstOrCreate(['name' => $role], ['description' => str_replace('_', ' ', $role)])]);
        $permissions = collect([
            'dashboard.view',
            'tenant.manage',
            'candidate.read',
            'candidate.write',
            'job.read',
            'job.write',
            'job.match',
            'ai_search.run',
            'ai_search.import',
            'interview.read',
            'interview.write',
            'interview.feedback',
            'salary.manage',
            'specialization.manage',
            'reports.export',
            'integrations.manage',
            'users.manage',
            'audit.read',
            'settings.manage',
        ])->mapWithKeys(fn ($name) => [$name => Permission::firstOrCreate(['name' => $name], ['description' => $name])]);

        $roles['SUPER_ADMIN']->permissions()->sync($permissions->pluck('id')->all());
        $roles['COMPANY_ADMIN']->permissions()->sync($permissions->except([
            'tenant.manage',
            'integrations.manage',
            'settings.manage',
        ])->pluck('id')->all());
        $roles['HR_MANAGER']->permissions()->sync($permissions->only([
            'dashboard.view', 'candidate.read', 'candidate.write', 'job.read', 'job.write',
            'job.match', 'ai_search.run', 'ai_search.import', 'interview.read', 'interview.write',
            'interview.feedback', 'salary.manage', 'specialization.manage', 'reports.export',
        ])->pluck('id')->all());
        $roles['RECRUITER']->permissions()->sync($permissions->only([
            'dashboard.view', 'candidate.read', 'candidate.write', 'job.read', 'job.write',
            'job.match', 'ai_search.run', 'ai_search.import', 'interview.read', 'interview.write',
            'interview.feedback', 'reports.export',
        ])->pluck('id')->all());
        $roles['HIRING_MANAGER']->permissions()->sync($permissions->only([
            'dashboard.view', 'candidate.read', 'candidate.write', 'job.read', 'job.write',
            'job.match', 'interview.read', 'interview.write', 'interview.feedback', 'reports.export',
        ])->pluck('id')->all());
        $roles['INTERVIEWER']->permissions()->sync($permissions->only([
            'dashboard.view', 'candidate.read', 'job.read', 'interview.read', 'interview.feedback',
        ])->pluck('id')->all());
        $roles['VIEWER']->permissions()->sync($permissions->only([
            'dashboard.view', 'candidate.read', 'job.read', 'interview.read', 'reports.export',
        ])->pluck('id')->all());
        $roles['AUDITOR']->permissions()->sync($permissions->only([
            'dashboard.view', 'candidate.read', 'job.read', 'interview.read', 'reports.export', 'audit.read',
        ])->pluck('id')->all());
        $roles['CANDIDATE']->permissions()->sync([]);

        User::updateOrCreate(
            ['username' => 'yahya'],
            [
                'company_id' => $company->id,
                'name' => 'Yahya',
                'email' => 'yahya@bassir.local',
                'password' => Hash::make('Bassir@2030'),
                'role_id' => $roles['SUPER_ADMIN']->id,
                'is_active' => true,
                'must_change_password' => true,
            ]
        );

        $ownerId = User::where('username', 'yahya')->value('id');
        $recruiter = User::updateOrCreate(
            ['username' => 'recruiter'],
            [
                'company_id' => $company->id,
                'name' => 'Demo Recruiter',
                'email' => 'recruiter@bassir.local',
                'password' => Hash::make('Bassir@2030'),
                'role_id' => $roles['RECRUITER']->id,
                'is_active' => true,
                'must_change_password' => true,
            ]
        );
        User::updateOrCreate(
            ['username' => 'hr.manager'],
            [
                'company_id' => $company->id,
                'name' => 'Demo HR Manager',
                'email' => 'hr.manager@bassir.local',
                'password' => Hash::make('Bassir@2030'),
                'role_id' => $roles['HR_MANAGER']->id,
                'is_active' => true,
                'must_change_password' => true,
            ]
        );

        foreach ($this->specializations() as [$name, $category]) {
            Specialization::firstOrCreate(['name' => $name], ['category' => $category, 'is_active' => true]);
        }

        $candidate = Candidate::updateOrCreate(
            ['email' => 'aisha.alfahad@example.com'],
            [
                'company_id' => $company->id,
                'full_name' => 'Aisha Al-Fahad',
                'phone' => '+966500000101',
                'title' => 'Senior BIM Engineer',
                'current_company' => 'Riyadh Construction Group',
                'specialization' => 'BIM Engineers',
                'industry' => 'Construction',
                'country' => 'Saudi Arabia',
                'city' => 'Riyadh',
                'nationality' => 'Saudi',
                'years_experience' => 8,
                'expected_salary' => 21000,
                'current_salary' => 18500,
                'availability' => '30 days',
                'notice_period' => '30 days',
                'consent_status' => 'CONSENTED',
                'consent_captured_at' => now()->toDateString(),
                'consent_captured_by' => $ownerId,
                'contact_allowed' => true,
                'quality_score' => 86,
                'cv_completeness_score' => 80,
                'recruiter_rating' => 85,
                'quality_factors' => ['demo' => true, 'skills_strength' => 90, 'experience_strength' => 80],
                'status' => 'SHORTLISTED',
                'ai_summary' => 'Senior BIM engineer with strong Saudi project delivery exposure.',
            ]
        );
        foreach (['Revit', 'Navisworks', 'BIM 360', 'QA/QC', 'Clash Detection'] as $skill) {
            $candidate->skills()->firstOrCreate(['name' => $skill], ['level' => 'Advanced']);
        }
        foreach (['Arabic', 'English'] as $language) {
            $candidate->languages()->firstOrCreate(['name' => $language]);
        }
        $candidate->sources()->firstOrCreate(['source_type' => 'Seed Data'], [
            'consent_note' => 'Demo consent for local setup.',
            'consent_captured_at' => now(),
            'consent_captured_by' => $ownerId,
            'contact_allowed' => true,
        ]);
        $pool = TalentPool::firstOrCreate([
            'company_id' => $company->id,
            'name' => 'Senior Engineering Talent',
        ], [
            'category' => 'Engineers',
            'description' => 'High-quality engineering candidates for urgent project roles.',
            'created_by' => $ownerId,
        ]);
        $pool->candidates()->syncWithoutDetaching([
            $candidate->id => ['added_by' => $ownerId, 'notes' => 'Seeded high-quality BIM profile.'],
        ]);

        $job = Job::updateOrCreate(
            ['title' => 'Senior BIM Engineer', 'location' => 'Riyadh'],
            [
                'company_id' => $company->id,
                'recruiter_id' => $recruiter->id,
                'department' => 'Engineering',
                'specialization' => 'BIM Engineers',
                'company' => 'Bassir Client',
                'public_slug' => 'senior-bim-engineer-riyadh',
                'project' => 'Riyadh Mixed Use Development',
                'employment_type' => 'Full-time',
                'required_experience' => 7,
                'salary_budget_min' => 18000,
                'salary_budget_max' => 24000,
                'description' => 'Lead BIM coordination and model quality control for a large construction program.',
                'requirements' => 'Strong Revit/Navisworks/BIM coordination experience with large construction projects.',
                'internal_notes' => 'Demo job for commercial SaaS pilot workflow.',
                'approval_status' => 'APPROVED',
                'hiring_manager' => 'Noura Salem',
                'vacancies' => 2,
            ]
        );
        foreach (['Revit', 'Navisworks', 'BIM 360', 'Clash Detection'] as $skill) {
            $job->requiredSkills()->firstOrCreate(['name' => $skill]);
        }

        $application = CandidateApplication::firstOrCreate([
            'candidate_id' => $candidate->id,
            'job_id' => $job->id,
        ], [
            'company_id' => $company->id,
            'source' => 'Seed Data',
            'current_stage' => 'AI_REVIEWED',
            'status' => 'ACTIVE',
            'reviewed_by' => $recruiter->id,
            'notes' => 'Demo application record for commercial pipeline reporting.',
        ]);
        PipelineStageHistory::firstOrCreate([
            'candidate_application_id' => $application->id,
            'to_stage' => 'AI_REVIEWED',
        ], [
            'company_id' => $company->id,
            'candidate_id' => $candidate->id,
            'job_id' => $job->id,
            'from_stage' => 'APPLIED',
            'updated_by' => $recruiter->id,
            'note' => 'Seeded AI review stage.',
        ]);
    }

    private function specializations(): array
    {
        return [
            ['Civil Engineers', 'Engineering'],
            ['Structural Engineers', 'Engineering'],
            ['Architects', 'Engineering'],
            ['Electrical Engineers', 'Engineering'],
            ['Mechanical Engineers', 'Engineering'],
            ['MEP Engineers', 'Engineering'],
            ['Interior Designers', 'Engineering'],
            ['Quantity Surveyors', 'Engineering'],
            ['Planning Engineers', 'Engineering'],
            ['Project Managers', 'Engineering'],
            ['Site Engineers', 'Engineering'],
            ['HSE Engineers', 'Engineering'],
            ['QA/QC Engineers', 'Engineering'],
            ['BIM Engineers', 'Engineering'],
            ['Infrastructure Engineers', 'Engineering'],
            ['Roads Engineers', 'Engineering'],
            ['Geotechnical Engineers', 'Engineering'],
            ['Facade Engineers', 'Engineering'],
            ['Fire Fighting Engineers', 'Engineering'],
            ['Low Current Engineers', 'Engineering'],
            ['Software Developers', 'Technology'],
            ['UI/UX Designers', 'Technology'],
            ['Data Analysts', 'Technology'],
            ['Accountants', 'Finance'],
            ['Procurement Specialists', 'Operations'],
            ['HR Specialists', 'HR'],
            ['Logistics/Fleet Specialists', 'Operations'],
        ];
    }
}

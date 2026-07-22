<?php

use App\Http\Controllers\AiSearchController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AutoSourcingController;
use App\Http\Controllers\CandidateApplicationController;
use App\Http\Controllers\CandidateComparisonController;
use App\Http\Controllers\CandidateController;
use App\Http\Controllers\CandidateDocumentController;
use App\Http\Controllers\CandidateJobMatchController;
use App\Http\Controllers\CandidateImportController;
use App\Http\Controllers\CvUploadController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\InterviewController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\JobRankingController;
use App\Http\Controllers\LocalizationController;
use App\Http\Controllers\MatchingController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SalaryBenchmarkController;
use App\Http\Controllers\SearchAssistantController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SpecializationController;
use App\Http\Controllers\TalentPoolController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::view('/privacy', 'legal.privacy')->name('privacy');

Route::middleware('set_locale')->group(function () {
    Route::get('/', [AuthController::class, 'login'])->name('login');
    Route::get('/login', [AuthController::class, 'login'])->name('login.form');
    Route::post('/login', [AuthController::class, 'authenticate'])->middleware('throttle:10,1')->name('login.post');
    Route::get('/locale/{locale}', [LocalizationController::class, 'set'])->name('locale.set');
});

Route::middleware(['set_locale', 'auth', 'force_password_change'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('permission:dashboard.view')->name('dashboard');
    Route::get('/matching', [MatchingController::class, 'index'])->middleware('permission:job.match')->name('matching.index');

    Route::resource('candidates', CandidateController::class)->only(['index'])->middleware('permission:candidate.read');
    Route::middleware('permission:candidate.write')->group(function () {
        Route::resource('candidates', CandidateController::class)->only(['create', 'store', 'edit', 'update']);
        Route::post('/candidates/{candidate}/action', [CandidateController::class, 'action'])
            ->middleware('throttle:30,1')
            ->name('candidates.action');
    });
    Route::resource('candidates', CandidateController::class)->only(['show'])->middleware('permission:candidate.read');
    Route::get('/candidates/{candidate}/documents/{document}/download', [CandidateDocumentController::class, 'download'])
        ->middleware('permission:candidate.read')
        ->name('candidates.documents.download');
    Route::get('/candidates/{candidate}/job-matches', [CandidateJobMatchController::class, 'show'])
        ->middleware('permission:candidate.read')
        ->name('candidates.job-matches');
    Route::post('/candidates/{candidate}/job-matches/rebuild', [CandidateJobMatchController::class, 'rebuild'])
        ->middleware(['permission:job.match', 'throttle:20,1'])
        ->name('candidates.job-matches.rebuild');
    Route::get('/candidate-comparison', [CandidateComparisonController::class, 'index'])
        ->middleware('permission:candidate.read')
        ->name('comparisons.candidates');

    Route::get('/search-assistant', [SearchAssistantController::class, 'index'])
        ->middleware('permission:candidate.read')
        ->name('search-assistant.index');

    Route::get('/applications', [CandidateApplicationController::class, 'index'])
        ->middleware('permission:candidate.read')
        ->name('applications.index');
    Route::middleware('permission:candidate.write')->group(function () {
        Route::post('/applications', [CandidateApplicationController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('applications.store');
        Route::patch('/applications/{application}/stage', [CandidateApplicationController::class, 'updateStage'])
            ->middleware('throttle:40,1')
            ->name('applications.stage');
    });

    Route::resource('jobs', JobController::class)->only(['index'])->middleware('permission:job.read');
    Route::middleware('permission:job.write')->group(function () {
        Route::resource('jobs', JobController::class)->only(['create', 'store', 'edit', 'update']);
        Route::post('/jobs/{job}/match', [JobController::class, 'match'])
            ->middleware('permission:job.match')
            ->middleware('throttle:20,1')
            ->name('jobs.match');
    });
    Route::resource('jobs', JobController::class)->only(['show'])->middleware('permission:job.read');
    Route::middleware('permission:job.match')->group(function () {
        Route::get('/jobs/{job}/ranking', [JobRankingController::class, 'index'])->name('rankings.job');
        Route::post('/jobs/{job}/ranking/rebuild', [JobRankingController::class, 'rebuild'])
            ->middleware('throttle:20,1')
            ->name('rankings.job.rebuild');
        Route::post('/jobs/{job}/ranking/{candidate}/decision', [JobRankingController::class, 'decision'])
            ->middleware('throttle:60,1')
            ->name('rankings.job.decision');
    });

    Route::middleware('permission:candidate.write')->group(function () {
        Route::get('/talent-pools', [TalentPoolController::class, 'index'])->name('talent-pools.index');
        Route::post('/talent-pools', [TalentPoolController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('talent-pools.store');
        Route::post('/talent-pools/{pool}/candidates', [TalentPoolController::class, 'addCandidate'])
            ->middleware('throttle:60,1')
            ->name('talent-pools.candidates.store');
        Route::delete('/talent-pools/{pool}/candidates/{candidate}', [TalentPoolController::class, 'removeCandidate'])
            ->middleware('throttle:60,1')
            ->name('talent-pools.candidates.destroy');
    });

    Route::resource('interviews', InterviewController::class)->only(['index'])->middleware('permission:interview.read');
    Route::middleware('permission:interview.write')->group(function () {
        Route::resource('interviews', InterviewController::class)->only(['create', 'store']);
    });
    Route::middleware('permission:interview.feedback')->group(function () {
        Route::post('/interviews/{interview}/feedback', [InterviewController::class, 'feedback'])
            ->middleware('throttle:40,1')
            ->name('interviews.feedback');
    });

    Route::middleware('permission:specialization.manage')->group(function () {
        Route::resource('specializations', SpecializationController::class)->except(['show']);
    });

    Route::middleware('permission:salary.manage')->group(function () {
        Route::get('/salary-benchmarks', [SalaryBenchmarkController::class, 'index'])->name('salary-benchmarks.index');
        Route::post('/salary-benchmarks', [SalaryBenchmarkController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('salary-benchmarks.store');
    });

    Route::middleware('permission:ai_search.run')->group(function () {
        Route::get('/ai-search', [AiSearchController::class, 'index'])->name('ai-search.index');
        Route::post('/ai-search/cv-sourcing', [AiSearchController::class, 'cvSourcing'])
            ->middleware('throttle:8,1')
            ->name('ai-search.cv-sourcing');
    });

    Route::middleware('permission:ai_search.import')->group(function () {
        Route::post('/ai-search/import-result', [AiSearchController::class, 'importResult'])
            ->middleware('throttle:30,1')
            ->name('ai-search.import-result');
        Route::post('/ai-search/import-linkedin-manual', [AiSearchController::class, 'importLinkedinManual'])
            ->middleware('throttle:30,1')
            ->name('ai-search.import-linkedin-manual');
    });

    // Automated web sourcing (scheduled, compliant candidate discovery + import).
    Route::middleware('permission:ai_search.run')->group(function () {
        Route::get('/auto-sourcing', [AutoSourcingController::class, 'index'])->name('auto-sourcing.index');
    });
    Route::middleware('permission:ai_search.import')->group(function () {
        Route::post('/auto-sourcing', [AutoSourcingController::class, 'store'])
            ->middleware('throttle:20,1')->name('auto-sourcing.store');
        Route::post('/auto-sourcing/{sourcingSearch}/run', [AutoSourcingController::class, 'run'])
            ->middleware('throttle:8,1')->name('auto-sourcing.run');
        Route::delete('/auto-sourcing/{sourcingSearch}', [AutoSourcingController::class, 'destroy'])
            ->middleware('throttle:20,1')->name('auto-sourcing.destroy');
    });

    Route::middleware('permission:candidate.write')->group(function () {
        Route::get('/upload-cv', [CvUploadController::class, 'index'])->name('upload.index');
        Route::post('/upload-cv', [CvUploadController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('upload.store');
        Route::post('/import-candidates', [CandidateImportController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('candidates.import');
    });

    Route::middleware(['role:SUPER_ADMIN', 'permission:integrations.manage'])->group(function () {
        Route::get('/integrations', [IntegrationController::class, 'index'])->name('integrations.index');
        Route::post('/integrations', [IntegrationController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('integrations.store');
    });

    Route::middleware('permission:users.manage')->group(function () {
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::post('/users', [UserManagementController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('users.store');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])
            ->middleware('throttle:20,1')
            ->name('users.update');
    });

    Route::middleware('permission:audit.read')->group(function () {
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    });

    Route::middleware(['role:SUPER_ADMIN', 'permission:settings.manage'])->group(function () {
        Route::post('/settings/general', [SettingsController::class, 'updateGeneral'])->name('settings.general.update');
    });

    Route::get('/settings/profile', [SettingsController::class, 'profile'])->name('settings.profile');
    Route::post('/settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password.update');

    Route::middleware('permission:reports.export')->group(function () {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/candidates.csv', [ReportController::class, 'candidatesCsv'])->middleware('throttle:20,1')->name('reports.candidates.csv');
        Route::get('/reports/sources.csv', [ReportController::class, 'sourcesCsv'])->middleware('throttle:20,1')->name('reports.sources.csv');
        Route::get('/reports/interviews.csv', [ReportController::class, 'interviewsCsv'])->middleware('throttle:20,1')->name('reports.interviews.csv');
        Route::get('/reports/salary-benchmarks.csv', [ReportController::class, 'salaryBenchmarksCsv'])->middleware('throttle:20,1')->name('reports.salary-benchmarks.csv');
        Route::get('/reports/ai-search-success.csv', [ReportController::class, 'aiSearchSuccessCsv'])->middleware('throttle:20,1')->name('reports.ai-search-success.csv');
    });
});

Route::get('/health', HealthController::class)->name('health');

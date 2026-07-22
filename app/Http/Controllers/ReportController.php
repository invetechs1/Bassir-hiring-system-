<?php

namespace App\Http\Controllers;

use App\Models\AiSearchJob;
use App\Models\Candidate;
use App\Models\CandidateSource;
use App\Models\Interview;
use App\Models\SalaryBenchmark;
use App\Services\AuditService;
use App\Services\TenantService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(TenantService $tenant): View
    {
        $user = Auth::user();
        return view('reports.index', [
            'pipelineByStatus' => $tenant->scope(Candidate::query(), $user)
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->orderByDesc('total')
                ->get(),
            'sources' => CandidateSource::query()
                ->whereHas('candidate', fn ($query) => $tenant->scope($query, $user))
                ->selectRaw('source_type, count(*) as total')
                ->groupBy('source_type')
                ->orderByDesc('total')
                ->take(10)
                ->get(),
            'aiSearchStats' => [
                'runs' => $tenant->scope(AiSearchJob::query(), $user)->count(),
                'completed' => $tenant->scope(AiSearchJob::query(), $user)->where('status', 'COMPLETED')->count(),
                'imported_candidates' => DB::table('ai_search_results')
                    ->join('ai_search_jobs', 'ai_search_jobs.id', '=', 'ai_search_results.ai_search_job_id')
                    ->when(! $user?->isSuperAdmin(), fn ($query) => $query->where('ai_search_jobs.company_id', $user?->company_id))
                    ->whereNotNull('ai_search_results.candidate_id')
                    ->count(),
            ],
        ]);
    }

    public function candidatesCsv(Request $request, TenantService $tenant, AuditService $audit): Response
    {
        $rows = $tenant->scope(Candidate::with('scores'), Auth::user())->latest()->get();
        $audit->log(Auth::id(), 'REPORT_EXPORT', 'reports', 'candidate-pipeline', ['rows' => $rows->count()], $request);
        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, ['Name', 'Title', 'Specialization', 'City', 'Status', 'Expected Salary', 'AI Score']);
        foreach ($rows as $candidate) {
            fputcsv($csv, [
                $candidate->full_name,
                $candidate->title,
                $candidate->specialization,
                $candidate->city,
                $candidate->status,
                $candidate->expected_salary,
                $candidate->scores->last()?->overall,
            ]);
        }
        rewind($csv);
        return response(stream_get_contents($csv), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="candidate-pipeline.csv"',
        ]);
    }

    public function sourcesCsv(Request $request, TenantService $tenant, AuditService $audit): Response
    {
        $rows = CandidateSource::with('candidate')
            ->whereHas('candidate', fn ($query) => $tenant->scope($query, Auth::user()))
            ->latest()
            ->get();
        $audit->log(Auth::id(), 'REPORT_EXPORT', 'reports', 'sources', ['rows' => $rows->count()], $request);
        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, ['Candidate', 'Source Type', 'Source URL', 'Consent Note', 'Added At']);
        foreach ($rows as $row) {
            fputcsv($csv, [
                $row->candidate?->full_name,
                $row->source_type,
                $row->source_url,
                $row->consent_note,
                $row->created_at,
            ]);
        }
        rewind($csv);
        return response(stream_get_contents($csv), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="hiring-source-report.csv"',
        ]);
    }

    public function interviewsCsv(Request $request, TenantService $tenant, AuditService $audit): Response
    {
        $rows = $tenant->scope(Interview::with(['candidate', 'job', 'feedback']), Auth::user())->latest('starts_at')->get();
        $audit->log(Auth::id(), 'REPORT_EXPORT', 'reports', 'interviews', ['rows' => $rows->count()], $request);
        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, ['Candidate', 'Job', 'Date', 'Channel', 'Status', 'Avg Technical', 'Avg HR']);
        foreach ($rows as $row) {
            $avgTechnical = round((float) $row->feedback->avg('technical_score'), 1);
            $avgHr = round((float) $row->feedback->avg('hr_score'), 1);
            fputcsv($csv, [
                $row->candidate?->full_name,
                $row->job?->title,
                $row->starts_at,
                $row->channel,
                $row->status,
                $avgTechnical ?: null,
                $avgHr ?: null,
            ]);
        }
        rewind($csv);
        return response(stream_get_contents($csv), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="interview-performance-report.csv"',
        ]);
    }

    public function salaryBenchmarksCsv(Request $request, TenantService $tenant, AuditService $audit): Response
    {
        $rows = $tenant->scope(SalaryBenchmark::query(), Auth::user())->latest()->get();
        $audit->log(Auth::id(), 'REPORT_EXPORT', 'reports', 'salary-benchmarks', ['rows' => $rows->count()], $request);
        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, ['Job Title', 'Location', 'Experience Min', 'Experience Max', 'Min Salary', 'Max Salary', 'Source']);
        foreach ($rows as $row) {
            fputcsv($csv, [
                $row->job_title,
                $row->location,
                $row->years_experience_min,
                $row->years_experience_max,
                $row->min_salary,
                $row->max_salary,
                $row->source,
            ]);
        }
        rewind($csv);
        return response(stream_get_contents($csv), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="salary-benchmark-report.csv"',
        ]);
    }

    public function aiSearchSuccessCsv(Request $request, AuditService $audit): Response
    {
        $user = Auth::user();
        $rows = DB::table('ai_search_results')
            ->join('ai_search_jobs', 'ai_search_jobs.id', '=', 'ai_search_results.ai_search_job_id')
            ->select(
                'ai_search_jobs.id as job_id',
                'ai_search_jobs.created_at as searched_at',
                'ai_search_results.source',
                'ai_search_results.source_url',
                'ai_search_results.candidate_id'
            )
            ->when(! $user?->isSuperAdmin(), fn ($query) => $query->where('ai_search_jobs.company_id', $user?->company_id))
            ->orderByDesc('ai_search_results.id')
            ->get();
        $audit->log(Auth::id(), 'REPORT_EXPORT', 'reports', 'ai-search-success', ['rows' => $rows->count()], $request);

        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, ['Search Job', 'Searched At', 'Source', 'Source URL', 'Imported Candidate ID', 'Imported']);
        foreach ($rows as $row) {
            fputcsv($csv, [
                $row->job_id,
                $row->searched_at,
                $row->source,
                $row->source_url,
                $row->candidate_id,
                $row->candidate_id ? 'YES' : 'NO',
            ]);
        }
        rewind($csv);
        return response(stream_get_contents($csv), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="ai-search-success-report.csv"',
        ]);
    }
}

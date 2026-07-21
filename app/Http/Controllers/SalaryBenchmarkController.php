<?php

namespace App\Http\Controllers;

use App\Models\SalaryBenchmark;
use App\Services\AuditService;
use App\Services\SalaryEstimatorService;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SalaryBenchmarkController extends Controller
{
    public function index(Request $request, SalaryEstimatorService $estimator, TenantService $tenant): View
    {
        $estimate = null;
        if ($request->filled('estimate_job_title')) {
            $estimate = $estimator->estimate([
                'job_title' => $request->estimate_job_title,
                'years_experience' => $request->estimate_years_experience,
                'location' => $request->estimate_location,
                'gcc_experience' => $request->boolean('gcc_experience'),
                'skills' => array_filter(array_map('trim', preg_split('/[,;]/', (string) $request->estimate_skills))),
                'benchmark_min' => $request->benchmark_min,
                'benchmark_max' => $request->benchmark_max,
            ]);
        }

        return view('salary-benchmarks.index', [
            'benchmarks' => $tenant->scope(SalaryBenchmark::query(), Auth::user())->latest()->paginate(30),
            'estimate' => $estimate,
        ]);
    }

    public function store(Request $request, AuditService $audit, TenantService $tenant): RedirectResponse
    {
        $data = $request->validate([
            'job_title' => ['required', 'string', 'max:120'],
            'location' => ['required', 'string', 'max:120'],
            'min_salary' => ['required', 'numeric', 'min:0'],
            'max_salary' => ['required', 'numeric', 'min:0'],
            'years_experience_min' => ['required', 'integer', 'min:0'],
            'years_experience_max' => ['required', 'integer', 'min:0'],
            'source' => ['required', 'string', 'max:160'],
        ]);
        $data['company_id'] = $tenant->defaultCompanyId(Auth::user());
        $benchmark = SalaryBenchmark::create($data);
        $audit->log(Auth::id(), 'SALARY_BENCHMARK_CREATE', 'salary_benchmarks', (string) $benchmark->id, $data, $request);

        return back()->with('status', 'Salary benchmark saved');
    }
}

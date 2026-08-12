<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Job;
use Illuminate\View\View;

class PortalJobController extends Controller
{
    public function index(string $company): View
    {
        $company = Company::where('slug', $company)->firstOrFail();
        $jobs = Job::where('company_id', $company->id)
            ->where('approval_status', 'APPROVED')
            ->with('requiredSkills')
            ->latest()
            ->paginate(12);

        return view('portal.jobs.index', compact('company', 'jobs'));
    }

    public function show(string $company, string $job): View
    {
        $company = Company::where('slug', $company)->firstOrFail();
        $job = Job::where('company_id', $company->id)
            ->where('public_slug', $job)
            ->where('approval_status', 'APPROVED')
            ->with('requiredSkills')
            ->firstOrFail();

        return view('portal.jobs.show', compact('company', 'job'));
    }
}

<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\CandidateAccount;
use App\Models\Company;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PortalAuthController extends Controller
{
    public function showRegister(string $company): View
    {
        return view('portal.auth.register', ['company' => $this->company($company)]);
    }

    public function register(Request $request, string $company, AuditService $audit): RedirectResponse
    {
        $company = $this->company($company);
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190', Rule::unique('candidate_accounts')->where(fn ($q) => $q->where('company_id', $company->id))],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $account = DB::transaction(function () use ($data, $company) {
            // A recruiter may already hold a lead record for this email (CV upload, import,
            // AI sourcing) — link the new portal account to it instead of erroring on the
            // company+email uniqueness constraint.
            $candidate = Candidate::where('company_id', $company->id)->where('email', $data['email'])->first();
            if (! $candidate) {
                $candidate = Candidate::create([
                    'company_id' => $company->id,
                    'full_name' => $data['full_name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'] ?? null,
                    'title' => 'Candidate',
                    'specialization' => 'General',
                    'consent_status' => 'CONSENTED',
                    'consent_captured_at' => now()->toDateString(),
                    'contact_allowed' => true,
                    'status' => 'NEW',
                ]);
            } else {
                $candidate->update(['consent_status' => 'CONSENTED', 'consent_captured_at' => now()->toDateString(), 'contact_allowed' => true]);
            }
            $candidate->sources()->create([
                'source_type' => 'Candidate Self-Registration',
                'consent_note' => 'Candidate registered directly via the careers portal and consented to be contacted.',
                'consent_captured_at' => now(),
                'contact_allowed' => true,
            ]);

            return CandidateAccount::create([
                'company_id' => $company->id,
                'candidate_id' => $candidate->id,
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);
        });

        $audit->log(null, 'CANDIDATE_PORTAL_REGISTER', 'candidate_accounts', (string) $account->id, ['company_id' => $company->id], $request);

        Auth::guard('candidate')->login($account);
        $request->session()->regenerate();
        $request->session()->put('portal_company_slug', $company->slug);

        return redirect()->route('portal.dashboard');
    }

    public function showLogin(string $company): View
    {
        return view('portal.auth.login', ['company' => $this->company($company)]);
    }

    public function login(Request $request, string $company): RedirectResponse
    {
        $company = $this->company($company);
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $account = CandidateAccount::where('company_id', $company->id)->where('email', $data['email'])->first();
        if (! $account || ! Hash::check($data['password'], $account->password)) {
            return back()->withErrors(['email' => 'These credentials do not match our records.'])->onlyInput('email');
        }

        Auth::guard('candidate')->login($account);
        $request->session()->regenerate();
        $request->session()->put('portal_company_slug', $company->slug);

        return redirect()->intended(route('portal.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $company = Auth::guard('candidate')->user()?->company;
        Auth::guard('candidate')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.jobs.index', $company?->slug ?? 'bassir-demo');
    }

    private function company(string $slug): Company
    {
        return Company::where('slug', $slug)->firstOrFail();
    }
}

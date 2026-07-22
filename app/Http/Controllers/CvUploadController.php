<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Services\AiCandidateRankingService;
use App\Services\AiInsightsService;
use App\Services\AuditService;
use App\Services\CvParserService;
use App\Services\DuplicateDetectionService;
use App\Services\FileSecurityService;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;
use Illuminate\View\View;

class CvUploadController extends Controller
{
    public function index(): View
    {
        return view('upload.index');
    }

    public function store(
        Request $request,
        CvParserService $parser,
        DuplicateDetectionService $duplicates,
        AiInsightsService $insights,
        AuditService $audit,
        FileSecurityService $fileSecurity,
        TenantService $tenant,
        AiCandidateRankingService $ranking
    ): RedirectResponse
    {
        $request->validate([
            'cv' => [
                'required',
                'file',
                'max:'.((int) config('bassir.max_upload_kb', 10240)),
                'mimes:pdf,doc,docx,jpg,jpeg,png',
            ],
        ]);
        $file = $request->file('cv');
        try {
            $fileSecurity->assertAllowedCv($file);
        } catch (Throwable $e) {
            return back()->withErrors(['cv' => $e->getMessage()]);
        }

        $path = $file->store('private/cvs');
        $malwareScanStatus = $fileSecurity->malwareScan(Storage::path($path));
        if ($malwareScanStatus === 'FAILED') {
            Storage::disk('local')->delete($path);
            return back()->withErrors(['cv' => 'The uploaded CV did not pass the configured file security scan.']);
        }

        try {
            $parsed = $parser->parse($file);
        } catch (Throwable) {
            if (Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }

            return back()->withErrors(['cv' => 'Unable to parse this CV file. Please upload another file or use CSV import.']);
        }
        $candidateData = [
            'full_name' => $parsed['name'] ?: 'Imported Candidate',
            'email' => $parsed['email'],
            'phone' => $parsed['phone'],
            'title' => $parsed['current_job_title'] ?? $parsed['experience'][0] ?? 'Candidate',
            'current_company' => $parsed['current_company'] ?? null,
            'specialization' => 'Unclassified',
            'industry' => $parsed['industry'] ?? null,
            'country' => $parsed['location'],
            'city' => $parsed['city'] ?? null,
            'nationality' => $parsed['nationality'] ?? null,
            'years_experience' => $parsed['years_experience'] ?? 0,
            'expected_salary' => $parsed['expected_salary'] ?? null,
            'notice_period' => $parsed['notice_period'] ?? null,
            'ai_summary' => mb_substr($parsed['raw_text'], 0, 1200),
            'consent_status' => 'PENDING',
            'duplicate_hash' => $duplicates->hash(['full_name' => $parsed['name'], 'email' => $parsed['email'], 'phone' => $parsed['phone']]),
            'company_id' => $tenant->defaultCompanyId(Auth::user()),
            'parsed_profile' => [
                'previous_companies' => $parsed['previous_companies'] ?? [],
                'industry' => $parsed['industry'] ?? null,
                'notice_period' => $parsed['notice_period'] ?? null,
                'summary' => $parsed['summary'] ?? null,
            ],
        ];
        try {
            [$candidate, $existing] = DB::transaction(function () use ($candidateData, $parsed, $file, $path, $insights, $tenant, $fileSecurity, $malwareScanStatus) {
                $existing = $tenant->scope(Candidate::query(), Auth::user())
                    ->where(function ($query) use ($candidateData) {
                        $query->where('duplicate_hash', $candidateData['duplicate_hash'])
                            ->when($candidateData['email'] ?? null, fn ($q) => $q->orWhere('email', $candidateData['email']))
                            ->when($candidateData['phone'] ?? null, fn ($q) => $q->orWhere('phone', $candidateData['phone']));
                    })
                    ->first();
                $candidate = $existing ?: Candidate::create($candidateData);
                $candidate->forceFill(array_filter([
                    'title' => $candidateData['title'] ?? null,
                    'current_company' => $candidateData['current_company'] ?? null,
                    'industry' => $candidateData['industry'] ?? null,
                    'city' => $candidateData['city'] ?? null,
                    'nationality' => $candidateData['nationality'] ?? null,
                    'years_experience' => $candidateData['years_experience'] ?? null,
                    'expected_salary' => $candidateData['expected_salary'] ?? null,
                    'notice_period' => $candidateData['notice_period'] ?? null,
                    'parsed_profile' => $candidateData['parsed_profile'] ?? null,
                ], fn ($value) => ! is_null($value) && $value !== ''))->save();

                foreach ($parsed['skills'] as $skill) {
                    $candidate->skills()->firstOrCreate(['name' => $skill]);
                }
                foreach ($parsed['languages'] as $language) {
                    $candidate->languages()->firstOrCreate(['name' => $language]);
                }
                foreach ($parsed['experience_entries'] ?? [] as $entry) {
                    $candidate->experience()->firstOrCreate([
                        'title' => $entry['title'] ?? '',
                        'company' => $entry['company'] ?? '',
                    ], $entry);
                }
                foreach ($parsed['education_entries'] ?? [] as $entry) {
                    $candidate->education()->firstOrCreate([
                        'institution' => $entry['institution'] ?? '',
                        'degree' => $entry['degree'] ?? '',
                    ], $entry);
                }
                foreach ($parsed['certification_entries'] ?? [] as $entry) {
                    $candidate->certifications()->firstOrCreate([
                        'name' => $entry['name'] ?? '',
                        'issuer' => $entry['issuer'] ?? '',
                    ], $entry);
                }

                $ai = $insights->candidateInsight([
                    'full_name' => $candidate->full_name,
                    'title' => $candidate->title,
                    'specialization' => $candidate->specialization,
                    'skills' => $candidate->skills()->pluck('name')->all(),
                    'years_experience' => $candidate->years_experience,
                    'expected_salary' => $candidate->expected_salary,
                    'location' => trim(($candidate->city ?? '').' '.($candidate->country ?? '')),
                ]);
                $candidate->update(['ai_summary' => $ai['summary']]);

                $candidate->documents()->create([
                    'file_name' => $fileSecurity->safeOriginalName($file),
                    'mime_type' => $file->getMimeType(),
                    'storage_path' => $path,
                    'checksum' => hash_file('sha256', Storage::path($path)),
                    'scan_status' => 'COMPLETED',
                    'malware_scan_status' => $malwareScanStatus,
                ]);
                $candidate->sources()->create([
                    'source_type' => 'CV Upload',
                    'consent_note' => $existing ? 'New CV document attached to existing profile. Consent pending.' : 'Consent pending after upload.',
                ]);

                return [$candidate, $existing];
            });
        } catch (Throwable $e) {
            if (Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
            throw $e;
        }

        try {
            $ranking->suggestJobsForCandidate($candidate);
        } catch (Throwable) {
            // Candidate creation should not fail when ranking is temporarily unavailable.
        }
        $audit->log(Auth::id(), 'CV_UPLOAD_PARSE', 'candidates', (string) $candidate->id, ['existing' => (bool) $existing], $request);

        return redirect()->route('candidates.show', $candidate)->with('status', $existing ? 'CV attached to existing candidate profile' : 'CV parsed and candidate created');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Services\AuditService;
use App\Services\CandidateQualityService;
use App\Services\DuplicateDetectionService;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;
use PhpOffice\PhpSpreadsheet\IOFactory;

class CandidateImportController extends Controller
{
    public function store(Request $request, DuplicateDetectionService $duplicates, AuditService $audit, TenantService $tenant, CandidateQualityService $quality): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:csv,xls,xlsx'],
        ]);

        $rows = $this->rows($request->file('file')->getRealPath(), $request->file('file')->getClientOriginalExtension());
        if (empty($rows)) {
            return back()->withErrors(['file' => 'Import file is empty or unreadable.']);
        }

        $headers = $this->normalizedHeaders(array_shift($rows) ?? []);
        if (empty($headers)) {
            return back()->withErrors(['file' => 'Import file does not contain a valid header row.']);
        }

        $rows = array_values(array_filter($rows, fn ($row) => ! $this->isRowEmpty($row)));
        if (count($rows) > 5000) {
            return back()->withErrors(['file' => 'Import file is too large. Max 5000 candidate rows per import.']);
        }
        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $data = $this->mapRowToHeaders($row, $headers);

            $payload = [
                'full_name' => $data['full name'] ?? $data['name'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'linkedin_url' => $data['linkedin url'] ?? $data['linkedin'] ?? null,
                'title' => $data['job title'] ?? $data['title'] ?? null,
                'current_company' => $data['current company'] ?? $data['company'] ?? null,
                'specialization' => $data['specialization'] ?? $data['profession'] ?? null,
                'industry' => $data['industry'] ?? null,
                'country' => $data['country'] ?? null,
                'city' => $data['city'] ?? null,
                'nationality' => $data['nationality'] ?? null,
                'years_experience' => max(0, (int) ($data['years experience'] ?? $data['experience'] ?? 0)),
                'expected_salary' => $data['expected salary'] ?? null,
                'current_salary' => $data['current salary'] ?? null,
                'availability' => $data['availability'] ?? null,
                'notice_period' => $data['notice period'] ?? $data['notice'] ?? null,
                'recruiter_rating' => isset($data['recruiter rating']) ? max(0, min(100, (int) $data['recruiter rating'])) : null,
                'consent_status' => $this->consent($data['consent status'] ?? $data['consent'] ?? null),
                'status' => 'NEW',
                'company_id' => $tenant->defaultCompanyId(Auth::user()),
            ];
            if ($payload['consent_status'] === 'CONSENTED') {
                $payload['consent_captured_at'] = now()->toDateString();
                $payload['consent_captured_by'] = Auth::id();
                $payload['contact_allowed'] = true;
            }
            if (empty($payload['full_name']) || empty($payload['title']) || empty($payload['specialization'])) {
                continue;
            }
            $payload['duplicate_hash'] = $duplicates->hash($payload);
            $existing = $tenant->scope(Candidate::query(), Auth::user())
                ->where(function ($query) use ($payload) {
                    $query->where('duplicate_hash', $payload['duplicate_hash'])
                        ->when($payload['email'] ?? null, fn ($inner) => $inner->orWhere('email', $payload['email']))
                        ->when($payload['phone'] ?? null, fn ($inner) => $inner->orWhere('phone', $payload['phone']))
                        ->when($payload['linkedin_url'] ?? null, fn ($inner) => $inner->orWhere('linkedin_url', $payload['linkedin_url']));
                })
                ->exists();
            if ($existing) {
                $skipped++;
                continue;
            }
            try {
                DB::transaction(function () use ($payload, $data, $quality, &$imported) {
                    $candidate = Candidate::create($payload);
                    foreach ($this->split($data['skills'] ?? '') as $skill) {
                        $candidate->skills()->firstOrCreate(['name' => $skill]);
                    }
                    foreach ($this->split($data['languages'] ?? '') as $language) {
                        $candidate->languages()->firstOrCreate(['name' => $language]);
                    }
                    $candidate->sources()->create([
                        'source_type' => $data['source'] ?? 'CSV/Excel Import',
                        'consent_note' => $candidate->consent_status === 'CONSENTED' ? 'Consent supplied in import file.' : 'Consent pending after import.',
                        'consent_captured_at' => $candidate->consent_status === 'CONSENTED' ? now() : null,
                        'consent_captured_by' => $candidate->consent_status === 'CONSENTED' ? Auth::id() : null,
                        'contact_allowed' => $candidate->consent_status === 'CONSENTED',
                    ]);
                    $freshCandidate = $candidate->fresh(['skills', 'languages', 'education', 'certifications', 'experience', 'documents', 'interviews.feedback', 'scores']);
                    if ($freshCandidate) {
                        $quality->update($freshCandidate);
                    }
                    $imported++;
                });
            } catch (Throwable) {
                $skipped++;
            }
        }

        $audit->log(Auth::id(), 'CANDIDATE_BULK_IMPORT', 'candidates', null, ['imported' => $imported, 'skipped' => $skipped], $request);

        return back()->with('status', "Imported {$imported} candidates. Skipped {$skipped} duplicates.");
    }

    private function rows(string $path, string $extension): array
    {
        if (strtolower($extension) === 'csv') {
            return array_map('str_getcsv', file($path) ?: []);
        }
        $sheet = IOFactory::load($path)->getActiveSheet();
        return $sheet->toArray(null, true, true, false);
    }

    private function split(?string $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[,;]/', (string) $value))));
    }

    private function normalizedHeaders(array $rawHeaders): array
    {
        $headers = [];
        $seen = [];

        foreach ($rawHeaders as $index => $header) {
            $name = strtolower(trim((string) $header));
            if ($name === '') {
                continue;
            }

            // Avoid accidental key overwrite when same header appears more than once.
            if (isset($seen[$name])) {
                $seen[$name]++;
                $name = $name.'_'.$seen[$name];
            } else {
                $seen[$name] = 1;
            }

            $headers[(int) $index] = $name;
        }

        return $headers;
    }

    private function mapRowToHeaders(mixed $row, array $headers): array
    {
        $row = is_array($row) ? $row : [];
        $mapped = [];

        foreach ($headers as $index => $headerName) {
            $mapped[$headerName] = $row[$index] ?? null;
        }

        return $mapped;
    }

    private function isRowEmpty(mixed $row): bool
    {
        if (! is_array($row)) {
            return true;
        }

        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function consent(?string $value): string
    {
        $value = strtoupper(trim((string) $value));
        return in_array($value, ['CONSENTED', 'YES', 'Y'], true) ? 'CONSENTED' : ($value === 'WITHDRAWN' ? 'WITHDRAWN' : 'PENDING');
    }
}

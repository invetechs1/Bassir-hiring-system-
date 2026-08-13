<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Shared validation for candidate create/update. Write permission is enforced by
 * route middleware, so authorization here is always allowed.
 */
abstract class CandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Candidate id to exclude from tenant-unique checks (null on create). */
    abstract protected function ignoreId(): ?int;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = Auth::user()?->company_id;
        $unique = fn (string $column) => Rule::unique('candidates', $column)
            ->where(fn ($query) => is_null($companyId) ? $query->whereNull('company_id') : $query->where('company_id', $companyId))
            ->ignore($this->ignoreId());

        return [
            'full_name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email', $unique('email')],
            'phone' => ['nullable', 'string', 'max:40'],
            'linkedin_url' => ['nullable', 'url', $unique('linkedin_url')],
            'title' => ['required', 'string', 'max:120'],
            'current_company' => ['nullable', 'string', 'max:160'],
            'specialization' => ['required', 'string', 'max:120'],
            'industry' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:80'],
            'nationality' => ['nullable', 'string', 'max:80'],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:60'],
            'expected_salary' => ['nullable', 'numeric', 'min:0'],
            'current_salary' => ['nullable', 'numeric', 'min:0'],
            'availability' => ['nullable', 'string', 'max:40'],
            'notice_period' => ['nullable', 'string', 'max:80'],
            'recruiter_rating' => ['nullable', 'integer', 'min:0', 'max:100'],
            'consent_status' => ['required', 'in:CONSENTED,PENDING,WITHDRAWN'],
            'status' => ['nullable', 'in:NEW,REVIEWED,SHORTLISTED,INTERVIEW,OFFER,HIRED,REJECTED,BLACKLISTED'],
        ];
    }
}

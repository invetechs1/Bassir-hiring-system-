<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared validation for job create/update. Write permission is enforced by
 * route middleware, so authorization here is always allowed.
 */
abstract class JobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'specialization' => ['nullable', 'string', 'max:120'],
            'department' => ['required', 'string', 'max:120'],
            'company' => ['required', 'string', 'max:120'],
            'project' => ['nullable', 'string', 'max:120'],
            'location' => ['required', 'string', 'max:120'],
            'employment_type' => ['nullable', 'string', 'max:80'],
            'required_experience' => ['required', 'integer', 'min:0'],
            'salary_budget_min' => ['required', 'numeric', 'min:0'],
            'salary_budget_max' => ['required', 'numeric', 'min:0'],
            'description' => ['required', 'string'],
            'requirements' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
            'approval_status' => ['required', 'in:DRAFT,PENDING,APPROVED,CLOSED'],
            'hiring_manager' => ['required', 'string', 'max:120'],
            'vacancies' => ['required', 'integer', 'min:1'],
        ];
    }
}

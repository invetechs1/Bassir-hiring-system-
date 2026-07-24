<?php

namespace App\Http\Requests;

use App\Models\Candidate;

class UpdateCandidateRequest extends CandidateRequest
{
    protected function ignoreId(): ?int
    {
        $candidate = $this->route('candidate');

        return $candidate instanceof Candidate ? $candidate->id : (is_numeric($candidate) ? (int) $candidate : null);
    }
}

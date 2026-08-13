<?php

namespace App\Http\Requests;

class StoreCandidateRequest extends CandidateRequest
{
    protected function ignoreId(): ?int
    {
        return null;
    }
}

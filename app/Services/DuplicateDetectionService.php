<?php

namespace App\Services;

class DuplicateDetectionService
{
    public function hash(array $input): string
    {
        $parts = array_filter([
            strtolower(trim((string) ($input['email'] ?? ''))),
            preg_replace('/[^\d+]/', '', (string) ($input['phone'] ?? '')),
            strtolower(trim((string) ($input['linkedin_url'] ?? ''))),
            strtolower(preg_replace('/\s+/', ' ', trim((string) ($input['full_name'] ?? '')))),
        ]);

        return hash('sha256', implode('|', $parts));
    }
}

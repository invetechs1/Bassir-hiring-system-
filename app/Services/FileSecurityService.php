<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;

class FileSecurityService
{
    public function assertAllowedCv(UploadedFile $file): void
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $bytes = (string) file_get_contents($file->getRealPath(), false, null, 0, 16);

        $allowed = match ($extension) {
            'pdf' => str_starts_with($bytes, '%PDF'),
            'doc' => str_starts_with($bytes, "\xD0\xCF\x11\xE0"),
            'docx' => str_starts_with($bytes, "PK"),
            'jpg', 'jpeg' => str_starts_with($bytes, "\xFF\xD8\xFF"),
            'png' => str_starts_with($bytes, "\x89PNG"),
            default => false,
        };

        if (! $allowed) {
            throw new RuntimeException('The uploaded CV file content does not match an allowed document type.');
        }
    }

    public function safeOriginalName(UploadedFile $file): string
    {
        $base = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $base = preg_replace('/[^A-Za-z0-9._-]/', '_', $base) ?: 'cv';

        return mb_substr($base, 0, 120).($extension ? '.'.$extension : '');
    }

    public function malwareScan(string $absolutePath): string
    {
        $command = trim((string) config('bassir.malware_scan_command', ''));
        if ($command === '') {
            return 'NOT_CONFIGURED';
        }

        $fullCommand = $command.' '.escapeshellarg($absolutePath);
        exec($fullCommand, $output, $code);

        return $code === 0 ? 'CLEAN' : 'FAILED';
    }
}

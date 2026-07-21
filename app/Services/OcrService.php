<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Throwable;

class OcrService
{
    public function __construct(
        private readonly ApiCredentialService $credentials,
        private readonly FileSecurityService $files
    ) {
    }

    public function extract(UploadedFile $file): string
    {
        $apiKey = $this->credentials->get('ocr_space', 'OCR_SPACE_API_KEY');
        if (empty($apiKey)) {
            return '';
        }

        try {
            $response = Http::timeout(40)
                ->attach('file', file_get_contents($file->getRealPath()) ?: '', $this->files->safeOriginalName($file))
                ->post('https://api.ocr.space/parse/image', [
                    'apikey' => $apiKey,
                    'language' => 'eng',
                    'isOverlayRequired' => false,
                    'OCREngine' => 2,
                ]);
        } catch (Throwable) {
            return '';
        }

        if (! $response->ok()) {
            return '';
        }

        $texts = collect($response->json('ParsedResults', []))
            ->pluck('ParsedText')
            ->filter()
            ->values()
            ->all();

        return trim(implode("\n", $texts));
    }
}

<?php

namespace App\Services;

use App\Models\ApiKey;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class ApiCredentialService
{
    public function get(string $provider, ?string $envFallback = null): ?string
    {
        foreach ($this->aliases($provider) as $alias) {
            $record = ApiKey::query()
                ->where('provider', $alias)
                ->where('status', 'ACTIVE')
                ->first();

            if (! $record) {
                continue;
            }

            try {
                $record->forceFill(['last_used_at' => now()])->save();
                return Crypt::decryptString($record->encrypted_value);
            } catch (Throwable) {
                continue;
            }
        }

        return $envFallback ? env($envFallback) : null;
    }

    public function aliases(string $provider): array
    {
        $provider = strtolower(trim($provider));

        return match ($provider) {
            'openai' => ['openai', 'openai_api', 'OpenAI API'],
            'google_cse_key' => ['google_cse_key', 'google_custom_search_key', 'Google Custom Search API'],
            'google_cse_id' => ['google_cse_id', 'google_custom_search_engine_id', 'Google Custom Search Engine ID'],
            'bing_search' => ['bing_search', 'bing', 'Bing Search API'],
            'serpapi' => ['serpapi', 'SerpAPI'],
            'agency_feed_url' => ['agency_feed_url', 'AGENCY_FEED_URL'],
            'agency_feed_token' => ['agency_feed_token', 'Agency Feed'],
            'ocr_space' => ['ocr_space', 'ocr', 'OCR Space API'],
            default => [$provider],
        };
    }
}

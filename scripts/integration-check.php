<?php

declare(strict_types=1);

use App\Services\ApiCredentialService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/** @var ApiCredentialService $credentials */
$credentials = $app->make(ApiCredentialService::class);

$providers = [
    'openai' => ['required' => true, 'env' => 'OPENAI_API_KEY'],
    'google_cse_key' => ['required' => true, 'env' => 'GOOGLE_CUSTOM_SEARCH_API_KEY'],
    'google_cse_id' => ['required' => true, 'env' => 'GOOGLE_CUSTOM_SEARCH_ENGINE_ID'],
    'bing_search' => ['required' => true, 'env' => 'BING_SEARCH_API_KEY'],
    'serpapi' => ['required' => false, 'env' => 'SERPAPI_API_KEY'],
    'agency_feed_url' => ['required' => false, 'env' => 'AGENCY_FEED_URL'],
    'agency_feed_token' => ['required' => false, 'env' => 'AGENCY_FEED_TOKEN'],
    'ocr_space' => ['required' => false, 'env' => 'OCR_SPACE_API_KEY'],
];

$runRemoteTests = filter_var(getenv('RUN_REMOTE_INTEGRATION_TESTS') ?: 'false', FILTER_VALIDATE_BOOLEAN);
$query = trim((string) (getenv('INTEGRATION_TEST_QUERY') ?: 'site:example.com resume'));

$errors = 0;
echo "Integration presence check\n";
echo "Remote tests: ".($runRemoteTests ? 'enabled' : 'disabled')."\n";
echo "----------------------------------------\n";

$values = [];
foreach ($providers as $provider => $meta) {
    $value = $credentials->get($provider, $meta['env']);
    $values[$provider] = $value;
    $present = is_string($value) && trim($value) !== '';

    if (! $present && ($meta['required'] ?? false) === true) {
        $errors++;
        echo "[FAIL] Missing required integration: {$provider}\n";
        continue;
    }

    if (! $present) {
        echo "[WARN] Optional integration missing: {$provider}\n";
        continue;
    }

    $masked = substr((string) $value, 0, 4).'...'.substr((string) $value, -3);
    echo "[OK] {$provider} present ({$masked})\n";
}

if ($runRemoteTests) {
    echo "----------------------------------------\n";
    echo "Remote connectivity tests\n";

    if (! empty($values['openai'])) {
        try {
            $response = Http::withToken($values['openai'])->timeout(20)->get('https://api.openai.com/v1/models', ['limit' => 1]);
            if (! $response->ok()) {
                $errors++;
                echo "[FAIL] OpenAI connectivity test failed: HTTP ".$response->status()."\n";
            } else {
                echo "[OK] OpenAI connectivity test passed\n";
            }
        } catch (\Throwable $e) {
            $errors++;
            echo "[FAIL] OpenAI connectivity exception: {$e->getMessage()}\n";
        }
    }

    if (! empty($values['google_cse_key']) && ! empty($values['google_cse_id'])) {
        try {
            $response = Http::timeout(20)->get('https://www.googleapis.com/customsearch/v1', [
                'key' => $values['google_cse_key'],
                'cx' => $values['google_cse_id'],
                'q' => $query,
                'num' => 1,
            ]);
            if (! $response->ok()) {
                $errors++;
                echo "[FAIL] Google CSE connectivity test failed: HTTP ".$response->status()."\n";
            } else {
                echo "[OK] Google CSE connectivity test passed\n";
            }
        } catch (\Throwable $e) {
            $errors++;
            echo "[FAIL] Google CSE connectivity exception: {$e->getMessage()}\n";
        }
    }

    if (! empty($values['bing_search'])) {
        try {
            $response = Http::withHeaders(['Ocp-Apim-Subscription-Key' => $values['bing_search']])
                ->timeout(20)
                ->get('https://api.bing.microsoft.com/v7.0/search', [
                    'q' => $query,
                    'count' => 1,
                ]);
            if (! $response->ok()) {
                $errors++;
                echo "[FAIL] Bing connectivity test failed: HTTP ".$response->status()."\n";
            } else {
                echo "[OK] Bing connectivity test passed\n";
            }
        } catch (\Throwable $e) {
            $errors++;
            echo "[FAIL] Bing connectivity exception: {$e->getMessage()}\n";
        }
    }

    if (! empty($values['serpapi'])) {
        try {
            $response = Http::timeout(20)->get('https://serpapi.com/search.json', [
                'engine' => 'google',
                'q' => $query,
                'num' => 1,
                'api_key' => $values['serpapi'],
            ]);
            if (! $response->ok()) {
                $errors++;
                echo "[FAIL] SerpAPI connectivity test failed: HTTP ".$response->status()."\n";
            } else {
                echo "[OK] SerpAPI connectivity test passed\n";
            }
        } catch (\Throwable $e) {
            $errors++;
            echo "[FAIL] SerpAPI connectivity exception: {$e->getMessage()}\n";
        }
    }
}

echo "----------------------------------------\n";
echo $errors > 0 ? "Integration check failed ({$errors} issues)\n" : "Integration check passed\n";
exit($errors > 0 ? 1 : 0);

<?php

declare(strict_types=1);

use App\Models\ApiKey;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Crypt;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$status = strtoupper(trim((string) getenv('APPLY_INTEGRATIONS_STATUS')));
if (! in_array($status, ['ACTIVE', 'PAUSED'], true)) {
    $status = 'ACTIVE';
}

$dryRun = filter_var(getenv('APPLY_INTEGRATIONS_DRY_RUN') ?: 'false', FILTER_VALIDATE_BOOLEAN);
$allowMissingRequired = filter_var(getenv('ALLOW_MISSING_REQUIRED_INTEGRATIONS') ?: 'false', FILTER_VALIDATE_BOOLEAN);

$providers = [
    'openai' => ['env' => 'OPENAI_API_KEY', 'required' => true],
    'google_cse_key' => ['env' => 'GOOGLE_CUSTOM_SEARCH_API_KEY', 'required' => true],
    'google_cse_id' => ['env' => 'GOOGLE_CUSTOM_SEARCH_ENGINE_ID', 'required' => true],
    'bing_search' => ['env' => 'BING_SEARCH_API_KEY', 'required' => true],
    'serpapi' => ['env' => 'SERPAPI_API_KEY', 'required' => false],
    'agency_feed_url' => ['env' => 'AGENCY_FEED_URL', 'required' => false],
    'agency_feed_token' => ['env' => 'AGENCY_FEED_TOKEN', 'required' => false],
    'ocr_space' => ['env' => 'OCR_SPACE_API_KEY', 'required' => false],
];

$upserted = 0;
$skipped = 0;
$missingRequired = [];

echo "Applying integration keys from environment...\n";
echo "Status: {$status}\n";
echo 'Dry run: '.($dryRun ? 'yes' : 'no')."\n";
echo "----------------------------------------\n";

foreach ($providers as $provider => $meta) {
    $envName = $meta['env'];
    $value = getenv($envName);
    if ($value === false || trim((string) $value) === '') {
        $value = env($envName);
    }

    if (! is_string($value) || trim($value) === '') {
        $skipped++;
        if (($meta['required'] ?? false) === true) {
            $missingRequired[] = "{$provider} ({$envName})";
            echo "[MISSING REQUIRED] {$provider} from {$envName}\n";
        } else {
            echo "[SKIP] {$provider} ({$envName}) not provided\n";
        }
        continue;
    }

    if (! $dryRun) {
        ApiKey::updateOrCreate(
            ['provider' => $provider],
            [
                'encrypted_value' => Crypt::encryptString($value),
                'status' => $status,
            ]
        );
    }

    $upserted++;
    echo "[OK] {$provider} ({$envName})\n";
}

echo "----------------------------------------\n";
echo "Upserted: {$upserted}\n";
echo "Skipped: {$skipped}\n";

if (! $allowMissingRequired && $missingRequired !== []) {
    echo "Missing required providers:\n";
    foreach ($missingRequired as $item) {
        echo " - {$item}\n";
    }
    exit(1);
}

echo "Integration apply completed successfully.\n";
exit(0);


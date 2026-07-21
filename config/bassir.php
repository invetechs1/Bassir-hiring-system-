<?php

return [
    'openai_api_key' => env('OPENAI_API_KEY'),
    'openai_model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    'google_key' => env('GOOGLE_CUSTOM_SEARCH_API_KEY'),
    'google_cx' => env('GOOGLE_CUSTOM_SEARCH_ENGINE_ID'),
    'bing_key' => env('BING_SEARCH_API_KEY'),
    'serpapi_key' => env('SERPAPI_API_KEY'),
    'agency_feed_url' => env('AGENCY_FEED_URL'),
    'agency_feed_token' => env('AGENCY_FEED_TOKEN'),
    'ocr_space_api_key' => env('OCR_SPACE_API_KEY'),
    'ai_provider' => env('AI_PROVIDER', 'openai'),
    'ai_timeout_seconds' => (int) env('AI_TIMEOUT_SECONDS', 25),
    'ai_retry_attempts' => (int) env('AI_RETRY_ATTEMPTS', 1),
    'malware_scan_command' => env('CV_MALWARE_SCAN_COMMAND', ''),
    'storage_quota_mb' => (int) env('BASSIR_STORAGE_QUOTA_MB', 1024),
    'mobile_token_ttl_days' => (int) env('MOBILE_TOKEN_TTL_DAYS', 30),
    'mobile_api_max_page_size' => (int) env('MOBILE_API_MAX_PAGE_SIZE', 100),
    'allowed_cv_mimes' => [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'image/jpeg',
        'image/png',
    ],
    'max_upload_kb' => 10240,
];

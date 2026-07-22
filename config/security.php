<?php

return [
    // Enforce HTTPS redirects for production domains.
    'force_https' => filter_var(env('APP_FORCE_HTTPS', true), FILTER_VALIDATE_BOOLEAN),
    'trust_proxy_https_headers' => filter_var(env('APP_TRUST_PROXY_HTTPS_HEADERS', false), FILTER_VALIDATE_BOOLEAN),
];

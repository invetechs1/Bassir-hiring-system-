<?php

namespace App\Services\Connectors;

/**
 * Contract for an official/partner hiring-platform data connector.
 *
 * Connectors talk to a platform's OFFICIAL, authenticated API (LinkedIn Talent
 * Solutions, Indeed Partner API, etc.) — never scraping. When the required API
 * credential is not configured, a connector reports itself as not-configured and
 * returns no results, so the pipeline degrades gracefully.
 */
interface PlatformConnector
{
    /** Stable machine key, e.g. "linkedin_talent". */
    public function key(): string;

    /** Human label, e.g. "LinkedIn Talent Solutions". */
    public function label(): string;

    /** True when the API credential/contract for this connector is present. */
    public function isConfigured(): bool;

    /**
     * Search the platform's official API.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>  Normalized result rows.
     */
    public function search(array $filters): array;
}

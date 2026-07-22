<?php

namespace App\Services\Connectors;

use Throwable;

/**
 * Aggregates every official-platform connector. Configured connectors feed the
 * auto-sourcing pipeline; unconfigured ones are reported so the UI can prompt
 * the admin to connect that platform.
 */
class PlatformConnectorRegistry
{
    /** @var array<int, PlatformConnector> */
    private array $connectors;

    public function __construct(
        LinkedInTalentConnector $linkedin,
        IndeedConnector $indeed,
    ) {
        $this->connectors = [$linkedin, $indeed];
    }

    /** @return array<int, PlatformConnector> */
    public function all(): array
    {
        return $this->connectors;
    }

    /** @return array<int, PlatformConnector> */
    public function configured(): array
    {
        return array_values(array_filter($this->connectors, fn (PlatformConnector $c) => $c->isConfigured()));
    }

    /**
     * Status list for the admin UI.
     *
     * @return array<int, array{key:string,label:string,configured:bool}>
     */
    public function statuses(): array
    {
        return array_map(fn (PlatformConnector $c) => [
            'key' => $c->key(),
            'label' => $c->label(),
            'configured' => $c->isConfigured(),
        ], $this->connectors);
    }

    /**
     * Run every configured connector and merge normalized results.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters): array
    {
        $results = [];
        foreach ($this->configured() as $connector) {
            try {
                foreach ($connector->search($filters) as $row) {
                    $results[] = $row;
                }
            } catch (Throwable) {
                // A single connector outage must not break the pipeline.
            }
        }

        return $results;
    }
}

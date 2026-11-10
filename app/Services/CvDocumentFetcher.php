<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Downloads a public CV file found by web sourcing and parses it. Shared by the manual
 * "Import as candidate" action and the scheduled auto-sourcing engine so both apply the same rules.
 *
 * The URL comes from a third-party search index, so it is untrusted: only public http(s) hosts are
 * fetched (no localhost, private networks or cloud-metadata addresses — including via redirects),
 * HTML pages dressed up as ".pdf" are rejected, and the file extension we store is decided from the
 * downloaded bytes rather than from the URL.
 */
class CvDocumentFetcher
{
    public function __construct(
        private readonly FileSecurityService $fileSecurity,
        private readonly CvParserService $parser
    ) {
    }

    /**
     * @return array{path:string,file_name:string,mime_type:string,checksum:string,malware_scan_status:string,parsed:array<string,mixed>}|null
     */
    public function fetch(string $url, string $fallbackExtension = 'pdf', string $directory = 'private/cv-bank-imports'): ?array
    {
        if (! $this->isPublicHttpUrl($url) || str_contains(strtolower($url), 'linkedin.com')) {
            return null;
        }

        try {
            $response = Http::timeout(25)->withOptions([
                'allow_redirects' => [
                    'max' => 3,
                    'protocols' => ['http', 'https'],
                    'on_redirect' => function ($request, $response, $uri) {
                        if (! $this->isPublicHttpUrl((string) $uri)) {
                            throw new RuntimeException('Redirect to a non-public address blocked.');
                        }
                    },
                ],
            ])->get($url);
        } catch (Throwable) {
            return null;
        }
        if (! $response->ok()) {
            return null;
        }

        $body = $response->body();
        $maxBytes = ((int) config('bassir.max_upload_kb', 10240)) * 1024;
        if ($body === '' || strlen($body) > $maxBytes) {
            return null;
        }

        $extension = match (true) {
            str_starts_with($body, '%PDF') => 'pdf',
            str_starts_with($body, "PK\x03\x04") => 'docx',
            str_starts_with($body, "\xD0\xCF\x11\xE0") => 'doc',
            default => null,
        };
        if ($extension === null) {
            // Not a recognisable document container. Accept only plain text that isn't a web page.
            $contentType = strtolower((string) $response->header('Content-Type'));
            if (str_contains($contentType, 'text/html') || preg_match('/^\s*(<!doctype|<html|<head|<body|<\?xml)/i', $body)) {
                return null;
            }
            $extension = in_array($fallbackExtension, ['pdf', 'doc', 'docx'], true) ? $fallbackExtension : 'pdf';
        }

        $path = trim($directory, '/').'/'.Str::uuid()->toString().'.'.$extension;
        Storage::disk('local')->put($path, $body);
        $absolute = Storage::disk('local')->path($path);

        $scanStatus = $this->fileSecurity->malwareScan($absolute);
        if ($scanStatus === 'FAILED') {
            Storage::disk('local')->delete($path);

            return null;
        }

        try {
            $parsed = $this->parser->parse($absolute);
        } catch (Throwable) {
            Storage::disk('local')->delete($path);

            return null;
        }

        return [
            'path' => $path,
            'file_name' => Str::limit(basename(rawurldecode(parse_url($url, PHP_URL_PATH) ?: 'cv.'.$extension)), 180, ''),
            'mime_type' => match ($extension) {
                'pdf' => 'application/pdf',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                default => 'application/msword',
            },
            'checksum' => hash('sha256', $body),
            'malware_scan_status' => $scanStatus,
            'parsed' => $parsed,
        ];
    }

    /** Put a fetched CV in the same specialty folder an uploaded CV would land in, so the CV Bank stays consistent. */
    public function relocate(array $download, string $slug): array
    {
        $disk = Storage::disk('local');
        $target = 'private/cv-bank/'.$slug.'/'.basename($download['path']);
        try {
            $disk->makeDirectory('private/cv-bank/'.$slug);
            if ($disk->move($download['path'], $target)) {
                $download['path'] = $target;
            }
        } catch (Throwable) {
            // Leave the file where it is; the document record points at the real location either way.
        }

        return $download;
    }

    public function isPublicHttpUrl(string $url): bool
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = trim((string) ($parts['host'] ?? ''), '[]');
        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }
        if (isset($parts['port']) && ! in_array((int) $parts['port'], [80, 443], true)) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ip = $host;
        } else {
            $ip = gethostbyname($host);
            // A name that doesn't resolve can't be fetched anyway — let the HTTP call fail naturally.
            if ($ip === $host) {
                return true;
            }
        }

        return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
}

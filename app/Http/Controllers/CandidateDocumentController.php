<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\CandidateDocument;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CandidateDocumentController extends Controller
{
    public function download(Request $request, Candidate $candidate, CandidateDocument $document, AuditService $audit): StreamedResponse
    {
        $this->authorizeDocument($candidate, $document);

        // ASCII-safe fallback filename for response download header.
        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $document->file_name) ?: ('cv_'.$document->id.'.pdf');
        $document->forceFill([
            'download_count' => ((int) $document->download_count) + 1,
            'last_downloaded_at' => now(),
        ])->save();
        $audit->log(Auth::id(), 'CV_DOCUMENT_DOWNLOAD', 'candidate_documents', (string) $document->id, [
            'candidate_id' => $candidate->id,
            'file_name' => $document->file_name,
        ], $request);

        return Storage::disk('local')->download($document->storage_path, $name);
    }

    /**
     * Open the CV in the browser tab (PDF / scanned image) instead of forcing a download — the step a
     * recruiter takes right after finding a candidate. Word files can't be rendered by a browser, so
     * those fall back to the download; their extracted text is shown on the candidate profile.
     */
    public function view(Request $request, Candidate $candidate, CandidateDocument $document, AuditService $audit): BinaryFileResponse|RedirectResponse
    {
        $this->authorizeDocument($candidate, $document);

        $absolute = Storage::disk('local')->path($document->storage_path);
        // Decide from the file's own bytes, never from the stored (client-supplied) mime type.
        $mime = $this->inlineMime($absolute);
        if ($mime === null) {
            return redirect()->route('candidates.documents.download', [$candidate, $document]);
        }

        $audit->log(Auth::id(), 'CV_DOCUMENT_VIEW', 'candidate_documents', (string) $document->id, [
            'candidate_id' => $candidate->id,
            'file_name' => $document->file_name,
        ], $request);

        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $document->file_name) ?: ('cv_'.$document->id);

        return response()->file($absolute, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.$name.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            // The app-wide CSP says object-src 'none', which stops Chrome's built-in PDF viewer from
            // rendering. This response contains no scripts of ours, so give it a minimal policy that allows it.
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; object-src 'self'",
        ]);
    }

    private function authorizeDocument(Candidate $candidate, CandidateDocument $document): void
    {
        if ($document->candidate_id !== $candidate->id) {
            abort(404);
        }
        if (Auth::user() && ! Auth::user()->isSuperAdmin() && $candidate->company_id !== Auth::user()->company_id) {
            abort(404);
        }
        if (! Storage::disk('local')->exists($document->storage_path)) {
            abort(404, 'File not found');
        }
    }

    private function inlineMime(string $absolutePath): ?string
    {
        $head = (string) @file_get_contents($absolutePath, false, null, 0, 8);

        return match (true) {
            str_starts_with($head, '%PDF') => 'application/pdf',
            str_starts_with($head, "\xFF\xD8\xFF") => 'image/jpeg',
            str_starts_with($head, "\x89PNG") => 'image/png',
            default => null,
        };
    }
}

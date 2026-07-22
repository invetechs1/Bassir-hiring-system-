<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\CandidateDocument;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CandidateDocumentController extends Controller
{
    public function download(Request $request, Candidate $candidate, CandidateDocument $document, AuditService $audit): StreamedResponse
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
}

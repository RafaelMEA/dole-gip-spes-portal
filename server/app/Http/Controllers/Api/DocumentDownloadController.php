<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApplicationDocument;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentDownloadController extends Controller
{
    /**
     * Stream a stored document to the requesting user (applicant or staff).
     */
    public function download(ApplicationDocument $document): StreamedResponse|Response
    {
        $this->authorize('view', $document);

        if (! Storage::disk('docs')->exists($document->file_path)) {
            abort(404, 'The document file no longer exists.');
        }

        return Storage::disk('docs')->download($document->file_path, $document->file_name);
    }
}

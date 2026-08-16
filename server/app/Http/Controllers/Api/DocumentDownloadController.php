<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApplicationDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentDownloadController extends Controller
{
    /**
     * Stream a stored document to the requesting user (applicant or staff).
     *
     * By default the file is served as a download attachment. Requesting
     * ?disposition=inline serves it with an inline Content-Disposition so
     * authorized users can preview the document in the browser. Both paths
     * are authorized the same way and never expose the underlying storage
     * path to the client.
     */
    public function download(ApplicationDocument $document, Request $request): StreamedResponse|Response
    {
        $this->authorize('view', $document);

        if (! Storage::disk('docs')->exists($document->file_path)) {
            abort(404, 'The document file no longer exists.');
        }

        if ($request->query('disposition') === 'inline') {
            return Storage::disk('docs')->response($document->file_path, $document->file_name);
        }

        return Storage::disk('docs')->download($document->file_path, $document->file_name);
    }
}

<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Resources\ApplicationDocumentResource;
use App\Models\Application;
use App\Models\ApplicationDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    /**
     * List the documents uploaded to one of the student's applications.
     */
    public function index(Application $application, Request $request)
    {
        $this->authorize('view', $application);

        $documents = $application->documents()
            ->with(['requirement', 'verifiedBy'])
            ->orderByDesc('uploaded_at')
            ->get();

        return ApplicationDocumentResource::collection($documents);
    }

    /**
     * Upload a requirement document to an application.
     */
    public function store(Application $application, StoreDocumentRequest $request)
    {
        $file = $request->file('file');

        $path = $file->storeAs(
            'applications/'.$application->id,
            Str::uuid()->toString().'.'.$file->getClientOriginalExtension(),
            'docs',
        );

        $document = ApplicationDocument::create([
            'application_id' => $application->id,
            'requirement_id' => $request->input('requirement_id'),
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'verification_status' => 'pending',
            'uploaded_at' => now(),
        ]);

        return (new ApplicationDocumentResource($document->load('requirement')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Remove an unverified document the student uploaded.
     */
    public function destroy(Application $application, ApplicationDocument $document): Response
    {
        abort_if($document->application_id !== $application->id, 404);

        $this->authorize('delete', $document);

        Storage::disk('docs')->delete($document->file_path);
        $document->delete();

        return response()->noContent();
    }
}

<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Resources\ApplicationDocumentResource;
use App\Models\Application;
use App\Models\ApplicationDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

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
     * Upload (or replace) a requirement document on an application.
     *
     * Uploading again for the same requirement replaces the previous file.
     * The stored file name is derived from the detected content, never from
     * the client-supplied file name, and the previous file is only removed
     * after the database has been updated successfully.
     */
    public function store(Application $application, StoreDocumentRequest $request)
    {
        $file = $request->file('file');
        $requirementId = $request->validated('requirement_id');

        $storedPath = $this->storeFile($file, $application);

        try {
            [$document, $previousPath] = DB::transaction(function () use ($application, $file, $requirementId, $storedPath) {
                $existing = $application->documents()
                    ->where('requirement_id', $requirementId)
                    ->first();

                $attributes = [
                    'file_path' => $storedPath,
                    'file_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'verification_status' => 'pending',
                    'rejection_reason' => null,
                    'verified_by' => null,
                    'verified_at' => null,
                    'uploaded_at' => now(),
                ];

                if ($existing !== null) {
                    $previousPath = $existing->getOriginal('file_path');
                    $existing->update($attributes);

                    return [$existing, $previousPath];
                }

                $document = $application->documents()->create([
                    'requirement_id' => $requirementId,
                    ...$attributes,
                ]);

                return [$document, null];
            });
        } catch (Throwable $e) {
            Storage::disk('docs')->delete($storedPath);

            throw $e;
        }

        if ($previousPath !== null && $previousPath !== $storedPath) {
            Storage::disk('docs')->delete($previousPath);
        }

        return (new ApplicationDocumentResource($document->load('requirement')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Persist the uploaded file with an extension derived from its detected
     * content so a client cannot disguise an executable as an allowed file.
     */
    private function storeFile(UploadedFile $file, Application $application): string
    {
        $extension = strtolower((string) $file->guessExtension());

        if (! preg_match('/^[a-z0-9]+$/', $extension)) {
            $extension = 'bin';
        }

        return $file->storeAs(
            'applications/'.$application->id,
            Str::uuid()->toString().'.'.$extension,
            'docs',
        );
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

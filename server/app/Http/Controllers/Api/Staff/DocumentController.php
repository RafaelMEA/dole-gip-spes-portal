<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\VerifyDocumentRequest;
use App\Http\Resources\ApplicationDocumentResource;
use App\Models\Application;
use App\Models\ApplicationDocument;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    /**
     * List all documents for an application under review.
     */
    public function index(Application $application, Request $request)
    {
        $this->authorize('view', $application);

        $documents = $application->documents()
            ->with(['requirement', 'verifiedBy'])
            ->orderBy('uploaded_at')
            ->get();

        return ApplicationDocumentResource::collection($documents);
    }

    /**
     * Verify or reject a submitted document.
     */
    public function verify(Application $application, ApplicationDocument $document, VerifyDocumentRequest $request)
    {
        abort_if($document->application_id !== $application->id, 404);

        $this->authorize('verify', $document);

        if ($request->validated('verification_status') === 'verified') {
            $document->verify($request->user()->id);
        } else {
            $document->reject($request->user()->id, $request->validated('rejection_reason'));
        }

        return new ApplicationDocumentResource($document->load(['requirement', 'verifiedBy']));
    }
}

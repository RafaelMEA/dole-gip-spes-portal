<?php

namespace App\Http\Controllers\Api\Staff;

use App\Enums\DocumentVerificationStatus;
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
     *
     * The document is resolved both through the application route parameter
     * and the document route parameter, and the two are cross-checked so a
     * staff member can never touch a document that does not belong to the
     * application they are reviewing (IDOR protection). Only pending
     * documents may be decided; already-decided documents are immutable.
     */
    public function update(
        Application $application,
        ApplicationDocument $document,
        VerifyDocumentRequest $request,
    ) {
        abort_if($document->application_id !== $application->id, 404);

        $this->authorize('verify', $document);

        abort_if(
            $document->verification_status !== DocumentVerificationStatus::Pending,
            422,
            'Only pending documents can be verified or rejected.',
        );

        if ($request->validated('verification_status') === DocumentVerificationStatus::Verified->value) {
            $document->verify($request->user()->id);
        } else {
            $document->reject($request->user()->id, (string) $request->validated('rejection_reason'));
        }

        return new ApplicationDocumentResource($document->load(['requirement', 'verifiedBy']));
    }
}

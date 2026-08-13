<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewApplicationRequest;
use App\Http\Resources\ApplicationResource;
use App\Models\Application;
use App\Services\ApplicationService;
use DomainException;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    public function __construct(
        private readonly ApplicationService $applications,
    ) {
    }

    /**
     * The review queue, filterable by status, cycle, and applicant name.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Application::class);

        $query = Application::with(['applicant', 'programCycle.program'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('program_cycle_id'), fn ($q) => $q->where('program_cycle_id', $request->input('program_cycle_id')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = $request->input('search');
                $q->whereHas('applicant', fn ($a) => $a->where('name', 'like', "%{$term}%"));
            })
            ->orderByDesc('created_at');

        return ApplicationResource::collection($query->paginate($request->integer('per_page', 15)));
    }

    /**
     * Full application detail for staff review.
     */
    public function show(Application $application)
    {
        $this->authorize('view', $application);

        $application->load([
            'applicant.studentDetail',
            'programCycle.program',
            'programCycle.requirements',
            'documents.requirement',
            'documents.verifiedBy',
            'statusHistory.changedBy',
            'deploymentAssignment.hostAgency',
            'deploymentAssignment.deploymentSite',
        ]);

        return new ApplicationResource($application);
    }

    /**
     * Perform a workflow action on an application.
     */
    public function review(Application $application, ReviewApplicationRequest $request)
    {
        $this->authorize('review', $application);

        $action = $request->validated('action');
        $remarks = $request->input('remarks');

        try {
            $application = match ($action) {
                'start_review' => $this->applications->startReview($application, $request->user(), $remarks),
                'request_documents' => $this->applications->requestDocuments($application, $request->user(), $remarks ?? ''),
                'approve' => $this->applications->approve($application, $request->user(), $remarks),
                'reject' => $this->applications->reject($application, $request->user(), $remarks ?? ''),
                'schedule_deployment' => $this->applications->scheduleForDeployment($application, $request->user(), $remarks),
                'deploy' => $this->applications->deploy($application, $request->user(), $remarks),
                'complete' => $this->applications->complete($application, $request->user(), $remarks),
                default => $application,
            };
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new ApplicationResource($application->load([
            'applicant',
            'programCycle.program',
            'documents',
            'statusHistory.changedBy',
            'deploymentAssignment',
        ]));
    }
}

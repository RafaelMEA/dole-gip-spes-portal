<?php

namespace App\Http\Controllers\Api\Student;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Exceptions\IncompleteApplicationException;
use App\Http\Requests\StoreApplicationRequest;
use App\Http\Requests\UpdateApplicationRequest;
use App\Http\Resources\ApplicationHistoryEventResource;
use App\Http\Resources\ApplicationResource;
use App\Models\Application;
use App\Services\ApplicationCompletenessService;
use App\Services\ApplicationHistoryService;
use App\Services\ApplicationService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ApplicationController extends Controller
{
    public function __construct(
        private readonly ApplicationService $applications,
        private readonly ApplicationCompletenessService $completeness,
        private readonly ApplicationHistoryService $history,
    ) {}

    /**
     * List the authenticated student's applications.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Application::class);

        $applications = Application::with(['programCycle.program', 'deploymentAssignment'])
            ->where('applicant_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return ApplicationResource::collection($applications);
    }

    /**
     * Start a new application for an open program cycle.
     */
    public function store(StoreApplicationRequest $request)
    {
        $this->authorize('create', Application::class);

        $application = Application::create([
            'applicant_id' => $request->user()->id,
            'program_cycle_id' => $request->validated('program_cycle_id'),
            'status' => 'draft',
        ]);

        return (new ApplicationResource($application->load(['programCycle.program'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show one of the student's applications with all details.
     */
    public function show(Application $application)
    {
        $this->authorize('view', $application);

        $application->load([
            'programCycle.program',
            'programCycle.requirements',
            'documents.requirement',
            'documents.verifiedBy',
            'statusHistory.changedBy',
            'deploymentAssignment.hostAgency',
            'deploymentAssignment.deploymentSite',
            'deploymentAssignment.deploymentSlot',
            'decidedBy',
        ]);

        return new ApplicationResource($application);
    }

    /**
     * Update an owned draft application.
     */
    public function update(UpdateApplicationRequest $request, Application $application)
    {
        $this->authorize('update', $application);

        $application->fill($request->validated())->save();

        return new ApplicationResource($application->load(['programCycle.program']));
    }

    /**
     * The student-facing completeness summary for one of the student's
     * applications. This is advisory for UX; the backend re-validates
     * everything during submission.
     */
    public function completeness(Application $application)
    {
        $this->authorize('view', $application);

        return response()->json([
            'data' => $this->completeness->summarize($application),
        ]);
    }

    /**
     * Submit a draft application for review. Re-submitting after a correction
     * request is recorded as a distinct "resubmit" event in the history.
     */
    public function submit(Application $application, Request $request)
    {
        $this->authorize('submit', $application);

        $isResubmission = in_array($application->status->value, [
            ApplicationStatus::ReturnedForCorrection->value,
            ApplicationStatus::DocumentsIncomplete->value,
        ], true);

        try {
            if ($isResubmission) {
                $this->applications->resubmit($application, $request->user(), $request->input('remarks'));
            } else {
                $this->applications->submit($application, $request->user(), $request->input('remarks'));
            }
        } catch (IncompleteApplicationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => [
                    'application' => $e->missingApplicationFields() === []
                        ? []
                        : ['Required application information is incomplete: '.implode(', ', $e->missingApplicationFields()).'.'],
                    'documents' => $e->missingRequirements() === []
                        ? []
                        : ['Missing required documents: '.implode(', ', array_column($e->missingRequirements(), 'name')).'.'],
                ],
                'data' => [
                    'is_complete' => false,
                    'application_complete' => $e->missingApplicationFields() === [],
                    'documents_complete' => $e->missingRequirements() === [],
                    'missing_application_fields' => $e->missingApplicationFields(),
                    'missing_requirements' => $e->missingRequirements(),
                ],
            ], 422);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new ApplicationResource($application->load(['programCycle.program', 'statusHistory']));
    }

    /**
     * Withdraw a submitted/draft application.
     */
    public function withdraw(Application $application, Request $request)
    {
        $this->authorize('withdraw', $application);

        try {
            $this->applications->withdraw($application, $request->user(), $request->input('remarks'));
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new ApplicationResource($application->load(['programCycle.program', 'statusHistory']));
    }

    /**
     * The student-facing status history for one of the student's own
     * applications. Internal audit details are excluded; students see each
     * transition, when it happened, who performed it and any reason recorded
     * for them.
     */
    public function history(Application $application, Request $request)
    {
        $this->authorize('view', $application);

        $timeline = $this->history->studentTimeline(
            $application,
            page: $request->integer('page', 1),
            perPage: $request->integer('per_page', ApplicationHistoryService::DEFAULT_PER_PAGE),
        );

        return ApplicationHistoryEventResource::collection($timeline);
    }

    /**
     * Delete a draft application.
     */
    public function destroy(Application $application): Response
    {
        $this->authorize('delete', $application);

        $application->delete();

        return response()->noContent();
    }
}

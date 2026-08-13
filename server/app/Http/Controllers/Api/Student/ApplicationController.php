<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreApplicationRequest;
use App\Http\Resources\ApplicationResource;
use App\Models\Application;
use App\Services\ApplicationService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ApplicationController extends Controller
{
    public function __construct(
        private readonly ApplicationService $applications,
    ) {
    }

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
        ]);

        return new ApplicationResource($application);
    }

    /**
     * Submit a draft application for review.
     */
    public function submit(Application $application, Request $request)
    {
        $this->authorize('submit', $application);

        try {
            $this->applications->submit($application, $request->user(), $request->input('remarks'));
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
     * Delete a draft application.
     */
    public function destroy(Application $application): Response
    {
        $this->authorize('delete', $application);

        $application->delete();

        return response()->noContent();
    }
}

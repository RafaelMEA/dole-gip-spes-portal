<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeploymentAssignmentRequest;
use App\Http\Resources\DeploymentAssignmentResource;
use App\Models\Application;
use App\Models\DeploymentAssignment;
use App\Services\ApplicationService;
use DomainException;
use Illuminate\Http\Request;

class DeploymentController extends Controller
{
    public function __construct(
        private readonly ApplicationService $applications,
    ) {
    }

    /**
     * List deployment assignments.
     */
    public function index(Request $request)
    {
        $query = DeploymentAssignment::with([
            'hostAgency',
            'deploymentSite',
            'application.applicant',
        ])->orderByDesc('created_at');

        return DeploymentAssignmentResource::collection($query->paginate($request->integer('per_page', 15)));
    }

    /**
     * Schedule a deployment for an approved application.
     */
    public function store(StoreDeploymentAssignmentRequest $request)
    {
        $this->authorize('create', DeploymentAssignment::class);

        $application = Application::findOrFail($request->validated('application_id'));

        if ($application->status->value !== 'approved') {
            return response()->json([
                'message' => 'Only approved applications can be scheduled for deployment.',
            ], 422);
        }

        $assignment = DeploymentAssignment::create([
            'application_id' => $application->id,
            'host_agency_id' => $request->validated('host_agency_id'),
            'deployment_site_id' => $request->input('deployment_site_id'),
            'position' => $request->input('position'),
            'start_date' => $request->validated('start_date'),
            'end_date' => $request->input('end_date'),
            'status' => $request->input('status', 'scheduled'),
            'assigned_by' => $request->user()->id,
            'assigned_at' => now(),
            'remarks' => $request->input('remarks'),
        ]);

        try {
            $this->applications->scheduleForDeployment($application, $request->user());
        } catch (DomainException $e) {
            // Application already flagged for deployment; assignment still stands.
        }

        return (new DeploymentAssignmentResource($assignment->load([
            'hostAgency',
            'deploymentSite',
            'application.applicant',
        ])))->response()->setStatusCode(201);
    }

    /**
     * Update an assignment's status, which drives the application state.
     */
    public function update(DeploymentAssignment $assignment, Request $request)
    {
        $this->authorize('update', $assignment);

        $request->validate([
            'status' => ['required', 'in:scheduled,active,completed,cancelled'],
        ]);

        $status = $request->input('status');
        $application = $assignment->application;

        try {
            match ($status) {
                'active' => $this->applications->deploy($application, $request->user()),
                'completed' => $this->applications->complete($application, $request->user()),
                'cancelled' => $application->status = \App\Enums\ApplicationStatus::Approved,
                default => null,
            };
        } catch (DomainException) {
            // Assignment status is still updated below; application state is best-effort.
        }

        if ($status === 'cancelled') {
            $application->save();
        }

        $assignment->update(['status' => $status]);

        return new DeploymentAssignmentResource($assignment->load([
            'hostAgency',
            'deploymentSite',
            'application.applicant',
        ]));
    }
}

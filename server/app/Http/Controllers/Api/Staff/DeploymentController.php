<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignDeploymentRequest;
use App\Http\Requests\StoreDeploymentAssignmentRequest;
use App\Http\Resources\AuditLogResource;
use App\Http\Resources\DeploymentAssignmentResource;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\DeploymentAssignment;
use App\Models\DeploymentSlot;
use App\Services\ApplicationService;
use App\Services\AuditLogger;
use App\Services\DeploymentAssignmentService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeploymentController extends Controller
{
    public function __construct(
        private readonly ApplicationService $applications,
        private readonly DeploymentAssignmentService $deploymentAssignments,
    ) {
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', DeploymentAssignment::class);

        $query = DeploymentAssignment::with([
            'hostAgency',
            'deploymentSite',
            'deploymentSlot',
            'application.applicant',
            'assignedBy',
        ])->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('application.applicant', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('program_cycle_id')) {
            $query->whereHas('application', function ($q) use ($request) {
                $q->where('program_cycle_id', $request->input('program_cycle_id'));
            });
        }

        if ($request->filled('host_agency_id')) {
            $query->where('host_agency_id', $request->input('host_agency_id'));
        }

        if ($request->filled('deployment_site_id')) {
            $query->where('deployment_site_id', $request->input('deployment_site_id'));
        }

        return DeploymentAssignmentResource::collection($query->paginate($request->integer('per_page', 15)));
    }

    public function show(DeploymentAssignment $assignment)
    {
        $this->authorize('view', $assignment);

        $assignment->load([
            'hostAgency',
            'deploymentSite',
            'deploymentSlot.programCycle',
            'application.applicant',
            'assignedBy',
        ]);

        return new DeploymentAssignmentResource($assignment);
    }

    /**
     * The audit history of this deployment assignment, newest first.
     * Read-only.
     */
    public function history(DeploymentAssignment $assignment)
    {
        $this->authorize('view', $assignment);

        $logs = AuditLog::query()
            ->where('auditable_type', $assignment->getMorphClass())
            ->where('auditable_id', $assignment->id)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(min(max(1, request()->integer('per_page', 25)), 100))
            ->withQueryString();

        return AuditLogResource::collection($logs);
    }

    public function deploymentOptions(Application $application)
    {
        $this->authorize('create', DeploymentAssignment::class);

        try {
            $options = $this->deploymentAssignments->getDeploymentOptions($application);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $options]);
    }

    public function assign(Application $application, AssignDeploymentRequest $request)
    {
        $this->authorize('create', DeploymentAssignment::class);

        $slot = DeploymentSlot::findOrFail($request->validated('deployment_slot_id'));

        try {
            $assignment = $this->deploymentAssignments->assign(
                $application,
                $slot,
                $request->user(),
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return (new DeploymentAssignmentResource($assignment))->response()->setStatusCode(201);
    }

    public function cancel(DeploymentAssignment $assignment, Request $request)
    {
        $this->authorize('cancel', $assignment);

        $request->validate([
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $assignment = $this->deploymentAssignments->cancel(
                $assignment,
                $request->user(),
                $request->input('remarks'),
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new DeploymentAssignmentResource($assignment);
    }

    public function store(StoreDeploymentAssignmentRequest $request)
    {
        $this->authorize('create', DeploymentAssignment::class);

        $application = Application::findOrFail($request->validated('application_id'));

        if ($application->status->value !== 'approved') {
            return response()->json([
                'message' => 'Only approved applications can be scheduled for deployment.',
            ], 422);
        }

        $assignment = DB::transaction(function () use ($request, $application) {
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

            AuditLogger::log('assignment.created', $assignment, null, $assignment->only([
                'application_id', 'deployment_slot_id', 'host_agency_id',
                'deployment_site_id', 'position', 'start_date', 'status',
            ]));

            return $assignment;
        });

        try {
            $this->applications->scheduleForDeployment($application, $request->user());
        } catch (DomainException) {
            // Application already flagged for deployment; assignment still stands.
        }

        return (new DeploymentAssignmentResource($assignment->load([
            'hostAgency',
            'deploymentSite',
            'deploymentSlot',
            'application.applicant',
            'assignedBy',
        ])))->response()->setStatusCode(201);
    }

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

        DB::transaction(function () use ($assignment, $status) {
            $previousStatus = $assignment->status->value;

            $assignment->update(['status' => $status]);

            if ($previousStatus !== $status) {
                AuditLogger::log(
                    $status === 'cancelled' ? 'assignment.cancelled' : 'assignment.status_changed',
                    $assignment,
                    ['status' => $previousStatus],
                    ['status' => $status],
                );
            }
        });

        return new DeploymentAssignmentResource($assignment->load([
            'hostAgency',
            'deploymentSite',
            'deploymentSlot',
            'application.applicant',
            'assignedBy',
        ]));
    }
}

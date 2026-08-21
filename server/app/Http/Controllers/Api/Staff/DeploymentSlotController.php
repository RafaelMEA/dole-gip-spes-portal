<?php

namespace App\Http\Controllers\Api\Staff;

use App\Enums\DeploymentSlotStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeploymentSlotRequest;
use App\Http\Requests\UpdateDeploymentSlotRequest;
use App\Http\Resources\AuditLogResource;
use App\Http\Resources\DeploymentSlotResource;
use App\Models\AuditLog;
use App\Models\DeploymentSite;
use App\Models\DeploymentSlot;
use App\Models\HostAgency;
use App\Models\ProgramCycle;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeploymentSlotController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', DeploymentSlot::class);

        $query = DeploymentSlot::with(['programCycle', 'deploymentSite.hostAgency']);

        if ($request->filled('search')) {
            $search = $request->string('search')->trim()->lower();
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(deployment_slots.title) LIKE ?', ["%{$search}%"])
                    ->orWhereHas('deploymentSite', function ($sq) use ($search) {
                        $sq->whereRaw('LOWER(deployment_sites.name) LIKE ?', ["%{$search}%"]);
                    })
                    ->orWhereHas('deploymentSite.hostAgency', function ($hq) use ($search) {
                        $hq->whereRaw('LOWER(host_agencies.name) LIKE ?', ["%{$search}%"]);
                    });
            });
        }

        if ($request->filled('program_cycle_id')) {
            $query->where('program_cycle_id', $request->integer('program_cycle_id'));
        }

        if ($request->filled('deployment_site_id')) {
            $query->where('deployment_site_id', $request->integer('deployment_site_id'));
        }

        if ($request->filled('host_agency_id')) {
            $query->whereHas('deploymentSite', function ($q) use ($request) {
                $q->where('host_agency_id', $request->integer('host_agency_id'));
            });
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        $sort = $request->input('sort', 'created_at');
        $direction = $request->input('direction', 'desc');
        $allowedSorts = ['title', 'capacity', 'created_at', 'updated_at'];

        if (in_array($sort, $allowedSorts, true)) {
            $query->orderBy("deployment_slots.{$sort}", $direction === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderByDesc('deployment_slots.created_at');
        }

        $perPage = (int) $request->input('per_page', 20);
        $perPage = in_array($perPage, [10, 20, 50], true) ? $perPage : 20;

        return DeploymentSlotResource::collection($query->paginate($perPage));
    }

    public function show(DeploymentSlot $deploymentSlot)
    {
        $this->authorize('view', $deploymentSlot);

        $deploymentSlot->load(['programCycle', 'deploymentSite.hostAgency']);

        return new DeploymentSlotResource($deploymentSlot);
    }

    /**
     * The audit history of this deployment slot, newest first. Read-only.
     */
    public function history(DeploymentSlot $deploymentSlot)
    {
        $this->authorize('view', $deploymentSlot);

        $logs = AuditLog::query()
            ->where('auditable_type', $deploymentSlot->getMorphClass())
            ->where('auditable_id', $deploymentSlot->id)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(min(max(1, request()->integer('per_page', 25)), 100))
            ->withQueryString();

        return AuditLogResource::collection($logs);
    }

    public function store(StoreDeploymentSlotRequest $request)
    {
        $this->authorize('create', DeploymentSlot::class);

        $cycle = ProgramCycle::findOrFail($request->validated('program_cycle_id'));
        $site = DeploymentSite::findOrFail($request->validated('deployment_site_id'));

        if (! $site->is_active) {
            return response()->json([
                'message' => 'Cannot create a slot for an inactive deployment site.',
            ], 422);
        }

        $cycleStatus = $cycle->storedStatus()->value;
        if (in_array($cycleStatus, ['draft', 'archived'], true)) {
            return response()->json([
                'message' => 'Cannot create a slot for a draft or archived program cycle.',
            ], 422);
        }

        $slot = DB::transaction(function () use ($request) {
            $slot = DeploymentSlot::create($request->validated());

            AuditLogger::log('deployment_slot.created', $slot, null, $slot->only([
                'program_cycle_id', 'deployment_site_id', 'title', 'description', 'capacity', 'status',
            ]));

            return $slot;
        });

        return (new DeploymentSlotResource($slot->load(['programCycle', 'deploymentSite.hostAgency'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateDeploymentSlotRequest $request, DeploymentSlot $deploymentSlot)
    {
        $this->authorize('update', $deploymentSlot);

        $data = $request->validated();

        if (isset($data['deployment_site_id'])) {
            $site = DeploymentSite::findOrFail($data['deployment_site_id']);

            if (! $site->is_active) {
                return response()->json([
                    'message' => 'Cannot move a slot to an inactive deployment site.',
                ], 422);
            }
        }

        if (isset($data['capacity'])) {
            $assignedCount = $deploymentSlot->assigned_count;
            if ($data['capacity'] < $assignedCount) {
                return response()->json([
                    'message' => "Cannot reduce capacity below the current assigned count ({$assignedCount}).",
                ], 422);
            }
        }

        DB::transaction(function () use ($deploymentSlot, $data) {
            $oldValues = $deploymentSlot->only(array_keys($data));

            $deploymentSlot->update($data);

            $changed = $deploymentSlot->only(array_keys($data));
            if ($changed !== $oldValues) {
                AuditLogger::log('deployment_slot.updated', $deploymentSlot, $oldValues, $changed);
            }
        });

        return new DeploymentSlotResource($deploymentSlot->load(['programCycle', 'deploymentSite.hostAgency']));
    }

    public function updateStatus(Request $request, DeploymentSlot $deploymentSlot)
    {
        $this->authorize('changeStatus', $deploymentSlot);

        $request->validate([
            'status' => ['required', 'string', 'in:active,inactive'],
        ]);

        $status = DeploymentSlotStatus::from($request->input('status'));

        DB::transaction(function () use ($deploymentSlot, $status) {
            $previous = $deploymentSlot->storedStatus()->value;

            if ($previous === $status->value) {
                return;
            }

            $deploymentSlot->update(['status' => $status]);

            AuditLogger::log(
                $status === DeploymentSlotStatus::Active
                    ? 'deployment_slot.activated'
                    : 'deployment_slot.deactivated',
                $deploymentSlot,
                ['status' => $previous],
                ['status' => $status->value],
            );
        });

        return new DeploymentSlotResource($deploymentSlot->load(['programCycle', 'deploymentSite.hostAgency']));
    }
}

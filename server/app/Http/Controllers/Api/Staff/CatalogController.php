<?php

namespace App\Http\Controllers\Api\Staff;

use App\Enums\ProgramCycleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCycleRequirementRequest;
use App\Http\Requests\StoreDeploymentSiteRequest;
use App\Http\Requests\StoreHostAgencyRequest;
use App\Http\Requests\UpdateHostAgencyRequest;
use App\Http\Requests\StoreProgramCycleRequest;
use App\Http\Requests\StoreProgramRequest;
use App\Http\Requests\StoreRequirementRequest;
use App\Http\Resources\DeploymentSiteResource;
use App\Http\Resources\HostAgencyResource;
use App\Http\Resources\ProgramCycleResource;
use App\Http\Resources\ProgramResource;
use App\Http\Resources\RequirementResource;
use App\Models\DeploymentSite;
use App\Models\HostAgency;
use App\Models\Program;
use App\Models\ProgramCycle;
use App\Models\Requirement;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatalogController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Programs
    |--------------------------------------------------------------------------
    */

    public function programs(Request $request)
    {
        $this->authorize('viewAny', Program::class);

        return ProgramResource::collection(
            Program::with(['programCycles' => function ($q) {
                $q->with('requirements')->withCount('applications')->orderByDesc('application_deadline');
            }])->orderBy('name')->get(),
        );
    }

    public function showProgram(Program $program, Request $request)
    {
        $this->authorize('view', $program);

        $program->load(['programCycles' => function ($q) {
            $q->with('requirements')->withCount('applications')->orderByDesc('application_deadline');
        }]);

        return new ProgramResource($program);
    }

    public function storeProgram(StoreProgramRequest $request)
    {
        $this->authorize('create', Program::class);

        $program = Program::create($request->validated());

        return (new ProgramResource($program))->response()->setStatusCode(201);
    }

    public function updateProgram(Program $program, StoreProgramRequest $request)
    {
        $this->authorize('update', $program);

        $program->update($request->validated());

        return new ProgramResource($program);
    }

    public function destroyProgram(Program $program, Request $request)
    {
        $this->authorize('delete', $program);

        if ($program->programCycles()->exists()) {
            return response()->json([
                'message' => 'This program still has cycles. Delete or move its cycles first.',
            ], 422);
        }

        $program->delete();

        return response()->noContent();
    }

    /*
    |--------------------------------------------------------------------------
    | Program cycles
    |--------------------------------------------------------------------------
    */

    public function cycles(Request $request)
    {
        $this->authorize('viewAny', ProgramCycle::class);

        return ProgramCycleResource::collection(
            ProgramCycle::with(['program', 'requirements'])
                ->withCount('applications')
                ->orderByDesc('application_deadline')
                ->get(),
        );
    }

    public function showCycle(ProgramCycle $cycle, Request $request)
    {
        $this->authorize('view', $cycle);

        $cycle->load(['program', 'requirements']);
        $cycle->loadCount('applications');

        return new ProgramCycleResource($cycle);
    }

    public function storeCycle(StoreProgramCycleRequest $request)
    {
        $this->authorize('create', ProgramCycle::class);

        $data = $request->validated();
        unset($data['requirements']);

        $cycle = ProgramCycle::create($data + ['created_by' => $request->user()->id]);

        if ($request->filled('requirements')) {
            $cycle->requirements()->syncWithPivotValues(
                $request->input('requirements'),
                ['is_required' => true],
            );
        }

        return (new ProgramCycleResource($cycle->load('program', 'requirements')))
            ->response()
            ->setStatusCode(201);
    }

    public function updateCycle(ProgramCycle $cycle, StoreProgramCycleRequest $request)
    {
        $this->authorize('update', $cycle);

        $data = $request->validated();
        unset($data['requirements']);

        $cycle->update($data);

        if ($request->has('requirements')) {
            $cycle->requirements()->sync($request->input('requirements', []));
        }

        return new ProgramCycleResource($cycle->load('program', 'requirements'));
    }

    public function destroyCycle(ProgramCycle $cycle, Request $request)
    {
        $this->authorize('delete', $cycle);

        if ($cycle->applications()->exists()) {
            return response()->json([
                'message' => 'This cycle already has applications and cannot be deleted.',
            ], 422);
        }

        $cycle->delete();

        return response()->noContent();
    }

    public function publishCycle(ProgramCycle $cycle, Request $request)
    {
        $this->authorize('update', $cycle);

        if ($cycle->phaseStatus() === ProgramCycleStatus::Draft) {
            return response()->json([
                'message' => 'Set the application dates before publishing this cycle.',
            ], 422);
        }

        $cycle->status = $cycle->phaseStatus()->value;
        $cycle->save();

        return new ProgramCycleResource($cycle->load('program', 'requirements'));
    }

    public function unpublishCycle(ProgramCycle $cycle, Request $request)
    {
        $this->authorize('update', $cycle);

        $cycle->status = ProgramCycleStatus::Draft->value;
        $cycle->save();

        return new ProgramCycleResource($cycle->load('program', 'requirements'));
    }

    /*
    |--------------------------------------------------------------------------
    | Requirements
    |--------------------------------------------------------------------------
    */

    public function requirements(Request $request)
    {
        $this->authorize('viewAny', Requirement::class);

        return RequirementResource::collection(Requirement::orderBy('name')->get());
    }

    public function storeRequirement(StoreRequirementRequest $request)
    {
        $this->authorize('create', Requirement::class);

        $requirement = Requirement::create($request->validated());

        return (new RequirementResource($requirement))->response()->setStatusCode(201);
    }

    public function updateRequirement(Requirement $requirement, StoreRequirementRequest $request)
    {
        $this->authorize('update', $requirement);

        $requirement->update($request->validated());

        return new RequirementResource($requirement);
    }

    public function showRequirement(Requirement $requirement, Request $request)
    {
        $this->authorize('view', $requirement);

        return new RequirementResource($requirement);
    }

    public function destroyRequirement(Requirement $requirement, Request $request)
    {
        $this->authorize('delete', $requirement);

        if ($requirement->documents()->exists()) {
            return response()->json([
                'message' => 'This requirement is already referenced by uploaded documents and cannot be deleted. Deactivate it instead.',
            ], 422);
        }

        $requirement->delete();

        return response()->noContent();
    }

    /*
    |--------------------------------------------------------------------------
    | Cycle requirements
    |--------------------------------------------------------------------------
    */

    public function cycleRequirements(ProgramCycle $cycle, Request $request)
    {
        $this->authorize('view', $cycle);

        $requirements = $cycle->requirements()
            ->orderBy('requirements.id')
            ->get();

        return RequirementResource::collection($requirements);
    }

    public function storeCycleRequirement(StoreCycleRequirementRequest $request, ProgramCycle $cycle)
    {
        $this->authorize('create', Requirement::class);

        $isRequired = (bool) $request->input('is_required', true);

        if ($request->filled('requirement_id')) {
            $requirement = Requirement::findOrFail($request->integer('requirement_id'));

            if ($cycle->requirements()->whereKey($requirement->id)->exists()) {
                return response()->json([
                    'message' => 'This requirement is already attached to the cycle.',
                ], 422);
            }

            $cycle->requirements()->attach($requirement->id, ['is_required' => $isRequired]);

            $requirement = $cycle->requirements()->whereKey($requirement->id)->first();

            return (new RequirementResource($requirement))->response()->setStatusCode(201);
        }

        $data = $request->validated();
        unset($data['is_required']);

        $requirement = Requirement::create($data);
        $cycle->requirements()->attach($requirement->id, ['is_required' => $isRequired]);

        $requirement = $cycle->requirements()->whereKey($requirement->id)->first();

        return (new RequirementResource($requirement))->response()->setStatusCode(201);
    }

    public function updateCycleRequirement(StoreCycleRequirementRequest $request, ProgramCycle $cycle, Requirement $requirement)
    {
        $this->authorize('update', $requirement);

        if ($request->filled('requirement_id')) {
            return response()->json([
                'message' => 'Attaching an existing requirement is not allowed here.',
            ], 422);
        }

        if (! $cycle->requirements()->whereKey($requirement->id)->exists()) {
            abort(404);
        }

        $data = $request->validated();
        $isRequired = (bool) ($data['is_required'] ?? true);
        unset($data['is_required']);

        $requirement->update($data);
        $cycle->requirements()->updateExistingPivot($requirement->id, ['is_required' => $isRequired]);

        $requirement = $cycle->requirements()->whereKey($requirement->id)->first();

        return new RequirementResource($requirement);
    }

    public function destroyCycleRequirement(ProgramCycle $cycle, Requirement $requirement, Request $request)
    {
        $this->authorize('update', $requirement);

        if (! $cycle->requirements()->whereKey($requirement->id)->exists()) {
            abort(404);
        }

        $cycle->requirements()->detach($requirement->id);

        return response()->noContent();
    }

    /*
    |--------------------------------------------------------------------------
    | Host agencies
    |--------------------------------------------------------------------------
    */

    public function hostAgencies(Request $request)
    {
        $this->authorize('viewAny', HostAgency::class);

        $query = HostAgency::withCount(['deploymentAssignments as active_assignments_count']);

        if ($request->filled('search')) {
            $search = $request->string('search')->trim()->lower();
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(contact_person) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"]);
            });
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('is_active', $request->input('status') === 'active');
        }

        $sort = $request->input('sort', 'name');
        $direction = $request->input('direction', 'asc');
        $allowedSorts = ['name', 'created_at', 'updated_at'];

        if (in_array($sort, $allowedSorts, true)) {
            $query->orderBy($sort, $direction === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('name');
        }

        $perPage = (int) $request->input('per_page', 20);
        $perPage = in_array($perPage, [10, 20, 50], true) ? $perPage : 20;

        return HostAgencyResource::collection($query->paginate($perPage));
    }

    public function showHostAgency(HostAgency $agency)
    {
        $this->authorize('view', $agency);

        $agency->loadCount(['deploymentAssignments as active_assignments_count']);

        return new HostAgencyResource($agency);
    }

    public function storeHostAgency(StoreHostAgencyRequest $request)
    {
        $this->authorize('create', HostAgency::class);

        $agency = DB::transaction(function () use ($request) {
            $agency = HostAgency::create($request->validated());

            AuditLogger::log('host_agency.created', $agency, null, $agency->only([
                'name', 'agency_type', 'address', 'contact_person', 'contact_number', 'email', 'is_active',
            ]));

            return $agency;
        });

        return (new HostAgencyResource($agency))->response()->setStatusCode(201);
    }

    public function updateHostAgency(HostAgency $agency, UpdateHostAgencyRequest $request)
    {
        $this->authorize('update', $agency);

        DB::transaction(function () use ($agency, $request) {
            $data = $request->validated();
            $oldValues = $agency->only(array_keys($data));

            $agency->update($data);

            $changed = $agency->only(array_keys($data));
            if ($changed !== $oldValues) {
                AuditLogger::log('host_agency.updated', $agency, $oldValues, $changed);
            }
        });

        return new HostAgencyResource($agency);
    }

    public function updateHostAgencyStatus(HostAgency $agency, Request $request)
    {
        $this->authorize('manage', $agency);

        $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($agency, $request) {
            $isActive = $request->boolean('is_active');
            $previous = (bool) $agency->is_active;

            if ($previous === $isActive) {
                return;
            }

            $agency->update(['is_active' => $isActive]);

            AuditLogger::log(
                $isActive ? 'host_agency.activated' : 'host_agency.deactivated',
                $agency,
                ['is_active' => $previous],
                ['is_active' => $isActive],
            );
        });

        return new HostAgencyResource($agency);
    }

    /*
    |--------------------------------------------------------------------------
    | Deployment sites
    |--------------------------------------------------------------------------
    */

    public function deploymentSites(Request $request)
    {
        $this->authorize('viewAny', DeploymentSite::class);

        $query = DeploymentSite::with('hostAgency');

        if ($request->filled('search')) {
            $search = $request->string('search')->trim()->lower();
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(deployment_sites.name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(deployment_sites.address) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(deployment_sites.contact_person) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(deployment_sites.email) LIKE ?', ["%{$search}%"])
                    ->orWhereHas('hostAgency', function ($hq) use ($search) {
                        $hq->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
                    });
            });
        }

        if ($request->filled('host_agency_id')) {
            $query->where('host_agency_id', $request->integer('host_agency_id'));
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('is_active', $request->input('status') === 'active');
        }

        $sort = $request->input('sort', 'name');
        $direction = $request->input('direction', 'asc');
        $allowedSorts = ['name', 'created_at', 'updated_at'];

        if (in_array($sort, $allowedSorts, true)) {
            $query->orderBy("deployment_sites.{$sort}", $direction === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('deployment_sites.name');
        }

        $perPage = (int) $request->input('per_page', 20);
        $perPage = in_array($perPage, [10, 20, 50], true) ? $perPage : 20;

        return DeploymentSiteResource::collection($query->paginate($perPage));
    }

    public function showDeploymentSite(DeploymentSite $site)
    {
        $this->authorize('view', $site);

        $site->load('hostAgency');

        return new DeploymentSiteResource($site);
    }

    public function storeDeploymentSite(StoreDeploymentSiteRequest $request)
    {
        $this->authorize('create', DeploymentSite::class);

        $site = DB::transaction(function () use ($request) {
            $site = DeploymentSite::create($request->validated());

            AuditLogger::log('deployment_site.created', $site, null, $site->only([
                'host_agency_id', 'name', 'address', 'city', 'region',
                'contact_person', 'contact_number', 'email', 'description', 'is_active',
            ]));

            return $site;
        });

        return (new DeploymentSiteResource($site->load('hostAgency')))->response()->setStatusCode(201);
    }

    public function updateDeploymentSite(DeploymentSite $site, StoreDeploymentSiteRequest $request)
    {
        $this->authorize('update', $site);

        DB::transaction(function () use ($site, $request) {
            $data = $request->validated();
            $oldValues = $site->only(array_keys($data));

            $site->update($data);

            $changed = $site->only(array_keys($data));
            if ($changed !== $oldValues) {
                AuditLogger::log('deployment_site.updated', $site, $oldValues, $changed);
            }
        });

        return new DeploymentSiteResource($site->load('hostAgency'));
    }

    public function updateDeploymentSiteStatus(DeploymentSite $site, Request $request)
    {
        $this->authorize('manage', $site);

        $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($site, $request) {
            $isActive = $request->boolean('is_active');
            $previous = (bool) $site->is_active;

            if ($previous === $isActive) {
                return;
            }

            $site->update(['is_active' => $isActive]);

            AuditLogger::log(
                $isActive ? 'deployment_site.activated' : 'deployment_site.deactivated',
                $site,
                ['is_active' => $previous],
                ['is_active' => $isActive],
            );
        });

        return new DeploymentSiteResource($site->load('hostAgency'));
    }
}

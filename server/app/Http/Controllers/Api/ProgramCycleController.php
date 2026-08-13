<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProgramCycleResource;
use App\Http\Resources\RequirementResource;
use App\Models\ProgramCycle;
use Illuminate\Http\Request;

class ProgramCycleController extends Controller
{
    /**
     * List published cycles the student may browse.
     */
    public function index(Request $request)
    {
        $cycles = ProgramCycle::with(['program', 'requirements'])
            ->withCount('applications')
            ->whereIn('status', ['upcoming', 'open', 'closed'])
            ->whereHas('program', fn ($query) => $query->where('is_active', true))
            ->orderByDesc('application_deadline')
            ->get();

        return ProgramCycleResource::collection($cycles);
    }

    /**
     * Show a single published cycle.
     */
    public function show(ProgramCycle $cycle)
    {
        if (! $cycle->isPublished() || ! $cycle->program->is_active) {
            abort(404);
        }

        $cycle->load(['program', 'requirements']);
        $cycle->loadCount('applications');

        return new ProgramCycleResource($cycle);
    }

    /**
     * List the active requirements a student may browse for a published cycle.
     */
    public function requirements(ProgramCycle $cycle)
    {
        if (! $cycle->isPublished() || ! $cycle->program->is_active) {
            abort(404);
        }

        $requirements = $cycle->requirements()
            ->where('requirements.is_active', true)
            ->orderBy('requirements.id')
            ->get();

        return RequirementResource::collection($requirements);
    }
}

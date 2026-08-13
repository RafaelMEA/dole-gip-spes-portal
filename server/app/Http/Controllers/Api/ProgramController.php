<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProgramResource;
use App\Models\Program;
use Illuminate\Http\Request;

class ProgramController extends Controller
{
    /**
     * List active programs with their published cycles.
     */
    public function index(Request $request)
    {
        $programs = Program::with([
            'programCycles' => function ($query) {
                $query->published()
                    ->with('requirements')
                    ->withCount('applications')
                    ->orderByDesc('application_deadline');
            },
        ])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return ProgramResource::collection($programs);
    }

    /**
     * Show a single active program with its published cycles.
     */
    public function show(Program $program)
    {
        $this->authorize('view', $program);

        abort_unless($program->is_active, 404);

        $program->load(['programCycles' => function ($query) {
            $query->published()
                ->with('requirements')
                ->withCount('applications')
                ->orderByDesc('application_deadline');
        }]);

        return new ProgramResource($program);
    }
}

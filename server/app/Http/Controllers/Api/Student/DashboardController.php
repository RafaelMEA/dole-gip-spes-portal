<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApplicationResource;
use App\Http\Resources\ProgramCycleResource;
use App\Models\Application;
use App\Models\ProgramCycle;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Student home: status summary plus open cycles.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $applications = Application::with(['programCycle.program'])
            ->where('applicant_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        $openCycles = ProgramCycle::with(['program', 'requirements'])
            ->withCount('applications')
            ->whereHas('program', fn ($q) => $q->where('is_active', true))
            ->get()
            ->filter(fn ($cycle) => $cycle->isAcceptingApplications() && $cycle->slots_remaining > 0)
            ->sortBy('application_deadline')
            ->take(3)
            ->values();

        return response()->json([
            'data' => [
                'stats' => [
                    'total_applications' => $applications->count(),
                    'draft_applications' => $applications->where('status', 'draft')->count(),
                    'active_applications' => $applications->reject(
                        fn ($a) => in_array($a->status->value, ['rejected', 'withdrawn', 'completed'], true),
                    )->count(),
                ],
                'applications' => ApplicationResource::collection($applications->take(5)->values()),
                'open_cycles' => ProgramCycleResource::collection($openCycles),
            ],
        ]);
    }
}

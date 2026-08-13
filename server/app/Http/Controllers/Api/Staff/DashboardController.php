<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApplicationResource;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\DeploymentAssignment;
use App\Models\ProgramCycle;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Staff home: overview counts across the review pipeline.
     */
    public function index(Request $request)
    {
        $stats = [
            'total_applications' => Application::count(),
            'pending_review' => Application::whereIn('status', ['submitted', 'under_review'])->count(),
            'documents_pending' => ApplicationDocument::where('verification_status', 'pending')->count(),
            'approved' => Application::where('status', 'approved')->count(),
            'deployed' => Application::where('status', 'deployed')->count(),
            'active_assignments' => DeploymentAssignment::whereIn('status', ['scheduled', 'active'])->count(),
            'open_cycles' => ProgramCycle::get()->filter->isAcceptingApplications()->count(),
        ];

        $recent = Application::with(['applicant', 'programCycle.program'])
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        $queue = Application::with(['applicant', 'programCycle.program'])
            ->whereIn('status', ['submitted', 'under_review', 'documents_incomplete'])
            ->orderBy('submitted_at')
            ->limit(5)
            ->get();

        return response()->json([
            'data' => [
                'stats' => $stats,
                'recent_applications' => ApplicationResource::collection($recent),
                'review_queue' => ApplicationResource::collection($queue),
            ],
        ]);
    }
}

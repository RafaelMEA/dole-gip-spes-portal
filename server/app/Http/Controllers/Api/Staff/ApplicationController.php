<?php

namespace App\Http\Controllers\Api\Staff;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListApplicationsRequest;
use App\Http\Requests\ReviewApplicationRequest;
use App\Http\Resources\ApplicationResource;
use App\Http\Resources\ApplicationResourceCollection;
use App\Models\Application;
use App\Services\ApplicationService;
use DomainException;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    public function __construct(
        private readonly ApplicationService $applications,
    ) {
    }

    /**
     * The staff application dashboard: searchable, filterable, sortable and
     * paginated. The default view prioritises submitted applications, newest
     * submitted first.
     */
    public function index(ListApplicationsRequest $request)
    {
        $this->authorize('viewAny', Application::class);

        $status = $request->validated('status');
        $sort = $request->validated('sort') ?? 'submitted_at';
        $direction = $request->validated('direction') ?? 'desc';

        $query = Application::with(['applicant', 'programCycle.program']);

        if ($status === null) {
            // Default view: submitted applications, newest first.
            $query->where('status', ApplicationStatus::Submitted->value);
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        $query
            ->when($request->filled('program_id'), function ($q) use ($request) {
                $q->whereHas('programCycle', fn ($cycle) => $cycle->where('program_id', $request->integer('program_id')));
            })
            ->when($request->filled('program_cycle_id'), function ($q) use ($request) {
                $q->where('program_cycle_id', $request->integer('program_cycle_id'));
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = trim((string) $request->validated('search'));

                if ($term === '') {
                    return;
                }

                $pattern = '%'.self::escapeLike($term).'%';

                $q->where(function ($inner) use ($pattern, $term) {
                    $inner->whereHas('applicant', fn ($applicant) => $applicant
                        ->where('name', 'like', $pattern)
                        ->orWhere('email', 'like', $pattern));

                    if (ctype_digit($term)) {
                        $inner->orWhere('applications.id', (int) $term);
                    }
                });
            })
            ->when($request->filled('submitted_from'), function ($q) use ($request) {
                $q->whereDate('submitted_at', '>=', $request->validated('submitted_from'));
            })
            ->when($request->filled('submitted_to'), function ($q) use ($request) {
                $q->whereDate('submitted_at', '<=', $request->validated('submitted_to'));
            })
            ->orderBy($sort, $direction)
            ->orderBy('id', $direction === 'asc' ? 'asc' : 'desc');

        return new ApplicationResourceCollection(
            $query->paginate($request->validated('per_page') ?? 20)->withQueryString(),
        );
    }

    /**
     * Full application detail for staff review.
     */
    public function show(Application $application)
    {
        $this->authorize('view', $application);

        $application->load([
            'applicant.studentDetail',
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
     * Perform a workflow action on an application.
     */
    public function review(Application $application, ReviewApplicationRequest $request)
    {
        $this->authorize('review', $application);

        $action = $request->validated('action');
        $remarks = $request->input('remarks');

        try {
            $application = match ($action) {
                'start_review' => $this->applications->startReview($application, $request->user(), $remarks),
                'request_documents' => $this->applications->requestDocuments($application, $request->user(), $remarks ?? ''),
                'approve' => $this->applications->approve($application, $request->user(), $remarks),
                'reject' => $this->applications->reject($application, $request->user(), $remarks ?? ''),
                'schedule_deployment' => $this->applications->scheduleForDeployment($application, $request->user(), $remarks),
                'deploy' => $this->applications->deploy($application, $request->user(), $remarks),
                'complete' => $this->applications->complete($application, $request->user(), $remarks),
                default => $application,
            };
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new ApplicationResource($application->load([
            'applicant',
            'programCycle.program',
            'documents',
            'statusHistory.changedBy',
            'deploymentAssignment',
        ]));
    }

    /**
     * Escape LIKE wildcards so user-supplied search terms are matched
     * literally instead of acting as SQL wildcards.
     */
    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}

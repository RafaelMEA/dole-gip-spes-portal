<?php

namespace Tests\Feature;

use App\Events\ApplicationApproved;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\DeploymentAssignment;
use App\Models\DeploymentSite;
use App\Models\DeploymentSlot;
use App\Models\HostAgency;
use App\Models\ProgramCycle;
use App\Models\Requirement;
use App\Models\StudentDetail;
use App\Models\User;
use App\Services\ApplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Concerns\SpaAuthentication;
use Tests\TestCase;

class NotificationWorkflowTest extends TestCase
{
    use RefreshDatabase, SpaAuthentication;

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * A fully eligible application: complete profile, open cycle with required
     * requirements and verified documents — submittable and approvable.
     */
    private function eligibleApplication(User $student): Application
    {
        StudentDetail::factory()->create(['user_id' => $student->id]);

        $cycle = ProgramCycle::factory()->open()->create();
        $cycle->requirements()->syncWithPivotValues(
            Requirement::factory()->count(2)->create()->pluck('id'),
            ['is_required' => true],
        );

        $application = Application::factory()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);

        foreach ($cycle->requirements as $requirement) {
            ApplicationDocument::factory()->verified()->create([
                'application_id' => $application->id,
                'requirement_id' => $requirement->id,
            ]);
        }

        return $application->refresh();
    }

    private function submitAs(User $student, Application $application)
    {
        Storage::fake('docs');

        return $this->fromSpa()
            ->postJson("/api/student/applications/{$application->id}/submit");
    }

    /**
     * Assert the user has exactly one notification of the given type.
     */
    private function assertNotification(User $user, string $type, int $expectedCount = 1): void
    {
        $found = $user->notifications()
            ->get()
            ->filter(fn ($n) => data_get($n->data, 'type') === $type);

        $this->assertSame(
            $expectedCount,
            $found->count(),
            "Expected {$expectedCount} \"{$type}\" notification(s) for user {$user->id}, found {$found->count()}.",
        );
    }

    private function notificationFor(User $user, string $type)
    {
        return $user->notifications()
            ->get()
            ->first(fn ($n) => data_get($n->data, 'type') === $type);
    }

    // ============================================================
    // APPLICATION SUBMISSION
    // ============================================================

    public function test_submitting_an_application_notifies_the_student(): void
    {
        $student = $this->loginAsStudent();
        $application = $this->eligibleApplication($student);

        $this->submitAs($student, $application)->assertOk();

        $this->assertNotification($student, 'application.submitted');

        $data = data_get($this->notificationFor($student, 'application.submitted'), 'data');
        $this->assertSame('Application Submitted', $data['title']);
        $this->assertSame('/student/applications/'.$application->id, $data['action_url']);
        $this->assertSame($application->id, $data['application_id']);
    }

    public function test_submitting_an_application_notifies_staff_reviewers(): void
    {
        $student = User::factory()->student()->create();
        $staffA = User::factory()->staff()->create();
        $staffB = User::factory()->staff()->create();
        $otherStudent = User::factory()->student()->create();

        $this->loginAs($student)->assertOk();
        $application = $this->eligibleApplication($student);

        $this->submitAs($student, $application)->assertOk();

        foreach ([$staffA, $staffB] as $staff) {
            $this->assertNotification($staff, 'application.submitted');

            $data = data_get($this->notificationFor($staff, 'application.submitted'), 'data');
            $this->assertSame('New Application Submitted', $data['title']);
            $this->assertSame('/staff/applications/'.$application->id, $data['action_url']);
        }

        // Students other than the applicant are never notified.
        $this->assertNotification($otherStudent, 'application.submitted', 0);
    }

    public function test_submission_message_does_not_expose_student_identity(): void
    {
        $student = User::factory()->student()->create(['name' => 'Very Distinct Name']);
        $staff = User::factory()->staff()->create();

        $this->loginAs($student)->assertOk();
        $application = $this->eligibleApplication($student);

        $this->submitAs($student, $application)->assertOk();

        $data = data_get($this->notificationFor($staff, 'application.submitted'), 'data');

        $this->assertStringNotContainsString('Very Distinct Name', $data['message']);
        $this->assertStringNotContainsString($student->email, $data['message']);

        // Payload contains only whitelisted display/navigation keys.
        $this->assertEqualsCanonicalizing(
            ['type', 'title', 'message', 'action_url', 'application_id'],
            array_keys($data),
        );
    }

    // ============================================================
    // RETURN FOR CORRECTION / DOCUMENTS REQUESTED
    // ============================================================

    public function test_returning_for_correction_notifies_the_student(): void
    {
        $this->loginAsStaff();
        $student = User::factory()->student()->create();
        $application = Application::factory()->submitted()->create([
            'applicant_id' => $student->id,
        ]);

        $reason = 'Please correct your barangay clearance.';
        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'return_for_correction',
                'remarks' => $reason,
            ])
            ->assertOk();

        $this->assertNotification($student, 'application.returned_for_correction');

        $data = data_get($this->notificationFor($student, 'application.returned_for_correction'), 'data');
        $this->assertSame('Application Returned for Correction', $data['title']);
        $this->assertStringContainsString($reason, $data['message']);
        $this->assertSame('/student/applications/'.$application->id, $data['action_url']);
    }

    public function test_requesting_documents_notifies_the_student(): void
    {
        $this->loginAsStaff();
        $student = User::factory()->student()->create();
        $application = Application::factory()->submitted()->create([
            'applicant_id' => $student->id,
        ]);

        // documents_incomplete is only reachable from under_review.
        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', ['action' => 'start_review'])
            ->assertOk();

        $reason = 'Please upload a clearer copy of your COR.';
        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'request_documents',
                'remarks' => $reason,
            ])
            ->assertOk();

        $this->assertNotification($student, 'application.documents_requested');
    }

    public function test_correction_notifications_are_not_sent_to_staff(): void
    {
        $staff = $this->loginAsStaff();
        $student = User::factory()->student()->create();
        $application = Application::factory()->submitted()->create([
            'applicant_id' => $student->id,
        ]);

        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'return_for_correction',
                'remarks' => 'Fix your documents.',
            ])
            ->assertOk();

        $this->assertNotification($staff, 'application.returned_for_correction', 0);
        $this->assertNotification($student, 'application.returned_for_correction', 1);
    }

    // ============================================================
    // RESUBMISSION
    // ============================================================

    public function test_resubmission_notifies_staff_and_confirms_to_the_student(): void
    {
        $student = User::factory()->student()->create();
        $this->loginAs($student)->assertOk();
        $staff = User::factory()->staff()->create();

        StudentDetail::factory()->create(['user_id' => $student->id]);
        $cycle = ProgramCycle::factory()->open()->create();
        $cycle->requirements()->syncWithPivotValues(
            Requirement::factory()->count(1)->create()->pluck('id'),
            ['is_required' => true],
        );
        $application = Application::factory()->returnedForCorrection()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);

        Storage::fake('docs');
        foreach ($cycle->requirements as $requirement) {
            $this->fromSpa()
                ->postJson("/api/student/applications/{$application->id}/documents", [
                    'requirement_id' => $requirement->id,
                    'file' => UploadedFile::fake()->create($requirement->slug.'.pdf', 512),
                ])->assertStatus(201);
        }

        $this->fromSpa()
            ->postJson("/api/student/applications/{$application->id}/submit")
            ->assertOk();

        $this->assertNotification($staff, 'application.resubmitted');
        $this->assertNotification($student, 'application.resubmitted');

        $staffData = data_get($this->notificationFor($staff, 'application.resubmitted'), 'data');
        $this->assertSame('Application Resubmitted', $staffData['title']);
        $this->assertSame('/staff/applications/'.$application->id, $staffData['action_url']);
    }

    // ============================================================
    // APPROVAL / REJECTION
    // ============================================================

    public function test_approval_notifies_the_student(): void
    {
        $staff = $this->loginAsStaff();
        $student = User::factory()->student()->create();
        $application = $this->eligibleApplication($student);
        $application->update(['status' => 'submitted', 'submitted_at' => now()]);

        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', ['action' => 'start_review'])
            ->assertOk();

        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', ['action' => 'approve'])
            ->assertOk();

        $this->assertNotification($student, 'application.approved');
        $this->assertNotification($staff, 'application.approved', 0);

        $data = data_get($this->notificationFor($student, 'application.approved'), 'data');
        $this->assertSame('Application Approved', $data['title']);
        $this->assertSame('/student/applications/'.$application->id, $data['action_url']);
    }

    public function test_rejection_notifies_the_student(): void
    {
        $this->loginAsStaff();
        $student = User::factory()->student()->create();
        $application = Application::factory()->submitted()->create([
            'applicant_id' => $student->id,
        ]);

        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'reject',
                'remarks' => 'Does not meet program requirements.',
            ])
            ->assertOk();

        $this->assertNotification($student, 'application.rejected');

        $data = data_get($this->notificationFor($student, 'application.rejected'), 'data');
        $this->assertSame('Application Rejected', $data['title']);
    }

    // ============================================================
    // DEPLOYMENT ASSIGNMENTS
    // ============================================================

    private function assignApprovedStudent(User $staff, Application $application): DeploymentAssignment
    {
        $agency = HostAgency::factory()->create(['is_active' => true]);
        $site = DeploymentSite::factory()->create(['host_agency_id' => $agency->id, 'is_active' => true]);
        $slot = DeploymentSlot::factory()->create([
            'program_cycle_id' => $application->program_cycle_id,
            'deployment_site_id' => $site->id,
            'capacity' => 5,
            'status' => 'active',
        ]);

        $response = $this->fromSpa()
            ->postJson("/api/staff/applications/{$application->id}/assign", [
                'deployment_slot_id' => $slot->id,
            ]);

        $response->assertStatus(201);

        return DeploymentAssignment::query()->findOrFail($response->json('data.id'));
    }

    public function test_assignment_creation_notifies_the_student(): void
    {
        $staff = $this->loginAsStaff();
        $student = User::factory()->student()->create();
        $application = Application::factory()->approved()->create([
            'applicant_id' => $student->id,
        ]);

        $assignment = $this->assignApprovedStudent($staff, $application);

        $this->assertNotification($student, 'deployment.assigned');

        $data = data_get($this->notificationFor($student, 'deployment.assigned'), 'data');
        $this->assertSame('Deployment Assignment Created', $data['title']);
        $this->assertSame($assignment->id, $data['assignment_id']);
        $this->assertSame('/student/applications/'.$application->id, $data['action_url']);
    }

    public function test_assignment_cancellation_notifies_the_student(): void
    {
        $staff = $this->loginAsStaff();
        $student = User::factory()->student()->create();
        $application = Application::factory()->approved()->create([
            'applicant_id' => $student->id,
        ]);

        $assignment = $this->assignApprovedStudent($staff, $application);
        $studentNotificationsBefore = $student->notifications()->count();

        $this->fromSpa()
            ->patchJson('/api/staff/deployments/'.$assignment->id.'/cancel')
            ->assertOk();

        $this->assertNotification($student, 'deployment.cancelled');
        // The earlier assignment-created notification is untouched.
        $this->assertNotification($student, 'deployment.assigned');
        $this->assertSame($studentNotificationsBefore + 1, $student->notifications()->count());
    }

    // ============================================================
    // TRANSACTION CONSISTENCY
    // ============================================================

    public function test_failed_state_change_creates_no_notification(): void
    {
        $this->loginAsStaff();
        $student = User::factory()->student()->create();

        // Submitted application with NO verified documents cannot be approved.
        $cycle = ProgramCycle::factory()->open()->create();
        $cycle->requirements()->syncWithPivotValues(
            Requirement::factory()->count(2)->create()->pluck('id'),
            ['is_required' => true],
        );
        $application = Application::factory()->submitted()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);

        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', ['action' => 'start_review'])
            ->assertOk();

        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', ['action' => 'approve'])
            ->assertStatus(422);

        $this->assertSame(0, DatabaseNotification::query()->count());

        $application->refresh();
        $this->assertSame('under_review', $application->status->value);
    }

    public function test_rollback_after_event_dispatch_leaves_no_notification(): void
    {
        $staffUser = User::factory()->staff()->create();
        $student = User::factory()->student()->create();
        $application = $this->eligibleApplication($student);
        $application->update(['status' => 'under_review']);

        $service = app(ApplicationService::class);

        // Simulate a failure after the notification listener ran: everything,
        // including the notification rows, must roll back together.
        Event::listen(ApplicationApproved::class, fn () => throw new RuntimeException('boom'));

        try {
            $service->approve($application, $staffUser);
            $this->fail('Expected the RuntimeException to propagate.');
        } catch (RuntimeException) {
            // expected path
        }

        $this->assertSame(0, DatabaseNotification::query()->count());
        $this->assertSame(0, $student->notifications()->count());

        $application->refresh();
        $this->assertSame('under_review', $application->status->value);
        $this->assertNull($application->approved_at);
    }

    // ============================================================
    // DUPLICATES
    // ============================================================

    public function test_retry_of_a_completed_transition_does_not_duplicate_notifications(): void
    {
        $student = User::factory()->student()->create();
        $this->loginAs($student)->assertOk();
        $staff = User::factory()->staff()->create();
        $application = $this->eligibleApplication($student);

        $this->submitAs($student, $application)->assertOk();

        // A repeated/retried request must be refused (the policy blocks
        // submitting an already-submitted application) and must not create a
        // second round of notifications.
        $this->submitAs($student, $application)->assertStatus(403);

        $this->assertNotification($student, 'application.submitted', 1);
        $this->assertNotification($staff, 'application.submitted', 1);
    }
}

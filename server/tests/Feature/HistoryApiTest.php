<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\AuditLog;
use App\Models\DeploymentAssignment;
use App\Models\DeploymentSite;
use App\Models\DeploymentSlot;
use App\Models\ProgramCycle;
use App\Models\Requirement;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SpaAuthentication;
use Tests\TestCase;

class HistoryApiTest extends TestCase
{
    use RefreshDatabase, SpaAuthentication;

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    public function test_guest_cannot_access_any_history_endpoint(): void
    {
        $application = Application::factory()->submitted()->create([
            'program_cycle_id' => ProgramCycle::factory()->open()->create()->id,
        ]);
        $slot = DeploymentSlot::factory()->active()->create();
        $assignment = DeploymentAssignment::factory()->create([
            'application_id' => $application->id,
            'deployment_slot_id' => $slot->id,
        ]);

        $this->fromSpa()
            ->getJson("/api/student/applications/{$application->id}/history")
            ->assertUnauthorized();

        $this->fromSpa()
            ->getJson("/api/staff/applications/{$application->id}/history")
            ->assertUnauthorized();

        $this->fromSpa()
            ->getJson("/api/staff/deployment-slots/{$slot->id}/history")
            ->assertUnauthorized();

        $this->fromSpa()
            ->getJson("/api/staff/deployments/{$assignment->id}/history")
            ->assertUnauthorized();
    }

    public function test_student_cannot_access_staff_history_endpoints(): void
    {
        $this->loginAsStudent();
        $application = Application::factory()->submitted()->create([
            'program_cycle_id' => ProgramCycle::factory()->open()->create()->id,
        ]);
        $slot = DeploymentSlot::factory()->active()->create();
        $assignment = DeploymentAssignment::factory()->create([
            'application_id' => $application->id,
            'deployment_slot_id' => $slot->id,
        ]);

        $this->fromSpa()
            ->getJson("/api/staff/applications/{$application->id}/history")
            ->assertForbidden();

        $this->fromSpa()
            ->getJson("/api/staff/deployment-slots/{$slot->id}/history")
            ->assertForbidden();

        $this->fromSpa()
            ->getJson("/api/staff/deployments/{$assignment->id}/history")
            ->assertForbidden();
    }

    public function test_student_can_view_their_own_application_history(): void
    {
        $student = $this->loginAsStudent();
        $application = Application::factory()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => ProgramCycle::factory()->open()->create()->id,
        ]);
        $application->statusHistory()->create([
            'status' => 'submitted',
            'action' => 'submit',
            'changed_by' => $student->id,
            'changed_at' => now(),
        ]);

        $this->fromSpa()
            ->getJson("/api/student/applications/{$application->id}/history")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.action', 'submit')
            ->assertJsonPath('data.0.source', 'status_history');
    }

    public function test_student_cannot_view_another_students_application_history(): void
    {
        $this->loginAsStudent();
        $otherApplication = Application::factory()->submitted()->create([
            'applicant_id' => User::factory()->student()->create()->id,
            'program_cycle_id' => ProgramCycle::factory()->open()->create()->id,
        ]);

        $this->fromSpa()
            ->getJson("/api/student/applications/{$otherApplication->id}/history")
            ->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Student timeline content
    |--------------------------------------------------------------------------
    */

    public function test_student_timeline_shows_transitions_without_internal_details(): void
    {
        [$student, $application] = $this->runSubmissionReturnResubmissionFlow();

        $response = $this->fromSpa()
            ->getJson("/api/student/applications/{$application->id}/history")
            ->assertOk();

        $response->assertJsonPath('meta.total', 3);

        $actions = collect($response->json('data'))->pluck('action')->all();
        $this->assertSame(['resubmit', 'return_for_correction', 'submit'], $actions);

        foreach ($response->json('data') as $event) {
            $this->assertSame('status_history', $event['source']);
            $this->assertNotNull($event['label']);
            $this->assertNotNull($event['occurred_at']);
            $this->assertNull($event['old_values']);
            $this->assertNull($event['new_values']);
            $this->assertNull($event['metadata']);
        }

        // The rejection reason recorded by staff is visible to the student.
        $returned = collect($response->json('data'))->firstWhere('action', 'return_for_correction');
        $this->assertSame('Barangay clearance is missing.', $returned['reason']);
        $this->assertNotNull($returned['actor']);

        $this->assertStringNotContainsString('file_path', $response->getContent());
    }

    public function test_student_timeline_excludes_document_audit_events(): void
    {
        [$student, $application] = $this->runSubmissionReturnResubmissionFlow();

        // The uploads in the flow above already produced document audit rows,
        // which staff can see but students must never receive.
        $this->assertTrue(AuditLog::query()->where('action', 'document.uploaded')->exists());

        $this->loginAs(User::factory()->staff()->create());
        $staffSources = collect(
            $this->fromSpa()
                ->getJson("/api/staff/applications/{$application->id}/history")
                ->assertOk()
                ->json('data')
        )->pluck('source')->unique()->all();
        $this->assertContains('audit_log', $staffSources);

        $this->loginAs($student);
        $studentSources = collect(
            $this->fromSpa()
                ->getJson("/api/student/applications/{$application->id}/history")
                ->assertOk()
                ->json('data')
        )->pluck('source')->unique()->all();

        $this->assertSame(['status_history'], $studentSources);
    }

    /*
    |--------------------------------------------------------------------------
    | Staff merged timeline
    |--------------------------------------------------------------------------
    */

    public function test_staff_timeline_merges_status_and_audit_events_newest_first(): void
    {
        $staff = $this->loginAsStaff();
        $cycle = $this->openCycleWithRequirements();

        $student = User::factory()->student()->create();
        \App\Models\StudentDetail::factory()->create(['user_id' => $student->id]);
        $application = Application::factory()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);

        Storage::fake('docs');
        foreach ($cycle->requirements as $req) {
            $this->loginAs($student);
            $this->fromSpa()->postJson('/api/student/applications/'.$application->id.'/documents', [
                'requirement_id' => $req->id,
                'file' => UploadedFile::fake()->createWithContent($req->slug.'.pdf', '%PDF-1.4'),
            ])->assertStatus(201);
        }

        // Submit through the real workflow so the status history exists.
        $this->fromSpa()->postJson('/api/student/applications/'.$application->id.'/submit')->assertOk();

        $document = $application->documents()->firstOrFail();

        // Staff reviews, verifies the documents, approves, then assigns.
        $this->loginAs($staff);
        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', ['action' => 'start_review'])
            ->assertOk();
        foreach ($application->documents as $doc) {
            $this->fromSpa()
                ->patchJson('/api/staff/applications/'.$application->id.'/documents/'.$doc->id.'/verification', [
                    'verification_status' => 'verified',
                ])
                ->assertOk();
        }
        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', ['action' => 'approve'])
            ->assertOk();

        $site = DeploymentSite::factory()->active()->create();
        $slot = DeploymentSlot::factory()->active()->create([
            'program_cycle_id' => $cycle->id,
            'deployment_site_id' => $site->id,
        ]);
        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/assign', [
                'deployment_slot_id' => $slot->id,
            ])
            ->assertStatus(201);

        // Make timestamps unambiguous so ordering assertions are deterministic.
        // Each workflow stage gets its own distinct point in the past; rows
        // sharing a timestamp (per-document upload/verify pairs) fall back to
        // the id tiebreaker, which follows insertion order.
        $stageOffsets = [
            'submit' => 120,
            'start_review' => 90,
            'approve' => 50,
            'schedule_deployment' => 40,
        ];
        foreach ($stageOffsets as $action => $minutesAgo) {
            DB::table('application_status_history')
                ->where('application_id', $application->id)
                ->where('action', $action)
                ->update(['changed_at' => now()->subMinutes($minutesAgo)]);
        }
        DB::table('audit_logs')
            ->where('action', 'document.uploaded')
            ->whereIn('auditable_id', $application->documents()->pluck('id'))
            ->update(['created_at' => now()->subMinutes(30)]);
        DB::table('audit_logs')
            ->where('action', 'document.verified')
            ->whereIn('auditable_id', $application->documents()->pluck('id'))
            ->update(['created_at' => now()->subMinutes(10)]);

        $response = $this->fromSpa()
            ->getJson("/api/staff/applications/{$application->id}/history")
            ->assertOk();

        $events = collect($response->json('data'));
        $timeline = $events->map(fn ($e) => $e['source'].':'.$e['action'])->values()->all();

        // Within an identical timestamp the higher id wins, so per document
        // the later verification precedes its own upload row.
        $this->assertSame([
            'audit_log:assignment.created',
            'audit_log:document.verified',
            'audit_log:document.verified',
            'audit_log:document.uploaded',
            'audit_log:document.uploaded',
            'status_history:schedule_deployment',
            'status_history:approve',
            'status_history:start_review',
            'status_history:submit',
        ], $timeline);

        // Audit events expose old/new values to staff.
        $verified = $events->firstWhere('action', 'document.verified');
        $this->assertSame('pending', $verified['old_values']['verification_status']);
        $this->assertSame('verified', $verified['new_values']['verification_status']);
        $this->assertSame($staff->name, $verified['actor']);

        // Storage paths never leave the API.
        $this->assertStringNotContainsString('file_path', $response->getContent());
    }

    public function test_staff_timeline_includes_assignment_cancellation_with_reason(): void
    {
        $this->loginAsStaff();
        [$application, $slot] = $this->approvedApplicationWithActiveSlot();

        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/assign', [
                'deployment_slot_id' => $slot->id,
            ])
            ->assertStatus(201);
        $assignment = DeploymentAssignment::query()->where('application_id', $application->id)->firstOrFail();

        $this->fromSpa()
            ->patchJson('/api/staff/deployments/'.$assignment->id.'/cancel', [
                'remarks' => 'Student requested a different placement.',
            ])
            ->assertOk();

        $response = $this->fromSpa()
            ->getJson("/api/staff/applications/{$application->id}/history")
            ->assertOk();

        $cancelled = collect($response->json('data'))->firstWhere('action', 'assignment.cancelled');
        $this->assertNotNull($cancelled);
        $this->assertSame('Student requested a different placement.', $cancelled['reason']);
        $this->assertSame('scheduled', $cancelled['old_values']['status']);
        $this->assertSame('cancelled', $cancelled['new_values']['status']);
    }

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */

    public function test_history_pagination_respects_page_and_per_page(): void
    {
        $student = $this->loginAsStudent();
        $application = Application::factory()->submitted()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => ProgramCycle::factory()->open()->create()->id,
        ]);

        // Three transitions total: submit -> start_review -> approve.
        $application->statusHistory()->create([
            'status' => 'submitted',
            'action' => 'submit',
            'changed_by' => $student->id,
            'changed_at' => now(),
        ]);
        $staff = User::factory()->staff()->create();
        $cycle = $application->programCycle;
        foreach ($cycle->requirements as $requirement) {
            ApplicationDocument::factory()->create([
                'application_id' => $application->id,
                'requirement_id' => $requirement->id,
                'verification_status' => 'verified',
            ]);
        }
        $this->loginAs($staff);
        $this->fromSpa()->postJson('/api/staff/applications/'.$application->id.'/review', [
            'action' => 'start_review',
        ])->assertOk();
        $this->fromSpa()->postJson('/api/staff/applications/'.$application->id.'/review', [
            'action' => 'approve',
        ])->assertOk();

        $this->loginAs($student);
        $page1 = $this->fromSpa()
            ->getJson("/api/student/applications/{$application->id}/history?per_page=2&page=1")
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.current_page', 1);
        $this->assertCount(2, $page1->json('data'));

        $page2 = $this->fromSpa()
            ->getJson("/api/student/applications/{$application->id}/history?per_page=2&page=2")
            ->assertOk()
            ->assertJsonPath('meta.current_page', 2);
        $this->assertCount(1, $page2->json('data'));
    }

    public function test_history_per_page_is_capped_at_100(): void
    {
        $this->loginAsStaff();
        $application = Application::factory()->submitted()->create([
            'program_cycle_id' => ProgramCycle::factory()->open()->create()->id,
        ]);
        $document = ApplicationDocument::factory()->create([
            'application_id' => $application->id,
        ]);

        for ($i = 0; $i < 12; $i++) {
            AuditLogger::log('document.updated', $document, null, ['file_size' => 1000 + $i]);
        }

        $this->fromSpa()
            ->getJson("/api/staff/applications/{$application->id}/history?per_page=500")
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100)
            ->assertJsonPath('meta.total', 12);
    }

    public function test_history_defaults_to_25_events_per_page(): void
    {
        $this->loginAsStaff();
        $application = Application::factory()->submitted()->create([
            'program_cycle_id' => ProgramCycle::factory()->open()->create()->id,
        ]);
        $document = ApplicationDocument::factory()->create([
            'application_id' => $application->id,
        ]);

        for ($i = 0; $i < 30; $i++) {
            AuditLogger::log('document.updated', $document, null, ['file_size' => 1000 + $i]);
        }

        $response = $this->fromSpa()
            ->getJson("/api/staff/applications/{$application->id}/history")
            ->assertOk()
            ->assertJsonPath('meta.per_page', 25)
            ->assertJsonPath('meta.total', 30);

        $this->assertCount(25, $response->json('data'));
    }

    /*
    |--------------------------------------------------------------------------
    | Contextual entity histories
    |--------------------------------------------------------------------------
    */

    public function test_slot_history_returns_only_that_slots_audit_events(): void
    {
        $this->loginAsStaff();
        $slotA = DeploymentSlot::factory()->active()->create(['title' => 'Slot A']);
        $slotB = DeploymentSlot::factory()->active()->create(['title' => 'Slot B']);

        AuditLogger::log('deployment_slot.created', $slotA, null, ['title' => 'Slot A', 'capacity' => 3]);
        AuditLogger::log('deployment_slot.updated', $slotA, ['capacity' => 3], ['capacity' => 7]);
        AuditLogger::log('deployment_slot.created', $slotB, null, ['title' => 'Slot B', 'capacity' => 2]);

        $response = $this->fromSpa()
            ->getJson("/api/staff/deployment-slots/{$slotA->id}/history")
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $actions = collect($response->json('data'))->pluck('action')->all();
        $this->assertSame(['deployment_slot.updated', 'deployment_slot.created'], $actions);

        foreach ($response->json('data') as $event) {
            $this->assertArrayHasKey('label', $event);
            $this->assertArrayHasKey('occurred_at', $event);
            $this->assertArrayNotHasKey('source', $event);
            $this->assertArrayNotHasKey('auditable_type', $event);
        }
    }

    public function test_slot_history_is_read_only(): void
    {
        $this->loginAsStaff();
        $slot = DeploymentSlot::factory()->active()->create();
        AuditLogger::log('deployment_slot.created', $slot, null, ['capacity' => 3]);

        foreach ([
            ['putJson', []],
            ['patchJson', []],
            ['deleteJson', []],
            ['postJson', []],
        ] as [$method, $payload]) {
            $this->fromSpa()->{$method}("/api/staff/deployment-slots/{$slot->id}/history", $payload);
        }

        $this->assertSame(1, AuditLog::count());
    }

    public function test_assignment_history_returns_created_and_cancelled_events(): void
    {
        $this->loginAsStaff();
        [$application, $slot] = $this->approvedApplicationWithActiveSlot();

        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/assign', [
                'deployment_slot_id' => $slot->id,
            ])
            ->assertStatus(201);
        $assignment = DeploymentAssignment::query()->where('application_id', $application->id)->firstOrFail();

        $this->fromSpa()
            ->patchJson('/api/staff/deployments/'.$assignment->id.'/cancel', ['remarks' => 'No longer needed.'])
            ->assertOk();

        $response = $this->fromSpa()
            ->getJson("/api/staff/deployments/{$assignment->id}/history")
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $events = collect($response->json('data'));
        $this->assertSame(['assignment.cancelled', 'assignment.created'], $events->pluck('action')->all());
        $this->assertSame('No longer needed.', $events->first()['reason']);
        $this->assertNull($events->last()['reason']);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Full student flow producing three status transitions:
     * submit -> return_for_correction -> resubmit.
     *
     * @return array{0: User, 1: Application}
     */
    private function runSubmissionReturnResubmissionFlow(): array
    {
        $student = $this->loginAsStudent();
        \App\Models\StudentDetail::factory()->create(['user_id' => $student->id]);

        $cycle = ProgramCycle::factory()->open()->create();
        $cycle->requirements()->syncWithPivotValues(
            Requirement::factory()->count(2)->create()->pluck('id'),
            ['is_required' => true],
        );
        $application = Application::factory()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);

        Storage::fake('docs');
        foreach ($cycle->requirements as $requirement) {
            $this->fromSpa()->postJson('/api/student/applications/'.$application->id.'/documents', [
                'requirement_id' => $requirement->id,
                'file' => UploadedFile::fake()->create($requirement->slug.'.pdf', 512),
            ])->assertStatus(201);
        }

        $this->fromSpa()->postJson('/api/student/applications/'.$application->id.'/submit')->assertOk();

        $this->loginAs(User::factory()->staff()->create());
        $this->fromSpa()->postJson('/api/staff/applications/'.$application->id.'/review', [
            'action' => 'return_for_correction',
            'remarks' => 'Barangay clearance is missing.',
        ])->assertOk();

        $this->loginAs($student);
        $this->fromSpa()->postJson('/api/student/applications/'.$application->id.'/submit')->assertOk();

        return [$student, $application];
    }

    private function approvedApplicationWithActiveSlot(): array
    {
        $cycle = ProgramCycle::factory()->open()->create();
        $site = DeploymentSite::factory()->active()->create();
        $slot = DeploymentSlot::factory()->active()->create([
            'program_cycle_id' => $cycle->id,
            'deployment_site_id' => $site->id,
            'capacity' => 3,
        ]);
        $application = Application::factory()->approved()->create(['program_cycle_id' => $cycle->id]);

        return [$application, $slot];
    }

    private function openCycleWithRequirements(): ProgramCycle
    {
        $cycle = ProgramCycle::factory()->open()->create();
        $cycle->requirements()->syncWithPivotValues(
            Requirement::factory()->count(2)->create()->pluck('id'),
            ['is_required' => true],
        );

        return $cycle;
    }
}

<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\AuditLog;
use App\Models\DeploymentAssignment;
use App\Models\DeploymentSite;
use App\Models\DeploymentSlot;
use App\Models\HostAgency;
use App\Models\ProgramCycle;
use App\Models\Requirement;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use Tests\Concerns\SpaAuthentication;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase, SpaAuthentication;

    // ============================================================
    // APPLICATION STATUS HISTORY
    // ============================================================

    public function test_submission_creates_status_history_with_action(): void
    {
        $student = $this->loginAsStudent();
        \App\Models\StudentDetail::factory()->create(['user_id' => $student->id]);
        $cycle = $this->openCycleWithRequirements();
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

        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/submit')
            ->assertOk();

        $this->assertDatabaseHas('application_status_history', [
            'application_id' => $application->id,
            'status' => 'submitted',
            'action' => 'submit',
            'changed_by' => $student->id,
        ]);
    }

    public function test_return_for_correction_creates_history_with_reason(): void
    {
        $staff = $this->loginAsStaff();
        $application = Application::factory()->submitted()->create([
            'program_cycle_id' => ProgramCycle::factory()->open()->create()->id,
        ]);

        $reason = 'Barangay clearance is missing.';
        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'return_for_correction',
                'remarks' => $reason,
            ])
            ->assertOk();

        $this->assertDatabaseHas('application_status_history', [
            'application_id' => $application->id,
            'status' => 'returned_for_correction',
            'action' => 'return_for_correction',
            'changed_by' => $staff->id,
            'remarks' => $reason,
        ]);
    }

    public function test_resubmission_is_distinguishable_from_first_submission(): void
    {
        $student = $this->loginAsStudent();
        \App\Models\StudentDetail::factory()->create(['user_id' => $student->id]);
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->returnedForCorrection()->create([
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

        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/submit')
            ->assertOk();

        $this->assertDatabaseHas('application_status_history', [
            'application_id' => $application->id,
            'status' => 'submitted',
            'action' => 'resubmit',
        ]);
    }

    public function test_approval_creates_history_with_actor(): void
    {
        $staff = $this->loginAsStaff();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->submitted()->create(['program_cycle_id' => $cycle->id]);

        foreach ($cycle->requirements as $requirement) {
            ApplicationDocument::factory()->create([
                'application_id' => $application->id,
                'requirement_id' => $requirement->id,
                'verification_status' => 'verified',
            ]);
        }

        $this->fromSpa()->postJson('/api/staff/applications/'.$application->id.'/review', [
            'action' => 'start_review',
        ])->assertOk();

        $this->fromSpa()->postJson('/api/staff/applications/'.$application->id.'/review', [
            'action' => 'approve',
        ])->assertOk();

        $this->assertDatabaseHas('application_status_history', [
            'application_id' => $application->id,
            'status' => 'approved',
            'action' => 'approve',
            'changed_by' => $staff->id,
        ]);
    }

    public function test_rejection_creates_history_with_reason_and_actor(): void
    {
        $staff = $this->loginAsStaff();
        $application = Application::factory()->submitted()->create([
            'program_cycle_id' => ProgramCycle::factory()->open()->create()->id,
        ]);

        $reason = 'Application does not meet program requirements.';
        $this->fromSpa()->postJson('/api/staff/applications/'.$application->id.'/review', [
            'action' => 'reject',
            'remarks' => $reason,
        ])->assertOk();

        $this->assertDatabaseHas('application_status_history', [
            'application_id' => $application->id,
            'status' => 'rejected',
            'action' => 'reject',
            'changed_by' => $staff->id,
            'remarks' => $reason,
        ]);
    }

    // ============================================================
    // DOCUMENT AUDIT EVENTS
    // ============================================================

    public function test_document_upload_creates_audit_record_without_file_path(): void
    {
        $student = $this->loginAsStudent();
        $cycle = ProgramCycle::factory()->open()->create();
        $requirement = Requirement::factory()->create();
        $cycle->requirements()->syncWithPivotValues([$requirement->id], ['is_required' => true]);
        $application = Application::factory()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);

        Storage::fake('docs');
        $this->fromSpa()->postJson('/api/student/applications/'.$application->id.'/documents', [
            'requirement_id' => $requirement->id,
            'file' => UploadedFile::fake()->createWithContent('clearance.pdf', '%PDF-1.4 test'),
        ])->assertStatus(201);

        $audit = AuditLog::query()
            ->where('action', 'document.uploaded')
            ->where('auditable_type', ApplicationDocument::class)
            ->firstOrFail();

        $this->assertSame($student->id, $audit->user_id);
        $this->assertSame($application->id, $audit->metadata['application_id']);
        $this->assertSame('pending', $audit->new_values['verification_status']);
        $this->assertSame('clearance.pdf', $audit->new_values['file_name']);
        $this->assertNull($audit->old_values);

        $loggedValues = array_merge($audit->new_values ?? [], $audit->old_values ?? [], $audit->metadata ?? []);
        $this->assertArrayNotHasKey('file_path', $loggedValues);
    }

    public function test_document_replacement_records_old_and_new_file_details(): void
    {
        $student = $this->loginAsStudent();
        $cycle = ProgramCycle::factory()->open()->create();
        $requirement = Requirement::factory()->create();
        $cycle->requirements()->syncWithPivotValues([$requirement->id], ['is_required' => true]);
        $application = Application::factory()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);

        Storage::fake('docs');
        $this->fromSpa()->postJson('/api/student/applications/'.$application->id.'/documents', [
            'requirement_id' => $requirement->id,
            'file' => UploadedFile::fake()->createWithContent('cor.pdf', '%PDF-1.4 original'),
        ])->assertStatus(201);

        $document = $application->documents()->firstOrFail();
        $staff = User::factory()->staff()->create();
        $document->verify($staff);

        $this->fromSpa()->postJson('/api/student/applications/'.$application->id.'/documents', [
            'requirement_id' => $requirement->id,
            'file' => UploadedFile::fake()->createWithContent('cor.pdf', '%PDF-1.4 replacement'),
        ])->assertStatus(201);

        $replaced = AuditLog::query()
            ->where('action', 'document.replaced')
            ->where('auditable_id', $document->id)
            ->firstOrFail();

        $this->assertSame('verified', $replaced->old_values['verification_status']);
        $this->assertSame('pending', $replaced->new_values['verification_status']);
        $this->assertSame($student->id, $replaced->user_id);
    }

    public function test_document_verification_creates_audit_record(): void
    {
        $staff = $this->loginAsStaff();
        $application = Application::factory()->submitted()->create([
            'program_cycle_id' => ProgramCycle::factory()->open()->create()->id,
        ]);
        $document = ApplicationDocument::factory()->create([
            'application_id' => $application->id,
            'verification_status' => 'pending',
        ]);

        $this->fromSpa()
            ->patchJson('/api/staff/applications/'.$application->id.'/documents/'.$document->id.'/verification', [
                'verification_status' => 'verified',
            ])
            ->assertOk();

        $audit = AuditLog::query()
            ->where('action', 'document.verified')
            ->where('auditable_type', ApplicationDocument::class)
            ->where('auditable_id', $document->id)
            ->firstOrFail();

        $this->assertSame($staff->id, $audit->user_id);
        $this->assertSame('pending', $audit->old_values['verification_status']);
        $this->assertSame('verified', $audit->new_values['verification_status']);
        $this->assertNull($audit->reason);
    }

    public function test_document_rejection_records_the_reason(): void
    {
        $staff = $this->loginAsStaff();
        $application = Application::factory()->submitted()->create([
            'program_cycle_id' => ProgramCycle::factory()->open()->create()->id,
        ]);
        $document = ApplicationDocument::factory()->create([
            'application_id' => $application->id,
            'verification_status' => 'pending',
        ]);

        $reason = 'The uploaded scan is unreadable.';
        $this->fromSpa()
            ->patchJson('/api/staff/applications/'.$application->id.'/documents/'.$document->id.'/verification', [
                'verification_status' => 'rejected',
                'rejection_reason' => $reason,
            ])
            ->assertOk();

        $audit = AuditLog::query()
            ->where('action', 'document.rejected')
            ->where('auditable_id', $document->id)
            ->firstOrFail();

        $this->assertSame($staff->id, $audit->user_id);
        $this->assertSame('rejected', $audit->new_values['verification_status']);
        $this->assertSame($reason, $audit->reason);
    }

    // ============================================================
    // DEPLOYMENT SLOT / SITE / HOST AGENCY AUDIT EVENTS
    // ============================================================

    public function test_slot_creation_creates_audit_record(): void
    {
        $staff = $this->loginAsStaff();
        $site = DeploymentSite::factory()->active()->create();
        $cycle = ProgramCycle::factory()->open()->create();

        $this->fromSpa()->postJson('/api/staff/deployment-slots', [
            'program_cycle_id' => $cycle->id,
            'deployment_site_id' => $site->id,
            'title' => 'Records Assistant',
            'capacity' => 5,
        ])->assertStatus(201);

        $slot = DeploymentSlot::query()->where('title', 'Records Assistant')->firstOrFail();
        $audit = AuditLog::query()
            ->where('action', 'deployment_slot.created')
            ->where('auditable_type', DeploymentSlot::class)
            ->where('auditable_id', $slot->id)
            ->firstOrFail();

        $this->assertSame($staff->id, $audit->user_id);
        $this->assertSame(5, $audit->new_values['capacity']);
        $this->assertSame('active', $audit->new_values['status']);
        $this->assertNull($audit->old_values);
    }

    public function test_slot_update_records_changed_fields_only(): void
    {
        $this->loginAsStaff();
        $slot = DeploymentSlot::factory()->active()->create(['capacity' => 5, 'title' => 'Encoder']);

        $this->fromSpa()->putJson('/api/staff/deployment-slots/'.$slot->id, [
            'title' => 'Encoder II',
            'capacity' => 10,
        ])->assertOk();

        $audit = AuditLog::query()
            ->where('action', 'deployment_slot.updated')
            ->where('auditable_id', $slot->id)
            ->firstOrFail();

        $this->assertSame(5, $audit->old_values['capacity']);
        $this->assertSame(10, $audit->new_values['capacity']);
        $this->assertSame('Encoder', $audit->old_values['title']);
        $this->assertSame('Encoder II', $audit->new_values['title']);
    }

    public function test_slot_deactivation_and_reactivation_create_audit_records(): void
    {
        $staff = $this->loginAsStaff();
        $slot = DeploymentSlot::factory()->active()->create();

        $this->fromSpa()
            ->patchJson('/api/staff/deployment-slots/'.$slot->id.'/status', ['status' => 'inactive'])
            ->assertOk();

        $deactivated = AuditLog::query()
            ->where('action', 'deployment_slot.deactivated')
            ->where('auditable_id', $slot->id)
            ->firstOrFail();
        $this->assertSame('active', $deactivated->old_values['status']);
        $this->assertSame('inactive', $deactivated->new_values['status']);

        $this->fromSpa()
            ->patchJson('/api/staff/deployment-slots/'.$slot->id.'/status', ['status' => 'active'])
            ->assertOk();

        $activated = AuditLog::query()
            ->where('action', 'deployment_slot.activated')
            ->where('auditable_id', $slot->id)
            ->firstOrFail();
        $this->assertSame($staff->id, $activated->user_id);
    }

    public function test_toggling_a_slot_to_its_current_status_does_not_create_a_duplicate_audit_record(): void
    {
        $this->loginAsStaff();
        $slot = DeploymentSlot::factory()->active()->create();

        $this->fromSpa()
            ->patchJson('/api/staff/deployment-slots/'.$slot->id.'/status', ['status' => 'active'])
            ->assertOk();

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_host_agency_lifecycle_creates_audit_records(): void
    {
        $staff = $this->loginAsStaff();

        $this->fromSpa()->postJson('/api/staff/catalog/host-agencies', [
            'name' => 'City Social Welfare Office',
            'agency_type' => 'government',
        ])->assertStatus(201);

        $agency = HostAgency::query()->where('name', 'City Social Welfare Office')->firstOrFail();
        $created = AuditLog::query()
            ->where('action', 'host_agency.created')
            ->where('auditable_id', $agency->id)
            ->firstOrFail();
        $this->assertSame($staff->id, $created->user_id);
        $this->assertTrue($created->new_values['is_active']);

        $this->fromSpa()->putJson('/api/staff/catalog/host-agencies/'.$agency->id, [
            'name' => 'City Social Welfare and Development Office',
        ])->assertOk();

        $updated = AuditLog::query()
            ->where('action', 'host_agency.updated')
            ->where('auditable_id', $agency->id)
            ->firstOrFail();
        $this->assertSame('City Social Welfare Office', $updated->old_values['name']);
        $this->assertSame('City Social Welfare and Development Office', $updated->new_values['name']);

        $this->fromSpa()
            ->patchJson('/api/staff/catalog/host-agencies/'.$agency->id.'/status', ['is_active' => false])
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'host_agency.deactivated',
            'auditable_id' => $agency->id,
        ]);
    }

    public function test_deployment_site_lifecycle_creates_audit_records(): void
    {
        $this->loginAsStaff();

        $this->fromSpa()->postJson('/api/staff/catalog/deployment-sites', [
            'host_agency_id' => HostAgency::factory()->active()->create()->id,
            'name' => 'PESO Main Office',
        ])->assertStatus(201);

        $site = DeploymentSite::query()->where('name', 'PESO Main Office')->firstOrFail();
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'deployment_site.created',
            'auditable_type' => DeploymentSite::class,
            'auditable_id' => $site->id,
        ]);

        $this->fromSpa()->putJson('/api/staff/catalog/deployment-sites/'.$site->id, [
            'host_agency_id' => $site->host_agency_id,
            'name' => 'PESO Annex Office',
        ])->assertOk();

        $updated = AuditLog::query()
            ->where('action', 'deployment_site.updated')
            ->where('auditable_id', $site->id)
            ->firstOrFail();
        $this->assertSame('PESO Main Office', $updated->old_values['name']);
        $this->assertSame('PESO Annex Office', $updated->new_values['name']);

        $this->fromSpa()
            ->patchJson('/api/staff/catalog/deployment-sites/'.$site->id.'/status', ['is_active' => false])
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'deployment_site.deactivated',
            'auditable_id' => $site->id,
        ]);
    }

    // ============================================================
    // ASSIGNMENT AUDIT EVENTS
    // ============================================================

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

    public function test_student_assignment_creates_audit_record(): void
    {
        $staff = $this->loginAsStaff();
        [$application, $slot] = $this->approvedApplicationWithActiveSlot();

        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/assign', [
                'deployment_slot_id' => $slot->id,
            ])
            ->assertStatus(201);

        $assignment = DeploymentAssignment::query()->where('application_id', $application->id)->firstOrFail();
        $audit = AuditLog::query()
            ->where('action', 'assignment.created')
            ->where('auditable_type', DeploymentAssignment::class)
            ->where('auditable_id', $assignment->id)
            ->firstOrFail();

        $this->assertSame($staff->id, $audit->user_id);
        $this->assertSame($slot->id, $audit->new_values['deployment_slot_id']);
        $this->assertSame('scheduled', $audit->new_values['status']);
    }

    public function test_assignment_cancellation_creates_audit_record_with_reason(): void
    {
        $staff = $this->loginAsStaff();
        [$application, $slot] = $this->approvedApplicationWithActiveSlot();

        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/assign', [
                'deployment_slot_id' => $slot->id,
            ])
            ->assertStatus(201);

        $assignment = DeploymentAssignment::query()->where('application_id', $application->id)->firstOrFail();

        $reason = 'Student requested a different placement.';
        $this->fromSpa()
            ->patchJson('/api/staff/deployments/'.$assignment->id.'/cancel', ['remarks' => $reason])
            ->assertOk();

        $cancelled = AuditLog::query()
            ->where('action', 'assignment.cancelled')
            ->where('auditable_id', $assignment->id)
            ->orderByDesc('id')
            ->firstOrFail();

        $this->assertSame($staff->id, $cancelled->user_id);
        $this->assertSame('scheduled', $cancelled->old_values['status']);
        $this->assertSame('cancelled', $cancelled->new_values['status']);
        $this->assertSame($reason, $cancelled->reason);
    }

    // ============================================================
    // SECURITY: ACTOR IDENTITY
    // ============================================================

    public function test_actor_cannot_be_spoofed_through_request_input(): void
    {
        $student = $this->loginAsStudent();
        $otherUser = User::factory()->staff()->create();
        $cycle = ProgramCycle::factory()->open()->create();
        $requirement = Requirement::factory()->create();
        $cycle->requirements()->syncWithPivotValues([$requirement->id], ['is_required' => true]);
        $application = Application::factory()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);

        Storage::fake('docs');
        $this->fromSpa()->postJson('/api/student/applications/'.$application->id.'/documents', [
            'requirement_id' => $requirement->id,
            'file' => UploadedFile::fake()->create('doc.pdf', 512),
            'user_id' => $otherUser->id,
        ])->assertStatus(201);

        $audit = AuditLog::query()->where('action', 'document.uploaded')->firstOrFail();
        $this->assertSame($student->id, $audit->user_id);
        $this->assertNotSame($otherUser->id, $audit->user_id);
    }

    public function test_staff_actions_record_the_authenticated_staff_member(): void
    {
        $staffA = $this->loginAsStaff();
        $staffB = User::factory()->staff()->create();
        $application = Application::factory()->submitted()->create([
            'program_cycle_id' => ProgramCycle::factory()->open()->create()->id,
        ]);

        $this->fromSpa()->postJson('/api/staff/applications/'.$application->id.'/review', [
            'action' => 'start_review',
            'user_id' => $staffB->id,
        ])->assertOk();

        $this->assertDatabaseHas('application_status_history', [
            'application_id' => $application->id,
            'action' => 'start_review',
            'changed_by' => $staffA->id,
        ]);
    }

    // ============================================================
    // SECURITY: SENSITIVE FIELDS
    // ============================================================

    public function test_passwords_and_tokens_are_never_logged(): void
    {
        $this->fromSpa()->postJson('/api/register', [
            'name' => 'Juan Dela Cruz',
            'email' => 'juan.audit@example.com',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ])->assertStatus(201);

        $this->fromSpa()->postJson('/api/login', [
            'email' => 'juan.audit@example.com',
            'password' => 'Secret123!',
        ])->assertOk();

        $this->assertSame(0, AuditLog::count());

        foreach (AuditLog::all() as $log) {
            $encoded = json_encode([$log->old_values, $log->new_values, $log->metadata]);
            $this->assertStringNotContainsString('Secret123!', (string) $encoded);
        }
    }

    public function test_audit_values_are_scrubbed_of_sensitive_keys_defensively(): void
    {
        $staff = User::factory()->staff()->create();
        $slot = DeploymentSlot::factory()->active()->create();

        app(AuditLogger::class)->record(
            'deployment_slot.updated',
            $slot,
            oldValues: ['capacity' => 5, 'password' => 'should-not-persist'],
            newValues: ['capacity' => 8, 'token' => 'should-not-persist'],
            actor: $staff,
        );

        $audit = AuditLog::firstOrFail();
        $this->assertArrayNotHasKey('password', $audit->old_values ?? []);
        $this->assertArrayNotHasKey('token', $audit->new_values ?? []);
        $this->assertSame(5, $audit->old_values['capacity']);
        $this->assertSame(8, $audit->new_values['capacity']);
    }

    public function test_untracked_entities_are_rejected_rather_than_logged_indiscriminately(): void
    {
        $staff = User::factory()->staff()->create();
        $user = User::factory()->staff()->create();

        $this->expectException(InvalidArgumentException::class);

        app(AuditLogger::class)->record(
            'user.updated',
            $user,
            newValues: ['name' => 'x'],
            actor: $staff,
        );
    }

    // ============================================================
    // IMMUTABILITY
    // ============================================================

    public function test_audit_records_cannot_be_updated_or_deleted_at_model_level(): void
    {
        $this->loginAsStaff();
        $slot = DeploymentSlot::factory()->active()->create();

        $this->fromSpa()->postJson('/api/staff/deployment-slots', [
            'program_cycle_id' => $slot->program_cycle_id,
            'deployment_site_id' => $slot->deployment_site_id,
            'title' => 'Immutable Slot',
            'capacity' => 2,
        ])->assertStatus(201);

        $audit = AuditLog::firstOrFail();

        $this->expectException(RuntimeException::class);
        $audit->forceFill(['action' => 'tampered'])->save();
    }

    public function test_audit_records_cannot_be_deleted(): void
    {
        $this->loginAsStaff();
        $slot = DeploymentSlot::factory()->active()->create();

        $this->fromSpa()->postJson('/api/staff/deployment-slots', [
            'program_cycle_id' => $slot->program_cycle_id,
            'deployment_site_id' => $slot->deployment_site_id,
            'title' => 'Immutable Slot',
            'capacity' => 2,
        ])->assertStatus(201);

        $audit = AuditLog::firstOrFail();

        $this->expectException(RuntimeException::class);
        $audit->delete();
    }

    public function test_no_http_method_can_modify_or_remove_audit_records(): void
    {
        $this->loginAsStaff();
        $slot = DeploymentSlot::factory()->active()->create();

        $this->fromSpa()->postJson('/api/staff/deployment-slots', [
            'program_cycle_id' => $slot->program_cycle_id,
            'deployment_site_id' => $slot->deployment_site_id,
            'title' => 'Immutable Slot',
            'capacity' => 2,
        ])->assertStatus(201);

        $auditId = AuditLog::firstOrFail()->id;

        foreach (['putJson', 'patchJson', 'deleteJson', 'postJson'] as $method) {
            $this->fromSpa()->{$method}("/api/audit-logs/{$auditId}", [
                'action' => 'tampered',
            ]);
        }

        $this->assertSame('deployment_slot.created', AuditLog::findOrFail($auditId)->action);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_mass_assignment_cannot_create_or_alter_audit_records(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\MassAssignmentException::class);

        AuditLog::create([
            'user_id' => 1,
            'action' => 'forged.event',
            'auditable_type' => DeploymentSlot::class,
            'auditable_id' => 1,
        ]);
    }

    // ============================================================
    // TRANSACTIONS
    // ============================================================

    public function test_failed_state_change_does_not_create_false_history(): void
    {
        $this->loginAsStaff();
        $application = Application::factory()->approved()->create([
            'program_cycle_id' => ProgramCycle::factory()->open()->create()->id,
        ]);

        // Approving an already-approved application is invalid; nothing should be recorded.
        $this->fromSpa()->postJson('/api/staff/applications/'.$application->id.'/review', [
            'action' => 'approve',
        ])->assertStatus(422);

        $this->assertDatabaseMissing('application_status_history', [
            'application_id' => $application->id,
            'action' => 'approve',
        ]);
    }

    public function test_failing_audit_write_rolls_back_the_state_change(): void
    {
        $this->loginAsStaff();
        $application = Application::factory()->submitted()->create([
            'program_cycle_id' => ProgramCycle::factory()->open()->create()->id,
        ]);
        $document = ApplicationDocument::factory()->create([
            'application_id' => $application->id,
            'verification_status' => 'pending',
        ]);

        // Simulate the audit write failing: the whole state change must roll back.
        $logger = \Mockery::mock(AuditLogger::class);
        $logger->shouldReceive('record')->andThrow(new RuntimeException('disk failure'));
        $this->swap(AuditLogger::class, $logger);

        $this->fromSpa()
            ->patchJson('/api/staff/applications/'.$application->id.'/documents/'.$document->id.'/verification', [
                'verification_status' => 'verified',
            ]);

        $document->refresh();
        $this->assertSame('pending', $document->verification_status->value);
        $this->assertNull($document->verified_by);
        $this->assertNull($document->verified_at);
        $this->assertSame(0, AuditLog::count());
    }

    public function test_successful_state_change_still_creates_history_after_transaction_check(): void
    {
        $this->loginAsStaff();
        $application = Application::factory()->submitted()->create([
            'program_cycle_id' => ProgramCycle::factory()->open()->create()->id,
        ]);

        $this->fromSpa()->postJson('/api/staff/applications/'.$application->id.'/review', [
            'action' => 'start_review',
        ])->assertOk();

        $this->assertDatabaseHas('application_status_history', [
            'application_id' => $application->id,
            'action' => 'start_review',
        ]);
    }

    // ============================================================
    // HELPERS
    // ============================================================

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

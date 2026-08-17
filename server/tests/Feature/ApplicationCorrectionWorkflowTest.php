<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ProgramCycle;
use App\Models\Requirement;
use App\Models\StudentDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SpaAuthentication;
use Tests\TestCase;

class ApplicationCorrectionWorkflowTest extends TestCase
{
    use RefreshDatabase, SpaAuthentication;

    private function openCycleWithRequirements(): ProgramCycle
    {
        $cycle = ProgramCycle::factory()->open()->create();
        $cycle->requirements()->syncWithPivotValues(
            Requirement::factory()->count(2)->create()->pluck('id'),
            ['is_required' => true],
        );

        return $cycle;
    }

    // ============================================================
    // APPROVAL TESTS
    // ============================================================

    public function test_staff_can_approve_a_valid_submitted_application(): void
    {
        $this->loginAsStaff();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->submitted()->create(['program_cycle_id' => $cycle->id]);

        // Verify all required documents
        foreach ($cycle->requirements as $requirement) {
            \App\Models\ApplicationDocument::factory()->create([
                'application_id' => $application->id,
                'requirement_id' => $requirement->id,
                'verification_status' => 'verified',
            ]);
        }

        // Start review first: submitted -> under_review
        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'start_review',
            ])
            ->assertOk();

        // Then approve: under_review -> approved
        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'approve',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $application->refresh();
        $this->assertNotNull($application->approved_at);
        $this->assertDatabaseHas('application_status_history', [
            'application_id' => $application->id,
            'status' => 'approved',
        ]);
    }

    public function test_non_staff_cannot_approve_an_application(): void
    {
        $student = $this->loginAsStudent();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->submitted()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);

        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'approve',
            ])
            ->assertStatus(403);
    }

    public function test_student_cannot_approve_through_staff_endpoint(): void
    {
        $this->loginAsStudent();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->submitted()->create(['program_cycle_id' => $cycle->id]);

        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'approve',
            ])
            ->assertStatus(403);
    }

    public function test_draft_application_cannot_be_approved(): void
    {
        $this->loginAsStaff();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->create(['program_cycle_id' => $cycle->id]);

        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'approve',
            ])
            ->assertStatus(422);
    }

    public function test_application_with_rejected_documents_cannot_be_approved(): void
    {
        $this->loginAsStaff();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->submitted()->create(['program_cycle_id' => $cycle->id]);

        // Create a verified document for the first requirement
        $firstRequirement = $cycle->requirements->first();
        \App\Models\ApplicationDocument::factory()->create([
            'application_id' => $application->id,
            'requirement_id' => $firstRequirement->id,
            'verification_status' => 'verified',
        ]);

        // Create a rejected document for the second requirement
        $secondRequirement = $cycle->requirements->last();
        \App\Models\ApplicationDocument::factory()->create([
            'application_id' => $application->id,
            'requirement_id' => $secondRequirement->id,
            'verification_status' => 'rejected',
        ]);

        // Start review first
        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'start_review',
            ])
            ->assertOk();

        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'approve',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'rejected'));
    }

    public function test_application_with_pending_documents_cannot_be_approved(): void
    {
        $this->loginAsStaff();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->submitted()->create(['program_cycle_id' => $cycle->id]);

        // Create a pending document for the first requirement
        $firstRequirement = $cycle->requirements->first();
        \App\Models\ApplicationDocument::factory()->create([
            'application_id' => $application->id,
            'requirement_id' => $firstRequirement->id,
            'verification_status' => 'pending',
        ]);

        // Create a verified document for the second requirement
        $secondRequirement = $cycle->requirements->last();
        \App\Models\ApplicationDocument::factory()->create([
            'application_id' => $application->id,
            'requirement_id' => $secondRequirement->id,
            'verification_status' => 'verified',
        ]);

        // Start review first
        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'start_review',
            ])
            ->assertOk();

        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'approve',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'pending'));
    }

    public function test_application_with_missing_documents_cannot_be_approved(): void
    {
        $this->loginAsStaff();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->submitted()->create(['program_cycle_id' => $cycle->id]);

        // No documents uploaded at all

        // Start review first
        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'start_review',
            ])
            ->assertOk();

        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'approve',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'missing'));
    }

    public function test_approval_metadata_is_correctly_stored(): void
    {
        $this->loginAsStaff();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->submitted()->create(['program_cycle_id' => $cycle->id]);

        foreach ($cycle->requirements as $requirement) {
            \App\Models\ApplicationDocument::factory()->create([
                'application_id' => $application->id,
                'requirement_id' => $requirement->id,
                'verification_status' => 'verified',
            ]);
        }

        // Start review first
        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'start_review',
            ])
            ->assertOk();

        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'approve',
            ])
            ->assertOk();

        $application->refresh();
        $this->assertNotNull($application->approved_at);
        $this->assertNotNull($application->approved_by);
    }

    // ============================================================
    // REJECTION TESTS
    // ============================================================

    public function test_staff_can_reject_an_application(): void
    {
        $this->loginAsStaff();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->submitted()->create(['program_cycle_id' => $cycle->id]);

        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'reject',
                'remarks' => 'Application does not meet program requirements.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $application->refresh();
        $this->assertNotNull($application->decision_reason);
        $this->assertNotNull($application->decided_by);
        $this->assertNotNull($application->decided_at);
    }

    public function test_rejection_requires_a_reason(): void
    {
        $this->loginAsStaff();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->submitted()->create(['program_cycle_id' => $cycle->id]);

        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'reject',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('remarks');
    }

    public function test_rejection_with_empty_remarks_is_rejected(): void
    {
        $this->loginAsStaff();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->submitted()->create(['program_cycle_id' => $cycle->id]);

        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'reject',
                'remarks' => '   ',
            ])
            ->assertStatus(422);
    }

    public function test_student_cannot_reject_an_application(): void
    {
        $this->loginAsStudent();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->submitted()->create(['program_cycle_id' => $cycle->id]);

        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'reject',
                'remarks' => 'Some reason.',
            ])
            ->assertStatus(403);
    }

    public function test_rejected_application_cannot_be_approved_through_normal_workflow(): void
    {
        $this->loginAsStaff();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->submitted()->create(['program_cycle_id' => $cycle->id]);

        // First reject it
        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'reject',
                'remarks' => 'Application does not meet program requirements.',
            ])
            ->assertOk();

        // Then try to approve it
        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'approve',
            ])
            ->assertStatus(422);
    }

    public function test_rejected_application_cannot_be_rejected_again(): void
    {
        $this->loginAsStaff();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->submitted()->create(['program_cycle_id' => $cycle->id]);

        // First reject it
        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'reject',
                'remarks' => 'Application does not meet program requirements.',
            ])
            ->assertOk();

        // Then try to reject it again
        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'reject',
                'remarks' => 'Another reason.',
            ])
            ->assertStatus(422);
    }

    public function test_rejection_reason_is_stored_in_application_model(): void
    {
        $this->loginAsStaff();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->submitted()->create(['program_cycle_id' => $cycle->id]);

        $reason = 'Application does not meet program requirements. Please reapply next semester.';
        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'reject',
                'remarks' => $reason,
            ])
            ->assertOk();

        $application->refresh();
        $this->assertEquals($reason, $application->decision_reason);
    }

    // ============================================================
    // RETURN FOR CORRECTION TESTS
    // ============================================================

    public function test_staff_can_return_application_for_correction(): void
    {
        $this->loginAsStaff();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->submitted()->create(['program_cycle_id' => $cycle->id]);

        $reason = 'Please upload your current Certificate of Registration.';
        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'return_for_correction',
                'remarks' => $reason,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'returned_for_correction');

        $application->refresh();
        $this->assertEquals($reason, $application->decision_reason);
        $this->assertNotNull($application->decided_by);
        $this->assertNotNull($application->decided_at);
    }

    public function test_return_for_correction_requires_a_reason(): void
    {
        $this->loginAsStaff();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->submitted()->create(['program_cycle_id' => $cycle->id]);

        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'return_for_correction',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('remarks');
    }

    public function test_return_for_correction_with_empty_remarks_is_rejected(): void
    {
        $this->loginAsStaff();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->submitted()->create(['program_cycle_id' => $cycle->id]);

        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'return_for_correction',
                'remarks' => '   ',
            ])
            ->assertStatus(422);
    }

    public function test_student_cannot_return_application_for_correction(): void
    {
        $this->loginAsStudent();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->submitted()->create(['program_cycle_id' => $cycle->id]);

        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'return_for_correction',
                'remarks' => 'Some reason.',
            ])
            ->assertStatus(403);
    }

    public function test_under_review_application_can_be_returned_for_correction(): void
    {
        $this->loginAsStaff();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->submitted()->create(['program_cycle_id' => $cycle->id]);

        // Start review first
        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'start_review',
            ])
            ->assertOk();

        // Then return for correction
        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'return_for_correction',
                'remarks' => 'The Certificate of Registration is from the previous semester.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'returned_for_correction');
    }

    public function test_student_can_access_own_returned_application(): void
    {
        $student = $this->loginAsStudent();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->returnedForCorrection()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);

        $this->fromSpa()
            ->getJson('/api/student/applications/'.$application->id)
            ->assertOk()
            ->assertJsonPath('data.status', 'returned_for_correction');
    }

    public function test_student_cannot_access_another_students_returned_application(): void
    {
        $this->loginAsStudent();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->returnedForCorrection()->create([
            'program_cycle_id' => $cycle->id,
        ]);

        $this->fromSpa()
            ->getJson('/api/student/applications/'.$application->id)
            ->assertStatus(403);
    }

    public function test_student_can_edit_application_in_correction_state(): void
    {
        $student = $this->loginAsStudent();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->returnedForCorrection()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);

        $this->fromSpa()
            ->putJson('/api/student/applications/'.$application->id, [
                'remarks' => 'Updated remarks after correction request.',
            ])
            ->assertOk();

        $application->refresh();
        $this->assertEquals('Updated remarks after correction request.', $application->remarks);
    }

    public function test_student_cannot_change_application_status_directly(): void
    {
        $student = $this->loginAsStudent();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->returnedForCorrection()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);

        $this->fromSpa()
            ->putJson('/api/student/applications/'.$application->id, [
                'status' => 'approved',
            ])
            ->assertOk();

        $application->refresh();
        $this->assertEquals('returned_for_correction', $application->status->value);
    }

    public function test_student_cannot_modify_decision_reason(): void
    {
        $student = $this->loginAsStudent();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->returnedForCorrection()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
            'decision_reason' => 'Original staff reason.',
        ]);

        $this->fromSpa()
            ->putJson('/api/student/applications/'.$application->id, [
                'decision_reason' => 'Hacked reason.',
            ])
            ->assertOk();

        $application->refresh();
        $this->assertEquals('Original staff reason.', $application->decision_reason);
    }

    // ============================================================
    // RESUBMISSION TESTS
    // ============================================================

    public function test_corrected_application_can_be_resubmitted(): void
    {
        $student = $this->loginAsStudent();
        StudentDetail::factory()->create(['user_id' => $student->id]);
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->returnedForCorrection()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);

        // Upload corrected documents
        Storage::fake('docs');
        foreach ($cycle->requirements as $requirement) {
            $this->fromSpa()
                ->postJson('/api/student/applications/'.$application->id.'/documents', [
                    'requirement_id' => $requirement->id,
                    'file' => UploadedFile::fake()->create($requirement->slug.'.pdf', 512),
                ])->assertStatus(201);
        }

        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/submit')
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted');

        $this->assertDatabaseHas('application_status_history', [
            'application_id' => $application->id,
            'status' => 'submitted',
        ]);
    }

    public function test_incomplete_correction_cannot_be_submitted(): void
    {
        $student = $this->loginAsStudent();
        StudentDetail::factory()->create(['user_id' => $student->id]);
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->returnedForCorrection()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);

        // Don't upload any documents

        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/submit')
            ->assertStatus(422);
    }

    public function test_resubmission_returns_application_to_submitted_state(): void
    {
        $student = $this->loginAsStudent();
        StudentDetail::factory()->create(['user_id' => $student->id]);
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->returnedForCorrection()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);

        Storage::fake('docs');
        foreach ($cycle->requirements as $requirement) {
            $this->fromSpa()
                ->postJson('/api/student/applications/'.$application->id.'/documents', [
                    'requirement_id' => $requirement->id,
                    'file' => UploadedFile::fake()->create($requirement->slug.'.pdf', 512),
                ])->assertStatus(201);
        }

        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/submit')
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted');
    }

    public function test_student_cannot_directly_manipulate_application_status(): void
    {
        $student = $this->loginAsStudent();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->returnedForCorrection()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);

        $this->fromSpa()
            ->putJson('/api/student/applications/'.$application->id, [
                'status' => 'submitted',
            ])
            ->assertOk();

        $application->refresh();
        $this->assertEquals('returned_for_correction', $application->status->value);
    }

    // ============================================================
    // DOCUMENT SECURITY TESTS
    // ============================================================

    public function test_student_cannot_change_verification_status_directly(): void
    {
        $student = $this->loginAsStudent();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->returnedForCorrection()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);

        $requirement = $cycle->requirements->first();
        $document = \App\Models\ApplicationDocument::factory()->create([
            'application_id' => $application->id,
            'requirement_id' => $requirement->id,
            'verification_status' => 'pending',
        ]);

        // Try to change verification status via document upload (should reset to pending)
        Storage::fake('docs');
        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/documents', [
                'requirement_id' => $requirement->id,
                'file' => UploadedFile::fake()->create('test.pdf', 512),
            ])
            ->assertStatus(201);

        $document->refresh();
        $this->assertEquals('pending', $document->verification_status->value);
        $this->assertNull($document->verified_by);
        $this->assertNull($document->verified_at);
    }

    public function test_replacement_document_belongs_to_correct_application(): void
    {
        $student = $this->loginAsStudent();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->returnedForCorrection()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);

        $requirement = $cycle->requirements->first();
        $document = \App\Models\ApplicationDocument::factory()->create([
            'application_id' => $application->id,
            'requirement_id' => $requirement->id,
            'verification_status' => 'rejected',
        ]);

        Storage::fake('docs');
        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/documents', [
                'requirement_id' => $requirement->id,
                'file' => UploadedFile::fake()->create('corrected.pdf', 512),
            ])
            ->assertStatus(201);

        $document->refresh();
        $this->assertEquals('pending', $document->verification_status->value);
        $this->assertNull($document->rejection_reason);
        $this->assertNull($document->verified_by);
        $this->assertNull($document->verified_at);
    }

    // ============================================================
    // STATE TRANSITION TESTS
    // ============================================================

    public function test_valid_transitions_succeed(): void
    {
        $this->loginAsStaff();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->submitted()->create(['program_cycle_id' => $cycle->id]);

        // submitted -> under_review
        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'start_review',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'under_review');

        // under_review -> returned_for_correction
        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'return_for_correction',
                'remarks' => 'Please update your documents.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'returned_for_correction');
    }

    public function test_invalid_transitions_fail(): void
    {
        $this->loginAsStaff();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->approved()->create(['program_cycle_id' => $cycle->id]);

        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'reject',
                'remarks' => 'Some reason.',
            ])
            ->assertStatus(422);
    }

    public function test_terminal_states_cannot_be_modified_through_normal_workflow(): void
    {
        $this->loginAsStaff();
        $cycle = $this->openCycleWithRequirements();

        // Test rejected state
        $application = Application::factory()->rejected()->create(['program_cycle_id' => $cycle->id]);

        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'approve',
            ])
            ->assertStatus(422);

        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'return_for_correction',
                'remarks' => 'Some reason.',
            ])
            ->assertStatus(422);
    }

    public function test_student_can_resubmit_from_returned_for_correction_state(): void
    {
        $student = $this->loginAsStudent();
        StudentDetail::factory()->create(['user_id' => $student->id]);
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->returnedForCorrection()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);

        Storage::fake('docs');
        foreach ($cycle->requirements as $requirement) {
            $this->fromSpa()
                ->postJson('/api/student/applications/'.$application->id.'/documents', [
                    'requirement_id' => $requirement->id,
                    'file' => UploadedFile::fake()->create($requirement->slug.'.pdf', 512),
                ])->assertStatus(201);
        }

        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/submit')
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted');
    }

    // ============================================================
    // AUTHORIZATION TESTS
    // ============================================================

    public function test_unauthenticated_user_cannot_access_staff_endpoints(): void
    {
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->submitted()->create(['program_cycle_id' => $cycle->id]);

        $this->postJson('/api/staff/applications/'.$application->id.'/review', [
            'action' => 'approve',
        ])->assertStatus(401);
    }

    public function test_student_cannot_access_staff_application_detail(): void
    {
        $this->loginAsStudent();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->submitted()->create(['program_cycle_id' => $cycle->id]);

        $this->fromSpa()
            ->getJson('/api/staff/applications/'.$application->id)
            ->assertStatus(403);
    }

    // ============================================================
    // NO DUPLICATE SUBMISSION TESTS
    // ============================================================

    public function test_approved_application_cannot_be_approved_again(): void
    {
        $this->loginAsStaff();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->approved()->create(['program_cycle_id' => $cycle->id]);

        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'approve',
            ])
            ->assertStatus(422);
    }

    public function test_withdrawn_application_cannot_be_approved(): void
    {
        $this->loginAsStaff();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->create([
            'program_cycle_id' => $cycle->id,
            'status' => 'withdrawn',
        ]);

        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'approve',
            ])
            ->assertStatus(422);
    }

    // ============================================================
    // STATUS HISTORY TESTS
    // ============================================================

    public function test_status_history_records_correction_decision(): void
    {
        $this->loginAsStaff();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->submitted()->create(['program_cycle_id' => $cycle->id]);

        $reason = 'Please upload your current COR.';
        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'return_for_correction',
                'remarks' => $reason,
            ])
            ->assertOk();

        $this->assertDatabaseHas('application_status_history', [
            'application_id' => $application->id,
            'status' => 'returned_for_correction',
            'remarks' => $reason,
        ]);
    }

    public function test_status_history_records_rejection_decision(): void
    {
        $this->loginAsStaff();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->submitted()->create(['program_cycle_id' => $cycle->id]);

        $reason = 'Application does not meet program requirements.';
        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'reject',
                'remarks' => $reason,
            ])
            ->assertOk();

        $this->assertDatabaseHas('application_status_history', [
            'application_id' => $application->id,
            'status' => 'rejected',
            'remarks' => $reason,
        ]);
    }
}

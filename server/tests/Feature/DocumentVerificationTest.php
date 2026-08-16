<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\ProgramCycle;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SpaAuthentication;
use Tests\TestCase;

/**
 * Staff document verification workflow: authorization, ownership, state
 * transitions, validation and metadata integrity.
 */
class DocumentVerificationTest extends TestCase
{
    use RefreshDatabase, SpaAuthentication;

    private const VALID_REASON = 'The uploaded COR is from the previous semester. Please provide the current one.';

    /**
     * Create a submitted application carrying one pending requirement document
     * and return [application, document, student].
     *
     * @return array{0: Application, 1: ApplicationDocument, 2: User}
     */
    private function submittedApplicationWithPendingDocument(): array
    {
        $student = User::factory()->student()->create();
        $cycle = ProgramCycle::factory()->open()->create();
        $requirement = Requirement::factory()->create();
        $cycle->requirements()->syncWithPivotValues([$requirement->id], ['is_required' => true]);

        $application = Application::factory()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $document = ApplicationDocument::factory()->create([
            'application_id' => $application->id,
            'requirement_id' => $requirement->id,
            'verification_status' => 'pending',
        ]);

        return [$application, $document, $student];
    }

    private function verifyUrl(Application $application, ApplicationDocument $document): string
    {
        return '/api/staff/applications/'.$application->id.'/documents/'.$document->id.'/verification';
    }

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    public function test_a_guest_cannot_verify_a_document(): void
    {
        [$application, $document] = $this->submittedApplicationWithPendingDocument();

        $this->fromSpa()
            ->patchJson($this->verifyUrl($application, $document), [
                'verification_status' => 'verified',
            ])
            ->assertStatus(401);

        $this->assertDatabaseHas('application_documents', [
            'id' => $document->id,
            'verification_status' => 'pending',
        ]);
    }

    public function test_a_student_cannot_verify_a_document(): void
    {
        [$application, $document, $student] = $this->submittedApplicationWithPendingDocument();
        $this->loginAs($student)->assertOk();

        $this->fromSpa()
            ->patchJson($this->verifyUrl($application, $document), [
                'verification_status' => 'verified',
            ])
            ->assertStatus(403);

        $this->assertDatabaseHas('application_documents', [
            'id' => $document->id,
            'verification_status' => 'pending',
        ]);
    }

    public function test_a_staff_member_can_verify_a_document(): void
    {
        [$application, $document] = $this->submittedApplicationWithPendingDocument();
        $this->loginAsStaff();

        $this->fromSpa()
            ->patchJson($this->verifyUrl($application, $document), [
                'verification_status' => 'verified',
            ])
            ->assertOk()
            ->assertJsonPath('data.verification_status', 'verified')
            ->assertJsonPath('data.rejection_reason', null);
    }

    /*
    |--------------------------------------------------------------------------
    | Ownership / relationship security
    |--------------------------------------------------------------------------
    */

    public function test_a_staff_member_can_verify_a_document_belonging_to_the_application(): void
    {
        [$application, $document] = $this->submittedApplicationWithPendingDocument();
        $this->loginAsStaff();

        $this->fromSpa()
            ->patchJson($this->verifyUrl($application, $document), [
                'verification_status' => 'verified',
            ])
            ->assertOk()
            ->assertJsonPath('data.application_id', $application->id)
            ->assertJsonPath('data.id', $document->id);
    }

    public function test_a_staff_member_cannot_verify_a_document_of_another_application(): void
    {
        [$applicationA, $documentA] = $this->submittedApplicationWithPendingDocument();
        [$applicationB, $documentB] = $this->submittedApplicationWithPendingDocument();
        $this->loginAsStaff();

        // Document B is passed under application A's route.
        $this->fromSpa()
            ->patchJson($this->verifyUrl($applicationA, $documentB), [
                'verification_status' => 'verified',
            ])
            ->assertStatus(404);

        $this->assertDatabaseHas('application_documents', [
            'id' => $documentB->id,
            'verification_status' => 'pending',
        ]);
    }

    public function test_changing_the_application_id_cannot_bypass_ownership(): void
    {
        [$applicationA, $documentA] = $this->submittedApplicationWithPendingDocument();
        [$applicationB] = $this->submittedApplicationWithPendingDocument();
        $this->loginAsStaff();

        // Document A is passed under application B's route.
        $this->fromSpa()
            ->patchJson($this->verifyUrl($applicationB, $documentA), [
                'verification_status' => 'verified',
            ])
            ->assertStatus(404);

        $this->assertDatabaseHas('application_documents', [
            'id' => $documentA->id,
            'verification_status' => 'pending',
        ]);
    }

    public function test_changing_the_document_id_cannot_bypass_ownership(): void
    {
        [$applicationA] = $this->submittedApplicationWithPendingDocument();
        [$applicationB, $documentB] = $this->submittedApplicationWithPendingDocument();
        $this->loginAsStaff();

        // Document B is passed under application A's route.
        $this->fromSpa()
            ->patchJson($this->verifyUrl($applicationA, $documentB), [
                'verification_status' => 'verified',
            ])
            ->assertStatus(404);

        $this->assertDatabaseHas('application_documents', [
            'id' => $documentB->id,
            'verification_status' => 'pending',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Verification transitions
    |--------------------------------------------------------------------------
    */

    public function test_a_pending_document_can_be_verified(): void
    {
        [$application, $document] = $this->submittedApplicationWithPendingDocument();
        $staff = $this->loginAsStaff();

        $this->fromSpa()
            ->patchJson($this->verifyUrl($application, $document), [
                'verification_status' => 'verified',
            ])
            ->assertOk()
            ->assertJsonPath('data.verification_status', 'verified');

        $this->assertDatabaseHas('application_documents', [
            'id' => $document->id,
            'verification_status' => 'verified',
            'verified_by' => $staff->id,
            'rejection_reason' => null,
        ]);
        $this->assertNotNull($document->fresh()->verified_at);
    }

    public function test_a_verified_document_carries_verifier_information(): void
    {
        [$application, $document] = $this->submittedApplicationWithPendingDocument();
        $staff = $this->loginAsStaff();

        $this->fromSpa()
            ->patchJson($this->verifyUrl($application, $document), [
                'verification_status' => 'verified',
            ])
            ->assertOk();

        $fresh = $document->fresh();
        $this->assertSame('verified', $fresh->verification_status->value);
        $this->assertSame($staff->id, $fresh->verified_by);
        $this->assertNotNull($fresh->verified_at);
        $this->assertNull($fresh->rejection_reason);
    }

    public function test_a_pending_document_can_be_rejected(): void
    {
        [$application, $document] = $this->submittedApplicationWithPendingDocument();
        $staff = $this->loginAsStaff();

        $this->fromSpa()
            ->patchJson($this->verifyUrl($application, $document), [
                'verification_status' => 'rejected',
                'rejection_reason' => self::VALID_REASON,
            ])
            ->assertOk()
            ->assertJsonPath('data.verification_status', 'rejected')
            ->assertJsonPath('data.rejection_reason', self::VALID_REASON);

        $this->assertDatabaseHas('application_documents', [
            'id' => $document->id,
            'verification_status' => 'rejected',
            'verified_by' => $staff->id,
            'rejection_reason' => self::VALID_REASON,
        ]);
    }

    public function test_a_rejected_document_is_not_marked_verified(): void
    {
        [$application, $document] = $this->submittedApplicationWithPendingDocument();
        $this->loginAsStaff();

        $this->fromSpa()
            ->patchJson($this->verifyUrl($application, $document), [
                'verification_status' => 'rejected',
                'rejection_reason' => self::VALID_REASON,
            ])
            ->assertOk();

        $fresh = $document->fresh();
        $this->assertSame('rejected', $fresh->verification_status->value);
        $this->assertNotNull($fresh->verified_at);
    }

    public function test_verification_timestamp_is_generated_server_side(): void
    {
        [$application, $document] = $this->submittedApplicationWithPendingDocument();
        $this->loginAsStaff();

        $clientTimestamp = now()->addYear()->toDateTimeString();

        $this->fromSpa()
            ->patchJson($this->verifyUrl($application, $document), [
                'verification_status' => 'verified',
                'verified_at' => $clientTimestamp,
            ])
            ->assertOk();

        $fresh = $document->fresh();
        $this->assertNotSame($clientTimestamp, $fresh->verified_at?->toDateTimeString());
        $this->assertNotNull($fresh->verified_at);
    }

    public function test_verifier_is_generated_server_side(): void
    {
        [$application, $document] = $this->submittedApplicationWithPendingDocument();
        $staff = $this->loginAsStaff();
        $intruder = User::factory()->staff()->create();

        $this->fromSpa()
            ->patchJson($this->verifyUrl($application, $document), [
                'verification_status' => 'verified',
                'verified_by' => $intruder->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('application_documents', [
            'id' => $document->id,
            'verified_by' => $staff->id,
        ]);
        $this->assertDatabaseMissing('application_documents', [
            'id' => $document->id,
            'verified_by' => $intruder->id,
        ]);
    }

    public function test_verifying_clears_any_previous_rejection_reason(): void
    {
        [$application, $document] = $this->submittedApplicationWithPendingDocument();

        // Simulate a record that somehow carried a stale reason while pending.
        $document->update(['rejection_reason' => 'stale reason from a previous cycle']);

        $this->loginAsStaff();

        $this->fromSpa()
            ->patchJson($this->verifyUrl($application, $document), [
                'verification_status' => 'verified',
            ])
            ->assertOk();

        $this->assertDatabaseHas('application_documents', [
            'id' => $document->id,
            'verification_status' => 'verified',
            'rejection_reason' => null,
        ]);
    }

    public function test_an_already_verified_document_cannot_be_re_decided(): void
    {
        [$application, $document] = $this->submittedApplicationWithPendingDocument();
        $document->update(['verification_status' => 'verified', 'verified_at' => now()]);
        $this->loginAsStaff();

        $this->fromSpa()
            ->patchJson($this->verifyUrl($application, $document), [
                'verification_status' => 'verified',
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('application_documents', [
            'id' => $document->id,
            'verification_status' => 'verified',
        ]);
    }

    public function test_an_already_rejected_document_cannot_be_re_decided(): void
    {
        [$application, $document] = $this->submittedApplicationWithPendingDocument();
        $document->update([
            'verification_status' => 'rejected',
            'rejection_reason' => self::VALID_REASON,
            'verified_at' => now(),
        ]);
        $this->loginAsStaff();

        $this->fromSpa()
            ->patchJson($this->verifyUrl($application, $document), [
                'verification_status' => 'rejected',
                'rejection_reason' => self::VALID_REASON,
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('application_documents', [
            'id' => $document->id,
            'verification_status' => 'rejected',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Application status restriction
    |--------------------------------------------------------------------------
    */

    public function test_staff_cannot_verify_a_document_of_a_draft_application(): void
    {
        $student = User::factory()->student()->create();
        $application = Application::factory()->create([
            'applicant_id' => $student->id,
            'status' => 'draft',
        ]);
        $document = ApplicationDocument::factory()->create([
            'application_id' => $application->id,
            'verification_status' => 'pending',
        ]);
        $this->loginAsStaff();

        $this->fromSpa()
            ->patchJson($this->verifyUrl($application, $document), [
                'verification_status' => 'verified',
            ])
            ->assertStatus(403);

        $this->assertDatabaseHas('application_documents', [
            'id' => $document->id,
            'verification_status' => 'pending',
        ]);
    }

    public function test_staff_cannot_verify_a_document_of_an_approved_application(): void
    {
        $student = User::factory()->student()->create();
        $application = Application::factory()->approved()->create([
            'applicant_id' => $student->id,
        ]);
        $document = ApplicationDocument::factory()->create([
            'application_id' => $application->id,
            'verification_status' => 'pending',
        ]);
        $this->loginAsStaff();

        $this->fromSpa()
            ->patchJson($this->verifyUrl($application, $document), [
                'verification_status' => 'verified',
            ])
            ->assertStatus(403);

        $this->assertDatabaseHas('application_documents', [
            'id' => $document->id,
            'verification_status' => 'pending',
        ]);
    }

    public function test_staff_cannot_verify_a_document_of_a_documents_incomplete_application(): void
    {
        $student = User::factory()->student()->create();
        $application = Application::factory()->create([
            'applicant_id' => $student->id,
            'status' => ApplicationStatus::DocumentsIncomplete->value,
        ]);
        $document = ApplicationDocument::factory()->create([
            'application_id' => $application->id,
            'verification_status' => 'pending',
        ]);
        $this->loginAsStaff();

        $this->fromSpa()
            ->patchJson($this->verifyUrl($application, $document), [
                'verification_status' => 'verified',
            ])
            ->assertStatus(403);
    }

    public function test_staff_can_verify_a_document_of_an_under_review_application(): void
    {
        $student = User::factory()->student()->create();
        $application = Application::factory()->create([
            'applicant_id' => $student->id,
            'status' => ApplicationStatus::UnderReview->value,
            'submitted_at' => now(),
        ]);
        $document = ApplicationDocument::factory()->create([
            'application_id' => $application->id,
            'verification_status' => 'pending',
        ]);
        $this->loginAsStaff();

        $this->fromSpa()
            ->patchJson($this->verifyUrl($application, $document), [
                'verification_status' => 'verified',
            ])
            ->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    public function test_an_invalid_status_is_rejected(): void
    {
        [$application, $document] = $this->submittedApplicationWithPendingDocument();
        $this->loginAsStaff();

        $this->fromSpa()
            ->patchJson($this->verifyUrl($application, $document), [
                'verification_status' => 'approved',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('verification_status');
    }

    public function test_pending_is_not_accepted_as_a_target_status(): void
    {
        [$application, $document] = $this->submittedApplicationWithPendingDocument();
        $this->loginAsStaff();

        $this->fromSpa()
            ->patchJson($this->verifyUrl($application, $document), [
                'verification_status' => 'pending',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('verification_status');
    }

    public function test_rejection_without_a_reason_is_rejected(): void
    {
        [$application, $document] = $this->submittedApplicationWithPendingDocument();
        $this->loginAsStaff();

        $this->fromSpa()
            ->patchJson($this->verifyUrl($application, $document), [
                'verification_status' => 'rejected',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rejection_reason');
    }

    public function test_an_empty_rejection_reason_is_rejected(): void
    {
        [$application, $document] = $this->submittedApplicationWithPendingDocument();
        $this->loginAsStaff();

        $this->fromSpa()
            ->patchJson($this->verifyUrl($application, $document), [
                'verification_status' => 'rejected',
                'rejection_reason' => '',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rejection_reason');
    }

    public function test_a_whitespace_only_rejection_reason_is_rejected(): void
    {
        [$application, $document] = $this->submittedApplicationWithPendingDocument();
        $this->loginAsStaff();

        $this->fromSpa()
            ->patchJson($this->verifyUrl($application, $document), [
                'verification_status' => 'rejected',
                'rejection_reason' => '          ',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rejection_reason');
    }

    public function test_a_very_short_rejection_reason_is_rejected(): void
    {
        [$application, $document] = $this->submittedApplicationWithPendingDocument();
        $this->loginAsStaff();

        $this->fromSpa()
            ->patchJson($this->verifyUrl($application, $document), [
                'verification_status' => 'rejected',
                'rejection_reason' => 'No',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rejection_reason');
    }

    public function test_an_excessively_long_rejection_reason_is_rejected(): void
    {
        [$application, $document] = $this->submittedApplicationWithPendingDocument();
        $this->loginAsStaff();

        $this->fromSpa()
            ->patchJson($this->verifyUrl($application, $document), [
                'verification_status' => 'rejected',
                'rejection_reason' => str_repeat('a', 1001),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rejection_reason');
    }

    public function test_a_rejection_reason_is_not_allowed_when_verifying(): void
    {
        [$application, $document] = $this->submittedApplicationWithPendingDocument();
        $this->loginAsStaff();

        $this->fromSpa()
            ->patchJson($this->verifyUrl($application, $document), [
                'verification_status' => 'verified',
                'rejection_reason' => 'This should not be accepted alongside a verification.',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rejection_reason');
    }

    /*
    |--------------------------------------------------------------------------
    | Security: metadata integrity, mass assignment, file privacy
    |--------------------------------------------------------------------------
    */

    public function test_a_student_cannot_inject_verification_metadata_while_uploading(): void
    {
        Storage::fake('docs');

        $student = User::factory()->student()->create();
        $this->loginAs($student)->assertOk();

        $cycle = ProgramCycle::factory()->open()->create();
        $requirement = Requirement::factory()->create();
        $cycle->requirements()->syncWithPivotValues([$requirement->id], ['is_required' => true]);

        $application = Application::factory()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);

        $intruder = User::factory()->staff()->create();

        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/documents', [
                'requirement_id' => $requirement->id,
                'file' => UploadedFile::fake()->create('cor.pdf', 512),
                'verification_status' => 'verified',
                'verified_by' => $intruder->id,
                'verified_at' => now()->toDateTimeString(),
                'rejection_reason' => 'injected by student',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.verification_status', 'pending');

        $this->assertDatabaseHas('application_documents', [
            'requirement_id' => $requirement->id,
            'verification_status' => 'pending',
            'verified_by' => null,
            'verified_at' => null,
            'rejection_reason' => null,
        ]);
    }

    public function test_a_student_cannot_turn_a_document_verified_through_the_upload_endpoint(): void
    {
        Storage::fake('docs');

        $student = User::factory()->student()->create();
        $this->loginAs($student)->assertOk();

        $cycle = ProgramCycle::factory()->open()->create();
        $requirement = Requirement::factory()->create();
        $cycle->requirements()->syncWithPivotValues([$requirement->id], ['is_required' => true]);

        $application = Application::factory()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);

        $document = ApplicationDocument::factory()->create([
            'application_id' => $application->id,
            'requirement_id' => $requirement->id,
            'verification_status' => 'rejected',
            'rejection_reason' => self::VALID_REASON,
        ]);

        // Uploading a replacement for the same requirement must reset the
        // document to pending — never inherit a previous decision.
        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/documents', [
                'requirement_id' => $requirement->id,
                'file' => UploadedFile::fake()->create('cor.pdf', 512),
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.verification_status', 'pending')
            ->assertJsonPath('data.rejection_reason', null);

        $this->assertDatabaseHas('application_documents', [
            'id' => $document->id,
            'verification_status' => 'pending',
            'rejection_reason' => null,
        ]);
    }

    public function test_document_files_remain_private(): void
    {
        Storage::fake('docs');

        [$application, $document] = $this->submittedApplicationWithPendingDocument();
        Storage::disk('docs')->put($document->file_path, 'secret contents');

        $this->loginAsStaff();
        $this->fromSpa()
            ->getJson('/api/documents/'.$document->id.'/download')
            ->assertOk();

        // A different student must not be able to read the file.
        $other = User::factory()->student()->create();
        $this->loginAs($other)->assertOk();
        $this->fromSpa()
            ->getJson('/api/documents/'.$document->id.'/download')
            ->assertStatus(403);
    }

    public function test_the_api_resource_never_exposes_filesystem_paths(): void
    {
        [$application, $document] = $this->submittedApplicationWithPendingDocument();
        $this->loginAsStaff();

        $response = $this->fromSpa()
            ->patchJson($this->verifyUrl($application, $document), [
                'verification_status' => 'verified',
            ])
            ->assertOk();

        $response->assertJsonMissingPath('data.file_path');
        $this->assertStringNotContainsString('applications/', (string) $response->getContent());
        $this->assertStringNotContainsString($document->file_path, (string) $response->getContent());
    }

    public function test_a_student_never_sees_the_verifying_staff_name(): void
    {
        [$application, $document, $student] = $this->submittedApplicationWithPendingDocument();
        $staff = $this->loginAsStaff();

        $this->fromSpa()
            ->patchJson($this->verifyUrl($application, $document), [
                'verification_status' => 'verified',
            ])
            ->assertOk();

        $this->loginAs($student)->assertOk();
        $this->fromSpa()
            ->getJson('/api/student/applications/'.$application->id.'/documents')
            ->assertOk()
            ->assertJsonMissingPath('data.0.verified_by')
            ->assertJsonMissingPath('data.0.file_path');

        $this->assertSame($staff->id, $document->fresh()->verified_by);
    }

    /*
    |--------------------------------------------------------------------------
    | No automatic application approval
    |--------------------------------------------------------------------------
    */

    public function test_verifying_every_document_does_not_approve_the_application(): void
    {
        $student = User::factory()->student()->create();
        $cycle = ProgramCycle::factory()->open()->create();
        $requirements = Requirement::factory()->count(2)->create();
        $cycle->requirements()->syncWithPivotValues($requirements->pluck('id')->all(), ['is_required' => true]);

        $application = Application::factory()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $this->loginAsStaff();

        foreach ($requirements as $requirement) {
            $document = ApplicationDocument::factory()->create([
                'application_id' => $application->id,
                'requirement_id' => $requirement->id,
                'verification_status' => 'pending',
            ]);

            $this->fromSpa()
                ->patchJson($this->verifyUrl($application, $document), [
                    'verification_status' => 'verified',
                ])
                ->assertOk();
        }

        $this->assertSame(2, $application->documents()->where('verification_status', 'verified')->count());
        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => ApplicationStatus::Submitted->value,
        ]);
    }
}

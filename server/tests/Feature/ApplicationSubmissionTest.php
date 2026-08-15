<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\ProgramCycle;
use App\Models\Requirement;
use App\Models\StudentDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SpaAuthentication;
use Tests\TestCase;

class ApplicationSubmissionTest extends TestCase
{
    use RefreshDatabase, SpaAuthentication;

    private function createProfile(User $student): void
    {
        StudentDetail::factory()->create(['user_id' => $student->id]);
    }

    /**
     * An open cycle with the given mix of required and optional requirements.
     */
    private function cycleWithRequirements(int $required = 1, int $optional = 0): ProgramCycle
    {
        $cycle = ProgramCycle::factory()->open()->create();

        $pivots = [];
        foreach (Requirement::factory()->count($required)->create() as $requirement) {
            $pivots[$requirement->id] = ['is_required' => true];
        }
        foreach (Requirement::factory()->count($optional)->create() as $requirement) {
            $pivots[$requirement->id] = ['is_required' => false];
        }

        $cycle->requirements()->sync($pivots);

        return $cycle;
    }

    private function upload(Application $application, Requirement $requirement): void
    {
        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/documents', [
                'requirement_id' => $requirement->id,
                'file' => UploadedFile::fake()->create($requirement->slug.'.pdf', 512),
            ])
            ->assertStatus(201);
    }

    /**
     * Create a student with a complete profile and an application whose every
     * required document has been uploaded. Returns [student, application].
     *
     * @return array{0: User, 1: Application}
     */
    private function makeCompleteApplication(int $required = 2, int $optional = 1): array
    {
        $student = $this->loginAsStudent();
        $this->createProfile($student);

        $cycle = $this->cycleWithRequirements($required, $optional);
        $application = Application::factory()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);

        Storage::fake('docs');
        foreach ($cycle->requirements()->wherePivot('is_required', true)->get() as $requirement) {
            $this->upload($application, $requirement);
        }

        return [$student, $application];
    }

    /*
    |--------------------------------------------------------------------------
    | Submission
    |--------------------------------------------------------------------------
    */

    public function test_a_complete_application_can_be_submitted(): void
    {
        [, $application] = $this->makeCompleteApplication();

        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/submit')
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted');

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => 'submitted',
        ]);
        $this->assertNotNull($application->fresh()->submitted_at);
    }

    public function test_submitted_at_is_generated_server_side_and_client_values_are_ignored(): void
    {
        [, $application] = $this->makeCompleteApplication();

        $fakeTimestamp = now()->subYears(3)->toISOString();

        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/submit', [
                'status' => 'approved',
                'submitted_at' => $fakeTimestamp,
                'approved_at' => $fakeTimestamp,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted');

        $fresh = $application->fresh();
        $this->assertNotNull($fresh->submitted_at);
        $this->assertNotSame($fakeTimestamp, $fresh->submitted_at->toISOString());
        $this->assertTrue($fresh->submitted_at->diffInSeconds(now()) < 60);
        $this->assertNull($fresh->approved_at);
    }

    public function test_a_missing_required_document_prevents_submission(): void
    {
        $student = $this->loginAsStudent();
        $this->createProfile($student);

        $cycle = $this->cycleWithRequirements(required: 2);
        $application = Application::factory()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);

        $required = $cycle->requirements()->wherePivot('is_required', true)->get();
        $missing = $required->last();

        Storage::fake('docs');
        foreach ($required->take($required->count() - 1) as $requirement) {
            $this->upload($application, $requirement);
        }

        $response = $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/submit')
            ->assertStatus(422)
            ->assertJsonPath('data.application_complete', true)
            ->assertJsonPath('data.documents_complete', false)
            ->assertJsonPath('data.is_complete', false)
            ->assertJsonPath('message', 'Your application cannot be submitted yet. Missing required documents: '.$missing->name);

        $response->assertJsonPath('data.missing_requirements.0.id', $missing->id)
            ->assertJsonPath('data.missing_requirements.0.name', $missing->name);

        $this->assertStringContainsString($missing->name, $response->json('errors.documents.0'));

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => 'draft',
            'submitted_at' => null,
        ]);
    }

    public function test_a_missing_optional_document_does_not_prevent_submission(): void
    {
        $student = $this->loginAsStudent();
        $this->createProfile($student);

        $cycle = $this->cycleWithRequirements(required: 1, optional: 1);
        $application = Application::factory()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);

        $optional = $cycle->requirements()->wherePivot('is_required', false)->firstOrFail();

        Storage::fake('docs');
        $this->upload($application, $cycle->requirements()->wherePivot('is_required', true)->firstOrFail());

        $this->assertDatabaseMissing('application_documents', ['requirement_id' => $optional->id]);

        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/submit')
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted');
    }

    public function test_missing_application_information_prevents_submission(): void
    {
        $student = $this->loginAsStudent();

        $cycle = $this->cycleWithRequirements(required: 1);
        $application = Application::factory()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);

        Storage::fake('docs');
        $this->upload($application, $cycle->requirements()->first());

        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/submit')
            ->assertStatus(422)
            ->assertJsonPath('data.application_complete', false)
            ->assertJsonPath('data.is_complete', false)
            ->assertJsonPath('message', 'Your application cannot be submitted yet. Required application information is incomplete: School name, Course, Year level');

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => 'draft',
        ]);
    }

    public function test_documents_keep_their_verification_state_after_submission(): void
    {
        [, $application] = $this->makeCompleteApplication();

        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/submit')
            ->assertOk();

        $documents = $application->documents()->get();

        $this->assertNotEmpty($documents);
        foreach ($documents as $document) {
            $this->assertSame('pending', $document->verification_status->value);
            $this->assertNull($document->verified_at);
            $this->assertNull($document->verified_by);
        }
    }

    public function test_a_document_that_no_longer_satisfies_the_file_rules_blocks_submission(): void
    {
        $student = $this->loginAsStudent();
        $this->createProfile($student);

        $cycle = $this->cycleWithRequirements(required: 1);
        $application = Application::factory()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);
        $requirement = $cycle->requirements()->first();

        Storage::fake('docs');
        $this->upload($application, $requirement);

        // The requirement rules tighten after the document was uploaded.
        $requirement->update(['allowed_file_types' => ['png']]);

        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/submit')
            ->assertStatus(422)
            ->assertJsonPath('data.missing_requirements.0.id', $requirement->id);

        $this->assertDatabaseHas('applications', ['id' => $application->id, 'status' => 'draft']);
    }

    public function test_a_document_pointing_at_a_foreign_requirement_does_not_count(): void
    {
        $student = $this->loginAsStudent();
        $this->createProfile($student);

        $cycle = $this->cycleWithRequirements(required: 1);
        $application = Application::factory()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);
        $requirement = $cycle->requirements()->first();

        Storage::fake('docs');

        // A document is attached, but it belongs to a different cycle's
        // requirement — simulating direct database tampering.
        $foreign = Requirement::factory()->create();
        $otherCycle = ProgramCycle::factory()->open()->create();
        $otherCycle->requirements()->syncWithPivotValues([$foreign->id], ['is_required' => true]);

        $application->documents()->create([
            'requirement_id' => $foreign->id,
            'file_path' => 'applications/'.$application->id.'/forged.pdf',
            'file_name' => 'forged.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 512,
            'verification_status' => 'pending',
            'uploaded_at' => now(),
        ]);
        Storage::disk('docs')->put('applications/'.$application->id.'/forged.pdf', '%PDF-1.4 forged');

        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/submit')
            ->assertStatus(422)
            ->assertJsonPath('data.missing_requirements.0.id', $requirement->id);

        $this->assertDatabaseHas('applications', ['id' => $application->id, 'status' => 'draft']);
    }

    /*
    |--------------------------------------------------------------------------
    | Status protection
    |--------------------------------------------------------------------------
    */

    public function test_a_submitted_application_cannot_be_submitted_again(): void
    {
        [$student, $application] = $this->makeCompleteApplication();

        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/submit')
            ->assertOk();

        $this->assertSame('submitted', $application->fresh()->status->value);

        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/submit')
            ->assertStatus(403);
    }

    public function test_a_withdrawn_application_cannot_be_submitted(): void
    {
        $student = $this->loginAsStudent();
        $this->createProfile($student);

        $cycle = $this->cycleWithRequirements(required: 1);
        $application = Application::factory()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
            'status' => 'withdrawn',
        ]);

        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/submit')
            ->assertStatus(403);

        $this->assertSame('withdrawn', $application->fresh()->status->value);
    }

    public function test_submitted_documents_cannot_be_uploaded_replaced_or_deleted(): void
    {
        [, $application] = $this->makeCompleteApplication();

        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/submit')
            ->assertOk();

        $requirement = $application->programCycle->requirements()->wherePivot('is_required', true)->first();
        $document = $application->documents()->firstOrFail();
        $count = $application->documents()->count();

        Storage::fake('docs');

        // Uploading a new document is forbidden once submitted.
        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/documents', [
                'requirement_id' => $requirement->id,
                'file' => UploadedFile::fake()->create('replacement.pdf', 512),
            ])
            ->assertStatus(403);

        // Replacing (re-uploading for the same requirement) is also forbidden.
        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/documents', [
                'file' => UploadedFile::fake()->create('unlisted.pdf', 512),
            ])
            ->assertStatus(403);

        // Deleting is forbidden once submitted.
        $this->fromSpa()
            ->deleteJson('/api/student/applications/'.$application->id.'/documents/'.$document->id)
            ->assertStatus(403);

        $this->assertDatabaseCount('application_documents', $count);
    }

    /*
    |--------------------------------------------------------------------------
    | Ownership
    |--------------------------------------------------------------------------
    */

    public function test_a_student_cannot_submit_another_students_application(): void
    {
        $this->loginAsStudent();

        $cycle = $this->cycleWithRequirements(required: 1);
        $application = Application::factory()->create(['program_cycle_id' => $cycle->id]);

        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/submit')
            ->assertStatus(403);

        $this->assertSame('draft', $application->fresh()->status->value);
    }

    public function test_a_student_cannot_view_another_students_completeness(): void
    {
        $this->loginAsStudent();

        $cycle = $this->cycleWithRequirements(required: 1);
        $application = Application::factory()->create(['program_cycle_id' => $cycle->id]);

        $this->fromSpa()
            ->getJson('/api/student/applications/'.$application->id.'/completeness')
            ->assertStatus(403);
    }

    /*
    |--------------------------------------------------------------------------
    | Business rules: application period
    |--------------------------------------------------------------------------
    */

    public function test_submission_is_blocked_when_the_application_period_has_closed(): void
    {
        [$student, $application] = $this->makeCompleteApplication();

        // The draft was created while the cycle was open; the deadline passes
        // before the student submits.
        $application->programCycle->update([
            'application_deadline' => now()->subDay()->toDateString(),
        ]);

        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/submit')
            ->assertStatus(422)
            ->assertJsonPath('message', 'The application period for this program has closed.');

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => 'draft',
            'submitted_at' => null,
        ]);
    }

    public function test_submission_is_blocked_for_an_unpublished_cycle(): void
    {
        [$student, $application] = $this->makeCompleteApplication();

        $application->programCycle->update(['status' => 'draft']);

        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/submit')
            ->assertStatus(422)
            ->assertJsonPath('message', 'The application period for this program has closed.');

        $this->assertDatabaseHas('applications', ['id' => $application->id, 'status' => 'draft']);
    }

    /*
    |--------------------------------------------------------------------------
    | Security / roles
    |--------------------------------------------------------------------------
    */

    public function test_a_guest_cannot_submit(): void
    {
        $cycle = $this->cycleWithRequirements(required: 1);
        $application = Application::factory()->create(['program_cycle_id' => $cycle->id]);

        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/submit')
            ->assertStatus(401);
    }

    public function test_a_staff_member_cannot_use_the_student_submission_endpoint(): void
    {
        $this->loginAsStaff();

        $cycle = $this->cycleWithRequirements(required: 1);
        $application = Application::factory()->create(['program_cycle_id' => $cycle->id]);

        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/submit')
            ->assertStatus(403);

        $this->assertDatabaseHas('applications', ['id' => $application->id, 'status' => 'draft']);
    }

    public function test_a_student_cannot_use_the_staff_review_endpoint(): void
    {
        $this->loginAsStudent();

        $cycle = $this->cycleWithRequirements(required: 1);
        $application = Application::factory()->create(['program_cycle_id' => $cycle->id]);

        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', ['action' => 'start_review'])
            ->assertStatus(403);

        $this->assertDatabaseHas('applications', ['id' => $application->id, 'status' => 'draft']);
    }

    /*
    |--------------------------------------------------------------------------
    | Completeness endpoint
    |--------------------------------------------------------------------------
    */

    public function test_the_completeness_endpoint_reports_a_complete_application(): void
    {
        [$student, $application] = $this->makeCompleteApplication();

        $this->fromSpa()
            ->getJson('/api/student/applications/'.$application->id.'/completeness')
            ->assertOk()
            ->assertJsonPath('data.is_complete', true)
            ->assertJsonPath('data.application_complete', true)
            ->assertJsonPath('data.documents_complete', true)
            ->assertJsonPath('data.missing_application_fields', [])
            ->assertJsonPath('data.missing_requirements', []);
    }

    public function test_the_completeness_endpoint_reports_missing_documents(): void
    {
        $student = $this->loginAsStudent();
        $this->createProfile($student);

        $cycle = $this->cycleWithRequirements(required: 2, optional: 1);
        $application = Application::factory()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);

        $required = $cycle->requirements()->wherePivot('is_required', true)->get();
        $missing = $required->last();

        Storage::fake('docs');
        foreach ($required->take($required->count() - 1) as $requirement) {
            $this->upload($application, $requirement);
        }

        $this->fromSpa()
            ->getJson('/api/student/applications/'.$application->id.'/completeness')
            ->assertOk()
            ->assertJsonPath('data.is_complete', false)
            ->assertJsonPath('data.documents_complete', false)
            ->assertJsonPath('data.missing_requirements.0.id', $missing->id)
            ->assertJsonPath('data.missing_requirements.0.name', $missing->name)
            ->assertJsonPath('data.missing_requirements.0.is_required', true);
    }

    public function test_a_guest_cannot_read_completeness(): void
    {
        $cycle = $this->cycleWithRequirements(required: 1);
        $application = Application::factory()->create(['program_cycle_id' => $cycle->id]);

        $this->fromSpa()
            ->getJson('/api/student/applications/'.$application->id.'/completeness')
            ->assertStatus(401);
    }
}

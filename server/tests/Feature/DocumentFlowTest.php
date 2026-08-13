<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ProgramCycle;
use App\Models\Requirement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SpaAuthentication;
use Tests\TestCase;

class DocumentFlowTest extends TestCase
{
    use RefreshDatabase, SpaAuthentication;

    private function setupStudentApplication(): Application
    {
        $student = $this->loginAsStudent();
        $cycle = ProgramCycle::factory()->open()->create();
        $requirement = Requirement::factory()->create();
        $cycle->requirements()->syncWithPivotValues([$requirement->id], ['is_required' => true]);

        return Application::factory()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);
    }

    public function test_a_student_can_upload_a_document_to_their_application(): void
    {
        Storage::fake('docs');

        $application = $this->setupStudentApplication();
        $requirement = Requirement::first();

        $response = $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/documents', [
                'requirement_id' => $requirement->id,
                'file' => UploadedFile::fake()->create('cor.pdf', 512),
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.verification_status', 'pending')
            ->assertJsonPath('data.requirement', $requirement->name);

        $document = \App\Models\ApplicationDocument::firstOrFail();
        Storage::disk('docs')->assertExists($document->file_path);
    }

    public function test_a_student_cannot_upload_to_another_students_application(): void
    {
        Storage::fake('docs');

        $this->loginAsStudent();
        $cycle = ProgramCycle::factory()->open()->create();
        $application = Application::factory()->create(['program_cycle_id' => $cycle->id]);

        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/documents', [
                'file' => UploadedFile::fake()->create('cor.pdf', 512),
            ])
            ->assertStatus(403);
    }

    public function test_document_must_be_a_valid_file_type(): void
    {
        Storage::fake('docs');

        $application = $this->setupStudentApplication();

        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/documents', [
                'file' => UploadedFile::fake()->create('notes.txt', 512),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_staff_can_verify_a_document(): void
    {
        Storage::fake('docs');

        $application = $this->setupStudentApplication();
        $requirement = Requirement::first();

        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/documents', [
                'requirement_id' => $requirement->id,
                'file' => UploadedFile::fake()->create('cor.pdf', 512),
            ])->assertStatus(201);

        $document = $application->documents()->firstOrFail();

        $this->loginAsStaff();

        $this->fromSpa()
            ->putJson('/api/staff/applications/'.$application->id.'/documents/'.$document->id.'/verify', [
                'verification_status' => 'verified',
            ])
            ->assertOk()
            ->assertJsonPath('data.verification_status', 'verified');
    }

    public function test_staff_cannot_verify_without_a_reason_when_rejecting(): void
    {
        Storage::fake('docs');

        $application = $this->setupStudentApplication();
        $requirement = Requirement::first();

        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/documents', [
                'requirement_id' => $requirement->id,
                'file' => UploadedFile::fake()->create('cor.pdf', 512),
            ])->assertStatus(201);

        $document = $application->documents()->firstOrFail();

        $this->loginAsStaff();

        $this->fromSpa()
            ->putJson('/api/staff/applications/'.$application->id.'/documents/'.$document->id.'/verify', [
                'verification_status' => 'rejected',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rejection_reason');
    }

    public function test_a_document_can_only_be_downloaded_by_its_owner_or_staff(): void
    {
        Storage::fake('docs');

        $application = $this->setupStudentApplication();
        $requirement = Requirement::first();

        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/documents', [
                'requirement_id' => $requirement->id,
                'file' => UploadedFile::fake()->create('cor.pdf', 512),
            ])->assertStatus(201);

        $document = $application->documents()->firstOrFail();

        $this->fromSpa()
            ->getJson('/api/documents/'.$document->id.'/download')
            ->assertOk();

        // A different student cannot access the file.
        $this->loginAsStudent();
        $this->fromSpa()
            ->getJson('/api/documents/'.$document->id.'/download')
            ->assertStatus(403);
    }

    public function test_a_student_can_delete_an_unverified_document(): void
    {
        Storage::fake('docs');

        $application = $this->setupStudentApplication();
        $requirement = Requirement::first();

        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/documents', [
                'requirement_id' => $requirement->id,
                'file' => UploadedFile::fake()->create('cor.pdf', 512),
            ])->assertStatus(201);

        $document = $application->documents()->firstOrFail();

        $this->fromSpa()
            ->deleteJson('/api/student/applications/'.$application->id.'/documents/'.$document->id)
            ->assertNoContent();

        $this->assertDatabaseCount('application_documents', 0);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ProgramCycle;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\SpaAuthentication;
use Tests\TestCase;

class SecureDocumentUploadTest extends TestCase
{
    use RefreshDatabase, SpaAuthentication;

    private function setupApplication(): Application
    {
        $student = $this->loginAsStudent();
        $cycle = ProgramCycle::factory()->open()->create();

        return Application::factory()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);
    }

    private function attachRequirement(Application $application, Requirement $requirement, bool $isRequired = true): void
    {
        $application->programCycle->requirements()
            ->syncWithPivotValues([$requirement->id], ['is_required' => $isRequired]);
    }

    public function test_uploading_again_for_the_same_requirement_replaces_the_existing_document(): void
    {
        Storage::fake('docs');

        $application = $this->setupApplication();
        $requirement = Requirement::factory()->create();
        $this->attachRequirement($application, $requirement);

        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/documents', [
                'requirement_id' => $requirement->id,
                'file' => UploadedFile::fake()->createWithContent('cor.pdf', '%PDF-1.4 original'),
            ])
            ->assertStatus(201);

        $document = $application->documents()->firstOrFail();
        $originalPath = $document->file_path;

        // A staff member already verified the original file...
        $staff = User::factory()->staff()->create();
        $document->verify($staff);
        $this->assertSame('verified', $document->fresh()->verification_status->value);

        // ...and the student replaces it with a new file.
        $response = $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/documents', [
                'requirement_id' => $requirement->id,
                'file' => UploadedFile::fake()->createWithContent('cor.pdf', '%PDF-1.4 replacement'),
            ])
            ->assertStatus(201);

        $this->assertDatabaseCount('application_documents', 1);

        $replaced = $application->documents()->firstOrFail();
        $this->assertNotSame($originalPath, $replaced->file_path);
        $this->assertSame('pending', $replaced->verification_status->value);
        $this->assertNull($replaced->verified_by);
        $this->assertNull($replaced->verified_at);
        $this->assertNull($replaced->rejection_reason);

        Storage::disk('docs')->assertMissing($originalPath);
        Storage::disk('docs')->assertExists($replaced->file_path);

        $response->assertJsonPath('data.verification_status', 'pending');
    }

    public function test_a_requirement_must_belong_to_the_applications_cycle(): void
    {
        Storage::fake('docs');

        $application = $this->setupApplication();
        $otherCycle = ProgramCycle::factory()->open()->create();
        $foreignRequirement = Requirement::factory()->create();
        $otherCycle->requirements()->syncWithPivotValues([$foreignRequirement->id], ['is_required' => true]);

        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/documents', [
                'requirement_id' => $foreignRequirement->id,
                'file' => UploadedFile::fake()->create('cor.pdf', 512),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('requirement_id');

        $this->assertDatabaseCount('application_documents', 0);
        $this->assertEmpty(Storage::disk('docs')->allFiles());
    }

    public function test_file_type_must_match_the_requirements_allowed_types(): void
    {
        Storage::fake('docs');

        $application = $this->setupApplication();
        $requirement = Requirement::factory()->create([
            'allowed_file_types' => ['pdf'],
            'max_file_size' => 5120,
        ]);
        $this->attachRequirement($application, $requirement);

        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/documents', [
                'requirement_id' => $requirement->id,
                'file' => UploadedFile::fake()->create('scan.png', 512),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/documents', [
                'requirement_id' => $requirement->id,
                'file' => UploadedFile::fake()->create('scan.pdf', 512),
            ])
            ->assertStatus(201);
    }

    public function test_file_size_must_not_exceed_the_requirements_max_size(): void
    {
        Storage::fake('docs');

        $application = $this->setupApplication();
        $requirement = Requirement::factory()->create([
            'allowed_file_types' => ['pdf'],
            'max_file_size' => 1024,
        ]);
        $this->attachRequirement($application, $requirement);

        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/documents', [
                'requirement_id' => $requirement->id,
                'file' => UploadedFile::fake()->create('big.pdf', 2048),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/documents', [
                'requirement_id' => $requirement->id,
                'file' => UploadedFile::fake()->create('small.pdf', 256),
            ])
            ->assertStatus(201);
    }

    public function test_stored_file_extension_is_derived_from_content_not_the_client_name(): void
    {
        Storage::fake('docs');

        $application = $this->setupApplication();

        $path = tempnam(sys_get_temp_dir(), 'doc');
        file_put_contents($path, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n%%EOF");

        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/documents', [
                'file' => new UploadedFile($path, 'scan.png', null, null, true),
            ])
            ->assertStatus(201);

        $document = $application->documents()->firstOrFail();
        $this->assertTrue(Str::endsWith($document->file_path, '.pdf'));
        $this->assertSame('scan.png', $document->file_name);
        $this->assertSame('application/pdf', $document->mime_type);
        Storage::disk('docs')->assertExists($document->file_path);
    }

    public function test_stored_file_is_removed_when_the_database_write_fails(): void
    {
        Storage::fake('docs');

        $application = $this->setupApplication();

        // The overlong client file name exceeds the file_name varchar(255)
        // column, so the insert fails after the file was already stored.
        $response = $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/documents', [
                'file' => UploadedFile::fake()->create(str_repeat('a', 300).'.pdf', 512),
            ]);

        $response->assertStatus(500);

        $this->assertDatabaseCount('application_documents', 0);
        $this->assertEmpty(Storage::disk('docs')->allFiles());
    }

    public function test_a_student_cannot_upload_once_the_application_is_non_editable(): void
    {
        Storage::fake('docs');

        $application = $this->setupApplication();
        $application->status = 'approved';
        $application->save();

        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/documents', [
                'file' => UploadedFile::fake()->create('cor.pdf', 512),
            ])
            ->assertStatus(403);
    }
}

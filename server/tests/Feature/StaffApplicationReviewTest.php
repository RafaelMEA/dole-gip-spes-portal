<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\Program;
use App\Models\ProgramCycle;
use App\Models\Requirement;
use App\Models\StudentDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SpaAuthentication;
use Tests\TestCase;

class StaffApplicationReviewTest extends TestCase
{
    use RefreshDatabase, SpaAuthentication;

    /**
     * Create a student + application in one step. The cycle (and its program)
     * is shared across applications within a test to keep the number of
     * generated programs small, since the program factory's name is faked
     * from a small unique pool.
     */
    private function makeApplication(
        string $status = 'submitted',
        ?string $name = null,
        ?ProgramCycle $cycle = null,
    ): Application {
        $student = User::factory()->student()->create($name !== null ? ['name' => $name] : []);

        return Application::factory()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle?->id ?? ProgramCycle::factory()->create()->id,
            'status' => $status,
            'submitted_at' => $status === 'submitted' ? now() : null,
        ]);
    }

    /**
     * Create an application for each given status, all sharing one cycle.
     */
    private function makeApplicationsWithStatuses(array $statuses, ProgramCycle $cycle): array
    {
        $applications = [];
        foreach ($statuses as $status) {
            $applications[] = $this->makeApplication($status, cycle: $cycle);
        }

        return $applications;
    }

    private function submittedApplicationWithStudent(?array $studentAttrs = []): array
    {
        $student = User::factory()->student()->create($studentAttrs);
        $application = Application::factory()->submitted()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => ProgramCycle::factory()->create()->id,
        ]);

        return [$student, $application];
    }

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    public function test_a_guest_cannot_list_staff_applications(): void
    {
        $this->fromSpa()->getJson('/api/staff/applications')->assertStatus(401);
    }

    public function test_a_student_cannot_list_staff_applications(): void
    {
        $this->loginAsStudent();

        $this->fromSpa()->getJson('/api/staff/applications')->assertStatus(403);
    }

    public function test_a_staff_member_can_list_applications(): void
    {
        $this->loginAsStaff();
        [$student] = $this->submittedApplicationWithStudent(['email' => 'staff.view@example.com']);

        $this->fromSpa()->getJson('/api/staff/applications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'submitted')
            ->assertJsonPath('data.0.applicant.email', 'staff.view@example.com');
    }

    public function test_a_guest_cannot_view_an_application_detail(): void
    {
        $this->makeApplication();

        $this->fromSpa()->getJson('/api/staff/applications/1')->assertStatus(401);
    }

    public function test_a_student_cannot_view_an_application_detail_via_the_staff_endpoint(): void
    {
        $this->loginAsStudent();
        $application = $this->makeApplication();

        $this->fromSpa()->getJson('/api/staff/applications/'.$application->id)->assertStatus(403);
    }

    /*
    |--------------------------------------------------------------------------
    | Default view + status filter
    |--------------------------------------------------------------------------
    */

    public function test_the_default_view_only_shows_submitted_applications(): void
    {
        $this->loginAsStaff();
        $cycle = ProgramCycle::factory()->create();
        $this->makeApplicationsWithStatuses(['submitted', 'draft', 'rejected'], $cycle);

        $this->fromSpa()->getJson('/api/staff/applications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'submitted');
    }

    public function test_status_all_returns_every_status(): void
    {
        $this->loginAsStaff();
        $cycle = ProgramCycle::factory()->create();
        $this->makeApplicationsWithStatuses(['submitted', 'draft', 'rejected'], $cycle);

        $this->fromSpa()->getJson('/api/staff/applications?status=all')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_the_status_filter_limits_the_results(): void
    {
        $this->loginAsStaff();
        $this->makeApplication('submitted');
        $this->makeApplication('withdrawn');

        $this->fromSpa()->getJson('/api/staff/applications?status=withdrawn')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'withdrawn');
    }

    public function test_an_unknown_status_filter_is_rejected(): void
    {
        $this->loginAsStaff();

        $this->fromSpa()->getJson('/api/staff/applications?status=hacked')->assertStatus(422);
    }

    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    */

    public function test_search_finds_applications_by_student_name(): void
    {
        $this->loginAsStaff();
        $this->makeApplication('submitted', name: 'Maria Santos');
        $this->makeApplication('submitted', name: 'Juan Dela Cruz');

        $this->fromSpa()->getJson('/api/staff/applications?search=Maria')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.applicant.name', 'Maria Santos');
    }

    public function test_search_finds_applications_by_email(): void
    {
        $this->loginAsStaff();
        $this->submittedApplicationWithStudent(['email' => 'juan.doe@example.com']);
        $this->makeApplication('submitted');

        $this->fromSpa()->getJson('/api/staff/applications?search='.urlencode('juan.doe@example.com'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.applicant.email', 'juan.doe@example.com');
    }

    public function test_search_finds_applications_by_id(): void
    {
        $this->loginAsStaff();
        $target = $this->makeApplication('submitted');
        $this->makeApplication('submitted');

        $this->fromSpa()->getJson('/api/staff/applications?search='.$target->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $target->id);
    }

    public function test_search_escapes_like_wildcards(): void
    {
        $this->loginAsStaff();
        $this->makeApplication('submitted', name: '100% Real Student');
        $this->makeApplication('submitted', name: '100 Real Student');

        $this->fromSpa()->getJson('/api/staff/applications?search='.urlencode('100%'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.applicant.name', '100% Real Student');
    }

    public function test_search_handles_sql_injection_attempts_safely(): void
    {
        $this->loginAsStaff();
        $this->makeApplication('submitted');

        $this->fromSpa()
            ->getJson('/api/staff/applications?search='.urlencode("'; DROP TABLE applications; --"))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->assertDatabaseCount('applications', 1);
    }

    /*
    |--------------------------------------------------------------------------
    | Filters
    |--------------------------------------------------------------------------
    */

    public function test_the_program_filter_works(): void
    {
        $this->loginAsStaff();
        $programA = Program::factory()->create(['name' => 'GIP']);
        $programB = Program::factory()->create(['name' => 'SPES']);
        $cycleA = ProgramCycle::factory()->create(['program_id' => $programA->id]);
        $cycleB = ProgramCycle::factory()->create(['program_id' => $programB->id]);
        Application::factory()->submitted()->create(['program_cycle_id' => $cycleA->id]);
        Application::factory()->submitted()->create(['program_cycle_id' => $cycleB->id]);

        $this->fromSpa()->getJson('/api/staff/applications?program_id='.$programA->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.program_cycle.program_id', $programA->id);
    }

    public function test_the_cycle_filter_works(): void
    {
        $this->loginAsStaff();
        $cycleA = ProgramCycle::factory()->create();
        $cycleB = ProgramCycle::factory()->create();
        Application::factory()->submitted()->create(['program_cycle_id' => $cycleA->id]);
        Application::factory()->submitted()->create(['program_cycle_id' => $cycleB->id]);

        $this->fromSpa()->getJson('/api/staff/applications?program_cycle_id='.$cycleB->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.program_cycle.id', $cycleB->id);
    }

    public function test_the_submitted_date_range_filter_works(): void
    {
        $this->loginAsStaff();
        $old = Application::factory()->submitted()->create([
            'program_cycle_id' => ProgramCycle::factory()->create()->id,
            'submitted_at' => now()->subMonths(2),
        ]);
        $recent = Application::factory()->submitted()->create([
            'program_cycle_id' => ProgramCycle::factory()->create()->id,
            'submitted_at' => now()->subDay(),
        ]);

        $this->fromSpa()->getJson('/api/staff/applications?submitted_from='.now()->subMonth()->toDateString())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $recent->id);

        $this->fromSpa()->getJson('/api/staff/applications?submitted_to='.now()->subMonth()->toDateString())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $old->id);
    }

    /*
    |--------------------------------------------------------------------------
    | Sorting
    |--------------------------------------------------------------------------
    */

    public function test_the_default_sort_is_newest_submitted_first(): void
    {
        $this->loginAsStaff();
        $older = Application::factory()->submitted()->create([
            'program_cycle_id' => ProgramCycle::factory()->create()->id,
            'submitted_at' => now()->subDays(5),
        ]);
        $newer = Application::factory()->submitted()->create([
            'program_cycle_id' => ProgramCycle::factory()->create()->id,
            'submitted_at' => now()->subDay(),
        ]);

        $this->fromSpa()->getJson('/api/staff/applications')
            ->assertOk()
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id);
    }

    public function test_an_allowlisted_sort_column_and_direction_work(): void
    {
        $this->loginAsStaff();
        $older = Application::factory()->submitted()->create([
            'program_cycle_id' => ProgramCycle::factory()->create()->id,
            'created_at' => now()->subDays(5),
            'submitted_at' => now()->subDays(5),
        ]);
        $this->makeApplication('submitted');

        $this->fromSpa()->getJson('/api/staff/applications?sort=created_at&direction=asc')
            ->assertOk()
            ->assertJsonPath('data.0.id', $older->id);
    }

    public function test_arbitrary_sort_columns_are_rejected(): void
    {
        $this->loginAsStaff();

        $this->fromSpa()->getJson('/api/staff/applications?sort=password;select')
            ->assertStatus(422);
    }

    public function test_an_invalid_sort_direction_is_rejected(): void
    {
        $this->loginAsStaff();

        $this->fromSpa()->getJson('/api/staff/applications?direction=sideways')->assertStatus(422);
    }

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */

    public function test_pagination_returns_flat_metadata_and_respects_page_size(): void
    {
        $this->loginAsStaff();
        $cycle = ProgramCycle::factory()->create();
        for ($i = 0; $i < 25; $i++) {
            Application::factory()->submitted()->create(['program_cycle_id' => $cycle->id]);
        }

        $this->fromSpa()->getJson('/api/staff/applications?per_page=10&page=1')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('per_page', 10)
            ->assertJsonPath('total', 25)
            ->assertJsonPath('last_page', 3)
            ->assertJsonPath('current_page', 1);

        $this->fromSpa()->getJson('/api/staff/applications?per_page=10&page=3')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('current_page', 3);
    }

    public function test_per_page_is_restricted_to_the_allowlist(): void
    {
        $this->loginAsStaff();

        $this->fromSpa()->getJson('/api/staff/applications?per_page=15')->assertStatus(422);
    }

    public function test_an_invalid_page_number_is_rejected(): void
    {
        $this->loginAsStaff();

        $this->fromSpa()->getJson('/api/staff/applications?page=0')->assertStatus(422);
    }

    /*
    |--------------------------------------------------------------------------
    | Details
    |--------------------------------------------------------------------------
    */

    public function test_a_staff_member_can_view_an_application_with_all_review_data(): void
    {
        $this->loginAsStaff();

        $student = User::factory()->student()->create();
        StudentDetail::factory()->create([
            'user_id' => $student->id,
            'school_name' => 'Example University',
            'course' => 'BS Information Technology',
            'year_level' => 3,
        ]);
        $cycle = ProgramCycle::factory()->create();
        $requirement = Requirement::factory()->create();
        $cycle->requirements()->syncWithPivotValues([$requirement->id], ['is_required' => true]);

        $application = Application::factory()->submitted()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
            'remarks' => 'Prefers morning shifts.',
        ]);
        $document = ApplicationDocument::factory()->create([
            'application_id' => $application->id,
            'requirement_id' => $requirement->id,
        ]);

        $response = $this->fromSpa()->getJson('/api/staff/applications/'.$application->id)
            ->assertOk()
            ->assertJsonPath('data.id', $application->id)
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.remarks', 'Prefers morning shifts.')
            ->assertJsonPath('data.applicant.name', $student->name)
            ->assertJsonPath('data.applicant.email', $student->email)
            ->assertJsonPath('data.applicant.student_detail.school_name', 'Example University')
            ->assertJsonPath('data.applicant.student_detail.course', 'BS Information Technology')
            ->assertJsonPath('data.applicant.student_detail.year_level', 3)
            ->assertJsonPath('data.program_cycle.id', $cycle->id)
            ->assertJsonPath('data.program_cycle.program_id', $cycle->program_id)
            ->assertJsonPath('data.program_cycle.requirements.0.id', $requirement->id)
            ->assertJsonPath('data.program_cycle.requirements.0.is_required', true)
            ->assertJsonPath('data.documents.0.file_name', $document->file_name)
            ->assertJsonPath('data.documents.0.mime_type', 'application/pdf')
            ->assertJsonPath('data.documents.0.verification_status', 'pending')
            ->assertJsonPath('data.documents.0.requirement.name', $requirement->name);

        $this->assertNotNull($response->json('data.submitted_at'));
    }

    public function test_an_invalid_application_returns_404(): void
    {
        $this->loginAsStaff();

        $this->fromSpa()->getJson('/api/staff/applications/999999')->assertStatus(404);
    }

    public function test_the_application_payload_exposes_no_sensitive_internal_fields(): void
    {
        $this->loginAsStaff();
        [$student, $application] = $this->submittedApplicationWithStudent();
        ApplicationDocument::factory()->create(['application_id' => $application->id]);

        $response = $this->fromSpa()->getJson('/api/staff/applications/'.$application->id)->assertOk();
        $data = $response->json('data');

        $this->assertArrayNotHasKey('password', $data['applicant']);
        $this->assertArrayNotHasKey('remember_token', $data['applicant']);
        $this->assertArrayNotHasKey('file_path', $data['documents'][0]);
        $this->assertArrayNotHasKey('file_path', $data['applicant']);

        $this->assertStringNotContainsString('file_path', $response->getContent());
        $this->assertStringNotContainsString('password', $response->getContent());
        $this->assertStringNotContainsString('remember_token', $response->getContent());
    }

    public function test_the_list_response_exposes_no_sensitive_internal_fields(): void
    {
        $this->loginAsStaff();
        $this->makeApplication('submitted');

        $response = $this->fromSpa()->getJson('/api/staff/applications')->assertOk();

        $this->assertStringNotContainsString('file_path', $response->getContent());
        $this->assertStringNotContainsString('password', $response->getContent());
        $this->assertStringNotContainsString('remember_token', $response->getContent());
    }

    /*
    |--------------------------------------------------------------------------
    | Documents
    |--------------------------------------------------------------------------
    */

    public function test_a_staff_member_can_list_an_applications_documents(): void
    {
        $this->loginAsStaff();
        $application = Application::factory()->submitted()->create([
            'program_cycle_id' => ProgramCycle::factory()->create()->id,
        ]);
        $document = ApplicationDocument::factory()->create(['application_id' => $application->id]);

        $this->fromSpa()->getJson('/api/staff/applications/'.$application->id.'/documents')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $document->id)
            ->assertJsonPath('data.0.application_id', $application->id);
    }

    public function test_a_staff_member_can_download_an_applications_document(): void
    {
        Storage::fake('docs');
        $this->loginAsStaff();
        $application = Application::factory()->submitted()->create([
            'program_cycle_id' => ProgramCycle::factory()->create()->id,
        ]);
        $document = ApplicationDocument::factory()->create([
            'application_id' => $application->id,
            'file_path' => 'applications/'.$application->id.'/proof.pdf',
        ]);
        Storage::disk('docs')->put($document->file_path, '%PDF-1.4 fake');

        $this->fromSpa()->get('/api/documents/'.$document->id.'/download')
            ->assertOk();
    }

    public function test_a_student_cannot_download_another_students_document(): void
    {
        Storage::fake('docs');
        $this->loginAsStudent();
        $application = Application::factory()->submitted()->create([
            'program_cycle_id' => ProgramCycle::factory()->create()->id,
        ]);
        $document = ApplicationDocument::factory()->create([
            'application_id' => $application->id,
            'file_path' => 'applications/'.$application->id.'/proof.pdf',
        ]);
        Storage::disk('docs')->put($document->file_path, '%PDF-1.4 fake');

        $this->fromSpa()->get('/api/documents/'.$document->id.'/download')->assertStatus(403);
    }

    public function test_a_guest_cannot_download_a_document(): void
    {
        Storage::fake('docs');
        $application = Application::factory()->submitted()->create([
            'program_cycle_id' => ProgramCycle::factory()->create()->id,
        ]);
        $document = ApplicationDocument::factory()->create(['application_id' => $application->id]);

        $this->fromSpa()->getJson('/api/documents/'.$document->id.'/download')->assertStatus(401);
    }

    /*
    |--------------------------------------------------------------------------
    | Upload helper sanity
    |--------------------------------------------------------------------------
    */

    public function test_uploaded_documents_are_private_files_served_only_via_the_download_route(): void
    {
        Storage::fake('docs');
        $this->loginAsStaff();
        $application = Application::factory()->submitted()->create([
            'program_cycle_id' => ProgramCycle::factory()->create()->id,
        ]);
        $document = ApplicationDocument::factory()->create([
            'application_id' => $application->id,
            'file_path' => 'applications/'.$application->id.'/secret.pdf',
        ]);
        Storage::disk('docs')->put($document->file_path, '%PDF-1.4 top secret');

        $response = $this->fromSpa()->getJson('/api/staff/applications/'.$application->id)->assertOk();
        $downloadUrl = $response->json('data.documents.0.download_url');

        $this->assertStringContainsString('/api/documents/'.$document->id.'/download', $downloadUrl);
        $this->assertStringNotContainsString('secret.pdf', $downloadUrl);
        $this->assertStringNotContainsString('applications/', $downloadUrl);
    }
}

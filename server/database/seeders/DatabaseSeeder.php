<?php

namespace Database\Seeders;

use App\Models\DeploymentSite;
use App\Models\HostAgency;
use App\Models\Program;
use App\Models\ProgramCycle;
use App\Models\Requirement;
use App\Models\StudentDetail;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Demo account passwords shared by both seeded users.
     */
    private const DEMO_PASSWORD = 'Secret123';

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Programs
        |--------------------------------------------------------------------------
        */
        $gip = Program::updateOrCreate(['slug' => 'gip'], [
            'name' => 'Government Internship Program',
            'description' => 'The Government Internship Program provides learning opportunities for students and fresh graduates to develop their knowledge, skills, and values in public service.',
            'is_active' => true,
        ]);

        $spes = Program::updateOrCreate(['slug' => 'spes'], [
            'name' => 'Special Program for Employment of Students',
            'description' => 'The Special Program for Employment of Students provides temporary employment to poor but deserving students during summer or Christmas vacations.',
            'is_active' => true,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Requirement catalog
        |--------------------------------------------------------------------------
        */
        $requirements = [
            ['name' => 'Certificate of Registration', 'slug' => 'certificate-of-registration', 'description' => 'Official proof of enrollment for the current semester.'],
            ['name' => 'Valid Government ID', 'slug' => 'valid-government-id', 'description' => 'Any government-issued ID (National ID, UMID, Driver\'s License, Passport).'],
            ['name' => 'Copy of Latest Grades', 'slug' => 'latest-grades', 'description' => 'Certified true copy or printed copy of your latest grades.'],
            ['name' => 'Barangay Clearance', 'slug' => 'barangay-clearance', 'description' => 'Clearance issued by your barangay within the last three months.'],
            ['name' => 'Parents Consent', 'slug' => 'parents-consent', 'description' => 'Signed parental consent form if you are below 18 years old.'],
            ['name' => 'Income Tax Return (ITR)', 'slug' => 'income-tax-return', 'description' => 'Latest ITR of parents, or Certificate of Exemption / No Income.'],
        ];

        foreach ($requirements as $data) {
            Requirement::updateOrCreate(['slug' => $data['slug']], $data);
        }

        /*
        |--------------------------------------------------------------------------
        | Open demo cycle (GIP)
        |--------------------------------------------------------------------------
        */
        $staff = User::updateOrCreate(
            ['email' => 'staff@example.com'],
            [
                'name' => 'Marites Santos',
                'password' => Hash::make(self::DEMO_PASSWORD),
                'role' => User::ROLE_STAFF,
            ],
        );

        $cycle = ProgramCycle::updateOrCreate(
            ['name' => 'GIP 2026 Second Semester'],
            [
                'program_id' => $gip->id,
                'description' => 'Apply now for the second semester of the Government Internship Program. Slots are limited.',
                'status' => 'open',
                'total_slots' => 30,
                'application_start' => now()->subWeek()->toDateString(),
                'application_deadline' => now()->addWeeks(3)->toDateString(),
                'deployment_start' => now()->addWeeks(4)->toDateString(),
                'deployment_end' => now()->addWeeks(12)->toDateString(),
                'created_by' => $staff->id,
            ],
        );

        $cycle->requirements()->syncWithPivotValues(
            Requirement::whereIn('slug', ['certificate-of-registration', 'valid-government-id', 'latest-grades', 'barangay-clearance', 'parents-consent', 'income-tax-return'])->pluck('id'),
            ['is_required' => true],
        );

        // A second, upcoming cycle for SPES so both programs show content.
        ProgramCycle::updateOrCreate(
            ['name' => 'SPES 2026 Summer'],
            [
                'program_id' => $spes->id,
                'description' => 'Summer employment for eligible students. Applications open soon.',
                'status' => 'upcoming',
                'total_slots' => 25,
                'application_start' => now()->addWeeks(4)->toDateString(),
                'application_deadline' => now()->addWeeks(8)->toDateString(),
                'deployment_start' => now()->addWeeks(10)->toDateString(),
                'deployment_end' => now()->addWeeks(16)->toDateString(),
                'created_by' => $staff->id,
            ],
        );

        /*
        |--------------------------------------------------------------------------
        | Host agencies
        |--------------------------------------------------------------------------
        */
        $agencies = [
            ['name' => 'Public Employment Service Office', 'address' => 'City Hall Complex, Capitol Compound'],
            ['name' => 'City Social Welfare and Development Office', 'address' => 'City Hall, 2nd Floor'],
            ['name' => 'Municipal Agriculture Office', 'address' => 'Municipal Hall Grounds'],
            ['name' => 'DepEd Schools Division Office', 'address' => 'Rizal Avenue Extension'],
            ['name' => 'Department of Environment and Natural Resources', 'address' => 'Forestry Compound'],
            ['name' => 'Provincial Planning and Development Office', 'address' => 'Provincial Capitol Building'],
        ];

        foreach ($agencies as $index => $data) {
            HostAgency::updateOrCreate(['name' => $data['name']], [
                'address' => $data['address'],
                'contact_person' => ['R. Dizon', 'L. Mercado', 'A. Villanueva', 'C. Reyes', 'M. Pascual', 'J. Ramos'][$index],
                'contact_number' => '(045) 555-0'.(100 + $index),
                'email' => 'agency'.$index.'@example.com',
                'is_active' => true,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Deployment sites
        |--------------------------------------------------------------------------
        */
        $sites = [
            ['name' => 'DOLE Provincial Field Office', 'address' => 'Capitol Compound', 'city' => 'San Fernando', 'region' => 'Region III'],
            ['name' => 'City Hall Main Building', 'address' => 'City Hall Complex', 'city' => 'San Fernando', 'region' => 'Region III'],
            ['name' => 'PESO Satellite Office', 'address' => 'Public Market Annex', 'city' => 'Mabalacat', 'region' => 'Region III'],
            ['name' => 'Regional Training Center', 'address' => 'Ayala Blvd.', 'city' => 'San Fernando', 'region' => 'Region III'],
        ];

        foreach ($sites as $data) {
            DeploymentSite::updateOrCreate(
                ['name' => $data['name']],
                ['address' => $data['address'], 'city' => $data['city'], 'region' => $data['region'], 'is_active' => true],
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Demo student accounts
        |--------------------------------------------------------------------------
        */
        $student = User::updateOrCreate(
            ['email' => 'student@example.com'],
            [
                'name' => 'Juan Dela Cruz',
                'password' => Hash::make(self::DEMO_PASSWORD),
                'role' => User::ROLE_STUDENT,
            ],
        );

        StudentDetail::updateOrCreate(['user_id' => $student->id], [
            'school_name' => 'Don Honorio Ventura State University',
            'course' => 'Bachelor of Science in Information Technology',
            'year_level' => 3,
            'gwa' => 1.75,
            'is_indigent' => true,
            'is_4ps_member' => false,
            'address' => 'Brgy. San Nicolas, San Fernando City, Pampanga',
            'birthplace' => 'San Fernando City, Pampanga',
            'birthdate' => '2004-05-12',
            'sex' => 'male',
        ]);

        User::updateOrCreate(
            ['email' => 'rafael@example.com'],
            [
                'name' => 'Rafael Martin Aquino',
                'password' => Hash::make(self::DEMO_PASSWORD),
                'role' => User::ROLE_STUDENT,
            ],
        );

        $this->command->info('Database seeded.');
        $this->command->info('Demo accounts (password: '.self::DEMO_PASSWORD.'):');
        $this->command->info('  staff@example.com  (staff)');
        $this->command->info('  student@example.com (student)');
        $this->command->info('  rafael@example.com (student)');
    }
}

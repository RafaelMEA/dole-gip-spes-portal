<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\Requirement;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApplicationDocument>
 */
class ApplicationDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'requirement_id' => Requirement::factory(),
            'file_path' => 'applications/'.Str::uuid().'.pdf',
            'file_name' => $this->faker->slug(2).'.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => $this->faker->numberBetween(50_000, 2_000_000),
            'verification_status' => 'pending',
            'rejection_reason' => null,
            'verified_by' => null,
            'verified_at' => null,
            'uploaded_at' => now(),
        ];
    }

    public function verified(): static
    {
        return $this->state(fn () => [
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);
    }
}

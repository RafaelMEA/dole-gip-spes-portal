<?php

namespace Database\Factories;

use App\Models\DeploymentAssignment;
use App\Models\HostAgency;
use App\Models\Application;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeploymentAssignment>
 */
class DeploymentAssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'host_agency_id' => HostAgency::factory(),
            'deployment_site_id' => null,
            'position' => $this->faker->jobTitle(),
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addMonths(3)->toDateString(),
            'status' => 'scheduled',
            'assigned_by' => User::factory()->staff(),
            'assigned_at' => now(),
            'remarks' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => 'active',
            'start_date' => now()->toDateString(),
        ]);
    }
}

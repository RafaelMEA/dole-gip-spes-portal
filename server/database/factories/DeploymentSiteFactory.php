<?php

namespace Database\Factories;

use App\Models\DeploymentSite;
use App\Models\HostAgency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeploymentSite>
 */
class DeploymentSiteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'host_agency_id' => HostAgency::factory(),
            'name' => $this->faker->randomElement([
                'City Hall Main Building',
                'Municipal Hall',
                'Public Employment Service Office (PESO)',
                'Regional Training Center',
                'Provincial Field Office',
            ]),
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'region' => $this->faker->randomElement(['Region IV-A', 'Region III', 'NCR']),
            'contact_person' => $this->faker->name(),
            'contact_number' => $this->faker->phoneNumber(),
            'email' => $this->faker->unique()->safeEmail(),
            'description' => $this->faker->sentence(),
            'is_active' => true,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['is_active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}

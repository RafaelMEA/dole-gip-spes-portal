<?php

namespace Database\Factories;

use App\Models\DeploymentSite;
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
            'name' => $this->faker->randomElement([
                'DOLE Provincial Field Office',
                'City Hall Main Building',
                'Municipal Hall',
                'Public Employment Service Office (PESO)',
                'Regional Training Center',
            ]),
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'region' => $this->faker->randomElement(['Region IV-A', 'Region III', 'NCR']),
            'is_active' => true,
        ];
    }
}

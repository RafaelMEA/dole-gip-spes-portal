<?php

namespace Database\Factories;

use App\Models\DeploymentSite;
use App\Models\DeploymentSlot;
use App\Models\ProgramCycle;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeploymentSlotFactory extends Factory
{
    protected $model = DeploymentSlot::class;

    public function definition(): array
    {
        return [
            'program_cycle_id' => ProgramCycleFactory::new(),
            'deployment_site_id' => DeploymentSiteFactory::new(),
            'title' => fake()->randomElement([
                'Administrative Assistant',
                'IT Support Assistant',
                'Encoder',
                'Records Officer',
                'Research Assistant',
                'Community Outreach Assistant',
            ]),
            'description' => fake()->optional(0.6)->sentence(),
            'capacity' => fake()->numberBetween(1, 15),
            'status' => 'active',
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => 'active']);
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }
}

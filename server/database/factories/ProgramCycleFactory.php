<?php

namespace Database\Factories;

use App\Models\Program;
use App\Models\ProgramCycle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProgramCycle>
 */
class ProgramCycleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('-2 months', '-1 day');

        return [
            'program_id' => Program::factory(),
            'name' => 'Batch '.$this->faker->unique()->numberBetween(1, 40),
            'description' => $this->faker->sentence(10),
            'status' => 'upcoming',
            'total_slots' => $this->faker->numberBetween(5, 40),
            'application_start' => $start,
            'application_deadline' => (clone $start)->modify('+3 weeks'),
            'deployment_start' => (clone $start)->modify('+4 weeks'),
            'deployment_end' => (clone $start)->modify('+8 weeks'),
            'created_by' => null,
        ];
    }

    /**
     * A cycle currently accepting applications.
     */
    public function open(): static
    {
        $start = now()->subWeek();

        return $this->state([
            'application_start' => $start,
            'application_deadline' => now()->addWeeks(3),
            'deployment_start' => now()->addWeeks(4),
            'deployment_end' => now()->addWeeks(10),
        ]);
    }

    /**
     * A cycle still in preparation (hidden from students).
     */
    public function draft(): static
    {
        return $this->state(['status' => 'draft']);
    }

    /**
     * A retired cycle (hidden from students).
     */
    public function archived(): static
    {
        return $this->state(['status' => 'archived']);
    }
}

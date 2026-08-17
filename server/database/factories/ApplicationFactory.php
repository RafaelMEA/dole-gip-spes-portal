<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\ProgramCycle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'applicant_id' => User::factory()->student(),
            'program_cycle_id' => ProgramCycle::factory(),
            'status' => 'draft',
            'remarks' => null,
            'submitted_at' => null,
            'approved_at' => null,
            'approved_by' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => 'approved',
            'submitted_at' => now()->subDays(3),
            'approved_at' => now()->subDay(),
            'approved_by' => User::factory()->staff(),
        ]);
    }

    public function returnedForCorrection(): static
    {
        return $this->state(fn () => [
            'status' => 'returned_for_correction',
            'submitted_at' => now()->subDays(2),
            'decision_reason' => 'Please upload your current Certificate of Registration.',
            'decided_by' => User::factory()->staff(),
            'decided_at' => now()->subDay(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => 'rejected',
            'submitted_at' => now()->subDays(3),
            'decision_reason' => 'Application does not meet program requirements.',
            'decided_by' => User::factory()->staff(),
            'decided_at' => now()->subDay(),
        ]);
    }
}

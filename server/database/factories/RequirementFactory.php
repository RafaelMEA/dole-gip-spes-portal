<?php

namespace Database\Factories;

use App\Models\Requirement;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Requirement>
 */
class RequirementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->randomElement([
            'Certificate of Registration',
            'Valid Government ID',
            'Copy of Latest Grades',
            'Barangay Clearance',
            'Parents Consent',
            'PWD ID',
        ]);

        return [
            'name' => $name,
            'description' => $this->faker->sentence(8),
            'slug' => Str::slug($name),
            'is_active' => true,
        ];
    }
}

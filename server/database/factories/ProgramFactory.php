<?php

namespace Database\Factories;

use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Program>
 */
class ProgramFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->randomElement([
            'Government Internship Program',
            'Special Program for Employment of Students',
        ]);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $this->faker->sentence(12),
            'is_active' => true,
        ];
    }

    public function gip(): static
    {
        return $this->state([
            'name' => 'Government Internship Program',
            'slug' => 'gip',
        ]);
    }

    public function spes(): static
    {
        return $this->state([
            'name' => 'Special Program for Employment of Students',
            'slug' => 'spes',
        ]);
    }
}

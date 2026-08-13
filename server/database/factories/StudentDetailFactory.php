<?php

namespace Database\Factories;

use App\Models\StudentDetail;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentDetail>
 */
class StudentDetailFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'school_name' => $this->faker->randomElement([
                'Don Honorio Ventura State University',
                'Bulacan State University',
                'University of the Philippines',
                'Polytechnic University of the Philippines',
            ]),
            'course' => $this->faker->randomElement([
                'Bachelor of Science in Information Technology',
                'Bachelor of Science in Business Administration',
                'Bachelor of Science in Education',
                'Bachelor of Science in Accountancy',
            ]),
            'year_level' => $this->faker->numberBetween(1, 4),
            'gwa' => $this->faker->randomFloat(2, 1.25, 2.5),
            'is_indigent' => $this->faker->boolean(60),
            'is_4ps_member' => $this->faker->boolean(20),
            'address' => $this->faker->streetAddress().', San Fernando City, Pampanga',
            'birthplace' => $this->faker->city().', Pampanga',
            'birthdate' => $this->faker->date('Y-m-d', '-18 years'),
            'sex' => $this->faker->randomElement(['male', 'female']),
        ];
    }
}

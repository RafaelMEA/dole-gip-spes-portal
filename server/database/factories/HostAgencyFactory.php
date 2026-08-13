<?php

namespace Database\Factories;

use App\Models\HostAgency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HostAgency>
 */
class HostAgencyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement([
                'City Social Welfare Office',
                'Local Government Unit of San Isidro',
                'Department of Agriculture Regional Office',
                'Municipal Engineering Office',
                'Public Employment Service Office',
                'DepEd Schools Division Office',
            ]),
            'address' => $this->faker->streetAddress(),
            'contact_person' => $this->faker->name(),
            'contact_number' => $this->faker->phoneNumber(),
            'email' => $this->faker->unique()->safeEmail(),
            'is_active' => true,
        ];
    }
}

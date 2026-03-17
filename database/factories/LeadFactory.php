<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'             => fake()->company(),
            'email'            => fake()->email(),
            'phone'            => fake()->phoneNumber(),
            'company'          => fake()->company(),
            'status'           => 'open',
            'assigned_user_id' => User::factory(),
        ];
    }
}

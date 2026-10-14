<?php

namespace Database\Factories;

use App\Enums\ReportStatus;
use App\Enums\ReportUrgency;
use App\Models\Category;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('???###')),
            'category_id' => Category::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'location' => 'Lantai '.fake()->numberBetween(1, 3),
            'urgency' => ReportUrgency::Medium,
            'status' => ReportStatus::New,
            'guest_name' => fake()->name(),
            'guest_contact' => fake()->phoneNumber(),
        ];
    }

    /**
     * Laporan milik user terdaftar (bukan guest).
     */
    public function forUser(User $user): static
    {
        return $this->state(fn () => [
            'reporter_user_id' => $user->id,
            'guest_name' => null,
            'guest_contact' => null,
        ]);
    }

    public function withStatus(ReportStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}

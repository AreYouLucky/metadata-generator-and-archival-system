<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\YoutubeAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<YoutubeAccount>
 */
class YoutubeAccountFactory extends Factory
{
    /**
     * A connected channel whose access token is still valid.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->encoder(),
            'channel_id' => 'UC'.fake()->regexify('[A-Za-z0-9_-]{22}'),
            'channel_title' => fake()->company(),
            'channel_thumbnail_url' => null,
            'access_token' => 'ya29.'.fake()->sha256(),
            'refresh_token' => '1//'.fake()->sha256(),
            'token_expires_at' => now()->addHour(),
            'scopes' => 'https://www.googleapis.com/auth/youtube.upload https://www.googleapis.com/auth/youtube.readonly',
        ];
    }

    public function expired(): static
    {
        return $this->state(['token_expires_at' => now()->subMinutes(5)]);
    }
}

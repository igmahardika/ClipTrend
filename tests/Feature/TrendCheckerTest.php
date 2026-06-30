<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\Video\TrendIntelligenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrendCheckerTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_trend_report_can_be_generated_without_api_key(): void
    {
        $role = Role::create(['name' => 'User', 'slug' => 'user']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($role);

        $report = app(TrendIntelligenceService::class)->report($user, [
            'topic' => 'motivasi produktivitas',
            'platform' => 'tiktok',
            'region' => 'ID',
        ]);

        $this->assertSame($user->id, $report->user_id);
        $this->assertNotEmpty($report->hashtags);
        $this->assertNotEmpty($report->hooks);
        $this->assertGreaterThan(0, $report->score);
    }

    public function test_trend_checker_page_accepts_post_without_external_api(): void
    {
        $role = Role::create(['name' => 'User', 'slug' => 'user']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($role);

        $response = $this->actingAs($user)->post(route('trends.store'), [
            'topic' => 'edukasi bisnis online',
            'platform' => 'shorts',
            'region' => 'ID',
        ]);

        $response->assertOk();
        $response->assertSee('edukasi bisnis online', false);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Evident;
use App\Models\Period;
use App\Models\Rhk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email): User
    {
        return User::create(['name' => $email, 'email' => $email, 'google_id' => $email,
            'google_access_token' => 'a', 'google_refresh_token' => 'r', 'token_expires_at' => now()->addHour()]);
    }

    private function seedFor(User $u, array $statuses): void
    {
        $p = Period::create(['user_id' => $u->id, 'title' => 'TW I', 'year' => 2025]);
        $r = Rhk::create(['period_id' => $p->id, 'code' => 'R1', 'title' => 't']);
        foreach ($statuses as $s) {
            Evident::create(['rhk_id' => $r->id, 'title' => 'e', 'evident_type' => 'file_upload', 'status' => $s]);
        }
    }

    public function test_stats_count_only_own_data_and_hide_tokens(): void
    {
        $me = $this->user('me@example.com');
        $other = $this->user('other@example.com');
        $this->seedFor($me, ['ready', 'ready', 'processing', 'failed']);
        $this->seedFor($other, ['ready', 'ready', 'ready']);

        $this->actingAs($me)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('stats.periods', 1)
            ->where('stats.rhks', 1)
            ->where('stats.evidents', ['ready' => 2, 'processing' => 1, 'failed' => 1])
            ->where('auth.user', ['id' => $me->id, 'name' => 'me@example.com', 'email' => 'me@example.com']));
    }

    public function test_login_page_renders_for_guest(): void
    {
        $this->get('/login')->assertInertia(fn (Assert $p) => $p->component('Auth/Login'));
    }
}

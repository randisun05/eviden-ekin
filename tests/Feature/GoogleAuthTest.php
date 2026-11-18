<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGoogle(array $scopes = ['https://www.googleapis.com/auth/drive.file'], ?string $refresh = 'RT-NEW'): void
    {
        $u = (new SocialUser)->map(['id' => 'g-1', 'name' => 'Budi', 'email' => 'budi@example.com']);
        $u->token = 'AT-NEW';
        $u->refreshToken = $refresh;
        $u->expiresIn = 3600;
        $u->approvedScopes = $scopes;

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($u);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_redirect_requests_offline_access_and_drive_scope(): void
    {
        $res = $this->get('/auth/google');
        $res->assertRedirect();
        $url = $res->headers->get('Location');
        $this->assertStringContainsString('accounts.google.com', $url);
        $this->assertStringContainsString('access_type=offline', $url);
        $this->assertStringContainsString('prompt=consent', $url);
        $this->assertStringContainsString(urlencode('https://www.googleapis.com/auth/drive.file'), $url);
    }

    public function test_callback_creates_user_with_encrypted_tokens_and_logs_in(): void
    {
        $this->fakeGoogle();

        $this->get('/auth/google/callback?code=x')->assertRedirect('/dashboard');

        $user = User::firstWhere('email', 'budi@example.com');
        $this->assertAuthenticatedAs($user);
        $this->assertSame('RT-NEW', $user->google_refresh_token);
        $this->assertNotSame('RT-NEW', \DB::table('users')->value('google_refresh_token'));
    }

    public function test_existing_refresh_token_kept_when_google_sends_none(): void
    {
        User::create(['name' => 'Budi', 'email' => 'budi@example.com', 'google_id' => 'g-1',
            'google_access_token' => 'old', 'google_refresh_token' => 'RT-OLD']);
        $this->fakeGoogle(refresh: null);

        $this->get('/auth/google/callback?code=x');

        $this->assertSame('RT-OLD', User::first()->google_refresh_token);
        $this->assertSame(1, User::count());
    }

    public function test_login_refused_when_drive_scope_not_granted(): void
    {
        $this->fakeGoogle(['openid', 'email']);

        $this->get('/auth/google/callback?code=x')->assertRedirect('/login');

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_user_denying_consent_returns_to_login(): void
    {
        $this->get('/auth/google/callback?error=access_denied')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_user_without_refresh_token_is_sent_to_reauthorize(): void
    {
        $u = User::create(['name' => 'X', 'email' => 'x@example.com', 'google_id' => 'g']);

        $this->actingAs($u)->get('/dashboard')->assertRedirect(route('auth.google'));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }
}

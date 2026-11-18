<?php

namespace Tests\Feature;

use App\Models\Evident;
use App\Models\Period;
use App\Models\Rhk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicViewerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Rhk $rhk;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('x');
        $this->owner = User::create(['name' => 'Budi Santoso', 'email' => 'b@example.com', 'google_id' => 'b',
            'google_access_token' => 'a', 'google_refresh_token' => 'r', 'token_expires_at' => now()->addHour()]);
        $p = Period::create(['user_id' => $this->owner->id, 'title' => 'Triwulan I', 'year' => 2025]);
        $this->rhk = Rhk::create(['period_id' => $p->id, 'code' => 'RHK-01', 'title' => 'Laporan', 'target_description' => '12 dokumen']);
    }

    private function ev(array $attrs = []): Evident
    {
        return Evident::create($attrs + ['rhk_id' => $this->rhk->id, 'title' => 'Dok', 'evident_type' => 'file_upload',
            'file_name' => 'a.pdf', 'mime_type' => 'application/pdf', 'gdrive_file_id' => 'FILE1',
            'gdrive_web_view_link' => 'https://drive.google.com/file/d/FILE1/view', 'status' => 'ready']);
    }

    public function test_code_namespaces_cannot_collide(): void
    {
        $e = $this->ev();
        $this->assertSame(10, strlen($this->rhk->app_short_code));
        $this->assertSame(8, strlen($e->app_short_code));
    }

    public function test_bundle_page_lists_only_ready_items_without_login(): void
    {
        $this->ev(['title' => 'Siap PDF']);
        $this->ev(['title' => 'Gambar', 'file_name' => 'x.png', 'mime_type' => 'image/png']);
        $this->ev(['title' => 'Tautan', 'evident_type' => 'external_link', 'external_url' => 'https://example.com/x', 'gdrive_file_id' => null]);
        $this->ev(['title' => 'Proses', 'status' => 'processing']);
        $this->ev(['title' => 'Gagal', 'status' => 'failed']);

        $res = $this->get('/v/'.$this->rhk->app_short_code);

        $res->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Public/Viewer')
            ->where('mode', 'bundle')
            ->where('owner', 'Budi Santoso')
            ->where('period', 'Triwulan I 2025')
            ->where('rhk.code', 'RHK-01')
            ->has('items', 3)
            ->where('items.0.kind', 'pdf')
            ->where('items.0.preview_url', 'https://drive.google.com/file/d/FILE1/preview')
            ->where('items.1.kind', 'image')
            ->where('items.2.kind', 'link')
            ->missing('items.0.access_pin'));
        $res->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $res->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', $res->headers->get('Cache-Control'));
    }

    public function test_single_evident_page_and_unfinished_or_unknown_are_404(): void
    {
        $ok = $this->ev();
        $pending = $this->ev(['status' => 'processing']);

        $this->get('/v/'.$ok->app_short_code)->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('mode', 'single')->has('items', 1));
        $this->get('/v/'.$pending->app_short_code)->assertNotFound();
        $this->get('/v/zzzzzzzz')->assertNotFound();
        $this->get('/v/AB')->assertNotFound();       // di luar pola rute
        $this->get('/v/abc-defgh')->assertNotFound();
    }

    public function test_expired_link_returns_410_and_no_data(): void
    {
        $e = $this->ev(['expires_at' => now()->subMinute()]);
        $this->rhk->update(['expires_at' => now()->subSecond()]);

        $this->get('/v/'.$e->app_short_code)->assertStatus(410)
            ->assertInertia(fn (Assert $p) => $p->component('Public/Unavailable')->missing('items'));
        $this->get('/v/'.$this->rhk->app_short_code)->assertStatus(410);

        $this->rhk->update(['expires_at' => now()->addDay()]);
        $this->get('/v/'.$this->rhk->app_short_code)->assertOk();
    }

    public function test_protected_page_hides_content_until_correct_pin(): void
    {
        $this->rhk->update(['access_pin' => '1234', 'is_protected' => true]);
        $this->ev();
        $code = $this->rhk->app_short_code;

        $this->get("/v/$code")->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Public/Pin')->missing('items')->missing('rhk')->missing('owner'));

        $this->post("/v/$code/pin", ['pin' => '9999'])->assertSessionHasErrors('pin');
        $this->get("/v/$code")->assertInertia(fn (Assert $p) => $p->component('Public/Pin'));

        $this->post("/v/$code/pin", ['pin' => '1234'])->assertRedirect("/v/$code");
        $this->get("/v/$code")->assertInertia(fn (Assert $p) => $p->component('Public/Viewer')->has('items', 1));
    }

    public function test_changing_pin_revokes_existing_unlock(): void
    {
        $this->rhk->update(['access_pin' => '1234', 'is_protected' => true]);
        $code = $this->rhk->app_short_code;
        $this->post("/v/$code/pin", ['pin' => '1234']);
        $this->get("/v/$code")->assertInertia(fn (Assert $p) => $p->component('Public/Viewer'));

        $this->rhk->update(['access_pin' => '5678']);

        $this->get("/v/$code")->assertInertia(fn (Assert $p) => $p->component('Public/Pin'));
    }

    public function test_unlock_of_one_target_does_not_unlock_another(): void
    {
        $this->rhk->update(['access_pin' => '1234', 'is_protected' => true]);
        $e = $this->ev(['access_pin' => '1234', 'is_protected' => true]);
        $this->post('/v/'.$this->rhk->app_short_code.'/pin', ['pin' => '1234']);

        $this->get('/v/'.$e->app_short_code)->assertInertia(fn (Assert $p) => $p->component('Public/Pin'));
    }

    public function test_pin_guessing_is_rate_limited_even_for_the_right_pin(): void
    {
        $this->rhk->update(['access_pin' => '1234', 'is_protected' => true]);
        $code = $this->rhk->app_short_code;

        for ($i = 0; $i < 5; $i++) {
            $this->post("/v/$code/pin", ['pin' => '000'.$i])->assertSessionHasErrors('pin');
        }
        // percobaan ke-6 dengan PIN BENAR tetap diblokir
        $res = $this->post("/v/$code/pin", ['pin' => '1234']);
        $res->assertSessionHasErrors('pin');
        $msg = session('errors')->first('pin');
        $this->assertStringContainsString('Terlalu banyak', $msg);
        $this->assertStringContainsString('10 menit', $msg); // blokir per-IP, bukan 60 menit milik kunci target
        $this->get("/v/$code")->assertInertia(fn (Assert $p) => $p->component('Public/Pin'));
    }

    public function test_owner_can_set_pin_expiry_and_clear_them(): void
    {
        $this->actingAs($this->owner);
        $e = $this->ev();

        $this->patch("/rhks/{$this->rhk->id}/share", ['pin' => '4321', 'expires_at' => now()->addDays(3)->format('Y-m-d')])
            ->assertSessionHasNoErrors();
        $r = $this->rhk->fresh();
        $this->assertTrue($r->is_protected);
        $this->assertNotSame('4321', $r->getRawOriginal('access_pin'));
        $this->assertTrue($r->expires_at->isFuture());
        $this->assertTrue($r->expires_at->isSameDay(now()->addDays(3)));

        $this->patch("/rhks/{$this->rhk->id}/share", ['remove_pin' => true, 'expires_at' => null]);
        $r->refresh();
        $this->assertFalse($r->is_protected);
        $this->assertNull($r->access_pin);
        $this->assertNull($r->expires_at);

        $this->patch("/evidents/{$e->id}/share", ['pin' => '1111'])->assertSessionHasNoErrors();
        $this->assertTrue($e->fresh()->is_protected);
    }

    public function test_share_validation_rejects_past_dates_and_bad_pin(): void
    {
        $this->actingAs($this->owner);
        $this->patch("/rhks/{$this->rhk->id}/share", ['expires_at' => now()->subDay()->format('Y-m-d')])->assertSessionHasErrors('expires_at');
        $this->patch("/rhks/{$this->rhk->id}/share", ['pin' => '12'])->assertSessionHasErrors('pin');
        $this->patch("/rhks/{$this->rhk->id}/share", ['pin' => 'abcd'])->assertSessionHasErrors('pin');
    }

    public function test_regenerate_revokes_old_link(): void
    {
        $this->actingAs($this->owner);
        $e = $this->ev();
        $old = $e->app_short_code;
        $oldRhk = $this->rhk->app_short_code;

        $this->post("/evidents/{$e->id}/share/regenerate");
        $this->post("/rhks/{$this->rhk->id}/share/regenerate");

        $this->get("/v/$old")->assertNotFound();
        $this->get("/v/$oldRhk")->assertNotFound();
        $this->get('/v/'.$e->fresh()->app_short_code)->assertOk();
        $this->assertSame(8, strlen($e->fresh()->app_short_code));
        $this->assertSame(10, strlen($this->rhk->fresh()->app_short_code));
    }

    public function test_other_users_cannot_change_sharing(): void
    {
        $evil = User::create(['name' => 'E', 'email' => 'e@example.com', 'google_id' => 'e',
            'google_access_token' => 'a', 'google_refresh_token' => 'r', 'token_expires_at' => now()->addHour()]);
        $e = $this->ev();

        $this->actingAs($evil)->patch("/rhks/{$this->rhk->id}/share", ['pin' => '1234'])->assertForbidden();
        $this->actingAs($evil)->post("/evidents/{$e->id}/share/regenerate")->assertForbidden();
        $this->assertFalse($this->rhk->fresh()->is_protected);
    }

    public function test_owner_page_exposes_both_links(): void
    {
        $this->actingAs($this->owner);
        $e = $this->ev();
        config(['evident.shortlink_base' => 'https://evident.example.com']);

        $this->get("/rhks/{$this->rhk->id}")->assertInertia(fn (Assert $p) => $p
            ->where('share.url', 'https://evident.example.com/v/'.$this->rhk->app_short_code)
            ->where('evidents.0.links.direct', 'https://drive.google.com/file/d/FILE1/view')
            ->where('evidents.0.links.short', 'https://evident.example.com/v/'.$e->app_short_code));
    }
}

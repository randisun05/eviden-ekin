<?php

namespace Tests\Feature;

use App\Jobs\DeleteGdriveFiles;
use App\Jobs\ProcessGdriveUpload;
use App\Models\Evident;
use App\Models\Period;
use App\Models\Rhk;
use App\Models\User;
use App\Services\Evident\ReadinessChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EvidenceManagementTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email = 'me@example.com'): User
    {
        return User::create(['name' => $email, 'email' => $email, 'google_id' => $email,
            'google_access_token' => 'a', 'google_refresh_token' => 'r', 'token_expires_at' => now()->addHour()]);
    }

    private function rhkFor(User $u, string $code = 'RHK-01'): Rhk
    {
        $p = Period::firstOrCreate(['user_id' => $u->id, 'title' => 'Triwulan I', 'year' => 2025]);

        return Rhk::create(['period_id' => $p->id, 'code' => $code, 'title' => 'Judul']);
    }

    public function test_create_period_and_rhk(): void
    {
        $u = $this->user();

        $res = $this->actingAs($u)->post('/periods', ['title' => 'Triwulan II', 'year' => 2025]);
        $period = Period::firstOrFail();
        $res->assertRedirect(route('periods.show', $period));
        $this->assertSame($u->id, $period->user_id);
        $this->assertSame(36, strlen($period->id));

        $this->actingAs($u)->post("/periods/{$period->id}/rhks", ['code' => 'RHK-01', 'title' => 'x'])->assertRedirect();
        $this->assertSame(1, $period->rhks()->count());
    }

    public function test_rhk_code_rules(): void
    {
        $u = $this->user();
        $rhk = $this->rhkFor($u);
        $url = "/periods/{$rhk->period_id}/rhks";

        $this->actingAs($u)->post($url, ['code' => 'RHK-01', 'title' => 'dup'])->assertSessionHasErrors('code');
        $this->actingAs($u)->post($url, ['code' => 'A/B', 'title' => 'x'])->assertSessionHasErrors('code');
        $this->actingAs($u)->post($url, ['code' => 'A\\B', 'title' => 'x'])->assertSessionHasErrors('code');
        $this->actingAs($u)->put("/rhks/{$rhk->id}", ['code' => 'RHK-01', 'title' => 'edit'])->assertSessionDoesntHaveErrors();
        $this->assertSame('edit', $rhk->fresh()->title);
    }

    public function test_other_users_cannot_touch_my_data(): void
    {
        $me = $this->user();
        $evil = $this->user('evil@example.com');
        $rhk = $this->rhkFor($me);
        $e = Evident::create(['rhk_id' => $rhk->id, 'title' => 't', 'evident_type' => 'external_link',
            'external_url' => 'https://example.com', 'status' => 'ready']);

        $this->actingAs($evil)->get("/periods/{$rhk->period_id}")->assertForbidden();
        $this->actingAs($evil)->get("/rhks/{$rhk->id}")->assertForbidden();
        $this->actingAs($evil)->post("/periods/{$rhk->period_id}/rhks", ['code' => 'X', 'title' => 'x'])->assertForbidden();
        $this->actingAs($evil)->post("/rhks/{$rhk->id}/evidents", ['evident_type' => 'external_link', 'title' => 't', 'external_url' => 'https://a.b'])->assertForbidden();
        $this->actingAs($evil)->delete("/evidents/{$e->id}")->assertForbidden();
        $this->actingAs($evil)->delete("/periods/{$rhk->period_id}")->assertForbidden();
        $this->assertDatabaseCount('evidents', 1);
    }

    public function test_file_upload_validation(): void
    {
        Storage::fake('staging');
        Queue::fake();
        $u = $this->user();
        $rhk = $this->rhkFor($u);
        $url = "/rhks/{$rhk->id}/evidents";
        $base = ['evident_type' => 'file_upload', 'title' => 'Laporan'];

        // ekstensi berbahaya
        $this->actingAs($u)->post($url, $base + ['files' => [UploadedFile::fake()->create('x.php', 1)]])->assertSessionHasErrors('files.0');
        // kode PHP sungguhan yang disamarkan sebagai .pdf -> ditolak dari ISI file (bukan hanya nama)
        $tmp = tempnam(sys_get_temp_dir(), 'ev');
        file_put_contents($tmp, '<?php echo system($_GET["c"]); ?>');
        $disguised = new UploadedFile($tmp, 'x.pdf', 'application/pdf', null, true);
        $this->actingAs($u)->post($url, $base + ['files' => [$disguised]])->assertSessionHasErrors('files.0');
        @unlink($tmp);
        // terlalu besar
        config(['evident.max_upload_mb' => 1]);
        $this->actingAs($u)->post($url, $base + ['files' => [UploadedFile::fake()->create('big.pdf', 2048, 'application/pdf')]])->assertSessionHasErrors('files.0');
        // PIN tidak valid
        config(['evident.max_upload_mb' => 50]);
        $this->actingAs($u)->post($url, $base + ['pin' => '12', 'files' => [UploadedFile::fake()->create('a.pdf', 5, 'application/pdf')]])->assertSessionHasErrors('pin');

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('evidents', 0);
    }

    public function test_valid_file_upload_queues_job(): void
    {
        Storage::fake('staging');
        Queue::fake();
        $u = $this->user();
        $rhk = $this->rhkFor($u);

        $this->actingAs($u)->post("/rhks/{$rhk->id}/evidents", [
            'evident_type' => 'file_upload', 'title' => 'Laporan', 'pin' => '1234',
            'files' => [UploadedFile::fake()->create('a.pdf', 5, 'application/pdf')],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $e = Evident::firstOrFail();
        $this->assertSame('processing', $e->status);
        $this->assertSame(8, strlen($e->app_short_code));
        $this->assertCount(1, $e->files);
        Queue::assertPushed(ProcessGdriveUpload::class, 1);
    }

    public function test_multiple_files_make_one_evident_and_one_job_each(): void
    {
        Storage::fake('staging');
        Queue::fake();
        $u = $this->user();
        $rhk = $this->rhkFor($u);

        $this->actingAs($u)->post("/rhks/{$rhk->id}/evidents", [
            'evident_type' => 'file_upload', 'title' => 'Paket bukti',
            'files' => [
                UploadedFile::fake()->create('a.pdf', 5, 'application/pdf'),
                UploadedFile::fake()->create('b.pdf', 6, 'application/pdf'),
                UploadedFile::fake()->image('c.png'),
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $e = Evident::firstOrFail();
        $this->assertDatabaseCount('evidents', 1);
        $this->assertSame(['a.pdf', 'b.pdf', 'c.png'], $e->files->pluck('file_name')->all());
        $this->assertSame([0, 1, 2], $e->files->pluck('position')->all());
        $this->assertCount(3, Storage::disk('staging')->files());
        Queue::assertPushed(ProcessGdriveUpload::class, 3);
    }

    public function test_file_count_and_total_size_limits(): void
    {
        Storage::fake('staging');
        Queue::fake();
        $u = $this->user();
        $rhk = $this->rhkFor($u);
        $url = "/rhks/{$rhk->id}/evidents";
        $mk = fn (int $n, int $kb = 5) => array_map(fn ($i) => UploadedFile::fake()->create("f$i.pdf", $kb, 'application/pdf'), range(1, $n));

        config(['evident.max_files' => 2]);
        $this->actingAs($u)->post($url, ['evident_type' => 'file_upload', 'title' => 't', 'files' => $mk(3)])->assertSessionHasErrors('files');

        config(['evident.max_files' => 20, 'evident.max_total_mb' => 1]);
        $this->actingAs($u)->post($url, ['evident_type' => 'file_upload', 'title' => 't', 'files' => $mk(2, 700)])->assertSessionHasErrors('files');

        $this->actingAs($u)->post($url, ['evident_type' => 'file_upload', 'title' => 't', 'files' => []])->assertSessionHasErrors('files');
        $this->actingAs($u)->post($url, ['evident_type' => 'file_upload', 'title' => 't'])->assertSessionHasErrors('files');

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('evidents', 0);
        $this->assertCount(0, Storage::disk('staging')->files());
    }

    public function test_deleting_multi_file_evident_queues_every_drive_file(): void
    {
        Queue::fake();
        $u = $this->user();
        $rhk = $this->rhkFor($u);
        $e = Evident::create(['rhk_id' => $rhk->id, 'title' => 'a', 'evident_type' => 'file_upload', 'status' => 'ready']);
        foreach (['F1', 'F2', null] as $i => $id) {
            $e->files()->create(['position' => $i, 'file_name' => "f$i.pdf", 'gdrive_file_id' => $id, 'status' => $id ? 'ready' : 'failed']);
        }

        $this->actingAs($u)->delete("/evidents/{$e->id}");

        Queue::assertPushed(DeleteGdriveFiles::class, fn ($j) => $j->fileIds === ['F1', 'F2']);
        $this->assertDatabaseCount('evident_files', 0);
    }

    public function test_external_link_must_be_http_or_https(): void
    {
        $u = $this->user();
        $rhk = $this->rhkFor($u);
        $url = "/rhks/{$rhk->id}/evidents";

        $this->actingAs($u)->post($url, ['evident_type' => 'external_link', 'title' => 't', 'external_url' => 'javascript:alert(1)'])
            ->assertSessionHasErrors('external_url');
        $this->actingAs($u)->post($url, ['evident_type' => 'external_link', 'title' => 't', 'external_url' => 'https://drive.google.com/x'])
            ->assertSessionHasNoErrors();
        $this->assertSame('ready', Evident::firstOrFail()->status);
    }

    public function test_deleting_evident_queues_drive_cleanup_only_when_file_exists(): void
    {
        Queue::fake();
        $u = $this->user();
        $rhk = $this->rhkFor($u);
        $withFile = Evident::create(['rhk_id' => $rhk->id, 'title' => 'a', 'evident_type' => 'file_upload', 'status' => 'ready']);
        $withFile->files()->create(['file_name' => 'a.pdf', 'gdrive_file_id' => 'F1', 'status' => 'ready']);
        $link = Evident::create(['rhk_id' => $rhk->id, 'title' => 'b', 'evident_type' => 'external_link', 'status' => 'ready']);

        $this->actingAs($u)->delete("/evidents/{$link->id}");
        Queue::assertNothingPushed();

        $this->actingAs($u)->delete("/evidents/{$withFile->id}");
        Queue::assertPushed(DeleteGdriveFiles::class, fn ($j) => $j->fileIds === ['F1'] && $j->userId === $u->id);
        $this->assertDatabaseCount('evidents', 0);
    }

    public function test_deleting_period_cascades_and_queues_all_drive_files(): void
    {
        Queue::fake();
        $u = $this->user();
        $r1 = $this->rhkFor($u, 'R1');
        $r2 = $this->rhkFor($u, 'R2');
        foreach ([[$r1, 'F1'], [$r2, 'F2']] as [$r, $f]) {
            Evident::create(['rhk_id' => $r->id, 'title' => 't', 'evident_type' => 'file_upload', 'status' => 'ready'])
                ->files()->create(['file_name' => 'a.pdf', 'gdrive_file_id' => $f, 'status' => 'ready']);
        }

        $this->actingAs($u)->delete("/periods/{$r1->period_id}")->assertRedirect(route('periods.index'));

        $this->assertDatabaseCount('rhks', 0);
        $this->assertDatabaseCount('evidents', 0);
        Queue::assertPushed(DeleteGdriveFiles::class, fn ($j) => collect($j->fileIds)->sort()->values()->all() === ['F1', 'F2']);
    }

    public function test_readiness_states(): void
    {
        $u = $this->user();
        $mk = function (string $code, array $statuses) use ($u) {
            $r = $this->rhkFor($u, $code);
            foreach ($statuses as $s) {
                Evident::create(['rhk_id' => $r->id, 'title' => 't', 'evident_type' => 'file_upload', 'status' => $s]);
            }

            return $r->load('evidents');
        };
        $c = new ReadinessChecker;

        $this->assertSame('empty', $c->forRhk($mk('A', []))['state']);
        $this->assertSame('processing', $c->forRhk($mk('B', ['ready', 'processing']))['state']);
        $this->assertSame('attention', $c->forRhk($mk('C', ['ready', 'failed', 'processing']))['state']);
        $this->assertSame('ready', $c->forRhk($mk('D', ['ready', 'ready']))['state']);

        $period = Period::first()->load('rhks.evidents');
        $this->assertSame(['rhk_total' => 4, 'rhk_ready' => 1, 'percent' => 25], $c->forPeriod($period));
    }

    public function test_pages_render_with_expected_props(): void
    {
        $u = $this->user();
        $rhk = $this->rhkFor($u);
        Evident::create(['rhk_id' => $rhk->id, 'title' => 't', 'evident_type' => 'file_upload', 'status' => 'failed', 'metadata' => ['error' => 'boom']]);

        $this->actingAs($u)->get('/periods')->assertInertia(fn (Assert $p) => $p->component('Periods/Index')->has('periods', 1));
        $this->actingAs($u)->get("/periods/{$rhk->period_id}")->assertInertia(fn (Assert $p) => $p
            ->component('Periods/Show')->where('rhks.0.readiness.state', 'attention'));
        $this->actingAs($u)->get("/rhks/{$rhk->id}")->assertInertia(fn (Assert $p) => $p
            ->component('Rhks/Show')->where('evidents.0.error', 'boom')->missing('evidents.0.access_pin'));
    }
}

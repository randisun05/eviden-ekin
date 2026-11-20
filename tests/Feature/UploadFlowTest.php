<?php

namespace Tests\Feature;

use App\Exceptions\GoogleDriveException;
use App\Jobs\ProcessGdriveUpload;
use App\Models\Evident;
use App\Models\EvidentFile;
use App\Models\Period;
use App\Models\Rhk;
use App\Models\User;
use App\Services\Evident\EvidentUploadService;
use App\Services\Google\GoogleDriveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class UploadFlowTest extends TestCase
{
    use RefreshDatabase;

    private function rhk(): Rhk
    {
        $u = User::create(['name' => 'U', 'email' => 'u@example.com', 'google_id' => 'g',
            'google_access_token' => 'a', 'google_refresh_token' => 'r', 'token_expires_at' => now()->addHour()]);
        $p = Period::create(['user_id' => $u->id, 'title' => 'Triwulan I', 'year' => 2025]);

        return Rhk::create(['period_id' => $p->id, 'code' => 'RHK-01', 'title' => 'Judul']);
    }

    private function driveResult(): array
    {
        return ['id' => 'FILE1', 'name' => 'a.pdf', 'mime_type' => 'application/pdf', 'size' => 1234,
            'web_view_link' => 'https://drive.google.com/file/d/FILE1/view', 'folder_id' => 'FOL'];
    }

    public function test_upload_creates_processing_record_stages_file_and_queues_job(): void
    {
        Storage::fake('staging');
        Queue::fake();
        $rhk = $this->rhk();

        $e = app(EvidentUploadService::class)->storeFiles($rhk, [UploadedFile::fake()->create('lap.pdf', 10, 'application/pdf')], 'Laporan', 'Menyusun laporan', '1234');

        $this->assertSame('processing', $e->status);
        $this->assertTrue($e->is_protected);
        $this->assertNotSame('1234', $e->getRawOriginal('access_pin')); // hash
        $this->assertCount(1, Storage::disk('staging')->files());
        $this->assertSame('processing', $e->files->first()->status);
        Queue::assertPushedOn('uploads', ProcessGdriveUpload::class, fn ($j) => $j->fileId === $e->files->first()->id);
    }

    /** Bukti dengan N berkas 'processing'. @return array{0:Evident,1:list<EvidentFile>} */
    private function evidentWithFiles(int $n = 1, string $status = 'processing'): array
    {
        $rhk = $this->rhk();
        $e = Evident::create(['rhk_id' => $rhk->id, 'title' => 'T', 'evident_type' => 'file_upload', 'status' => $status]);
        $files = [];
        for ($i = 0; $i < $n; $i++) {
            $files[] = $e->files()->create(['position' => $i, 'file_name' => "a$i.pdf", 'mime_type' => 'application/pdf', 'status' => $status]);
        }

        return [$e, $files];
    }

    private function bindDrive(callable $configure): void
    {
        $svc = Mockery::mock(GoogleDriveService::class);
        $configure($svc);
        $this->app->bind(GoogleDriveService::class, fn () => $svc);
    }

    public function test_job_uploads_marks_ready_and_deletes_staging(): void
    {
        Storage::fake('staging');
        Storage::disk('staging')->put('s.pdf', 'x');
        [$e, [$f]] = $this->evidentWithFiles();

        $this->bindDrive(fn ($svc) => $svc->shouldReceive('uploadToPath')->once()
            ->with(['2025', 'Triwulan I', 'RHK-01'], Mockery::type('string'), 'a0.pdf', 'application/pdf', ['evident_file_id' => $f->id])
            ->andReturn($this->driveResult()));

        (new ProcessGdriveUpload($f->id, 's.pdf'))->handle();

        $f->refresh();
        $this->assertSame('ready', $f->status);
        $this->assertSame('FILE1', $f->gdrive_file_id);
        $this->assertSame('FOL', $f->gdrive_folder_id);
        $this->assertSame('ready', $e->fresh()->status);
        Storage::disk('staging')->assertMissing('s.pdf');
    }

    public function test_evident_stays_processing_until_every_file_is_ready(): void
    {
        Storage::fake('staging');
        Storage::disk('staging')->put('s0.pdf', 'x');
        Storage::disk('staging')->put('s1.pdf', 'x');
        [$e, [$f0, $f1]] = $this->evidentWithFiles(2);
        $this->bindDrive(fn ($svc) => $svc->shouldReceive('uploadToPath')->twice()->andReturn($this->driveResult(), $this->driveResult()));

        (new ProcessGdriveUpload($f0->id, 's0.pdf'))->handle();
        $this->assertSame('processing', $e->fresh()->status);
        $this->assertSame('ready', $f0->fresh()->status);

        (new ProcessGdriveUpload($f1->id, 's1.pdf'))->handle();
        $this->assertSame('ready', $e->fresh()->status);
    }

    public function test_one_failed_file_fails_the_evident_only_after_the_others_finish(): void
    {
        Storage::fake('staging');
        Storage::disk('staging')->put('s0.pdf', 'x');
        Storage::disk('staging')->put('s1.pdf', 'x');
        [$e, [$f0, $f1]] = $this->evidentWithFiles(2);
        $this->bindDrive(fn ($svc) => $svc->shouldReceive('uploadToPath')->once()->andReturn($this->driveResult()));

        (new ProcessGdriveUpload($f0->id, 's0.pdf'))->failed(GoogleDriveException::reauthRequired());
        $this->assertSame('processing', $e->fresh()->status); // berkas lain masih berjalan

        (new ProcessGdriveUpload($f1->id, 's1.pdf'))->handle();

        $e->refresh();
        $this->assertSame('failed', $e->status);
        $this->assertTrue($e->metadata['reauth_required']);
        $this->assertSame('ready', $f1->fresh()->status);
        $this->assertSame('failed', $f0->fresh()->status);
    }

    public function test_job_is_noop_when_already_ready(): void
    {
        Storage::fake('staging');
        Storage::disk('staging')->put('s.pdf', 'x');
        [, [$f]] = $this->evidentWithFiles(1, 'ready');
        $this->bindDrive(fn ($svc) => $svc->shouldNotReceive('uploadToPath'));

        (new ProcessGdriveUpload($f->id, 's.pdf'))->handle();

        Storage::disk('staging')->assertMissing('s.pdf');
    }

    public function test_job_cleans_up_when_file_was_deleted_meanwhile(): void
    {
        Storage::fake('staging');
        Storage::disk('staging')->put('s.pdf', 'x');
        [$e, [$f]] = $this->evidentWithFiles();
        $id = $f->id;
        $e->delete();
        $this->bindDrive(fn ($svc) => $svc->shouldNotReceive('uploadToPath'));

        (new ProcessGdriveUpload($id, 's.pdf'))->handle();

        Storage::disk('staging')->assertMissing('s.pdf');
    }

    public function test_failed_hook_marks_failed_and_flags_reauth(): void
    {
        Storage::fake('staging');
        Storage::disk('staging')->put('s.pdf', 'x');
        [$e, [$f]] = $this->evidentWithFiles();

        (new ProcessGdriveUpload($f->id, 's.pdf'))->failed(GoogleDriveException::reauthRequired());

        $e->refresh();
        $this->assertSame('failed', $e->status);
        $this->assertTrue($e->metadata['reauth_required']);
        $this->assertSame('failed', $f->fresh()->status);
        Storage::disk('staging')->assertMissing('s.pdf');
    }
}

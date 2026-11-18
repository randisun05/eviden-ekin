<?php

namespace Tests\Feature;

use App\Exceptions\GoogleDriveException;
use App\Jobs\ProcessGdriveUpload;
use App\Models\Evident;
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

        $e = app(EvidentUploadService::class)->storeFile($rhk, UploadedFile::fake()->create('lap.pdf', 10, 'application/pdf'), 'Laporan', '1234');

        $this->assertSame('processing', $e->status);
        $this->assertTrue($e->is_protected);
        $this->assertNotSame('1234', $e->getRawOriginal('access_pin')); // hash
        $this->assertCount(1, Storage::disk('staging')->files());
        Queue::assertPushedOn('uploads', ProcessGdriveUpload::class, fn ($j) => $j->evidentId === $e->id);
    }

    public function test_job_uploads_marks_ready_and_deletes_staging(): void
    {
        Storage::fake('staging');
        Storage::disk('staging')->put('s.pdf', 'x');
        $rhk = $this->rhk();
        $e = Evident::create(['rhk_id' => $rhk->id, 'title' => 'T', 'evident_type' => 'file_upload',
            'file_name' => 'a.pdf', 'mime_type' => 'application/pdf']);

        $svc = Mockery::mock(GoogleDriveService::class);
        $svc->shouldReceive('uploadToPath')->once()
            ->with(['2025', 'Triwulan I', 'RHK-01'], Mockery::type('string'), 'a.pdf', 'application/pdf', ['evident_id' => $e->id])
            ->andReturn($this->driveResult());
        $this->app->bind(GoogleDriveService::class, fn () => $svc);

        (new ProcessGdriveUpload($e->id, 's.pdf'))->handle();

        $e->refresh();
        $this->assertSame('ready', $e->status);
        $this->assertSame('FILE1', $e->gdrive_file_id);
        $this->assertSame('FOL', $e->gdrive_folder_id);
        Storage::disk('staging')->assertMissing('s.pdf');
    }

    public function test_job_is_noop_when_already_ready(): void
    {
        Storage::fake('staging');
        Storage::disk('staging')->put('s.pdf', 'x');
        $rhk = $this->rhk();
        $e = Evident::create(['rhk_id' => $rhk->id, 'title' => 'T', 'evident_type' => 'file_upload', 'status' => 'ready']);

        $svc = Mockery::mock(GoogleDriveService::class);
        $svc->shouldNotReceive('uploadToPath');
        $this->app->bind(GoogleDriveService::class, fn () => $svc);

        (new ProcessGdriveUpload($e->id, 's.pdf'))->handle();

        Storage::disk('staging')->assertMissing('s.pdf');
    }

    public function test_failed_hook_marks_failed_and_flags_reauth(): void
    {
        Storage::fake('staging');
        Storage::disk('staging')->put('s.pdf', 'x');
        $rhk = $this->rhk();
        $e = Evident::create(['rhk_id' => $rhk->id, 'title' => 'T', 'evident_type' => 'file_upload']);

        (new ProcessGdriveUpload($e->id, 's.pdf'))->failed(GoogleDriveException::reauthRequired());

        $e->refresh();
        $this->assertSame('failed', $e->status);
        $this->assertTrue($e->metadata['reauth_required']);
        Storage::disk('staging')->assertMissing('s.pdf');
    }
}

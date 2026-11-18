<?php

namespace Tests\Feature;

use App\Models\Evident;
use App\Models\Period;
use App\Models\Rhk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HousekeepingTest extends TestCase
{
    use RefreshDatabase;

    public function test_removes_only_old_staging_files_and_fails_only_old_processing(): void
    {
        Storage::fake('staging');
        Storage::disk('staging')->put('old.pdf', 'x');
        Storage::disk('staging')->put('fresh.pdf', 'x');
        touch(Storage::disk('staging')->path('old.pdf'), now()->subHours(49)->getTimestamp());

        $u = User::create(['name' => 'U', 'email' => 'u@example.com', 'google_id' => 'g']);
        $p = Period::create(['user_id' => $u->id, 'title' => 'T', 'year' => 2025]);
        $r = Rhk::create(['period_id' => $p->id, 'code' => 'R', 'title' => 't']);
        $mk = fn (string $status, $age) => tap(Evident::create(['rhk_id' => $r->id, 'title' => $status.$age, 'evident_type' => 'file_upload', 'status' => $status]),
            fn ($e) => $e->forceFill(['created_at' => now()->subHours($age)])->saveQuietly());
        $oldProc = $mk('processing', 50);
        $newProc = $mk('processing', 1);
        $oldReady = $mk('ready', 100);

        $this->artisan('evident:housekeeping --dry-run')->assertSuccessful();
        Storage::disk('staging')->assertExists('old.pdf');
        $this->assertSame('processing', $oldProc->fresh()->status);

        $this->artisan('evident:housekeeping')->assertSuccessful();

        Storage::disk('staging')->assertMissing('old.pdf');
        Storage::disk('staging')->assertExists('fresh.pdf');
        $this->assertSame('failed', $oldProc->fresh()->status);
        $this->assertSame('processing', $newProc->fresh()->status);
        $this->assertSame('ready', $oldReady->fresh()->status);
    }
}

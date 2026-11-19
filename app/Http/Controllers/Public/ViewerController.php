<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Evident;
use App\Models\Rhk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Landing page publik (tanpa login) untuk penilai/verifikator.
 *  - kode 10 karakter = bundling satu RHK
 *  - kode 8 karakter  = satu bukti
 *
 * PIN & masa berlaku melindungi HALAMAN ini. Berkas di Drive tetap "anyone with the link"
 * (sesuai desain), jadi PIN bukan pengganti kontrol akses Drive.
 */
class ViewerController extends Controller
{
    public function show(Request $request, string $code): Response
    {
        $target = $this->resolve($code);

        if ($target->isExpired()) {
            return Inertia::render('Public/Unavailable', ['reason' => 'expired'])
                ->toResponse($request)->setStatusCode(410);
        }

        if ($target->is_protected && ! $this->isUnlocked($request, $target)) {
            return Inertia::render('Public/Pin', ['code' => $code])->toResponse($request);
        }

        return Inertia::render('Public/Viewer', $this->payload($target))->toResponse($request);
    }

    public function unlock(Request $request, string $code): RedirectResponse
    {
        $target = $this->resolve($code);
        abort_if($target->isExpired(), 410);

        if (! $target->is_protected) {
            return redirect()->route('viewer.show', $code);
        }

        $request->validate(['pin' => ['required', 'digits_between:4,8']], [
            'pin.required' => 'PIN wajib diisi.',
            'pin.digits_between' => 'PIN terdiri dari 4–8 angka.',
        ]);

        // Dua lapis: per (target, IP) dan per target dari semua IP (menahan tebakan terdistribusi).
        $ipKey = 'viewer-pin:'.$target->getTable().$target->id.'|'.$request->ip();
        $targetKey = 'viewer-pin-all:'.$target->getTable().$target->id;

        $max = config('evident.pin_max_attempts');
        $maxAll = config('evident.pin_target_max_attempts');
        $blockedIp = RateLimiter::tooManyAttempts($ipKey, $max);
        $blockedAll = RateLimiter::tooManyAttempts($targetKey, $maxAll);
        if ($blockedIp || $blockedAll) {
            // Waktu tunggu hanya dari kunci yang benar-benar memblokir.
            $wait = max($blockedIp ? RateLimiter::availableIn($ipKey) : 0, $blockedAll ? RateLimiter::availableIn($targetKey) : 0);

            return back()->withErrors(['pin' => 'Terlalu banyak percobaan. Coba lagi dalam '.ceil($wait / 60).' menit.']);
        }

        if (! Hash::check($request->input('pin'), (string) $target->access_pin)) {
            RateLimiter::hit($ipKey, config('evident.pin_decay_seconds'));
            RateLimiter::hit($targetKey, 3600);

            return back()->withErrors(['pin' => 'PIN salah.']);
        }

        RateLimiter::clear($ipKey);
        $request->session()->regenerate();
        $request->session()->put($this->unlockKey($target), $this->fingerprint($target));

        return redirect()->route('viewer.show', $code);
    }

    private function resolve(string $code): Rhk|Evident
    {
        $target = strlen($code) === 10
            ? Rhk::with('period.user')->where('app_short_code', $code)->first()
            : Evident::with('rhk.period.user', 'files')->where('app_short_code', $code)->first();

        abort_if($target === null, 404);
        // Bukti yang belum selesai diunggah tidak boleh terlihat.
        abort_if($target instanceof Evident && $target->status !== 'ready', 404);

        return $target;
    }

    private function unlockKey(Rhk|Evident $t): string
    {
        return 'viewer_unlocked.'.$t->getTable().'.'.$t->id;
    }

    /** Sidik jari hash PIN: mengganti PIN otomatis mencabut akses yang sudah dibuka. */
    private function fingerprint(Rhk|Evident $t): string
    {
        return hash('sha256', (string) $t->access_pin);
    }

    private function isUnlocked(Request $request, Rhk|Evident $t): bool
    {
        return hash_equals($this->fingerprint($t), (string) $request->session()->get($this->unlockKey($t), ''));
    }

    private function payload(Rhk|Evident $target): array
    {
        $rhk = $target instanceof Rhk ? $target : $target->rhk;
        $evidents = $target instanceof Rhk
            ? $target->evidents()->with('files')->where('status', 'ready')->oldest()->get()
            : collect([$target]);

        return [
            'mode' => $target instanceof Rhk ? 'bundle' : 'single',
            'owner' => $rhk->period->user->name,
            'period' => $rhk->period->title.' '.$rhk->period->year,
            'rhk' => $rhk->only(['code', 'title', 'target_description']),
            'expires_at' => $target->expires_at?->toIso8601String(),
            'items' => $evidents->map(fn (Evident $e) => $this->item($e))->values(),
        ];
    }

    private function item(Evident $e): array
    {
        if ($e->evident_type === 'external_link') {
            return ['id' => $e->id, 'title' => $e->title, 'kind' => 'link', 'open_url' => $e->external_url];
        }

        $files = $e->files->where('status', 'ready')->map(function ($f) {
            $id = rawurlencode((string) $f->gdrive_file_id);
            $mime = (string) $f->mime_type;
            $kind = $mime === 'application/pdf' ? 'pdf' : (str_starts_with($mime, 'image/') ? 'image' : 'file');

            return [
                'id' => $f->id, 'kind' => $kind,
                'file_name' => $f->file_name, 'file_size' => $f->file_size,
                'preview_url' => in_array($kind, ['pdf', 'image']) ? "https://drive.google.com/file/d/{$id}/preview" : null,
                'download_url' => "https://drive.google.com/uc?export=download&id={$id}",
                'open_url' => $f->gdrive_web_view_link,
            ];
        })->values()->all();

        return ['id' => $e->id, 'title' => $e->title, 'kind' => 'file', 'files' => $files];
    }
}

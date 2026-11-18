<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Google\GoogleDriveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')
            ->scopes(GoogleDriveService::SCOPES)
            // offline + consent => Google selalu mengirim refresh_token.
            ->with(['access_type' => 'offline', 'prompt' => 'consent', 'include_granted_scopes' => 'true'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) { // user menekan "Batal"
            return redirect()->route('login')->with('error', 'Login Google dibatalkan.');
        }

        try {
            $g = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('login')->with('error', 'Login Google gagal. Silakan coba lagi.');
        }

        // Izin granular: user bisa menghapus centang akses Drive di layar persetujuan.
        $granted = $g->approvedScopes ?? [];
        $needed = collect(GoogleDriveService::SCOPES);
        if ($needed->diff($granted)->isNotEmpty()) {
            return redirect()->route('login')
                ->with('error', 'Akses Google Drive wajib disetujui agar bukti kerja dapat disimpan.');
        }

        // Cocokkan google_id dulu; fallback email (akun yang sama) lalu buat baru.
        $user = User::where('google_id', $g->getId())->first()
            ?? User::where('email', $g->getEmail())->first()
            ?? new User();

        $user->fill([
            'name' => $g->getName() ?: $g->getEmail(),
            'email' => $g->getEmail(),
            'google_id' => $g->getId(),
            'google_access_token' => $g->token,
            // Pertahankan refresh token lama bila Google tidak mengirim yang baru.
            'google_refresh_token' => $g->refreshToken ?: $user->google_refresh_token,
            'token_expires_at' => now()->addSeconds((int) ($g->expiresIn ?: 3600)),
        ])->save();

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended('/dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

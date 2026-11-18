<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Pastikan user punya refresh token Google (mis. belum dicabut) sebelum memakai fitur Drive. */
class EnsureDriveConnected
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->google_refresh_token) {
            return redirect()->route('auth.google')
                ->with('error', 'Hubungkan Google Drive Anda terlebih dahulu.');
        }

        return $next($request);
    }
}

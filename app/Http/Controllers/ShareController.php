<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateShareRequest;
use App\Models\Evident;
use App\Models\Rhk;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/** Pengaturan tautan bagikan: PIN, masa berlaku, dan pencabutan (kode baru) untuk RHK maupun bukti. */
class ShareController extends Controller
{
    public function updateRhk(UpdateShareRequest $request, Rhk $rhk): RedirectResponse
    {
        Gate::authorize('update', $rhk);
        $this->apply($request, $rhk);

        return back()->with('success', 'Pengaturan tautan disimpan.');
    }

    public function updateEvident(UpdateShareRequest $request, Evident $evident): RedirectResponse
    {
        Gate::authorize('update', $evident);
        $this->apply($request, $evident);

        return back()->with('success', 'Pengaturan tautan disimpan.');
    }

    /** Cabut tautan lama (mis. bocor) dengan menerbitkan kode baru. */
    public function regenerateRhk(Rhk $rhk): RedirectResponse
    {
        Gate::authorize('update', $rhk);
        $rhk->update(['app_short_code' => Rhk::generateShortCode()]);

        return back()->with('success', 'Tautan baru diterbitkan. Tautan lama tidak berlaku lagi.');
    }

    public function regenerateEvident(Evident $evident): RedirectResponse
    {
        Gate::authorize('update', $evident);
        $evident->update(['app_short_code' => Evident::generateShortCode()]);

        return back()->with('success', 'Tautan baru diterbitkan. Tautan lama tidak berlaku lagi.');
    }

    private function apply(UpdateShareRequest $request, Rhk|Evident $model): void
    {
        $changes = [];

        if ($request->boolean('remove_pin')) {
            $changes += ['access_pin' => null, 'is_protected' => false];
        } elseif ($request->filled('pin')) {
            $changes += ['access_pin' => $request->input('pin'), 'is_protected' => true];
        }

        if ($request->exists('expires_at')) {
            $changes['expires_at'] = $request->filled('expires_at')
                ? Carbon::createFromFormat('Y-m-d', $request->input('expires_at'))->endOfDay()
                : null;
        }

        $model->update($changes);
    }
}

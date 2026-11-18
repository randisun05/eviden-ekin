<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRhkRequest;
use App\Jobs\DeleteGdriveFiles;
use App\Models\Period;
use App\Models\Rhk;
use App\Services\Evident\LinkGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RhkController extends Controller
{
    public function store(StoreRhkRequest $request, Period $period): RedirectResponse
    {
        Gate::authorize('update', $period);
        $rhk = $period->rhks()->create($request->validated());

        return redirect()->route('rhks.show', $rhk)->with('success', 'RHK ditambahkan.');
    }

    public function show(Rhk $rhk, LinkGenerator $links): Response
    {
        Gate::authorize('view', $rhk);
        $rhk->load('period');

        return Inertia::render('Rhks/Show', [
            'rhk' => $rhk->only(['id', 'code', 'title', 'target_description']),
            'share' => [
                'url' => $links->forRhk($rhk),
                'is_protected' => $rhk->is_protected,
                'expires_at' => $rhk->expires_at?->format('Y-m-d'),
                'expired' => $rhk->isExpired(),
            ],
            'period' => $rhk->period->only(['id', 'title', 'year']),
            // Di-reload berkala (polling) selama ada yang 'processing'.
            'evidents' => fn () => $this->evidents($rhk, $links),
            'maxUploadMb' => config('evident.max_upload_mb'),
            'allowedExtensions' => config('evident.allowed_extensions'),
        ]);
    }

    private function evidents(Rhk $rhk, LinkGenerator $links): array
    {
        return $rhk->evidents()->latest()->get()->map(fn ($e) => [
            'id' => $e->id, 'title' => $e->title, 'type' => $e->evident_type,
            'file_name' => $e->file_name, 'file_size' => $e->file_size, 'status' => $e->status,
            'error' => $e->status === 'failed' ? ($e->metadata['error'] ?? null) : null,
            'reauth_required' => (bool) ($e->metadata['reauth_required'] ?? false),
            'is_protected' => $e->is_protected,
            'links' => $links->forEvident($e),
            'expires_at' => $e->expires_at?->format('Y-m-d'),
            'expired' => $e->isExpired(),
            'drive_link' => $e->gdrive_web_view_link, 'external_url' => $e->external_url,
            'created_at' => $e->created_at->toIso8601String(),
        ])->all();
    }

    public function update(StoreRhkRequest $request, Rhk $rhk): RedirectResponse
    {
        Gate::authorize('update', $rhk);
        $rhk->update($request->validated());

        return back()->with('success', 'RHK diperbarui.');
    }

    public function destroy(Rhk $rhk): RedirectResponse
    {
        Gate::authorize('delete', $rhk);
        $rhk->load('period');

        $fileIds = $rhk->evidents()->whereNotNull('gdrive_file_id')->pluck('gdrive_file_id')->all();
        $periodId = $rhk->period_id;
        $userId = $rhk->period->user_id;
        $rhk->delete();
        DeleteGdriveFiles::dispatchIf($fileIds !== [], $userId, $fileIds);

        return redirect()->route('periods.show', $periodId)->with('success', 'RHK dihapus.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEvidentRequest;
use App\Jobs\DeleteGdriveFiles;
use App\Models\Evident;
use App\Models\Rhk;
use App\Services\Evident\EvidentUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class EvidentController extends Controller
{
    public function store(StoreEvidentRequest $request, Rhk $rhk, EvidentUploadService $uploads): RedirectResponse
    {
        Gate::authorize('update', $rhk);

        if ($request->validated('evident_type') === 'file_upload') {
            $uploads->storeFiles($rhk, array_values($request->file('files')), $request->validated('title'), $request->validated('description'), $request->validated('pin'));

            return back()->with('success', 'Berkas diterima dan sedang diunggah ke Google Drive.');
        }

        $uploads->storeExternalLink($rhk, $request->validated('title'), $request->validated('description'), $request->validated('external_url'), $request->validated('pin'));

        return back()->with('success', 'Tautan eksternal ditambahkan.');
    }

    public function destroy(Evident $evident): RedirectResponse
    {
        Gate::authorize('delete', $evident);
        $evident->load('rhk.period');

        $fileIds = $evident->files()->whereNotNull('gdrive_file_id')->pluck('gdrive_file_id')->all();
        $userId = $evident->rhk->period->user_id;
        $evident->delete();
        DeleteGdriveFiles::dispatchIf($fileIds !== [], $userId, $fileIds);

        return back()->with('success', 'Bukti dihapus.');
    }
}

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
            $uploads->storeFile($rhk, $request->file('file'), $request->validated('title'), $request->validated('pin'));

            return back()->with('success', 'Berkas diterima dan sedang diunggah ke Google Drive.');
        }

        $uploads->storeExternalLink($rhk, $request->validated('title'), $request->validated('external_url'), $request->validated('pin'));

        return back()->with('success', 'Tautan eksternal ditambahkan.');
    }

    public function destroy(Evident $evident): RedirectResponse
    {
        Gate::authorize('delete', $evident);
        $evident->load('rhk.period');

        $fileId = $evident->gdrive_file_id;
        $userId = $evident->rhk->period->user_id;
        $evident->delete();
        DeleteGdriveFiles::dispatchIf($fileId !== null, $userId, [$fileId]);

        return back()->with('success', 'Bukti dihapus.');
    }
}

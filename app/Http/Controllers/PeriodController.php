<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePeriodRequest;
use App\Jobs\DeleteGdriveFiles;
use App\Models\Evident;
use App\Models\Period;
use App\Services\Evident\ReadinessChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PeriodController extends Controller
{
    public function index(Request $request, ReadinessChecker $readiness): Response
    {
        $periods = $request->user()->periods()
            ->with('rhks.evidents:id,rhk_id,status')
            ->orderByDesc('year')->orderByDesc('created_at')->get();

        return Inertia::render('Periods/Index', [
            'periods' => $periods->map(fn (Period $p) => [
                'id' => $p->id, 'title' => $p->title, 'year' => $p->year, 'status' => $p->status,
                'readiness' => $readiness->forPeriod($p),
            ])->values(),
        ]);
    }

    public function store(StorePeriodRequest $request): RedirectResponse
    {
        $period = $request->user()->periods()->create($request->safe()->only(['title', 'year']));

        return redirect()->route('periods.show', $period)->with('success', 'Periode dibuat.');
    }

    public function show(Period $period, ReadinessChecker $readiness): Response
    {
        Gate::authorize('view', $period);
        $period->load('rhks.evidents:id,rhk_id,status');

        return Inertia::render('Periods/Show', [
            'period' => $period->only(['id', 'title', 'year', 'status']) + ['readiness' => $readiness->forPeriod($period)],
            'rhks' => $period->rhks->sortBy('code', SORT_NATURAL)->map(fn ($r) => [
                'id' => $r->id, 'code' => $r->code, 'title' => $r->title,
                'readiness' => $readiness->forRhk($r),
            ])->values(),
        ]);
    }

    public function update(StorePeriodRequest $request, Period $period): RedirectResponse
    {
        Gate::authorize('update', $period);
        $period->update($request->validated());

        return back()->with('success', 'Periode diperbarui.');
    }

    public function destroy(Period $period): RedirectResponse
    {
        Gate::authorize('delete', $period);

        $fileIds = Evident::whereIn('rhk_id', $period->rhks()->select('id'))
            ->whereNotNull('gdrive_file_id')->pluck('gdrive_file_id')->all();
        $period->delete(); // cascade ke rhks & evidents
        DeleteGdriveFiles::dispatchIf($fileIds !== [], $period->user_id, $fileIds);

        return redirect()->route('periods.index')->with('success', 'Periode dihapus.');
    }
}

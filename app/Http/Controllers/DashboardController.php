<?php

namespace App\Http\Controllers;

use App\Models\Evident;
use App\Models\Period;
use App\Models\Rhk;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $userId = $request->user()->id;
        $periods = Period::where('user_id', $userId);
        $rhks = Rhk::whereIn('period_id', (clone $periods)->select('id'));
        $byStatus = Evident::whereIn('rhk_id', (clone $rhks)->select('id'))
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('Dashboard', [
            'stats' => [
                'periods' => $periods->count(),
                'rhks' => $rhks->count(),
                'evidents' => [
                    'ready' => (int) ($byStatus['ready'] ?? 0),
                    'processing' => (int) ($byStatus['processing'] ?? 0),
                    'failed' => (int) ($byStatus['failed'] ?? 0),
                ],
            ],
        ]);
    }
}

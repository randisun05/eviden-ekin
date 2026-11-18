<?php

namespace App\Services\Evident;

use App\Models\Period;
use App\Models\Rhk;

/**
 * Checklist kelengkapan. Mengharapkan relasi evidents(id,rhk_id,status) sudah di-eager-load.
 *
 * Status RHK: empty (belum ada bukti) | processing | attention (ada yang gagal) | ready.
 */
class ReadinessChecker
{
    /** @return array{state:string,ready:int,processing:int,failed:int,total:int} */
    public function forRhk(Rhk $rhk): array
    {
        $by = $rhk->evidents->countBy('status');
        $ready = $by['ready'] ?? 0;
        $processing = $by['processing'] ?? 0;
        $failed = $by['failed'] ?? 0;
        $total = $rhk->evidents->count();

        $state = match (true) {
            $total === 0 => 'empty',
            $failed > 0 => 'attention',
            $processing > 0 => 'processing',
            default => 'ready',
        };

        return compact('state', 'ready', 'processing', 'failed', 'total');
    }

    /** @return array{rhk_total:int,rhk_ready:int,percent:int} */
    public function forPeriod(Period $period): array
    {
        $total = $period->rhks->count();
        $ready = $period->rhks->filter(fn (Rhk $r) => $this->forRhk($r)['state'] === 'ready')->count();

        return [
            'rhk_total' => $total,
            'rhk_ready' => $ready,
            'percent' => $total ? (int) round($ready / $total * 100) : 0,
        ];
    }
}

<?php

namespace App\Console\Commands;

use App\Services\CompletedLiveOrderCleanup;
use Illuminate\Console\Command;

class PurgeCompletedLiveOrders extends Command
{
    protected $signature = 'orders:purge-completed {--minutes= : Dakikadan eski tamamlanan adisyonları sil}';

    protected $description = 'Canlı paneldeki eski tamamlanan adisyonları listeden gizler (admin arşivinde kalır)';

    public function handle(CompletedLiveOrderCleanup $cleanup): int
    {
        $minutes = $this->option('minutes');
        $minutes = $minutes !== null
            ? (int) $minutes
            : (int) config('live_orders.completed_retention_minutes', 120);

        if ($minutes <= 0) {
            $this->warn('Otomatik silme kapalı (LIVE_ORDERS_COMPLETED_RETENTION_MINUTES=0).');

            return self::SUCCESS;
        }

        $count = $cleanup->purgeOlderThan($minutes);
        $this->info("Canlı listeden gizlendi: {$count} adisyon ({$minutes} dakikadan eski).");

        return self::SUCCESS;
    }
}

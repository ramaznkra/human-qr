<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

class ArchiveStaleOrders extends Command
{
    protected $signature = 'orders:archive-stale {--hours=3 : Saatten eski aktif siparişleri arşivle}';

    protected $description = '3 saatten eski bekleyen/hazırlanan siparişleri iptal ederek canlı ekranı temizler';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $cutoff = now()->subHours($hours);

        $count = Order::query()
            ->live()
            ->where('created_at', '<', $cutoff)
            ->update(['status' => Order::STATUS_CANCELLED]);

        $this->info("Arşivlendi: {$count} sipariş ({$hours} saatten eski, durum: iptal).");

        return self::SUCCESS;
    }
}

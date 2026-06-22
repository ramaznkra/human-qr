<?php

namespace App\Console\Commands;

use App\Models\Table;
use App\Services\TableQrCodeService;
use Illuminate\Console\Command;

class RegenerateTableQrCodes extends Command
{
    protected $signature = 'tables:regenerate-qr';

    protected $description = 'Tüm masalar için QR kodlarını (SVG/PNG) yeniden üretir';

    public function handle(TableQrCodeService $qr): int
    {
        $tables = Table::all();
        $bar = $this->output->createProgressBar($tables->count());

        foreach ($tables as $table) {
            $qr->generateFor($table);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("{$tables->count()} masa için QR oluşturuldu.");

        return self::SUCCESS;
    }
}

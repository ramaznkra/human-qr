<?php

namespace App\Services\Pos;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Token / Beko POS mock istemcisi.
 * Gerçek cihaz URL'si POS_API_URL ile .env üzerinden verilir.
 */
class BekoPosClient
{
    public function initiatePayment(int $tableId, int $orderId, string $amount): string
    {
        $reference = 'POS-'.Str::upper(Str::random(10));

        Cache::put($this->cacheKey($reference), [
            'table_id' => $tableId,
            'order_id' => $orderId,
            'amount' => $amount,
            'status' => 'processing',
        ], now()->addMinutes(30));

        $url = config('services.pos.url');

        if ($url) {
            Http::timeout(5)->post($url, [
                'reference' => $reference,
                'table_id' => $tableId,
                'order_id' => $orderId,
                'amount' => $amount,
            ]);
        }

        return $reference;
    }

    /**
     * @return array{table_id: int, order_id: int, amount: string, status: string}|null
     */
    public function resolveReference(string $reference): ?array
    {
        return Cache::get($this->cacheKey($reference));
    }

    public function markSuccess(string $reference): void
    {
        $data = Cache::get($this->cacheKey($reference));

        if ($data) {
            Cache::put($this->cacheKey($reference), array_merge($data, ['status' => 'success']), now()->addMinutes(30));
        }
    }

    private function cacheKey(string $reference): string
    {
        return 'pos_payment:'.$reference;
    }
}

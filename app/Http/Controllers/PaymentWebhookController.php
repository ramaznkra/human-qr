<?php

namespace App\Http\Controllers;

use App\Services\KasaPanelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentWebhookController extends Controller
{
    public function posWebhook(Request $request, KasaPanelService $kasa): JsonResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:64'],
            'status' => ['required', 'in:success,failed'],
        ]);

        if ($data['status'] !== 'success') {
            return response()->json(['success' => false, 'message' => 'Ödeme başarısız.'], 422);
        }

        try {
            $payload = $kasa->completePosPayment($data['reference']);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Kart ödemesi tamamlandı · masa boşaltıldı.',
            ...$payload,
        ]);
    }
}

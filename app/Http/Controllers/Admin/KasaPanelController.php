<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\KasaPanelService;
use App\Services\DeliveryTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class KasaPanelController extends Controller
{
    public function selectTable(Request $request, KasaPanelService $kasa): JsonResponse
    {
        $data = $request->validate([
            'table_id' => ['required', 'integer', 'exists:tables,id'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
        ]);

        return response()->json([
            'success' => true,
            ...$kasa->selectTable((int) $data['table_id'], isset($data['order_id']) ? (int) $data['order_id'] : null),
        ]);
    }

    public function tableState(Request $request, KasaPanelService $kasa): JsonResponse
    {
        $data = $request->validate([
            'table_id' => ['required', 'integer', 'exists:tables,id'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
        ]);

        return response()->json([
            'success' => true,
            ...$kasa->selectTable((int) $data['table_id'], isset($data['order_id']) ? (int) $data['order_id'] : null),
        ]);
    }

    public function addItem(Request $request, KasaPanelService $kasa): JsonResponse
    {
        $data = $request->validate([
            'table_id' => ['required', 'integer', 'exists:tables,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'options' => 'nullable|array',
            'options.*.group_id' => 'required_with:options|integer',
            'options.*.option_id' => 'required_with:options|integer',
        ]);

        try {
            $payload = $kasa->addItem(
                (int) $data['table_id'],
                (int) $data['product_id'],
                $data['options'] ?? [],
                isset($data['order_id']) ? (int) $data['order_id'] : null,
            );
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Ürün adisyona eklenemedi. Lütfen tekrar deneyin.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            ...$payload,
        ]);
    }

    public function notifyWaiter(Request $request, KasaPanelService $kasa): JsonResponse
    {
        $data = $request->validate([
            'table_id' => ['required', 'integer', 'exists:tables,id'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
        ]);

        try {
            $payload = $kasa->notifyWaiter(
                (int) $data['table_id'],
                isset($data['order_id']) ? (int) $data['order_id'] : null,
            );
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Garsona bildirildi · masa servisi bekleniyor.',
            ...$payload,
        ]);
    }

    public function updateOrderItem(Request $request, KasaPanelService $kasa): JsonResponse
    {
        $data = $request->validate([
            'table_id' => ['required', 'integer', 'exists:tables,id'],
            'item_id' => ['required', 'integer', 'exists:order_items,id'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'quantity' => ['nullable', 'integer', 'min:0', 'max:99'],
            'remove' => ['nullable', 'boolean'],
        ]);

        try {
            $payload = $kasa->updateOrderItem(
                (int) $data['table_id'],
                (int) $data['item_id'],
                isset($data['quantity']) ? (int) $data['quantity'] : null,
                (bool) ($data['remove'] ?? false),
                isset($data['order_id']) ? (int) $data['order_id'] : null,
            );
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            ...$payload,
        ]);
    }

    public function resumeOrder(Request $request, KasaPanelService $kasa): JsonResponse
    {
        $data = $request->validate([
            'table_id' => ['required', 'integer', 'exists:tables,id'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
        ]);

        try {
            $payload = $kasa->resumeOrdering(
                (int) $data['table_id'],
                isset($data['order_id']) ? (int) $data['order_id'] : null,
            );
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            ...$payload,
        ]);
    }

    public function approveOrder(Request $request, KasaPanelService $kasa): JsonResponse
    {
        $data = $request->validate([
            'table_id' => ['required', 'integer', 'exists:tables,id'],
        ]);

        try {
            $payload = $kasa->approveOrder((int) $data['table_id']);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Sipariş onaylandı · mutfağa iletildi.',
            ...$payload,
        ]);
    }

    public function payWithCash(Request $request, KasaPanelService $kasa): JsonResponse
    {
        $data = $request->validate([
            'table_id' => ['required', 'integer', 'exists:tables,id'],
        ]);

        try {
            $payload = $kasa->payWithCash((int) $data['table_id'], $this->idempotencyKey($request));
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ödeme Başarılı · masa boşaltıldı.',
            ...$payload,
        ]);
    }

    public function payWithManualCard(Request $request, KasaPanelService $kasa): JsonResponse
    {
        $data = $request->validate([
            'table_id' => ['required', 'integer', 'exists:tables,id'],
        ]);

        try {
            $payload = $kasa->payWithManualCard((int) $data['table_id'], $this->idempotencyKey($request));
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Manuel Kart Ödemesi Başarılı · masa boşaltıldı.',
            ...$payload,
        ]);
    }

    public function payWithPos(Request $request, KasaPanelService $kasa): JsonResponse
    {
        $data = $request->validate([
            'table_id' => ['required', 'integer', 'exists:tables,id'],
        ]);

        try {
            $payload = $kasa->payWithPos((int) $data['table_id'], $this->idempotencyKey($request));
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            ...$payload,
        ]);
    }

    public function updateItemPreparationStatus(Request $request, DeliveryTaskService $tasks): JsonResponse
    {
        $data = $request->validate([
            'item_id' => ['required', 'integer', 'exists:order_items,id'],
            'status' => ['required', 'in:preparing,ready'],
        ]);

        try {
            $payload = $tasks->markItemStatus((int) $data['item_id'], (string) $data['status']);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $data['status'] === 'ready' ? 'Ürün hazır olarak işaretlendi.' : 'Ürün hazırlanıyor olarak işaretlendi.',
            ...$payload,
        ]);
    }

    private function idempotencyKey(Request $request): ?string
    {
        $key = $request->header('X-Idempotency-Key') ?: $request->input('idempotency_key');

        return is_string($key) && $key !== '' ? $key : null;
    }
}

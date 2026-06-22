<?php

namespace App\Http\Controllers;

use App\Models\Table;
use App\Services\MenuCatalogService;
use App\Support\MenuLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function __construct(
        private readonly MenuCatalogService $catalog,
    ) {}

    /** Genel menü (masa bağlamı yok). */
    public function index(Request $request): View
    {
        return $this->renderMenu($request, null);
    }

    /** Masa QR menüsü — URL: /table/{uuid} */
    public function table(Request $request, string $uuid): View
    {
        return $this->renderMenu($request, $uuid);
    }

    /** Eski /menu/{token} linklerini /table/{uuid} adresine yönlendir. */
    public function legacyTableRedirect(Request $request, string $token, ?string $restaurant = null): RedirectResponse
    {
        $table = Table::withoutGlobalScopes()
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('uuid', $token)->orWhere('qr_token', $token))
            ->first();

        if (! $table?->uuid) {
            abort(404);
        }

        $query = $request->query();
        $target = $restaurant
            ? route('menu.restaurant.table', ['restaurant' => $restaurant, 'uuid' => $table->uuid], false)
            : route('menu.table', ['uuid' => $table->uuid], false);

        if ($query !== []) {
            $target .= '?'.http_build_query($query);
        }

        return redirect()->to($target, 301);
    }

    private function renderMenu(Request $request, ?string $tableKey): View
    {
        $locale = MenuLocale::resolveForMenuPage($request);
        MenuLocale::apply($request, $locale);

        $table = null;

        if ($tableKey) {
            $table = Table::query()
                ->where('is_active', true)
                ->where(fn ($q) => $q->where('uuid', $tableKey)->orWhere('qr_token', $tableKey))
                ->first();

            if (! $table) {
                abort(404, 'Masa bulunamadı.');
            }
        }

        $catalog = $this->catalog->load($locale);

        return view('menu.index', [
            'categories' => $catalog['categories'],
            'settings' => $catalog['settings'],
            'productPopularity' => $catalog['productPopularity'],
            'table' => $table,
            'locale' => $locale,
            'spottedSliders' => \App\Models\CafeGallery::active()->get(),
        ]);
    }
}

<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Table;
use App\Models\TableCall;
use App\Models\User;
use App\Services\Pos\DisabledPosGateway;
use App\Services\Pos\FakePosGateway;
use App\Services\Pos\PosGateway;
use App\Support\CurrentRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PosGateway::class, function ($app) {
            $driver = strtolower((string) config('pos.driver', 'disabled'));

            if ($app->environment('production') && (bool) config('pos.auto_complete', false)) {
                return new DisabledPosGateway;
            }

            if ($driver === 'fake' && ! $app->environment(['local', 'testing'])) {
                return new DisabledPosGateway;
            }

            return match ($driver) {
                'fake' => new FakePosGateway($app),
                default => new DisabledPosGateway,
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureViteForLanClients();
        $this->registerTenantRouteBindings();

        Route::bind('waiter', function (string $value) {
            return User::query()
                ->whereKey($value)
                ->whereIn('role', User::MANAGEABLE_ROLES)
                ->firstOrFail();
        });

        View::composer('layouts.admin', function ($view) {
            $view->with('settings', Setting::allCached());
            $view->with('staffIsAdmin', session('admin_role', User::ROLE_ADMIN) === User::ROLE_ADMIN);
            $view->with('staffIsCashier', session('admin_role') === User::ROLE_CASHIER);
        });

        View::composer('admin.partials.staff-premium-topbar', function ($view) {
            $view->with('settings', Setting::allCached());
        });

        View::composer('admin.partials.sidebar', function ($view) {
            $view->with('staffIsAdmin', session('admin_role', User::ROLE_ADMIN) === User::ROLE_ADMIN);
            $view->with('staffIsCashier', session('admin_role') === User::ROLE_CASHIER);
        });

        View::composer([
            'layouts.waiter',
            'layouts.menu',
            'layouts.bar',
            'admin.auth.login',
        ], function ($view) {
            $view->with('settings', Setting::allCached());
        });
    }

    private function configureViteForLanClients(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        $host = request()->getHost();
        $isLocalHost = in_array($host, ['localhost', '127.0.0.1', '[::1]'], true);

        if (! $isLocalHost) {
            // LAN / telefon: Vite dev (127.0.0.1:5173) erişilemez; build kullan.
            Vite::useHotFile(storage_path('framework/vite-hot-disabled'));

            return;
        }

        // localhost ama hot dosyası LAN IP içeriyorsa JS yüklenmez → build'e düş.
        $hotPath = public_path('hot');
        if (is_file($hotPath)) {
            $hotUrl = trim((string) file_get_contents($hotPath));
            if ($hotUrl !== ''
                && ! str_contains($hotUrl, '127.0.0.1')
                && ! str_contains($hotUrl, 'localhost')) {
                Vite::useHotFile(storage_path('framework/vite-hot-disabled'));
            }
        }
    }

    /**
     *
     * @return list<class-string<Model>>
     */
    private function tenantModels(): array
    {
        return [
            Product::class,
            Category::class,
            Order::class,
            Table::class,
            TableCall::class,
            User::class,
        ];
    }

    private function registerTenantRouteBindings(): void
    {
        foreach ($this->tenantModels() as $modelClass) {
            $parameter = Str::camel(class_basename($modelClass));

            Route::bind($parameter, function (string $value) use ($modelClass) {
                /** @var Model $modelClass */
                return $modelClass::query()->whereKey($value)->firstOrFail();
            });
        }
    }
}

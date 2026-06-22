<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DisplaySlideController;
use App\Http\Controllers\Admin\CafeGalleryController;
use App\Http\Controllers\Admin\BarScreenController;
use App\Http\Controllers\Admin\KasaPanelController;
use App\Http\Controllers\Admin\LiveOrdersController;
use App\Http\Controllers\Admin\ManualOrderController;
use App\Http\Controllers\Admin\MenuHierarchyController;
use App\Http\Controllers\Admin\OperationsController;
use App\Http\Controllers\TableCallController;
use App\Http\Controllers\Admin\OrderAdminController;
use App\Http\Controllers\Admin\OrderArchiveController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TableController;
use App\Http\Controllers\DisplayController;
use App\Http\Controllers\KitchenController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\Waiter\WaiterDashboardController;
use App\Http\Controllers\Admin\WaiterController;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\AdminOnlyMiddleware;
use App\Http\Middleware\AuthenticateKitchenScreen;
use App\Http\Middleware\ResolveRestaurantFromTable;
use App\Http\Middleware\SetStaffRestaurantContext;
use App\Http\Middleware\WaiterOnlyMiddleware;
use Illuminate\Support\Facades\Route;

Route::get('/favicon.ico', fn () => redirect(\App\Support\SiteBranding::faviconPngUrl(), 302));

Route::get('/', fn () => redirect()->route('menu.index'));

Route::middleware(ResolveRestaurantFromTable::class)->group(function () {
    Route::get('/menu', [MenuController::class, 'index'])->name('menu.index');
    Route::get('/table/{uuid}', [MenuController::class, 'table'])->name('menu.table');
    Route::get('/menu/{token}', [MenuController::class, 'legacyTableRedirect'])->name('menu.legacy');
    Route::get('/r/{restaurant}/menu', [MenuController::class, 'index'])->name('menu.restaurant');
    Route::get('/r/{restaurant}/table/{uuid}', [MenuController::class, 'table'])->name('menu.restaurant.table');
    Route::get('/r/{restaurant}/menu/{token}', [MenuController::class, 'legacyTableRedirect'])->name('menu.restaurant.legacy');
    Route::post('/siparis', [OrderController::class, 'store'])->name('order.store');
    Route::get('/siparis/{orderToken}/durum', [OrderController::class, 'status'])->name('order.status');
    Route::get('/api/siparis/{orderToken}/durum', [OrderController::class, 'statusApi'])->name('order.status.api');
    Route::post('/api/table/call', [TableCallController::class, 'store'])->name('table.call.api');
    Route::get('/api/table/call/status', [TableCallController::class, 'status'])->name('table.call.status');
    Route::post('/api/pos/webhook', [PaymentWebhookController::class, 'posWebhook'])->name('pos.webhook');
});

Route::middleware(AuthenticateKitchenScreen::class)->group(function () {
    Route::get('/ekran', [DisplayController::class, 'index'])->name('display.index');
    Route::get('/api/ekran', [DisplayController::class, 'api'])->name('display.api');

    Route::get('/mutfak', [LiveOrdersController::class, 'screen'])->name('kitchen.index');
    Route::get('/api/admin/live-orders', [LiveOrdersController::class, 'liveOrders'])->name('live-orders.api');
    Route::delete('/api/admin/live-orders/completed', [LiveOrdersController::class, 'dismissAllCompleted'])->name('live-orders.completed.dismiss-all');
    Route::delete('/api/admin/live-orders/completed/{order}', [LiveOrdersController::class, 'dismissCompleted'])->name('live-orders.completed.dismiss');
    Route::patch('/api/admin/live-orders/{order}/status', [LiveOrdersController::class, 'updateStatus'])->name('live-orders.status');
    Route::patch('/api/admin/call/{call}/resolve', [LiveOrdersController::class, 'resolveCall'])->name('admin.call.resolve');
    Route::patch('/api/admin/call/{call}/forward', [LiveOrdersController::class, 'forwardCall'])->name('admin.call.forward');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/giris', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/giris', [AuthController::class, 'login']);
});

Route::get('/manifest-waiter.json', [\App\Http\Controllers\Waiter\WaiterPwaController::class, 'manifest'])
    ->name('waiter.manifest');

Route::middleware([AdminMiddleware::class, SetStaffRestaurantContext::class])->group(function () {
    Route::post('/admin/cikis', [AuthController::class, 'logout'])->name('admin.logout');

    Route::post('/api/waiter/complete', [WaiterDashboardController::class, 'complete'])->name('waiter.complete');
    Route::patch('/api/waiter/call/{call}/claim', [WaiterDashboardController::class, 'claimCall'])->name('waiter.call.claim');
    Route::patch('/api/waiter/call/{call}/resolve', [WaiterDashboardController::class, 'resolveCall'])->name('waiter.call.resolve');
    Route::post('/api/waiter/order/{order}/approve', [WaiterDashboardController::class, 'approveOrder'])->name('waiter.order.approve');
    Route::post('/api/waiter/tables/transfer', [WaiterDashboardController::class, 'transferTable'])->name('waiter.tables.transfer');

    Route::get('/admin/api/admin/manual-order/bootstrap', [ManualOrderController::class, 'bootstrap'])->name('admin.manual-order.bootstrap');
    Route::get('/admin/api/admin/manual-order/products', [ManualOrderController::class, 'searchProducts'])->name('admin.manual-order.products');
    Route::get('/admin/api/admin/manual-order/table/{table}/active', [ManualOrderController::class, 'activeTableOrder'])->name('admin.manual-order.table-active');
    Route::post('/admin/api/admin/manual-order/order/{order}/cancel', [ManualOrderController::class, 'cancelOrder'])->name('admin.manual-order.cancel');
    Route::post('/admin/api/admin/manual-order', [ManualOrderController::class, 'store'])->name('admin.manual-order.store');

    Route::get('/admin/api/kasa/table-state', [KasaPanelController::class, 'tableState'])->name('admin.kasa.table-state');
    Route::post('/admin/api/kasa/select-table', [KasaPanelController::class, 'selectTable'])->name('admin.kasa.select-table');
    Route::post('/admin/api/kasa/add-item', [KasaPanelController::class, 'addItem'])->name('admin.kasa.add-item');
    Route::post('/admin/api/kasa/update-item', [KasaPanelController::class, 'updateOrderItem'])->name('admin.kasa.update-item');
    Route::post('/admin/api/kasa/notify-waiter', [KasaPanelController::class, 'notifyWaiter'])->name('admin.kasa.notify-waiter');
    Route::post('/admin/api/kasa/resume-order', [KasaPanelController::class, 'resumeOrder'])->name('admin.kasa.resume-order');
    Route::post('/admin/api/kasa/approve-order', [KasaPanelController::class, 'approveOrder'])->name('admin.kasa.approve-order');
    Route::post('/admin/api/kasa/pay-cash', [KasaPanelController::class, 'payWithCash'])->name('admin.kasa.pay-cash');
    Route::post('/admin/api/kasa/pay-card', [KasaPanelController::class, 'payWithManualCard'])->name('admin.kasa.pay-card');
    Route::post('/admin/api/kasa/pay-pos', [KasaPanelController::class, 'payWithPos'])->name('admin.kasa.pay-pos');
    Route::post('/admin/api/kasa/item-status', [KasaPanelController::class, 'updateItemPreparationStatus'])->name('admin.kasa.item-status');
    Route::patch('/api/waiter/tasks/{task}/accept', [WaiterDashboardController::class, 'acceptTask'])->name('waiter.tasks.accept');
    Route::patch('/api/waiter/tasks/{task}/complete', [WaiterDashboardController::class, 'completeTask'])->name('waiter.tasks.complete');

    Route::middleware(WaiterOnlyMiddleware::class)->prefix('waiter')->name('waiter.')->group(function () {
        Route::get('/dashboard', [WaiterDashboardController::class, 'index'])->name('dashboard');
    });

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware(AdminOnlyMiddleware::class)->group(function () {
            Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
            Route::get('/api/operasyon', [OperationsController::class, 'live'])->name('operations.live');

            Route::get('orders/archive/export/{mode}', [OrderArchiveController::class, 'export'])
                ->name('orders.archive.export')
                ->where('mode', 'daily|report');
            Route::post('orders/archive/purge', [OrderArchiveController::class, 'purge'])->name('orders.archive.purge');
            Route::delete('orders/{order}', [OrderArchiveController::class, 'destroy'])->name('orders.destroy');

            Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [SettingController::class, 'update'])->name('settings.update');

            Route::get('waiters/{waiter}/panel', [WaiterController::class, 'editPanel'])->name('waiters.panel');
            Route::resource('waiters', WaiterController::class)->except(['show']);
            Route::patch('api/admin/waiters/{waiter}/toggle-active', [WaiterController::class, 'toggleActive'])
                ->name('waiters.toggle-active');

            Route::get('orders/archive', [OrderArchiveController::class, 'index'])->name('orders.archive');
            Route::get('orders', [OrderAdminController::class, 'index'])->name('orders.index');
            Route::get('orders/{order}', [OrderAdminController::class, 'show'])->name('orders.show');
            Route::patch('orders/{order}/status', [OrderAdminController::class, 'updateStatus'])->name('orders.status');
        });

        Route::resource('categories', CategoryController::class)->except(['show']);
        Route::patch('api/admin/categories/{category}/toggle-active', [CategoryController::class, 'toggleActive'])
            ->name('categories.toggle-active');
        Route::patch('api/admin/categories/sort-order', [CategoryController::class, 'updateSortOrder'])
            ->name('categories.sort-order');

        Route::resource('slides', DisplaySlideController::class)->except(['show']);
        Route::resource('cafe-galleries', CafeGalleryController::class)->except(['show']);

        Route::get('live-orders', [LiveOrdersController::class, 'index'])->name('live-orders.index');
        Route::patch('/api/cagri/{call}/onayla', [OperationsController::class, 'acknowledgeCall'])->name('operations.acknowledge');

        Route::get('products/{product}/panel', [ProductController::class, 'editPanel'])->name('products.panel');
        Route::resource('products', ProductController::class)->except(['show']);
        Route::patch('api/admin/products/{product}/toggle-availability', [ProductController::class, 'toggleAvailability'])
            ->name('products.toggle-availability');
        Route::patch('api/admin/products/{product}/toggle-in-stock', [ProductController::class, 'toggleInStock'])
            ->name('products.toggle-in-stock');
        Route::patch('api/admin/products/{product}/quick-update', [ProductController::class, 'quickUpdate'])
            ->name('products.quick-update');

        Route::get('api/menu-hierarchy/categories/{category}/tabs', [MenuHierarchyController::class, 'tabs'])
            ->name('menu-hierarchy.tabs');
        Route::post('api/menu-hierarchy/tabs/delete', [MenuHierarchyController::class, 'destroyTabById'])
            ->name('menu-hierarchy.tabs.delete');
        Route::post('api/menu-hierarchy/tabs', [MenuHierarchyController::class, 'storeTab'])
            ->name('menu-hierarchy.tabs.store');
        Route::delete('api/menu-hierarchy/tabs/{menuTab}', [MenuHierarchyController::class, 'destroyTab'])
            ->name('menu-hierarchy.tabs.destroy');
        Route::post('api/menu-hierarchy/tabs/{menuTab}/destroy', [MenuHierarchyController::class, 'destroyTab'])
            ->name('menu-hierarchy.tabs.destroy.post');
        Route::get('api/menu-hierarchy/tabs/{menuTab}/sections', [MenuHierarchyController::class, 'sections'])
            ->name('menu-hierarchy.sections');
        Route::post('api/menu-hierarchy/sections/delete', [MenuHierarchyController::class, 'destroySectionById'])
            ->name('menu-hierarchy.sections.delete');
        Route::post('api/menu-hierarchy/sections', [MenuHierarchyController::class, 'storeSection'])
            ->name('menu-hierarchy.sections.store');
        Route::delete('api/menu-hierarchy/sections/{menuSection}', [MenuHierarchyController::class, 'destroySection'])
            ->name('menu-hierarchy.sections.destroy');
        Route::post('api/menu-hierarchy/sections/{menuSection}/destroy', [MenuHierarchyController::class, 'destroySection'])
            ->name('menu-hierarchy.sections.destroy.post');

        Route::resource('tables', TableController::class)->except(['show']);
        Route::patch('api/admin/tables/{table}/toggle-active', [TableController::class, 'toggleActive'])
            ->name('tables.toggle-active');
        Route::post('tables/{table}/regenerate', [TableController::class, 'regenerate'])->name('tables.regenerate');
        Route::get('tables/{table}/qr.png', [TableController::class, 'qrPng'])->name('tables.qr.png');
        Route::get('tables/{table}/qr.svg', [TableController::class, 'qrSvg'])->name('tables.qr.svg');

        Route::get('bar', fn () => redirect()->route('admin.live-orders.index'))->name('bar.index');
        Route::get('api/bar/siparisler', [BarScreenController::class, 'orders'])->name('bar.orders');
        Route::patch('api/bar/siparis/{order}/hazir', [BarScreenController::class, 'markReady'])->name('bar.ready');
    });
});

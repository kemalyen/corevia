<?php

use App\Http\Controllers\Portal\AuthenticatedSessionController;
use App\Http\Controllers\Portal\OrderReportController;
use App\Livewire\Portal\Dashboard;
use App\Livewire\Portal\Login;
use App\Livewire\Portal\Orders\Create;
use App\Livewire\Portal\Orders\Index;
use App\Livewire\Portal\Orders\Show;
use App\Livewire\Portal\Products;
use App\Livewire\Portal\Profile;
use App\Livewire\Portal\Reports;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware('guest')->group(function (): void {
    Route::livewire('/portal/login', Login::class)->name('login');
});

Route::prefix('portal')
    ->name('portal.')
    ->middleware(['auth', 'auth.session', 'vendor.portal'])
    ->group(function (): void {
        Route::livewire('/', Dashboard::class)->name('dashboard');
        Route::livewire('/products', Products::class)->name('products');
        Route::livewire('/orders', Index::class)->name('orders.index');
        Route::livewire('/orders/create', Create::class)->name('orders.create');
        Route::livewire('/orders/{salesOrder}', Show::class)->name('orders.show');
        Route::livewire('/reports', Reports::class)->name('reports');
        Route::get('/reports/orders.csv', OrderReportController::class)->name('reports.orders.csv');
        Route::livewire('/profile', Profile::class)->name('profile');
        Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    });

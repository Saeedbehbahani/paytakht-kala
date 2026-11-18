<?php

namespace App\Providers;

use App\Contracts\ProductLookupProvider;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\NullProductLookupProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ProductLookupProvider::class, NullProductLookupProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('view-audit', fn (User $user) => $user->isAdmin());
        Gate::define('manage-finance', fn (User $user) => $user->isAdmin());
        Gate::define('view-payroll', fn (User $user, int $employeeId) => $user->isAdmin() || $user->id === $employeeId);
        AuditLog::deleting(fn () => false);

        View::composer(['layouts.app', 'sales.invoice'], function ($view) {
            $view->with('storeProfile', [
                'name' => Setting::valueOf('store_name', 'پایتخت کالا'),
                'phone' => Setting::valueOf('store_phone', ''),
                'invoiceInfo' => Setting::valueOf('invoice_info', ''),
            ]);
        });
    }
}

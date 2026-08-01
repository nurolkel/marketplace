<?php

namespace App\Providers;

use App\Models\Lunar\Collection;
use App\Models\Lunar\Customer;
use App\Models\Lunar\Order;
use App\Models\Lunar\Product;
use App\Models\Restaurant;
use App\Models\RestaurantOrder;
use App\Models\User;
use App\Policies\CategoryPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\ProductPolicy;
use App\Policies\RestaurantOrderPolicy;
use App\Policies\RestaurantPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Lunar\Admin\Support\Facades\LunarPanel;
use Lunar\Facades\ModelManifest;
use Lunar\Models\Contracts\Collection as CollectionContract;
use Lunar\Models\Contracts\Customer as CustomerContract;
use Lunar\Models\Contracts\Order as OrderContract;
use Lunar\Models\Contracts\Product as ProductContract;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        LunarPanel::register();

        ModelManifest::replace(ProductContract::class, Product::class);
        ModelManifest::replace(CollectionContract::class, Collection::class);
        ModelManifest::replace(CustomerContract::class, Customer::class);
        ModelManifest::replace(OrderContract::class, Order::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Gate::before(fn ($user): ?bool => $user instanceof User && $user->isMarketplaceAdmin()
            ? true
            : null);

        Gate::policy(Restaurant::class, RestaurantPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Collection::class, CategoryPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(RestaurantOrder::class, RestaurantOrderPolicy::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}

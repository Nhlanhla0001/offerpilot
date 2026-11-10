<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
    }

    /**
     * Limit the buyer endpoints per browser session, so a guest negotiating
     * without an account still cannot run up the assistant or checkout calls.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('buyer-negotiate', fn (Request $request): Limit => Limit::perMinute(20)->by($this->buyerKey($request)));
        RateLimiter::for('buyer-checkout', fn (Request $request): Limit => Limit::perMinute(30)->by($this->buyerKey($request)));
    }

    /**
     * Get the rate-limit key for the signed-in buyer or the guest session.
     */
    protected function buyerKey(Request $request): string
    {
        $user = $request->user();

        return 'buyer:'.($user !== null ? $user->id : $request->session()->getId());
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

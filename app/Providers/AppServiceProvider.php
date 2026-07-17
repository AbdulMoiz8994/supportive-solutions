<?php

namespace App\Providers;

use App\Models\PayRecord;
use App\Policies\PayrollPolicy;
use App\Services\GlobalSettingsPreserveService;
use App\Services\IntegrationConfigService;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $binding = function ($app) {
            return new \App\Overrides\Captcha(
                $app['Illuminate\Filesystem\Filesystem'],
                $app['Illuminate\Contracts\Config\Repository'],
                $app['Intervention\Image\ImageManager'],
                $app['Illuminate\Session\Store'],
                $app['Illuminate\Hashing\BcryptHasher'],
                $app['Illuminate\Support\Str']
            );
        };

        $this->app->bind('captcha', $binding);
        $this->app->bind(\Mews\Captcha\Captcha::class, $binding);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        Gate::policy(PayRecord::class, PayrollPolicy::class);

        if (Schema::hasTable('integration_credentials')) {
            app(IntegrationConfigService::class)->hydrateRuntimeConfig();
        }

        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            if ($event->command !== 'migrate:fresh') {
                return;
            }

            app(GlobalSettingsPreserveService::class)->backup();
        });

        Event::listen(CommandFinished::class, function (CommandFinished $event): void {
            if ($event->command !== 'migrate:fresh' || $event->exitCode !== 0) {
                return;
            }

            app(GlobalSettingsPreserveService::class)->restore();
        });

        // Force HTTPS in production
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        View::composer([
            'pages.settings.*',
            'pages.global-settings.*',
        ], function ($view): void {
            $view->with([
                'settingsLabel' => 'block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5',
                'settingsInput' => 'w-full px-3.5 py-2.5 text-sm bg-gray-50 border border-gray-100 rounded-xl focus:ring-2 focus:ring-blue-500/10 outline-none transition-all font-semibold',
                'settingsSelect' => 'w-full px-3.5 py-2.5 text-sm bg-gray-50 border border-gray-100 rounded-xl focus:ring-2 focus:ring-blue-500/10 outline-none transition-all font-semibold',
                'settingsSectionTitle' => 'text-xl font-black text-[#1e293b] tracking-tighter',
                'settingsSectionDesc' => 'text-sm text-[#64748b] mt-1.5 font-bold opacity-70',
                'settingsCard' => 'rounded-2xl border border-[#e2e8f0] bg-white shadow-sm',
                'settingsSubheading' => 'text-[10px] font-black uppercase tracking-widest text-gray-400',
            ]);
        });
    }
}

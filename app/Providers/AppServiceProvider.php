<?php

namespace App\Providers;

use App\Services\AuditService;
use App\Services\SettingsService;
use Carbon\Carbon;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsService::class);
        $this->app->singleton(AuditService::class);
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();
        Carbon::setLocale('pt');
        setlocale(LC_TIME, 'pt_PT.UTF-8', 'pt_PT', 'Portuguese_Portugal');

        Blade::directive('money', fn ($expression) => "<?php echo e(\App\Support\Money::format({$expression})); ?>");
        Blade::directive('date', fn ($expression) => "<?php echo e(optional({$expression})->format('d/m/Y') ?? '—'); ?>");
        Blade::directive('datetime', fn ($expression) => "<?php echo e(optional({$expression})->format('d/m/Y H:i') ?? '—'); ?>");

        View::composer('*', function ($view) {
            static $shared = null;
            if ($shared === null) {
                $settings = app(SettingsService::class);
                $shared = [
                    'site' => [
                        'name' => $settings->get('institution.name') ?: config('app.name'),
                        'tagline' => $settings->get('institution.tagline'),
                        'phone' => $settings->get('institution.phone'),
                        'email' => $settings->get('institution.email'),
                        'address' => $settings->get('institution.address'),
                        'whatsapp' => $settings->get('institution.whatsapp'),
                        'hours' => $settings->get('institution.hours'),
                        'legal_name' => $settings->get('institution.legal_name'),
                        'registration_info' => $settings->get('institution.registration_info'),
                    ],
                ];
            }
            $view->with($shared);
        });

        View::composer(['layouts.admin', 'layouts.client'], function ($view) {
            $user = auth()->user();
            if ($user) {
                $view->with('unreadNotifications', $user->unreadNotifications()->latest()->limit(6)->get());
                $view->with('unreadCount', $user->unreadNotifications()->count());
            }
        });
    }
}

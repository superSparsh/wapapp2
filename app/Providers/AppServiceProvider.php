<?php

namespace App\Providers;

use App\Domains\Admin\Services\ModuleErrorRecorder;
use App\Domains\Admin\Support\ErrorModuleResolver;
use App\Domains\Auth\Auth\TenantAwareUserProvider;
use App\Domains\Inbox\Contracts\OutboundMessageGateway;
use App\Domains\Inbox\Services\DelegatingOutboundMessageGateway;
use App\Domains\Infrastructure\Oci\Contracts\OciContainerInstanceClient;
use App\Domains\Infrastructure\Oci\HttpOciContainerInstanceClient;
use App\Domains\Infrastructure\Oci\LogOciContainerInstanceClient;
use App\Models\Message;
use App\Models\WhatsappLine;
use App\Observers\MessageObserver;
use App\Observers\WhatsappLineObserver;
use App\View\Composers\AdminHeaderComposer;
use App\View\Composers\HeaderComposer;
use App\View\Composers\SidebarComposer;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(OutboundMessageGateway::class, DelegatingOutboundMessageGateway::class);

        $this->app->bind(OciContainerInstanceClient::class, function () {
            $driver = (string) config('oci-workers.ephemeral.driver', 'log');

            return match ($driver) {
                'http' => new HttpOciContainerInstanceClient,
                default => new LogOciContainerInstanceClient,
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production') || str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // WapApp displays all times in Indian Standard Time (admin + customer).
        config(['app.timezone' => 'Asia/Kolkata']);
        date_default_timezone_set('Asia/Kolkata');
        \Carbon\Carbon::setLocale((string) config('app.locale', 'en'));

        \Illuminate\Support\Facades\Blade::directive('ist', function (string $expression): string {
            return "<?php echo e(format_ist({$expression})); ?>";
        });

        Auth::provider('tenant-eloquent', function ($app, array $config) {
            return new TenantAwareUserProvider($app['hash'], $config['model']);
        });

        View::composer([
            'components.app.header',
            'components.app.user-panel',
            'components.app.header-avatar',
            'components.app.notifications-panel',
            'components.app.impersonation-banner',
            'components.app.admin-area-ribbon',
            'components.app.line-context-banner',
            'components.app.line-context-exit-ribbon',
        ], HeaderComposer::class);

        View::composer([
            'components.admin.header',
            'components.admin.notifications-panel',
        ], AdminHeaderComposer::class);

        View::composer('components.app.sidebar', SidebarComposer::class);

        WhatsappLine::observe(WhatsappLineObserver::class);
        Message::observe(MessageObserver::class);

        Queue::failing(function (JobFailed $event): void {
            try {
                $exception = $event->exception;
                $displayName = $event->job->resolveName();

                // Inbox realtime / Reverb outages should not flood the Errors hub.
                if ($exception instanceof \Illuminate\Broadcasting\BroadcastException
                    || str_contains($displayName, 'BroadcastEvent')
                    || str_contains($displayName, 'Events\\Inbox\\')) {
                    return;
                }

                $resolver = app(ErrorModuleResolver::class);
                $recorder = app(ModuleErrorRecorder::class);
                $module = $resolver->fromDisplayName($displayName);

                $recorder->recordJob(
                    message: $exception->getMessage() !== ''
                        ? $exception->getMessage()
                        : $exception::class,
                    module: $module,
                    source: $displayName,
                    context: [
                        'queue' => $event->job->getQueue(),
                        'connection' => $event->connectionName,
                        'exception' => $exception::class,
                        'trace' => Str::limit($exception->getTraceAsString(), 4000, '…'),
                    ],
                );
            } catch (\Throwable) {
                //
            }
        });
    }
}

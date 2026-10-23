<?php

namespace App\Providers;

use App\Http\Controllers\WhatsAppCronController;
use App\Http\Controllers\WhatsAppWebhookController;
use App\Models\LeaveRequest;
use App\Services\WhatsApp\WhatsAppNotifier;
use App\Services\WhatsApp\WhatsAppSettings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Self-contained WhatsApp Business module. Registering routes and model hooks here keeps
 * routes/web.php and existing controllers untouched on live installs.
 */
class WhatsAppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WhatsAppSettings::class);
    }

    public function boot(): void
    {
        if (!$this->app->routesAreCached()) {
            Route::middleware('web')->group(base_path('routes/whatsapp.php'));

            // Called by Meta and the DirectAdmin cron: no session, cookies or CSRF.
            Route::get('/whatsapp/webhook', [WhatsAppWebhookController::class, 'verify'])->name('whatsapp.webhook.verify');
            Route::post('/whatsapp/webhook', [WhatsAppWebhookController::class, 'receive'])->name('whatsapp.webhook.receive');
            Route::get('/whatsapp/cron/{key}', [WhatsAppCronController::class, 'run'])->name('whatsapp.cron');
        }

        LeaveRequest::updated(function (LeaveRequest $leave) {
            if (!$leave->wasChanged('status')) {
                return;
            }

            try {
                $this->app->make(WhatsAppNotifier::class)->leaveDecision($leave);
            } catch (Throwable $exception) {
                // A WhatsApp problem must never block a leave decision.
                Log::warning('WhatsApp leave notification failed', ['leave_request_id' => $leave->id, 'error' => $exception->getMessage()]);
            }
        });
    }
}

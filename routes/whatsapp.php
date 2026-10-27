<?php

use App\Http\Controllers\WhatsAppBroadcastController;
use App\Http\Controllers\WhatsAppContactController;
use App\Http\Controllers\WhatsAppInboxController;
use App\Http\Controllers\WhatsAppSettingController;
use App\Http\Controllers\WhatsAppTemplateController;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\ForcePasswordChange;
use Illuminate\Support\Facades\Route;

/*
| WhatsApp Business module routes (v2.9.1). Loaded by WhatsAppServiceProvider inside the "web"
| middleware group so routes/web.php does not need to change. The public webhook and cron
| endpoints are registered separately by the provider without session/CSRF middleware.
*/

Route::middleware([EnsureInstalled::class, 'auth', ForcePasswordChange::class])->group(function () {
    Route::get('/whatsapp', [WhatsAppInboxController::class, 'index'])
        ->middleware(CheckPermission::class . ':whatsapp.view')
        ->name('whatsapp.inbox');
    Route::post('/whatsapp/start', [WhatsAppInboxController::class, 'start'])
        ->middleware(CheckPermission::class . ':whatsapp.send')
        ->name('whatsapp.inbox.start');
    Route::get('/whatsapp/conversations/{contact}', [WhatsAppInboxController::class, 'show'])
        ->middleware(CheckPermission::class . ':whatsapp.view')
        ->name('whatsapp.inbox.show');
    Route::get('/whatsapp/conversations/{contact}/poll', [WhatsAppInboxController::class, 'poll'])
        ->middleware(CheckPermission::class . ':whatsapp.view')
        ->name('whatsapp.inbox.poll');
    Route::post('/whatsapp/conversations/{contact}/text', [WhatsAppInboxController::class, 'sendText'])
        ->middleware(CheckPermission::class . ':whatsapp.send')
        ->name('whatsapp.inbox.text');
    Route::post('/whatsapp/conversations/{contact}/template', [WhatsAppInboxController::class, 'sendTemplate'])
        ->middleware(CheckPermission::class . ':whatsapp.send')
        ->name('whatsapp.inbox.template');
    Route::post('/whatsapp/conversations/{contact}/media', [WhatsAppInboxController::class, 'sendMedia'])
        ->middleware(CheckPermission::class . ':whatsapp.send')
        ->name('whatsapp.inbox.media');
    Route::put('/whatsapp/conversations/{contact}/contact', [WhatsAppInboxController::class, 'updateContact'])
        ->middleware(CheckPermission::class . ':whatsapp.contacts.manage')
        ->name('whatsapp.inbox.contact');
    Route::post('/whatsapp/messages/{message}/retry', [WhatsAppInboxController::class, 'retry'])
        ->middleware(CheckPermission::class . ':whatsapp.send')
        ->name('whatsapp.messages.retry');
    Route::get('/whatsapp/messages/{message}/media', [WhatsAppInboxController::class, 'media'])
        ->middleware(CheckPermission::class . ':whatsapp.view')
        ->name('whatsapp.messages.media');

    Route::get('/whatsapp/contacts', [WhatsAppContactController::class, 'index'])
        ->middleware(CheckPermission::class . ':whatsapp.contacts.manage')
        ->name('whatsapp.contacts');
    Route::post('/whatsapp/contacts/consent', [WhatsAppContactController::class, 'consent'])
        ->middleware(CheckPermission::class . ':whatsapp.contacts.manage')
        ->name('whatsapp.contacts.consent');

    Route::get('/whatsapp/templates', [WhatsAppTemplateController::class, 'index'])
        ->middleware(CheckPermission::class . ':whatsapp.view')
        ->name('whatsapp.templates');
    Route::post('/whatsapp/templates/sync', [WhatsAppTemplateController::class, 'sync'])
        ->middleware(CheckPermission::class . ':whatsapp.templates.manage')
        ->name('whatsapp.templates.sync');

    Route::get('/whatsapp/broadcasts', [WhatsAppBroadcastController::class, 'index'])
        ->middleware(CheckPermission::class . ':whatsapp.broadcast')
        ->name('whatsapp.broadcasts.index');
    Route::get('/whatsapp/broadcasts/create', [WhatsAppBroadcastController::class, 'create'])
        ->middleware(CheckPermission::class . ':whatsapp.broadcast')
        ->name('whatsapp.broadcasts.create');
    Route::post('/whatsapp/broadcasts/preview', [WhatsAppBroadcastController::class, 'preview'])
        ->middleware(CheckPermission::class . ':whatsapp.broadcast')
        ->name('whatsapp.broadcasts.preview');
    Route::post('/whatsapp/broadcasts', [WhatsAppBroadcastController::class, 'store'])
        ->middleware(CheckPermission::class . ':whatsapp.broadcast')
        ->name('whatsapp.broadcasts.store');
    Route::get('/whatsapp/broadcasts/{broadcast}', [WhatsAppBroadcastController::class, 'show'])
        ->middleware(CheckPermission::class . ':whatsapp.broadcast')
        ->name('whatsapp.broadcasts.show');

    Route::get('/settings/whatsapp', [WhatsAppSettingController::class, 'index'])
        ->middleware(CheckPermission::class . ':whatsapp.settings.view')
        ->name('whatsapp.settings');
    Route::put('/settings/whatsapp', [WhatsAppSettingController::class, 'update'])
        ->middleware(CheckPermission::class . ':whatsapp.settings.manage')
        ->name('whatsapp.settings.update');
    Route::post('/settings/whatsapp/test', [WhatsAppSettingController::class, 'test'])
        ->middleware(CheckPermission::class . ':whatsapp.settings.manage')
        ->name('whatsapp.settings.test');
    Route::post('/settings/whatsapp/test-message', [WhatsAppSettingController::class, 'sendTest'])
        ->middleware(CheckPermission::class . ':whatsapp.settings.manage')
        ->name('whatsapp.settings.test_message');
});

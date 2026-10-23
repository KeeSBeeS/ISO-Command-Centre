<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\WhatsAppSettings;
use App\Services\WhatsApp\WhatsAppTemplateSync;
use Throwable;

class WhatsAppTemplateController extends Controller
{
    public function index(WhatsAppSettings $settings)
    {
        abort_unless($settings->installed(), 404, 'Run the v2.9.0 update first.');

        $notificationTemplates = array_filter([
            'Leave decision' => $settings->get('whatsapp_leave_template'),
            'Employee document reminder' => $settings->get('whatsapp_document_template'),
            'Vehicle document reminder' => $settings->get('whatsapp_vehicle_document_template'),
        ]);

        return view('whatsapp.templates', [
            'templates' => WhatsAppTemplate::orderBy('name')->orderBy('language')->get(),
            'notificationTemplates' => $notificationTemplates,
            'language' => $settings->get('whatsapp_template_language', 'en'),
        ]);
    }

    public function sync(WhatsAppTemplateSync $sync)
    {
        try {
            $result = $sync->sync();
        } catch (Throwable $exception) {
            return redirect()->route('whatsapp.templates')->withErrors(['whatsapp' => $exception->getMessage()]);
        }

        return redirect()->route('whatsapp.templates')->with('success', 'Templates synced from Meta: ' . $result['synced'] . ' current, ' . $result['removed'] . ' removed.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\WhatsApp\WhatsAppInstaller;
use Throwable;

class WhatsAppSetupController extends Controller
{
    public function show(WhatsAppInstaller $installer)
    {
        return view('whatsapp.setup', [
            'status' => $installer->status(),
            'targetVersion' => WhatsAppInstaller::VERSION,
        ]);
    }

    public function apply(WhatsAppInstaller $installer)
    {
        try {
            $installer->install();
        } catch (Throwable $exception) {
            return redirect()->route('updates.v2_9_0')->withErrors(['whatsapp_setup' => $exception->getMessage()]);
        }

        return redirect()->route('updates.v2_9_0')->with('success', 'Version 2.9.0 applied. WhatsApp Business tables, settings and permissions are installed. Configure the API under WhatsApp Settings.');
    }
}

<?php

use App\Services\WhatsApp\WhatsAppActivation;
use App\Services\WhatsApp\WhatsAppInstaller;
use Illuminate\Database\Migrations\Migration;

/*
 * v2.9.1 WhatsApp Business: tables, settings and permissions, then register the module in the
 * live bootstrap/providers.php and add the menu link to the live layout (both patched in place
 * with .bak- copies, because the live files are newer than the repo).
 *
 * down() reverses everything, including dropping the WhatsApp tables and their data.
 */
return new class extends Migration {
    public function up(): void
    {
        app(WhatsAppInstaller::class)->install();
        (new WhatsAppActivation(base_path()))->add();
    }

    public function down(): void
    {
        (new WhatsAppActivation(base_path()))->remove();
        app(WhatsAppInstaller::class)->uninstall();
    }
};

<?php

namespace App\Services\WhatsApp;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemSetting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Web-run installer for v2.9.0 (no artisan on shared hosting). Safe to run repeatedly.
 * Foreign keys use short explicit names to stay under MySQL's 64 character identifier limit.
 */
class WhatsAppInstaller
{
    public const VERSION = '2.9.0';

    public const PERMISSIONS = [
        'whatsapp.view' => ['View WhatsApp Inbox', 'View WhatsApp conversations and message history.'],
        'whatsapp.send' => ['Send WhatsApp Messages', 'Reply to conversations, send templates and media, and start new conversations.'],
        'whatsapp.contacts.manage' => ['Manage WhatsApp Contacts', 'Edit WhatsApp contacts, link employees and record opt-in or opt-out.'],
        'whatsapp.broadcast' => ['Send WhatsApp Broadcasts', 'Send approved templates to groups of opted-in employees.'],
        'whatsapp.templates.manage' => ['Manage WhatsApp Templates', 'Sync WhatsApp message templates from Meta.'],
        'whatsapp.settings.view' => ['View WhatsApp Settings', 'View WhatsApp Business API settings.'],
        'whatsapp.settings.manage' => ['Manage WhatsApp Settings', 'Manage WhatsApp Business API credentials, webhook and notifications.'],
    ];

    /**
     * Default grants applied only when a permission is first created, so later role edits are respected.
     */
    public const DEFAULT_ROLE_GRANTS = [
        'director' => ['whatsapp.view', 'whatsapp.send', 'whatsapp.contacts.manage', 'whatsapp.broadcast', 'whatsapp.templates.manage'],
        'manager' => ['whatsapp.view', 'whatsapp.send'],
    ];

    public function __construct(private WhatsAppSettings $settings)
    {
    }

    public function status(): array
    {
        return [
            'system_settings' => Schema::hasTable('system_settings'),
            'whatsapp_contacts' => Schema::hasTable('whatsapp_contacts'),
            'whatsapp_messages' => Schema::hasTable('whatsapp_messages'),
            'whatsapp_templates' => Schema::hasTable('whatsapp_templates'),
            'whatsapp_broadcasts' => Schema::hasTable('whatsapp_broadcasts'),
            'whatsapp_webhook_events' => Schema::hasTable('whatsapp_webhook_events'),
            'permissions' => Permission::whereIn('slug', array_keys(self::PERMISSIONS))->count() === count(self::PERMISSIONS),
            'settings' => Schema::hasTable('system_settings') && SystemSetting::where('group', WhatsAppSettings::GROUP)->count() >= count(WhatsAppSettings::DEFINITIONS),
        ];
    }

    public function install(): void
    {
        if (!Schema::hasTable('system_settings')) {
            throw new \RuntimeException('System settings are missing. Run the v2.5.3 update before installing WhatsApp.');
        }

        $this->createTables();
        $this->seedSettings();
        $this->seedPermissions();
        $this->bumpVersion();
    }

    private function createTables(): void
    {
        if (!Schema::hasTable('whatsapp_contacts')) {
            Schema::create('whatsapp_contacts', function (Blueprint $table) {
                $table->id();
                $table->string('wa_id', 32)->unique();
                $table->string('profile_name')->nullable();
                $table->string('display_name')->nullable();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->dateTime('opted_in_at')->nullable();
                $table->string('opt_in_source')->nullable();
                $table->dateTime('opted_out_at')->nullable();
                $table->dateTime('last_inbound_at')->nullable();
                $table->dateTime('last_message_at')->nullable()->index();
                $table->string('last_message_preview', 160)->nullable();
                $table->unsignedInteger('unread_count')->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->foreign('user_id', 'wa_contacts_user_fk')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('whatsapp_templates')) {
            Schema::create('whatsapp_templates', function (Blueprint $table) {
                $table->id();
                $table->string('remote_id', 64)->nullable();
                $table->string('name', 191);
                $table->string('language', 20);
                $table->string('category', 40)->nullable();
                $table->string('status', 40)->default('UNKNOWN')->index();
                $table->string('parameter_format', 20)->default('POSITIONAL');
                $table->longText('components')->nullable();
                $table->dateTime('synced_at')->nullable();
                $table->timestamps();
                $table->unique(['name', 'language'], 'wa_templates_name_lang_uq');
            });
        }

        if (!Schema::hasTable('whatsapp_broadcasts')) {
            Schema::create('whatsapp_broadcasts', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('template_name', 191);
                $table->string('template_language', 20);
                $table->text('template_params')->nullable();
                $table->string('audience_type', 40);
                $table->text('audience_meta')->nullable();
                $table->string('status', 20)->default('queued');
                $table->unsignedInteger('total_count')->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->dateTime('started_at')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->timestamps();
                $table->foreign('created_by', 'wa_broadcasts_user_fk')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('whatsapp_messages')) {
            Schema::create('whatsapp_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('contact_id');
                $table->unsignedBigInteger('broadcast_id')->nullable()->index();
                $table->string('direction', 10);
                $table->string('wamid', 191)->nullable()->unique();
                $table->string('type', 20)->default('text');
                $table->text('body')->nullable();
                $table->string('template_name', 191)->nullable();
                $table->string('template_language', 20)->nullable();
                $table->text('template_params')->nullable();
                $table->string('media_id', 191)->nullable();
                $table->string('media_mime', 120)->nullable();
                $table->string('media_filename')->nullable();
                $table->string('media_path')->nullable();
                $table->string('context_wamid', 191)->nullable();
                $table->string('status', 20)->default('queued')->index();
                $table->string('error_code', 20)->nullable();
                $table->text('error_message')->nullable();
                $table->string('source', 20)->default('manual');
                $table->string('notification_key', 191)->nullable()->index();
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->unsignedBigInteger('sent_by')->nullable();
                $table->longText('payload')->nullable();
                $table->dateTime('sent_at')->nullable();
                $table->dateTime('delivered_at')->nullable();
                $table->dateTime('read_at')->nullable();
                $table->dateTime('failed_at')->nullable();
                $table->timestamps();
                $table->index(['contact_id', 'id'], 'wa_messages_contact_idx');
                $table->foreign('contact_id', 'wa_messages_contact_fk')->references('id')->on('whatsapp_contacts')->cascadeOnDelete();
                $table->foreign('broadcast_id', 'wa_messages_broadcast_fk')->references('id')->on('whatsapp_broadcasts')->nullOnDelete();
                $table->foreign('sent_by', 'wa_messages_user_fk')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('whatsapp_webhook_events')) {
            Schema::create('whatsapp_webhook_events', function (Blueprint $table) {
                $table->id();
                $table->boolean('signature_valid')->default(false);
                $table->longText('payload')->nullable();
                $table->dateTime('processed_at')->nullable();
                $table->text('error')->nullable();
                $table->timestamps();
                $table->index('created_at', 'wa_webhook_created_idx');
            });
        }
    }

    private function seedSettings(): void
    {
        $existing = SystemSetting::whereIn('key', array_keys(WhatsAppSettings::DEFINITIONS))->pluck('value', 'key');

        foreach (WhatsAppSettings::DEFINITIONS as $key => $definition) {
            if ($existing->has($key)) {
                // Refresh labels/descriptions without touching stored values.
                SystemSetting::where('key', $key)->update([
                    'group' => WhatsAppSettings::GROUP,
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                ]);
                continue;
            }

            $value = $definition['value'];
            if ($key === 'whatsapp_cron_key' || $key === 'whatsapp_webhook_verify_token') {
                $value = Str::random(40);
            }

            $this->settings->set($key, $value);
        }

        $this->settings->flush();
    }

    private function seedPermissions(): void
    {
        $newIds = [];
        $allIds = [];

        foreach (self::PERMISSIONS as $slug => [$name, $description]) {
            $permission = Permission::firstOrNew(['slug' => $slug]);
            $isNew = !$permission->exists;
            $permission->fill(['name' => $name, 'module' => 'WhatsApp', 'description' => $description])->save();

            $allIds[] = $permission->id;
            if ($isNew) {
                $newIds[$slug] = $permission->id;
            }
        }

        if ($systemAdministrator = Role::where('slug', 'system-administrator')->first()) {
            $systemAdministrator->permissions()->syncWithoutDetaching($allIds);
        }

        foreach (self::DEFAULT_ROLE_GRANTS as $roleSlug => $slugs) {
            $ids = array_values(array_intersect_key($newIds, array_flip($slugs)));
            if ($ids !== [] && ($role = Role::where('slug', $roleSlug)->first())) {
                $role->permissions()->syncWithoutDetaching($ids);
            }
        }
    }

    private function bumpVersion(): void
    {
        $current = (string) SystemSetting::where('key', 'platform_version')->value('value');
        if ($current !== '' && version_compare($current, self::VERSION, '>=')) {
            return;
        }

        DB::table('system_settings')->updateOrInsert(
            ['key' => 'platform_version'],
            [
                'group' => 'Identity',
                'label' => 'Platform Version',
                'value' => self::VERSION,
                'type' => 'text',
                'description' => 'Current ISO Admin Command Framework package version.',
                'sort_order' => 5,
                'is_core' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}

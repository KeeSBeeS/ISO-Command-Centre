<?php

namespace Tests\Feature\WhatsApp;

use App\Models\Role;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppInstaller;
use App\Services\WhatsApp\WhatsAppSettings;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recreates the platform tables that the web installer (not migrations) normally creates.
 */
trait InteractsWithWhatsApp
{
    protected string $appSecret = 'test-app-secret';

    protected function setUpPlatform(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_code')->nullable();
            $table->string('attendance_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('position')->nullable();
            $table->string('status')->default('active');
            $table->boolean('must_change_password')->default(false);
            $table->dateTime('password_changed_at')->nullable();
            $table->dateTime('credentials_emailed_at')->nullable();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('level')->default(10);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('module')->index();
            $table->text('description')->nullable();
            $table->timestamps();
        });
        foreach (['role_user' => 'role_id', 'permission_role' => 'permission_id', 'permission_user' => 'permission_id', 'department_user' => 'department_id'] as $pivot => $first) {
            Schema::create($pivot, function (Blueprint $table) use ($pivot, $first) {
                $table->id();
                $table->unsignedBigInteger($first);
                $table->unsignedBigInteger($pivot === 'permission_role' ? 'role_id' : 'user_id');
                $table->timestamps();
            });
        }
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('employee_number')->nullable();
            $table->string('job_title')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->date('started_at')->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('group')->default('Core')->index();
            $table->string('label');
            $table->text('value')->nullable();
            $table->string('type')->default('text');
            $table->text('options')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(10);
            $table->boolean('is_core')->default(true);
            $table->timestamps();
        });
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->timestamps();
        });
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('leave_type_id')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_days', 8, 2)->default(1);
            $table->string('status')->default('pending');
            $table->boolean('is_deductible')->default(true);
            $table->text('reason')->nullable();
            $table->text('manager_notes')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->string('document_type');
            $table->string('title');
            $table->string('file_path')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->boolean('has_expiry')->default(false);
            $table->date('expires_at')->nullable();
            $table->unsignedInteger('remind_days_before')->nullable();
            $table->date('reminder_date')->nullable();
            $table->dateTime('last_reminder_sent_at')->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        foreach (['system-administrator' => 100, 'director' => 80, 'manager' => 50, 'employee' => 10] as $slug => $level) {
            Role::create(['name' => ucwords(str_replace('-', ' ', $slug)), 'slug' => $slug, 'level' => $level, 'is_system' => true]);
        }

        @mkdir(storage_path('app'), 0777, true);
        touch(storage_path('app/isoadmin_installed.lock'));
    }

    protected function installWhatsApp(bool $configure = true): WhatsAppSettings
    {
        $this->app->make(WhatsAppInstaller::class)->install();
        $settings = $this->app->make(WhatsAppSettings::class);

        if ($configure) {
            $settings->set('whatsapp_enabled', '1');
            $settings->set('whatsapp_phone_number_id', '1234567890');
            $settings->set('whatsapp_business_account_id', '9876543210');
            $settings->set('whatsapp_access_token', 'test-token');
            $settings->set('whatsapp_app_secret', $this->appSecret);
            $settings->set('whatsapp_webhook_verify_token', 'verify-token-123');
            $settings->set('whatsapp_cron_key', 'cron-key-1234567890');
        }

        return $settings;
    }

    protected function makeUser(string $roleSlug, array $attributes = [], ?string $mobile = null): User
    {
        $user = User::create(array_merge([
            'name' => 'Test ' . ucfirst($roleSlug) . ' ' . uniqid(),
            'email' => uniqid() . '@example.com',
            'password' => 'secret-password',
            'status' => 'active',
        ], $attributes));
        $user->roles()->attach(Role::where('slug', $roleSlug)->value('id'));

        if ($mobile !== null) {
            $user->profile()->create(['mobile' => $mobile]);
        }

        return $user;
    }

    protected function signedWebhook(array $payload)
    {
        $raw = json_encode($payload);

        return $this->call('POST', '/whatsapp/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . hash_hmac('sha256', $raw, $this->appSecret),
        ], $raw);
    }

    protected function inboundPayload(string $from, string $wamid, array $message, ?string $name = 'Sipho'): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => '9876543210',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => ['display_phone_number' => '27110000000', 'phone_number_id' => '1234567890'],
                        'contacts' => [['profile' => ['name' => $name], 'wa_id' => $from]],
                        'messages' => [array_merge(['from' => $from, 'id' => $wamid, 'timestamp' => (string) now()->timestamp], $message)],
                    ],
                ]],
            ]],
        ];
    }

    protected function statusPayload(string $wamid, string $status, array $extra = []): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => '9876543210',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => ['phone_number_id' => '1234567890'],
                        'statuses' => [array_merge(['id' => $wamid, 'status' => $status, 'timestamp' => (string) now()->timestamp, 'recipient_id' => '27821234567'], $extra)],
                    ],
                ]],
            ]],
        ];
    }

    protected function fakeGraphSuccess(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'graph.facebook.com/*/messages' => fn () => \Illuminate\Support\Facades\Http::response([
                'messaging_product' => 'whatsapp',
                'contacts' => [['input' => '27821234567', 'wa_id' => '27821234567']],
                'messages' => [['id' => 'wamid.OUT' . uniqid()]],
            ]),
            '*' => \Illuminate\Support\Facades\Http::response(['success' => true]),
        ]);
    }
}

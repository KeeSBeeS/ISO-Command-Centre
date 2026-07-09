<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class CoreSettingController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeSystemAdministrator($request);
        abort_unless(Schema::hasTable('system_settings'), 404, 'Run the v2.5.3 update first.');

        $this->ensureAttendanceSettings();

        return view('settings.core.index', [
            'groups' => $this->coreSettingsQuery()->get()->groupBy('group'),
        ]);
    }

    public function update(Request $request)
    {
        $this->authorizeSystemAdministrator($request);
        abort_unless(Schema::hasTable('system_settings'), 404, 'Run the v2.5.3 update first.');

        $this->ensureAttendanceSettings();

        $settings = $this->coreSettingsQuery()->get();
        $submitted = $request->input('settings', []);
        $errors = [];

        foreach ($settings as $setting) {
            $key = $setting->key;
            $value = $submitted[$key] ?? null;

            if ($setting->type === 'boolean') {
                $value = $request->boolean('settings.' . $key) ? '1' : '0';
            } elseif ($setting->type === 'integer') {
                if ($value !== null && $value !== '' && !is_numeric($value)) {
                    $errors[$key] = $setting->label . ' must be a number.';
                }
                $value = ($value === null || $value === '') ? null : (string) (int) $value;
            } elseif ($setting->type === 'time') {
                $value = trim((string) $value);
                if ($value !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value)) {
                    $errors[$key] = $setting->label . ' must be a valid 24-hour time, for example 06:00 or 15:00.';
                }
                $value = $value === '' ? null : $value;
            } elseif ($setting->type === 'email') {
                $value = trim((string) $value);
                if ($value !== '') {
                    $validator = Validator::make(['email' => $value], ['email' => ['email', 'max:255']]);
                    if ($validator->fails()) {
                        $errors[$key] = $setting->label . ' must be a valid email address.';
                    }
                }
                $value = $value === '' ? null : $value;
            } elseif ($setting->type === 'url') {
                $value = trim((string) $value);
                if ($value !== '') {
                    $validator = Validator::make(['url' => $value], ['url' => ['url', 'max:500']]);
                    if ($validator->fails()) {
                        $errors[$key] = $setting->label . ' must be a valid URL.';
                    }
                }
                $value = $value === '' ? null : $value;
            } else {
                $value = trim((string) $value);
                $value = $value === '' ? null : $value;
            }

            $setting->value = $value;
        }

        if (!empty($errors)) {
            return back()->withErrors($errors)->withInput();
        }

        foreach ($settings as $setting) {
            $setting->save();
        }

        return redirect()->route('core_settings.index')->with('success', 'Core settings updated.');
    }

    private function coreSettingsQuery(): Builder
    {
        return SystemSetting::query()
            ->where('is_core', true)
            ->whereNotIn('group', ['Vehicle Tracking API', 'Google API'])
            ->orderByRaw("FIELD(`group`, 'Attendance', 'Identity', 'Notifications', 'Reminders', 'Documents', 'Security', 'Vehicles')")
            ->orderBy('group')
            ->orderBy('sort_order')
            ->orderBy('label');
    }

    private function ensureAttendanceSettings(): void
    {
        $this->ensureAttendanceTimeSetting(
            key: 'attendance_company_start_time',
            label: 'Office Start Time',
            value: '06:00',
            description: 'Official office start time. Employees checking in after this time are counted late.',
            sortOrder: 10,
            replaceLegacyValues: ['08:00']
        );

        $this->ensureAttendanceTimeSetting(
            key: 'attendance_company_close_time',
            label: 'Office Close Time',
            value: '15:00',
            description: 'Official office close time. Employees checking out before this time are counted as leaving early.',
            sortOrder: 20,
            replaceLegacyValues: ['17:00']
        );
    }

    private function ensureAttendanceTimeSetting(string $key, string $label, string $value, string $description, int $sortOrder, array $replaceLegacyValues = []): void
    {
        $setting = SystemSetting::firstOrNew(['key' => $key]);

        $setting->group = 'Attendance';
        $setting->label = $label;
        $setting->type = 'time';
        $setting->description = $description;
        $setting->sort_order = $sortOrder;
        $setting->is_core = true;

        if (!$setting->exists || $setting->value === null || $setting->value === '' || in_array($setting->value, $replaceLegacyValues, true)) {
            $setting->value = $value;
        }

        $setting->save();
    }

    private function authorizeSystemAdministrator(Request $request): void
    {
        abort_unless($request->user()?->hasRole('system-administrator'), 403, 'Only the System Administrator can access Core Settings.');
    }
}

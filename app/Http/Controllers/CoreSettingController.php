<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
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
            'groups' => SystemSetting::whereNotIn('group', ['Vehicle Tracking API', 'Google API'])->orderBy('group')->orderBy('sort_order')->orderBy('label')->get()->groupBy('group'),
        ]);
    }

    public function update(Request $request)
    {
        $this->authorizeSystemAdministrator($request);
        abort_unless(Schema::hasTable('system_settings'), 404, 'Run the v2.5.3 update first.');

        $this->ensureAttendanceSettings();

        $settings = SystemSetting::whereNotIn('group', ['Vehicle Tracking API', 'Google API'])->orderBy('group')->orderBy('sort_order')->get();
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
                    $errors[$key] = $setting->label . ' must be a valid 24-hour time, for example 08:00 or 17:00.';
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

    private function ensureAttendanceSettings(): void
    {
        $settings = [
            [
                'key' => 'attendance_company_start_time',
                'group' => 'Attendance',
                'label' => 'Company Start Time',
                'value' => '08:00',
                'type' => 'time',
                'description' => 'Official daily start time used to calculate late arrival minutes.',
                'sort_order' => 10,
                'is_core' => true,
            ],
            [
                'key' => 'attendance_company_close_time',
                'group' => 'Attendance',
                'label' => 'Company Close Time',
                'value' => '17:00',
                'type' => 'time',
                'description' => 'Official daily closing time used to calculate early-leave minutes.',
                'sort_order' => 20,
                'is_core' => true,
            ],
        ];

        foreach ($settings as $setting) {
            SystemSetting::firstOrCreate(['key' => $setting['key']], $setting);
        }
    }

    private function authorizeSystemAdministrator(Request $request): void
    {
        abort_unless($request->user()?->hasRole('system-administrator'), 403, 'Only the System Administrator can access Core Settings.');
    }
}

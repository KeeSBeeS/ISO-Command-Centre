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

        return view('settings.core.index', [
            'groups' => SystemSetting::whereNotIn('group', ['Vehicle Tracking API', 'Google API'])->orderBy('group')->orderBy('sort_order')->orderBy('label')->get()->groupBy('group'),
        ]);
    }

    public function update(Request $request)
    {
        $this->authorizeSystemAdministrator($request);
        abort_unless(Schema::hasTable('system_settings'), 404, 'Run the v2.5.3 update first.');

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

    private function authorizeSystemAdministrator(Request $request): void
    {
        abort_unless($request->user()?->hasRole('system-administrator'), 403, 'Only the System Administrator can access Core Settings.');
    }
}

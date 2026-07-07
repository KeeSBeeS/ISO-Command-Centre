<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class GoogleApiSettingController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeSystemAdministrator($request);
        abort_unless(Schema::hasTable('system_settings'), 404, 'Run the v2.5.3 update first.');

        $settings = SystemSetting::where('group', 'Google API')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get()
            ->keyBy('key');

        return view('settings.google_api.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $this->authorizeSystemAdministrator($request);
        abort_unless(Schema::hasTable('system_settings'), 404, 'Run the v2.5.3 update first.');

        $data = $request->validate([
            'google_maps_enabled' => ['nullable', 'boolean'],
            'google_maps_api_key' => ['nullable', 'string', 'max:500'],
            'google_maps_map_id' => ['nullable', 'string', 'max:255'],
            'google_maps_default_latitude' => ['required', 'numeric', 'between:-90,90'],
            'google_maps_default_longitude' => ['required', 'numeric', 'between:-180,180'],
            'google_maps_default_zoom' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $values = [
            'google_maps_enabled' => $request->boolean('google_maps_enabled') ? '1' : '0',
            'google_maps_api_key' => trim((string) ($data['google_maps_api_key'] ?? '')) ?: null,
            'google_maps_map_id' => trim((string) ($data['google_maps_map_id'] ?? '')) ?: null,
            'google_maps_default_latitude' => (string) $data['google_maps_default_latitude'],
            'google_maps_default_longitude' => (string) $data['google_maps_default_longitude'],
            'google_maps_default_zoom' => (string) (int) $data['google_maps_default_zoom'],
        ];

        foreach ($values as $key => $value) {
            SystemSetting::where('key', $key)->update(['value' => $value]);
        }

        return redirect()->route('google_api_settings.index')->with('success', 'Google API settings updated.');
    }

    private function authorizeSystemAdministrator(Request $request): void
    {
        abort_unless($request->user()?->hasRole('system-administrator'), 403, 'Only the System Administrator can manage Google API settings.');
    }
}

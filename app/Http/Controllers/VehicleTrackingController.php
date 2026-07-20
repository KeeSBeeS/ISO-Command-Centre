<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Models\Vehicle;
use App\Models\VehicleTrackingSnapshot;
use App\Services\CartrackFleetApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Throwable;

class VehicleTrackingController extends Controller
{
    public function index(CartrackFleetApiService $cartrack)
    {
        abort_unless(Schema::hasTable('vehicle_tracking_snapshots'), 404, 'Run the v2.6 update first.');

        $vehicles = Vehicle::query()
            ->with(['currentAssignment.user', 'latestTrackingSnapshot'])
            ->orderBy('make')
            ->orderBy('model')
            ->paginate(25);

        return view('vehicle_tracking.index', [
            'vehicles' => $vehicles,
            'configured' => $cartrack->isConfigured(),
        ]);
    }

    public function settings(CartrackFleetApiService $cartrack)
    {
        $this->authorizeSystemAdministrator(request());
        abort_unless(Schema::hasTable('system_settings'), 404, 'Run the v2.5.3 update first.');

        $keys = [
            'cartrack_enabled',
            'cartrack_region',
            'cartrack_base_url',
            'cartrack_username',
            'cartrack_password',
            'cartrack_timeout_seconds',
            'cartrack_sync_odometer',
            'cartrack_sync_location',
            'cartrack_sync_status',
            'cartrack_cron_key',
            'cartrack_last_sync_at',
            'cartrack_last_sync_message',
        ];

        $settings = SystemSetting::whereIn('key', $keys)->orderBy('sort_order')->get()->keyBy('key');

        return view('vehicle_tracking.settings', [
            'settings' => $settings,
            'configured' => $cartrack->isConfigured(),
            'cronUrl' => route('vehicle_tracking.cron', ['key' => SystemSetting::valueFor('cartrack_cron_key', 'change-this-key')]),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $this->authorizeSystemAdministrator($request);
        abort_unless(Schema::hasTable('system_settings'), 404, 'Run the v2.5.3 update first.');

        $data = $request->validate([
            'cartrack_enabled' => ['nullable', 'boolean'],
            'cartrack_region' => ['required', 'string', 'max:20'],
            'cartrack_base_url' => ['required', 'url', 'max:500'],
            'cartrack_username' => ['nullable', 'string', 'max:255'],
            'cartrack_password' => ['nullable', 'string', 'max:255'],
            'cartrack_timeout_seconds' => ['required', 'integer', 'min:5', 'max:120'],
            'cartrack_sync_odometer' => ['nullable', 'boolean'],
            'cartrack_sync_location' => ['nullable', 'boolean'],
            'cartrack_sync_status' => ['nullable', 'boolean'],
            'cartrack_cron_key' => ['required', 'string', 'min:12', 'max:120'],
        ]);

        foreach ([
            'cartrack_enabled',
            'cartrack_sync_odometer',
            'cartrack_sync_location',
            'cartrack_sync_status',
        ] as $booleanKey) {
            $data[$booleanKey] = $request->boolean($booleanKey) ? '1' : '0';
        }

        foreach ($data as $key => $value) {
            SystemSetting::where('key', $key)->update(['value' => $value]);
        }

        return redirect()->route('vehicle_tracking.settings')->with('success', 'Cartrack integration settings updated.');
    }

    public function test(CartrackFleetApiService $cartrack)
    {
        $this->authorizeSystemAdministrator(request());

        try {
            $result = $cartrack->testConnection();
            return redirect()->route('vehicle_tracking.settings')->with('success', $result['message']);
        } catch (Throwable $exception) {
            return redirect()->route('vehicle_tracking.settings')->withErrors(['cartrack' => $exception->getMessage()]);
        }
    }

    public function sync(Request $request, CartrackFleetApiService $cartrack)
    {
        try {
            $result = $cartrack->syncFleet();

            $message = 'Cartrack sync completed. Remote: ' . $result['remote_count'] . ', matched: ' . $result['matched'] . ', unmatched: ' . $result['unmatched'] . ', snapshots: ' . $result['snapshots'] . '.';
            if (!empty($result['unmatched_examples'])) {
                $message .= ' Unmatched examples: ' . implode(', ', $result['unmatched_examples']) . '. Check vehicle registration, Cartrack ID or Cartrack registration link.';
            }
            if (!empty($result['errors'])) {
                return back()->with('warning', $message . ' Errors: ' . implode(' | ', array_slice($result['errors'], 0, 3)));
            }
            if ((int) $result['remote_count'] > 0 && (int) $result['matched'] === 0) {
                return back()->with('warning', $message . ' The API returned vehicles but none matched local vehicles. Open each vehicle and set the Cartrack Registration or Cartrack ID exactly as returned by Cartrack.');
            }

            return back()->with('success', $message);
        } catch (Throwable $exception) {
            return back()->withErrors(['cartrack' => $exception->getMessage()]);
        }
    }

    public function syncVehicle(Vehicle $vehicle, CartrackFleetApiService $cartrack)
    {
        try {
            $result = $cartrack->syncFleet($vehicle);

            return redirect()->route('vehicles.show', $vehicle)->with('success', 'Vehicle tracking sync completed. Snapshots created: ' . $result['snapshots'] . '.');
        } catch (Throwable $exception) {
            return redirect()->route('vehicles.show', $vehicle)->withErrors(['cartrack' => $exception->getMessage()]);
        }
    }

    public function updateVehicleLink(Request $request, Vehicle $vehicle)
    {
        abort_unless(Schema::hasColumn('vehicles', 'cartrack_vehicle_id'), 404, 'Run the v2.6 update first.');

        $data = $request->validate([
            'cartrack_vehicle_id' => ['nullable', 'string', 'max:255'],
            'cartrack_registration' => ['nullable', 'string', 'max:255'],
            'cartrack_external_key' => ['nullable', 'string', 'max:255'],
        ]);

        $vehicle->update(array_merge($data, ['tracking_provider' => 'cartrack']));

        return redirect()->route('vehicles.show', $vehicle)->with('success', 'Cartrack vehicle link updated.');
    }

    public function cron(string $key, CartrackFleetApiService $cartrack)
    {
        abort_unless(Schema::hasTable('system_settings'), 503, 'Run the v2.5.3 update first.');

        $expected = (string) SystemSetting::valueFor('cartrack_cron_key', '');
        abort_unless($expected !== '' && hash_equals($expected, $key), 403);

        try {
            return response()->json(array_merge(['ok' => true], $cartrack->syncFleet()));
        } catch (Throwable $exception) {
            return response()->json(['ok' => false, 'error' => $exception->getMessage()], 500);
        }
    }

    private function authorizeSystemAdministrator(Request $request): void
    {
        abort_unless($request->user()?->hasRole('system-administrator'), 403, 'Only the System Administrator can manage integration settings.');
    }
}

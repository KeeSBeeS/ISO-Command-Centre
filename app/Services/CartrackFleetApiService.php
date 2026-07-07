<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\Vehicle;
use App\Models\VehicleTrackingSnapshot;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CartrackFleetApiService
{
    public function settings(): array
    {
        return [
            'enabled' => (bool) SystemSetting::valueFor('cartrack_enabled', false),
            'region' => strtolower((string) SystemSetting::valueFor('cartrack_region', 'za')),
            'base_url' => rtrim((string) SystemSetting::valueFor('cartrack_base_url', 'https://fleetapi-za.cartrack.com'), '/'),
            'username' => (string) SystemSetting::valueFor('cartrack_username', ''),
            'password' => (string) SystemSetting::valueFor('cartrack_password', ''),
            'timeout' => (int) SystemSetting::valueFor('cartrack_timeout_seconds', 20),
            'sync_odometer' => (bool) SystemSetting::valueFor('cartrack_sync_odometer', true),
            'sync_location' => (bool) SystemSetting::valueFor('cartrack_sync_location', true),
            'sync_status' => (bool) SystemSetting::valueFor('cartrack_sync_status', true),
        ];
    }

    public function isConfigured(): bool
    {
        $settings = $this->settings();

        return $settings['enabled']
            && $settings['base_url'] !== ''
            && $settings['username'] !== ''
            && $settings['password'] !== '';
    }

    public function testConnection(): array
    {
        $statusRows = [];
        $vehicleRows = [];
        $messages = [];

        try {
            $statusRows = $this->fetchVehicleStatusRows();
            $messages[] = '/rest/vehicles/status returned ' . count($statusRows) . ' records.';
        } catch (Throwable $exception) {
            $messages[] = '/rest/vehicles/status failed: ' . $exception->getMessage();
        }

        try {
            $vehicleRows = $this->fetchVehicleListRows();
            $messages[] = '/rest/vehicles returned ' . count($vehicleRows) . ' records.';
        } catch (Throwable $exception) {
            $messages[] = '/rest/vehicles failed: ' . $exception->getMessage();
        }

        if (!$statusRows && !$vehicleRows) {
            throw new RuntimeException('Cartrack connection responded, but no vehicle records were returned. ' . implode(' ', $messages));
        }

        return [
            'ok' => true,
            'message' => 'Connection successful. ' . implode(' ', $messages),
            'vehicle_count' => max(count($statusRows), count($vehicleRows)),
            'status_count' => count($statusRows),
            'vehicle_list_count' => count($vehicleRows),
        ];
    }

    public function fetchVehicles(): array
    {
        $statusRows = [];
        $vehicleRows = [];
        $errors = [];

        try {
            $statusRows = $this->fetchVehicleStatusRows();
        } catch (Throwable $exception) {
            $errors[] = 'status endpoint: ' . $exception->getMessage();
        }

        try {
            $vehicleRows = $this->fetchVehicleListRows();
        } catch (Throwable $exception) {
            $errors[] = 'vehicle list endpoint: ' . $exception->getMessage();
        }

        if ($statusRows && $vehicleRows) {
            return $this->mergeVehicleRows($vehicleRows, $statusRows);
        }

        if ($statusRows) {
            return $statusRows;
        }

        if ($vehicleRows) {
            return $vehicleRows;
        }

        throw new RuntimeException('Cartrack returned no usable vehicle data. ' . implode(' | ', $errors));
    }

    public function fetchVehicleStatusRows(): array
    {
        return $this->normaliseVehicleList($this->requestJson('/rest/vehicles/status'));
    }

    public function fetchVehicleListRows(): array
    {
        return $this->normaliseVehicleList($this->requestJson('/rest/vehicles'));
    }

    public function syncFleet(?Vehicle $specificVehicle = null): array
    {
        if (!Schema::hasTable('vehicle_tracking_snapshots')) {
            throw new RuntimeException('Run the v2.6 update first. The vehicle_tracking_snapshots table is missing.');
        }

        $remoteVehicles = $this->fetchVehicles();
        $matched = 0;
        $unmatched = 0;
        $snapshots = 0;
        $errors = [];
        $unmatchedExamples = [];

        foreach ($remoteVehicles as $remoteVehicle) {
            try {
                $vehicle = $specificVehicle ?: $this->matchLocalVehicle($remoteVehicle);

                if (!$vehicle) {
                    $unmatched++;
                    if (count($unmatchedExamples) < 5) {
                        $unmatchedExamples[] = $this->remoteVehicleLabel($remoteVehicle);
                    }
                    continue;
                }

                if ($specificVehicle && !$this->remoteLooksLikeVehicle($specificVehicle, $remoteVehicle)) {
                    continue;
                }

                $this->storeSnapshot($vehicle, $remoteVehicle);
                $matched++;
                $snapshots++;
            } catch (Throwable $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        $result = [
            'remote_count' => count($remoteVehicles),
            'matched' => $matched,
            'unmatched' => $unmatched,
            'snapshots' => $snapshots,
            'errors' => $errors,
            'unmatched_examples' => $unmatchedExamples,
        ];

        $this->recordSyncDiagnostics($result);

        return $result;
    }

    public function storeSnapshot(Vehicle $vehicle, array $remoteVehicle): VehicleTrackingSnapshot
    {
        $settings = $this->settings();
        $providerVehicleId = $this->extractProviderVehicleId($remoteVehicle);
        $registration = $this->extractRegistration($remoteVehicle);
        $odometer = $this->extractOdometer($remoteVehicle);
        $latitude = $this->firstValue($remoteVehicle, ['latitude', 'lat', 'position.latitude', 'position.lat', 'location.latitude', 'location.lat', 'gps.latitude', 'gps.lat', 'coordinates.latitude', 'last_position.latitude']);
        $longitude = $this->firstValue($remoteVehicle, ['longitude', 'lng', 'lon', 'long', 'position.longitude', 'position.lng', 'position.lon', 'position.long', 'location.longitude', 'location.lng', 'location.lon', 'location.long', 'gps.longitude', 'gps.lng', 'gps.lon', 'gps.long', 'coordinates.longitude', 'coordinates.long', 'last_position.longitude', 'last_position.long']);
        $speed = $this->firstValue($remoteVehicle, ['speed', 'speed_kph', 'speedKmH', 'speed_kmh', 'movement.speed', 'position.speed', 'gps.speed']);
        $ignition = $this->firstValue($remoteVehicle, ['ignition', 'ignition_on', 'ignitionOn', 'ignition_state', 'status.ignition', 'status.ignition_on', 'vehicle_status.ignition']);
        $status = $this->firstValue($remoteVehicle, ['status', 'vehicle_status', 'state', 'movement.status', 'trip_status', 'ignition_status']);
        $address = $this->firstScalarValue($remoteVehicle, ['address', 'location.address', 'position.address', 'gps.address', 'last_position.address', 'formatted_address']);
        $recordedAt = $this->extractRecordedAt($remoteVehicle) ?: now();

        $snapshot = VehicleTrackingSnapshot::create([
            'vehicle_id' => $vehicle->id,
            'provider' => 'cartrack',
            'provider_vehicle_id' => $providerVehicleId,
            'registration_number' => $registration,
            'recorded_at' => $recordedAt,
            'latitude' => is_numeric($latitude) ? $latitude : null,
            'longitude' => is_numeric($longitude) ? $longitude : null,
            'speed' => is_numeric($speed) ? $speed : null,
            'odometer' => is_numeric($odometer) ? (int) $odometer : null,
            'ignition' => $ignition === null ? null : filter_var($ignition, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'status' => is_scalar($status) ? (string) $status : null,
            'address' => is_scalar($address) ? (string) $address : null,
            'raw_payload' => $remoteVehicle,
        ]);

        $vehicleUpdates = [];

        if (Schema::hasColumn('vehicles', 'tracking_provider')) {
            $vehicleUpdates['tracking_provider'] = 'cartrack';
        }
        if (Schema::hasColumn('vehicles', 'cartrack_vehicle_id') && $providerVehicleId) {
            $vehicleUpdates['cartrack_vehicle_id'] = $providerVehicleId;
        }
        if (Schema::hasColumn('vehicles', 'cartrack_registration') && $registration) {
            $vehicleUpdates['cartrack_registration'] = $registration;
        }
        if (Schema::hasColumn('vehicles', 'tracking_last_sync_at')) {
            $vehicleUpdates['tracking_last_sync_at'] = now();
        }
        if ($settings['sync_status'] && Schema::hasColumn('vehicles', 'tracking_last_status')) {
            $vehicleUpdates['tracking_last_status'] = is_scalar($status) ? (string) $status : null;
        }
        if ($settings['sync_location'] && Schema::hasColumn('vehicles', 'tracking_last_latitude')) {
            $vehicleUpdates['tracking_last_latitude'] = is_numeric($latitude) ? $latitude : null;
            $vehicleUpdates['tracking_last_longitude'] = is_numeric($longitude) ? $longitude : null;
            $vehicleUpdates['tracking_last_address'] = is_scalar($address) ? (string) $address : null;
            $vehicleUpdates['tracking_last_speed'] = is_numeric($speed) ? $speed : null;
            $vehicleUpdates['tracking_last_ignition'] = $ignition === null ? null : filter_var($ignition, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }
        if ($settings['sync_odometer'] && $odometer !== null && is_numeric($odometer)) {
            if (Schema::hasColumn('vehicles', 'tracking_last_odometer')) {
                $vehicleUpdates['tracking_last_odometer'] = (int) $odometer;
            }
            if ((int) $odometer > (int) $vehicle->odo) {
                $vehicleUpdates['odo'] = (int) $odometer;
            }
        }
        if (Schema::hasColumn('vehicles', 'tracking_raw_payload')) {
            $vehicleUpdates['tracking_raw_payload'] = json_encode($remoteVehicle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        if (!empty($vehicleUpdates)) {
            $vehicle->update($vehicleUpdates);
        }

        return $snapshot;
    }

    public function matchLocalVehicle(array $remoteVehicle): ?Vehicle
    {
        $providerVehicleId = $this->normaliseKey($this->extractProviderVehicleId($remoteVehicle));
        $registration = $this->normaliseKey($this->extractRegistration($remoteVehicle));
        $name = $this->normaliseKey($this->firstValue($remoteVehicle, ['name', 'vehicle_name', 'vehicleName', 'description', 'label', 'model']));
        $device = $this->normaliseKey($this->firstValue($remoteVehicle, ['device_id', 'deviceId', 'unit_id', 'unitId', 'imei', 'device.imei', 'terminal_id']));

        $vehicles = Vehicle::query()->get();

        foreach ($vehicles as $vehicle) {
            if ($providerVehicleId && Schema::hasColumn('vehicles', 'cartrack_vehicle_id') && $this->normaliseKey($vehicle->cartrack_vehicle_id) === $providerVehicleId) {
                return $vehicle;
            }

            if ($registration && (
                $this->normaliseKey($vehicle->registration_number) === $registration
                || (Schema::hasColumn('vehicles', 'cartrack_registration') && $this->normaliseKey($vehicle->cartrack_registration) === $registration)
                || (Schema::hasColumn('vehicles', 'cartrack_external_key') && $this->normaliseKey($vehicle->cartrack_external_key) === $registration)
            )) {
                return $vehicle;
            }

            if ($device && $this->normaliseKey($vehicle->tracking_device_number) === $device) {
                return $vehicle;
            }

            if ($name && (
                $this->normaliseKey($vehicle->vehicle_key) === $name
                || $this->normaliseKey($vehicle->display_name) === $name
            )) {
                return $vehicle;
            }
        }

        return null;
    }

    public function remoteLooksLikeVehicle(Vehicle $vehicle, array $remoteVehicle): bool
    {
        $providerVehicleId = $this->normaliseKey($this->extractProviderVehicleId($remoteVehicle));
        $registration = $this->normaliseKey($this->extractRegistration($remoteVehicle));
        $device = $this->normaliseKey($this->firstValue($remoteVehicle, ['device_id', 'deviceId', 'unit_id', 'unitId', 'imei', 'device.imei', 'terminal_id']));

        if ($providerVehicleId && Schema::hasColumn('vehicles', 'cartrack_vehicle_id') && $this->normaliseKey($vehicle->cartrack_vehicle_id) === $providerVehicleId) {
            return true;
        }

        if ($registration && (
            $this->normaliseKey($vehicle->registration_number) === $registration
            || (Schema::hasColumn('vehicles', 'cartrack_registration') && $this->normaliseKey($vehicle->cartrack_registration) === $registration)
            || (Schema::hasColumn('vehicles', 'cartrack_external_key') && $this->normaliseKey($vehicle->cartrack_external_key) === $registration)
        )) {
            return true;
        }

        if ($device && $this->normaliseKey($vehicle->tracking_device_number) === $device) {
            return true;
        }

        return false;
    }

    public function extractProviderVehicleId(array $vehicle): ?string
    {
        $value = $this->firstValue($vehicle, ['id', 'vehicle_id', 'vehicleId', 'vehicle.id', 'vehicle.vehicle_id', 'car_id', 'carId', 'asset_id', 'assetId', 'unit_id', 'unitId']);
        return $value !== null && $value !== '' ? (string) $value : null;
    }

    public function extractRegistration(array $vehicle): ?string
    {
        $value = $this->firstValue($vehicle, ['registration', 'registration_number', 'registrationNumber', 'vehicle.registration', 'vehicle.registration_number', 'license_plate', 'licence_plate', 'plate', 'reg', 'fleet_number']);
        return $value !== null && $value !== '' ? (string) $value : null;
    }

    public function extractOdometer(array $vehicle): ?int
    {
        $value = $this->firstValue($vehicle, ['odometer', 'odo', 'odo_current', 'current_odometer', 'mileage', 'total_mileage', 'odometer_reading', 'clock_reading', 'vehicle.odometer', 'position.odometer', 'status.odometer', 'telemetry.odometer']);
        return is_numeric($value) ? (int) round((float) $value) : null;
    }

    private function extractRecordedAt(array $vehicle): ?Carbon
    {
        $value = $this->firstValue($vehicle, ['recorded_at', 'recordedAt', 'updated_at', 'updatedAt', 'position.recorded_at', 'position.timestamp', 'position_time', 'location.timestamp', 'timestamp', 'event_ts', 'gps_time', 'time']);

        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function requestJson(string $path): mixed
    {
        $settings = $this->settings();

        if (!$this->isConfigured()) {
            throw new RuntimeException('Cartrack integration is not fully configured. Enable it and set the base URL, username and API password.');
        }

        $response = Http::acceptJson()
            ->timeout(max(5, $settings['timeout']))
            ->withBasicAuth($settings['username'], $settings['password'])
            ->get($settings['base_url'] . $path);

        if ($response->status() === 401) {
            throw new RuntimeException('Cartrack returned 401 Unauthorized. Check the username, API password and regional base URL.');
        }

        if ($response->status() === 403) {
            throw new RuntimeException('Cartrack returned 403 Forbidden. The API user may not have access to this endpoint.');
        }

        if (!$response->successful()) {
            throw new RuntimeException('Cartrack API request failed on ' . $path . ' with HTTP ' . $response->status() . '.');
        }

        return $response->json();
    }

    private function mergeVehicleRows(array $vehicleRows, array $statusRows): array
    {
        $baseByKey = [];

        foreach ($vehicleRows as $row) {
            $key = $this->remoteRowKey($row);
            if ($key) {
                $baseByKey[$key] = $row;
            }
        }

        $merged = [];
        foreach ($statusRows as $row) {
            $key = $this->remoteRowKey($row);
            $merged[] = $key && isset($baseByKey[$key]) ? array_replace_recursive($baseByKey[$key], $row) : $row;
            if ($key) {
                unset($baseByKey[$key]);
            }
        }

        foreach ($baseByKey as $row) {
            $merged[] = $row;
        }

        return $merged;
    }

    private function remoteRowKey(array $row): ?string
    {
        return $this->normaliseKey($this->extractRegistration($row))
            ?: $this->normaliseKey($this->extractProviderVehicleId($row))
            ?: $this->normaliseKey($this->firstValue($row, ['name', 'vehicle_name', 'vehicleName', 'description', 'label']));
    }

    private function remoteVehicleLabel(array $row): string
    {
        return (string) ($this->extractRegistration($row)
            ?: $this->extractProviderVehicleId($row)
            ?: $this->firstValue($row, ['name', 'vehicle_name', 'vehicleName', 'description', 'label'])
            ?: 'Unknown remote vehicle');
    }

    private function normaliseVehicleList(mixed $payload): array
    {
        if (!is_array($payload)) {
            return [];
        }

        if ($this->looksLikeVehicleRow($payload)) {
            return [$payload];
        }

        foreach (['data', 'vehicles', 'items', 'results', 'result', 'response', 'rows'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                $rows = $this->normaliseVehicleList($payload[$key]);
                if ($rows) {
                    return $rows;
                }
            }
        }

        if (array_is_list($payload)) {
            $rows = array_values(array_filter($payload, fn ($item) => is_array($item) && $this->looksLikeVehicleRow($item)));
            if ($rows) {
                return $rows;
            }

            return array_values(array_filter($payload, 'is_array'));
        }

        foreach ($payload as $value) {
            if (is_array($value)) {
                $rows = $this->normaliseVehicleList($value);
                if ($rows) {
                    return $rows;
                }
            }
        }

        return [];
    }

    private function looksLikeVehicleRow(array $row): bool
    {
        return $this->extractRegistration($row) !== null
            || $this->extractProviderVehicleId($row) !== null
            || $this->firstValue($row, ['name', 'vehicle_name', 'vehicleName', 'description', 'label']) !== null;
    }

    private function firstScalarValue(array $array, array $keys): mixed
    {
        $value = $this->firstValue($array, $keys);
        return is_scalar($value) ? $value : null;
    }

    private function firstValue(array $array, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = str_contains($key, '.') ? Arr::get($array, $key) : ($array[$key] ?? null);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        foreach ($keys as $key) {
            $needle = Str::of($key)->afterLast('.')->lower()->toString();
            $value = $this->recursiveFindByKey($array, $needle);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function recursiveFindByKey(array $array, string $needle): mixed
    {
        foreach ($array as $key => $value) {
            if (strtolower((string) $key) === $needle) {
                return $value;
            }
            if (is_array($value)) {
                $found = $this->recursiveFindByKey($value, $needle);
                if ($found !== null && $found !== '') {
                    return $found;
                }
            }
        }

        return null;
    }

    private function normaliseKey(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Str::of((string) $value)
            ->upper()
            ->replaceMatches('/[^A-Z0-9]/', '')
            ->toString();
    }

    private function recordSyncDiagnostics(array $result): void
    {
        if (!Schema::hasTable('system_settings')) {
            return;
        }

        $message = 'Remote: ' . $result['remote_count'] . ', matched: ' . $result['matched'] . ', unmatched: ' . $result['unmatched'] . ', snapshots: ' . $result['snapshots'];
        if (!empty($result['unmatched_examples'])) {
            $message .= '. Unmatched examples: ' . implode(', ', $result['unmatched_examples']);
        }
        if (!empty($result['errors'])) {
            $message .= '. Errors: ' . implode(' | ', array_slice($result['errors'], 0, 3));
        }

        foreach ([
            'cartrack_last_sync_at' => now()->format('Y-m-d H:i:s'),
            'cartrack_last_sync_message' => $message,
        ] as $key => $value) {
            SystemSetting::updateOrCreate(
                ['key' => $key],
                [
                    'group' => 'Vehicle Tracking',
                    'label' => Str::of($key)->replace('_', ' ')->title()->toString(),
                    'value' => $value,
                    'type' => 'text',
                    'description' => 'Cartrack sync diagnostic value.',
                    'sort_order' => 90,
                    'is_core' => true,
                ]
            );
        }
    }
}

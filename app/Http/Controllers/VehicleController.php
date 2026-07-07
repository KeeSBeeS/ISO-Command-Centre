<?php

namespace App\Http\Controllers;

use App\Models\EmployeeDocument;
use App\Models\User;
use App\Models\SystemSetting;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use App\Models\VehicleDocument;
use App\Models\VehicleFuelUp;
use App\Models\VehicleServiceRecord;
use App\Models\VehicleTrackingSnapshot;
use App\Services\VehicleFuelCsvImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        $vehicles = Vehicle::query()
            ->with(['currentAssignment.user', 'latestFuelUp'])
            ->withCount('fuelUps')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(function ($q) use ($search) {
                    $q->where('make', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhere('registration_number', 'like', "%{$search}%")
                        ->orWhere('vehicle_key', 'like', "%{$search}%")
                        ->orWhere('colour', 'like', "%{$search}%")
                        ->orWhere('tracking_company_name', 'like', "%{$search}%")
                        ->orWhere('tracking_device_number', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $fuelDashboard = $this->fleetFuelDashboard();
        $googleMaps = $this->googleMapsConfig($request);
        $fleetMapVehicles = collect();
        $isoFleetMapConfig = $this->googleMapClientConfig($googleMaps, 7);

        if ($googleMaps['enabled'] && $request->user()?->hasPermission('vehicle_tracking.view') && Schema::hasColumn('vehicles', 'tracking_last_latitude')) {
            $fleetMapVehicles = Vehicle::query()
                ->with('currentAssignment.user')
                ->whereNotNull('tracking_last_latitude')
                ->whereNotNull('tracking_last_longitude')
                ->where('status', 'active')
                ->orderBy('make')
                ->orderBy('model')
                ->limit(500)
                ->get()
                ->map(fn (Vehicle $vehicle) => $this->vehicleMapPayload($vehicle))
                ->filter(fn (array $vehicle) => $vehicle['latitude'] !== null && $vehicle['longitude'] !== null)
                ->values();
        }

        return view('vehicles.index', compact('vehicles', 'fuelDashboard', 'googleMaps', 'fleetMapVehicles', 'isoFleetMapConfig'));
    }

    public function create()
    {
        return view('vehicles.create', ['vehicle' => new Vehicle(['status' => 'active'])]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedVehicle($request);
        $data = $this->filterVehicleServiceColumns($data);
        $data['created_by'] = $request->user()->id;

        $vehicle = Vehicle::create($data);

        return redirect()->route('vehicles.show', $vehicle)->with('success', 'Vehicle created.');
    }

    public function show(Vehicle $vehicle)
    {
        $loads = [
            'assignments.user.documents',
            'assignments.assignedBy',
            'currentAssignment.user.documents',
            'fuelUps.uploadedBy',
            'documents.uploader',
        ];

        if (Schema::hasTable('vehicle_service_records')) {
            $loads[] = 'serviceRecords.recordedBy';
        }

        if (Schema::hasTable('vehicle_tracking_snapshots')) {
            $loads[] = 'latestTrackingSnapshot';
        }

        $vehicle->load($loads);

        $googleMaps = $this->googleMapsConfig(request());
        $trackingHistory = collect();
        $vehicleMapPoint = null;
        $isoVehicleMapConfig = $this->googleMapClientConfig($googleMaps, 12);

        if ($googleMaps['enabled'] && request()->user()?->hasPermission('vehicle_tracking.view') && Schema::hasTable('vehicle_tracking_snapshots')) {
            $trackingHistory = VehicleTrackingSnapshot::query()
                ->where('vehicle_id', $vehicle->id)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->orderByDesc('recorded_at')
                ->limit(250)
                ->get()
                ->sortBy('recorded_at')
                ->values()
                ->map(fn (VehicleTrackingSnapshot $snapshot) => [
                    'latitude' => $snapshot->latitude !== null ? (float) $snapshot->latitude : null,
                    'longitude' => $snapshot->longitude !== null ? (float) $snapshot->longitude : null,
                    'recorded_at' => optional($snapshot->recorded_at)->format('Y-m-d H:i'),
                    'speed' => $snapshot->speed !== null ? (float) $snapshot->speed : null,
                    'odometer' => $snapshot->odometer,
                    'status' => $snapshot->status,
                    'address' => $snapshot->address,
                ])
                ->filter(fn (array $point) => $point['latitude'] !== null && $point['longitude'] !== null)
                ->values();

            $vehicleMapPoint = $this->vehicleMapPayload($vehicle);
        }

        $employees = User::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('vehicles.show', [
            'vehicle' => $vehicle,
            'employees' => $employees,
            'currentUserHasPolicy' => $vehicle->currentAssignment?->user ? $this->hasValidVehiclePolicy($vehicle->currentAssignment->user) : null,
            'vehicleServicesReady' => Schema::hasTable('vehicle_service_records') && Schema::hasColumn('vehicles', 'service_interval_km'),
            'googleMaps' => $googleMaps,
            'trackingHistory' => $trackingHistory,
            'vehicleMapPoint' => $vehicleMapPoint,
            'isoVehicleMapConfig' => $isoVehicleMapConfig,
        ]);
    }

    public function edit(Vehicle $vehicle)
    {
        return view('vehicles.edit', compact('vehicle'));
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        $vehicle->update($this->filterVehicleServiceColumns($this->validatedVehicle($request)));

        return redirect()->route('vehicles.show', $vehicle)->with('success', 'Vehicle updated.');
    }

    public function destroy(Vehicle $vehicle)
    {
        $vehicle->update(['status' => 'inactive']);
        $vehicle->assignments()->active()->update(['status' => 'inactive', 'unassigned_at' => now()]);

        return redirect()->route('vehicles.index')->with('success', 'Vehicle marked as inactive.');
    }

    public function assign(Request $request, Vehicle $vehicle)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'assigned_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $employee = User::findOrFail($data['user_id']);
        $hasPolicy = $this->hasValidVehiclePolicy($employee);

        $vehicle->assignments()->active()->update([
            'status' => 'inactive',
            'unassigned_at' => now(),
        ]);

        VehicleAssignment::create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $employee->id,
            'assigned_by' => $request->user()->id,
            'assigned_at' => $data['assigned_at'] ?? now(),
            'status' => 'active',
            'policy_warning' => !$hasPolicy,
            'notes' => $data['notes'] ?? null,
        ]);

        $message = 'Vehicle assigned to ' . $employee->name . '.';
        if (!$hasPolicy) {
            return redirect()->route('vehicles.show', $vehicle)
                ->with('success', $message)
                ->with('warning', 'Outstanding document: this person does not have a valid active Vehicle Policy on their employee profile.');
        }

        return redirect()->route('vehicles.show', $vehicle)->with('success', $message);
    }

    public function unassign(Request $request, Vehicle $vehicle)
    {
        $vehicle->assignments()->active()->update([
            'status' => 'inactive',
            'unassigned_at' => now(),
        ]);

        return redirect()->route('vehicles.show', $vehicle)->with('success', 'Vehicle unassigned.');
    }

    public function fuelSelect(Request $request)
    {
        $vehicles = Vehicle::query()
            ->with(['currentAssignment.user', 'latestFuelUp'])
            ->withCount('fuelUps')
            ->where('status', 'active')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(function ($q) use ($search) {
                    $q->where('make', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhere('registration_number', 'like', "%{$search}%")
                        ->orWhere('vehicle_key', 'like', "%{$search}%")
                        ->orWhere('cartrack_registration', 'like', "%{$search}%");
                });
            })
            ->orderBy('make')
            ->orderBy('model')
            ->paginate(20)
            ->withQueryString();

        return view('vehicles.fuel_select', compact('vehicles'));
    }

    public function fuelSelectProceed(Request $request)
    {
        $data = $request->validate([
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
        ]);

        $vehicle = Vehicle::where('status', 'active')->findOrFail($data['vehicle_id']);

        return redirect()->route('vehicles.fuel.create', $vehicle);
    }

    public function fuelCreate(Vehicle $vehicle)
    {
        $previousOdometer = $this->previousFuelOdometer($vehicle);

        return view('vehicles.fuel_create', compact('vehicle', 'previousOdometer'));
    }

    public function fuelStore(Request $request, Vehicle $vehicle)
    {
        $data = $request->validate([
            'fuelup_date' => ['required', 'date'],
            'odometer' => ['required', 'integer', 'min:0'],
            'litres' => ['required', 'numeric', 'min:0.01'],
            'price_per_litre' => ['nullable', 'numeric', 'min:0'],
            'total_cost' => ['nullable', 'numeric', 'min:0'],
            'brand' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'partial_fuelup' => ['nullable', 'boolean'],
            'missed_fuelup' => ['nullable', 'boolean'],
        ]);

        $odometer = (int) $data['odometer'];
        $litres = (float) $data['litres'];
        $price = isset($data['price_per_litre']) ? (float) $data['price_per_litre'] : null;
        $previousOdometer = $this->previousFuelOdometer($vehicle, $odometer);
        $km = ($previousOdometer !== null && $odometer > $previousOdometer) ? (float) ($odometer - $previousOdometer) : null;
        $kmPerLitre = ($km && $litres) ? round($km / $litres, 2) : null;
        $totalCost = $data['total_cost'] ?? (($price && $litres) ? round($price * $litres, 2) : null);

        $hash = hash('sha256', implode('|', [
            $vehicle->id,
            'manual',
            $data['fuelup_date'],
            $odometer,
            $litres,
            $price,
            now()->timestamp,
        ]));

        VehicleFuelUp::create([
            'vehicle_id' => $vehicle->id,
            'uploaded_by' => $request->user()->id,
            'source' => 'manual',
            'car_name' => $vehicle->vehicle_key,
            'model_name' => $vehicle->display_name,
            'km_per_litre' => $kmPerLitre,
            'odometer' => $odometer,
            'km' => $km,
            'litres' => $litres,
            'price_per_litre' => $price,
            'total_cost' => $totalCost,
            'fuelup_date' => $data['fuelup_date'],
            'date_added' => now(),
            'notes' => $data['notes'] ?? null,
            'missed_fuelup' => $request->boolean('missed_fuelup'),
            'partial_fuelup' => $request->boolean('partial_fuelup'),
            'brand' => $data['brand'] ?? null,
            'source_row_hash' => $hash,
        ]);

        if ($odometer > (int) $vehicle->odo) {
            $vehicle->update(['odo' => $odometer]);
        }

        $message = 'Fuel-up added.';
        if ($km === null) {
            $message .= ' KM travelled could not be calculated because no lower previous odometer reading was found.';
        }

        $redirect = redirect()->route('vehicles.show', $vehicle->fresh())->with('success', $message);
        if (Schema::hasTable('vehicle_service_records')) {
            $summary = $vehicle->fresh()->service_summary;
            if (in_array($summary['state'], ['overdue', 'due-soon'], true)) {
                $remaining = $summary['km_remaining'];
                $warning = $summary['label'] . '. Next service ODO: ' . number_format((int) $summary['next_service_odo']);
                if ($remaining !== null) {
                    $warning .= ' · KM remaining: ' . number_format((int) $remaining);
                }
                $redirect->with('warning', $warning);
            }
        }

        return $redirect;
    }

    public function fuelImport(Vehicle $vehicle)
    {
        return view('vehicles.fuel_import', compact('vehicle'));
    }

    public function fuelImportStore(Request $request, Vehicle $vehicle, VehicleFuelCsvImporter $importer)
    {
        $data = $request->validate([
            'fuel_csv' => ['required', 'file', 'max:10240', 'mimes:csv,txt'],
        ]);

        $file = $request->file('fuel_csv');
        $result = $importer->import($file->getRealPath(), $vehicle, $request->user(), 'csv_upload');

        return redirect()->route('vehicles.show', $vehicle)->with(
            'success',
            'Fuel CSV imported. Created: ' . $result['created'] . ', duplicates: ' . $result['duplicates'] . ', skipped: ' . $result['skipped'] . '.'
        );
    }

    public function serviceCreate(Vehicle $vehicle)
    {
        abort_unless(Schema::hasTable('vehicle_service_records'), 404, 'Run the v1.7 update first.');

        return view('vehicles.service_create', [
            'vehicle' => $vehicle,
            'latestOdometer' => $vehicle->latest_odometer,
            'serviceSummary' => $vehicle->service_summary,
        ]);
    }

    public function serviceStore(Request $request, Vehicle $vehicle)
    {
        abort_unless(Schema::hasTable('vehicle_service_records'), 404, 'Run the v1.7 update first.');

        $data = $request->validate([
            'service_date' => ['required', 'date'],
            'service_odo' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $interval = (int) ($vehicle->service_interval_km ?? 0);
        $serviceOdo = (int) $data['service_odo'];

        VehicleServiceRecord::create([
            'vehicle_id' => $vehicle->id,
            'recorded_by' => $request->user()->id,
            'service_date' => $data['service_date'],
            'service_odo' => $serviceOdo,
            'next_service_odo_snapshot' => $interval > 0 ? $serviceOdo + $interval : null,
            'notes' => $data['notes'] ?? null,
        ]);

        if ($serviceOdo > (int) $vehicle->odo) {
            $vehicle->update(['odo' => $serviceOdo]);
        }

        return redirect()->route('vehicles.show', $vehicle)->with('success', 'Vehicle service record added.');
    }

    public function serviceReminders(Request $request)
    {
        abort_unless(Schema::hasTable('vehicle_service_records'), 404, 'Run the v1.7 update first.');

        $filter = $request->get('filter', 'due');
        $vehicles = Vehicle::query()
            ->with(['currentAssignment.user'])
            ->where('status', 'active')
            ->orderBy('make')
            ->orderBy('model')
            ->get()
            ->filter(function (Vehicle $vehicle) use ($filter) {
                $summary = $vehicle->service_summary;

                return match ($filter) {
                    'overdue' => $summary['state'] === 'overdue',
                    'missing' => in_array($summary['state'], ['no-baseline', 'not-configured'], true),
                    'all' => true,
                    default => in_array($summary['state'], ['overdue', 'due-soon'], true),
                };
            })
            ->values();

        return view('vehicles.service_reminders', compact('vehicles', 'filter'));
    }

    public function documentCreate(Vehicle $vehicle)
    {
        return view('vehicles.document_create', [
            'vehicle' => $vehicle,
            'types' => VehicleDocument::TYPES,
        ]);
    }

    public function documentStore(Request $request, Vehicle $vehicle)
    {
        $data = $request->validate([
            'document_type' => ['required', 'string', 'in:' . implode(',', array_keys(VehicleDocument::TYPES))],
            'title' => ['required', 'string', 'max:255'],
            'attachment' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,csv,txt'],
            'has_expiry' => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date', 'required_if:has_expiry,1'],
            'remind_days_before' => ['nullable', 'integer', 'min:0', 'max:365'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $file = $request->file('attachment');
        $hasExpiry = $request->boolean('has_expiry');
        $expiresAt = $hasExpiry ? $data['expires_at'] : null;
        $remindDays = $hasExpiry ? (int) ($data['remind_days_before'] ?? 30) : null;
        $reminderDate = ($hasExpiry && $expiresAt) ? Carbon::parse($expiresAt)->subDays($remindDays)->toDateString() : null;

        $safeName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $extension = strtolower($file->getClientOriginalExtension() ?: 'file');
        $storedName = now()->format('YmdHis') . '-' . Str::random(8) . '-' . ($safeName ?: 'vehicle-document') . '.' . $extension;
        $path = $file->storeAs('vehicle-documents/' . $vehicle->id, $storedName);

        VehicleDocument::create([
            'vehicle_id' => $vehicle->id,
            'uploaded_by' => $request->user()->id,
            'document_type' => $data['document_type'],
            'title' => $data['title'],
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'has_expiry' => $hasExpiry,
            'expires_at' => $expiresAt,
            'remind_days_before' => $remindDays,
            'reminder_date' => $reminderDate,
            'status' => 'active',
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('vehicles.show', $vehicle)->with('success', 'Vehicle document uploaded.');
    }

    public function documentDownload(VehicleDocument $document)
    {
        if (!Storage::exists($document->file_path)) {
            abort(404, 'Vehicle document file not found.');
        }

        return Storage::download($document->file_path, $document->original_filename);
    }

    public function documentInactive(VehicleDocument $document)
    {
        $document->update(['status' => 'inactive']);

        return back()->with('success', 'Vehicle document marked as inactive.');
    }

    public function reminders(Request $request)
    {
        $filter = $request->get('filter', 'due');

        $documents = VehicleDocument::query()
            ->with(['vehicle.currentAssignment.user', 'uploader'])
            ->where('has_expiry', true)
            ->when($filter === 'due', function ($query) {
                $query->where('status', 'active')->whereDate('reminder_date', '<=', now()->toDateString());
            })
            ->when($filter === 'expired', function ($query) {
                $query->where('status', 'active')->whereDate('expires_at', '<', now()->toDateString());
            })
            ->when($filter === 'next60', function ($query) {
                $query->where('status', 'active')
                    ->whereDate('expires_at', '>=', now()->toDateString())
                    ->whereDate('expires_at', '<=', now()->addDays(60)->toDateString());
            })
            ->when($filter === 'inactive', fn ($query) => $query->where('status', 'inactive'))
            ->orderByRaw('case when expires_at is null then 1 else 0 end')
            ->orderBy('expires_at')
            ->paginate(25)
            ->withQueryString();

        return view('vehicles.reminders', compact('documents', 'filter'));
    }


    private function fleetFuelDashboard(): array
    {
        $monthStart = now()->startOfMonth()->toDateString();

        $empty = [
            'month_start' => $monthStart,
            'fuel_up_count' => 0,
            'litres' => 0,
            'cost' => 0,
            'average_km_per_litre' => null,
            'vehicles_fuelled' => 0,
            'recent_fuel_ups' => collect(),
            'top_vehicle_fuel' => collect(),
        ];

        if (!Schema::hasTable('vehicle_fuel_ups')) {
            return $empty;
        }

        $stats = VehicleFuelUp::query()
            ->whereDate('fuelup_date', '>=', $monthStart)
            ->selectRaw('COUNT(*) as fuel_up_count, COALESCE(SUM(litres),0) as litres, COALESCE(SUM(total_cost),0) as cost, AVG(km_per_litre) as average_km_per_litre, COUNT(DISTINCT vehicle_id) as vehicles_fuelled')
            ->first();

        $topVehicleFuel = VehicleFuelUp::query()
            ->with('vehicle.currentAssignment.user')
            ->whereDate('fuelup_date', '>=', $monthStart)
            ->selectRaw('vehicle_id, COUNT(*) as fuel_up_count, COALESCE(SUM(litres),0) as litres, COALESCE(SUM(total_cost),0) as cost, AVG(km_per_litre) as average_km_per_litre, MAX(fuelup_date) as last_fuelup_date')
            ->groupBy('vehicle_id')
            ->orderByDesc('cost')
            ->limit(8)
            ->get();

        $recentFuelUps = VehicleFuelUp::query()
            ->with('vehicle.currentAssignment.user')
            ->orderByDesc('fuelup_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return [
            'month_start' => $monthStart,
            'fuel_up_count' => (int) ($stats->fuel_up_count ?? 0),
            'litres' => (float) ($stats->litres ?? 0),
            'cost' => (float) ($stats->cost ?? 0),
            'average_km_per_litre' => $stats->average_km_per_litre !== null ? (float) $stats->average_km_per_litre : null,
            'vehicles_fuelled' => (int) ($stats->vehicles_fuelled ?? 0),
            'recent_fuel_ups' => $recentFuelUps,
            'top_vehicle_fuel' => $topVehicleFuel,
        ];
    }

    private function previousFuelOdometer(Vehicle $vehicle, ?int $newOdometer = null): ?int
    {
        $query = VehicleFuelUp::query()
            ->where('vehicle_id', $vehicle->id)
            ->whereNotNull('odometer');

        if ($newOdometer !== null) {
            $query->where('odometer', '<', $newOdometer);
        }

        $latestFuelOdo = $query->orderByDesc('odometer')->value('odometer');

        if ($latestFuelOdo !== null) {
            return (int) $latestFuelOdo;
        }

        if ($vehicle->odo !== null && $vehicle->odo > 0) {
            if ($newOdometer === null || (int) $vehicle->odo < $newOdometer) {
                return (int) $vehicle->odo;
            }
        }

        return null;
    }

    private function validatedVehicle(Request $request): array
    {
        return $request->validate([
            'make' => ['required', 'string', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
            'year_model' => ['nullable', 'integer', 'min:1900', 'max:' . ((int) date('Y') + 1)],
            'colour' => ['nullable', 'string', 'max:100'],
            'tracking_company_name' => ['nullable', 'string', 'max:255'],
            'tracking_company_contact' => ['nullable', 'string', 'max:255'],
            'tracking_device_number' => ['nullable', 'string', 'max:255'],
            'tracking_notes' => ['nullable', 'string', 'max:3000'],
            'cartrack_vehicle_id' => ['nullable', 'string', 'max:255'],
            'cartrack_registration' => ['nullable', 'string', 'max:255'],
            'cartrack_external_key' => ['nullable', 'string', 'max:255'],
            'odo' => ['required', 'integer', 'min:0'],
            'service_interval_km' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'service_reminder_km' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'vehicle_key' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);
    }


    private function filterVehicleServiceColumns(array $data): array
    {
        foreach ([
            'year_model',
            'colour',
            'tracking_company_name',
            'tracking_company_contact',
            'tracking_device_number',
            'tracking_notes',
            'cartrack_vehicle_id',
            'cartrack_registration',
            'cartrack_external_key',
        ] as $column) {
            if (!Schema::hasColumn('vehicles', $column)) {
                unset($data[$column]);
            }
        }

        if (!Schema::hasColumn('vehicles', 'service_interval_km')) {
            unset($data['service_interval_km'], $data['service_reminder_km']);
            return $data;
        }

        $data['service_interval_km'] = (int) ($data['service_interval_km'] ?? 0);
        $data['service_reminder_km'] = (int) ($data['service_reminder_km'] ?? 0);

        return $data;
    }

    private function googleMapClientConfig(array $googleMaps, int $defaultZoom): array
    {
        return [
            'default_latitude' => $googleMaps['default_latitude'] ?? -26.204103,
            'default_longitude' => $googleMaps['default_longitude'] ?? 28.047305,
            'default_zoom' => $googleMaps['default_zoom'] ?? $defaultZoom,
            'map_id' => $googleMaps['map_id'] ?? null,
        ];
    }

    private function googleMapsConfig(Request $request): array
    {
        $enabled = false;
        $apiKey = null;
        $mapId = null;

        if (Schema::hasTable('system_settings')) {
            $enabled = (bool) SystemSetting::valueFor('google_maps_enabled', false);
            $apiKey = (string) SystemSetting::valueFor('google_maps_api_key', '');
            $mapId = (string) SystemSetting::valueFor('google_maps_map_id', '');
        }

        return [
            'enabled' => $enabled && $apiKey !== '',
            'api_key' => $apiKey,
            'map_id' => $mapId,
            'default_latitude' => (float) (Schema::hasTable('system_settings') ? SystemSetting::valueFor('google_maps_default_latitude', -26.204103) : -26.204103),
            'default_longitude' => (float) (Schema::hasTable('system_settings') ? SystemSetting::valueFor('google_maps_default_longitude', 28.047305) : 28.047305),
            'default_zoom' => (int) (Schema::hasTable('system_settings') ? SystemSetting::valueFor('google_maps_default_zoom', 7) : 7),
        ];
    }

    private function vehicleMapPayload(Vehicle $vehicle): array
    {
        return [
            'id' => $vehicle->id,
            'name' => $vehicle->display_name,
            'registration' => $vehicle->registration_number ?: $vehicle->cartrack_registration,
            'driver' => optional(optional($vehicle->currentAssignment)->user)->name,
            'latitude' => $vehicle->tracking_last_latitude !== null ? (float) $vehicle->tracking_last_latitude : null,
            'longitude' => $vehicle->tracking_last_longitude !== null ? (float) $vehicle->tracking_last_longitude : null,
            'speed' => $vehicle->tracking_last_speed !== null ? (float) $vehicle->tracking_last_speed : null,
            'odometer' => $vehicle->tracking_last_odometer ?: $vehicle->odo,
            'status' => $vehicle->tracking_last_status,
            'ignition' => $vehicle->tracking_last_ignition,
            'address' => $vehicle->tracking_last_address,
            'last_sync' => optional($vehicle->tracking_last_sync_at)->format('Y-m-d H:i'),
            'url' => route('vehicles.show', $vehicle),
        ];
    }

    private function hasValidVehiclePolicy(User $employee): bool
    {
        return EmployeeDocument::query()
            ->where('user_id', $employee->id)
            ->where('document_type', 'vehicle_policy')
            ->where('status', 'active')
            ->where(function ($query) {
                $query->where('has_expiry', false)
                    ->orWhereNull('expires_at')
                    ->orWhereDate('expires_at', '>=', now()->toDateString());
            })
            ->exists();
    }
}

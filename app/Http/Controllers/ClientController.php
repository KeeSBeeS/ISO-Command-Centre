<?php

namespace App\Http\Controllers;

use App\Models\CrmClient;
use App\Models\CrmClientContact;
use App\Models\CrmClientSite;
use App\Models\User;
use App\Services\GoogleMapsDistanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(Schema::hasTable('crm_clients'), 404, 'Run the v2.2 update first.');

        $query = CrmClient::query()->with(['accountManager', 'sites']);

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('client_code', 'like', '%' . $search . '%')
                  ->orWhere('industry', 'like', '%' . $search . '%')
                  ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return view('clients.index', [
            'clients' => $query->withCount(['sites', 'contacts'])->orderBy('name')->paginate(20)->withQueryString(),
            'status' => $status,
            'search' => $search ?? '',
        ]);
    }

    public function create()
    {
        return view('clients.create', [
            'client' => new CrmClient(['status' => 'active', 'client_type' => 'customer']),
            'managers' => $this->assignableUsers(),
        ]);
    }

    public function store(Request $request)
    {
        $client = CrmClient::create($this->validatedClient($request));

        return redirect()->route('clients.show', $client)->with('success', 'Client added.');
    }

    public function show(CrmClient $client, GoogleMapsDistanceService $maps)
    {
        $client->load([
            'accountManager',
            'sites.contacts',
            'contacts' => fn ($q) => $q->whereNull('crm_client_site_id'),
        ]);

        return view('clients.show', [
            'client' => $client,
            'contactTypes' => $this->contactTypes(),
            'mapsConfigured' => $maps->configured(),
            'officeAddress' => \App\Models\PlatformSetting::getValue('company.office_address', ''),
            'maps' => $maps,
        ]);
    }

    public function edit(CrmClient $client)
    {
        return view('clients.edit', [
            'client' => $client,
            'managers' => $this->assignableUsers(),
        ]);
    }

    public function update(Request $request, CrmClient $client)
    {
        $client->update($this->validatedClient($request));

        return redirect()->route('clients.show', $client)->with('success', 'Client updated.');
    }

    public function destroy(CrmClient $client)
    {
        $client->update(['status' => 'inactive']);

        return redirect()->route('clients.index')->with('success', 'Client marked inactive.');
    }

    public function storeSite(Request $request, CrmClient $client)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'site_code' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'in:active,inactive'],
            'location' => ['required', 'string', 'max:1000'],
            'distance_from_office_km' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'google_place_id' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $client->sites()->create($data);

        return redirect()->route('clients.show', $client)->with('success', 'Client site added.');
    }

    public function calculateSiteDistance(CrmClient $client, CrmClientSite $site, GoogleMapsDistanceService $maps)
    {
        abort_unless((int) $site->crm_client_id === (int) $client->id, 404);

        $result = $maps->distanceFromOffice($site->location);
        if (!$result['ok']) {
            return redirect()->route('clients.show', $client)->with('warning', $result['message']);
        }

        $site->update([
            'distance_from_office_km' => $result['distance_km'],
            'maps_distance_minutes' => $result['duration_minutes'],
            'maps_distance_last_checked_at' => now(),
        ]);

        return redirect()->route('clients.show', $client)->with('success', 'Distance updated for ' . $site->name . ': ' . $result['distance_km'] . ' km / ±' . $result['duration_minutes'] . ' min.');
    }

    public function destroySite(CrmClient $client, CrmClientSite $site)
    {
        abort_unless((int) $site->crm_client_id === (int) $client->id, 404);
        $site->delete();

        return redirect()->route('clients.show', $client)->with('success', 'Client site removed.');
    }

    public function storeContact(Request $request, CrmClient $client)
    {
        $data = $this->validatedContact($request);

        if ($request->boolean('is_primary')) {
            $client->contacts()->whereNull('crm_client_site_id')->update(['is_primary' => false]);
        }

        $data['is_primary'] = $request->boolean('is_primary');
        $data['crm_client_site_id'] = null;
        $client->contacts()->create($data);

        return redirect()->route('clients.show', $client)->with('success', 'Client contact added.');
    }

    public function storeSiteContact(Request $request, CrmClient $client, CrmClientSite $site)
    {
        abort_unless((int) $site->crm_client_id === (int) $client->id, 404);

        $data = $this->validatedContact($request);

        if ($request->boolean('is_primary')) {
            $site->contacts()->update(['is_primary' => false]);
        }

        $data['is_primary'] = $request->boolean('is_primary');
        $data['crm_client_site_id'] = $site->id;
        $client->contacts()->create($data);

        return redirect()->route('clients.show', $client)->with('success', 'Site contact added for ' . $site->name . '.');
    }

    public function destroyContact(CrmClient $client, CrmClientContact $contact)
    {
        abort_unless((int) $contact->crm_client_id === (int) $client->id, 404);
        $contact->delete();

        return redirect()->route('clients.show', $client)->with('success', 'Contact removed.');
    }

    private function assignableUsers()
    {
        return User::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    private function validatedClient(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'client_code' => ['nullable', 'string', 'max:100'],
            'client_type' => ['required', 'in:customer,prospect,supplier,partner,other'],
            'industry' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:active,prospect,inactive'],
            'account_manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'distance_from_office_km' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'phone' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function validatedContact(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'contact_type' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:100'],
            'mobile' => ['nullable', 'string', 'max:100'],
            'is_primary' => ['nullable', 'boolean'],
            'status' => ['required', 'in:active,inactive'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function contactTypes(): array
    {
        return [
            'Engineer',
            'Foreman',
            'Stock Controller',
            'Accounts',
            'Buyer',
            'Storeman',
            'Maintenance Manager',
            'Production Manager',
            'Safety Officer',
            'Site Manager',
            'Procurement',
            'Operations Manager',
            'Plant Manager',
            'Other',
        ];
    }
}

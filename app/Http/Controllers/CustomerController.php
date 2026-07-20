<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\CustomerInteraction;
use App\Models\CustomerSite;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(Schema::hasTable('customers'), 404, 'Run the v2.6.10 update first.');

        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status', '');
        $type = $request->query('type', '');

        $customers = Customer::query()
            ->with('accountManager')
            ->withCount(['sites', 'contacts'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('company_name', 'like', "%{$search}%")
                        ->orWhere('customer_code', 'like', "%{$search}%")
                        ->orWhere('contact_person', 'like', "%{$search}%")
                        ->orWhere('industry', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('status', $status))
            ->when(in_array($type, $this->customerTypeValues(), true), fn ($query) => $query->where('customer_type', $type))
            ->orderBy('company_name')
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total' => Customer::count(),
            'active' => Customer::where('status', 'active')->count(),
            'inactive' => Customer::where('status', 'inactive')->count(),
            'prospects' => Customer::where('customer_type', 'prospect')->count(),
        ];

        return view('customers.index', compact('customers', 'search', 'status', 'type', 'stats'));
    }

    public function create()
    {
        abort_unless(Schema::hasTable('customers'), 404, 'Run the v2.6.10 update first.');

        return view('customers.create', [
            'customer' => new Customer(['status' => 'active', 'customer_type' => 'customer']),
            'managers' => $this->assignableUsers(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(Schema::hasTable('customers'), 404, 'Run the v2.6.10 update first.');

        $data = $this->validated($request);
        $data['created_by'] = $request->user()?->id;

        $customer = Customer::create($data);

        return redirect()->route('customers.show', $customer)->with('success', 'Customer created.');
    }

    public function show(Customer $customer)
    {
        $customer->load([
            'accountManager',
            'sites.contacts',
            'companyContacts',
            'interactions.creator',
            'interactions.site',
            'interactions.contact',
        ]);

        return view('customers.show', [
            'customer' => $customer,
            'contactTypes' => $this->contactTypes(),
            'interactionTypes' => $this->interactionTypes(),
        ]);
    }

    public function edit(Customer $customer)
    {
        return view('customers.edit', [
            'customer' => $customer,
            'managers' => $this->assignableUsers(),
        ]);
    }

    public function update(Request $request, Customer $customer)
    {
        $customer->update($this->validated($request, $customer->id));

        return redirect()->route('customers.show', $customer)->with('success', 'Customer updated.');
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();

        return redirect()->route('customers.index')->with('success', 'Customer deleted.');
    }

    public function storeSite(Request $request, Customer $customer)
    {
        $customer->sites()->create($this->validatedSite($request));

        return redirect()->route('customers.show', $customer)->with('success', 'Site added.');
    }

    public function updateSite(Request $request, Customer $customer, CustomerSite $site)
    {
        abort_unless((int) $site->customer_id === (int) $customer->id, 404);

        $site->update($this->validatedSite($request));

        return redirect()->route('customers.show', $customer)->with('success', 'Site updated.');
    }

    public function destroySite(Customer $customer, CustomerSite $site)
    {
        abort_unless((int) $site->customer_id === (int) $customer->id, 404);

        $site->delete();

        return redirect()->route('customers.show', $customer)->with('success', 'Site removed.');
    }

    public function storeContact(Request $request, Customer $customer)
    {
        $data = $this->validatedContact($request);

        if ($request->boolean('is_primary')) {
            $customer->companyContacts()->update(['is_primary' => false]);
        }

        $data['is_primary'] = $request->boolean('is_primary');
        $data['customer_site_id'] = null;
        $customer->contacts()->create($data);

        return redirect()->route('customers.show', $customer)->with('success', 'Contact added.');
    }

    public function storeSiteContact(Request $request, Customer $customer, CustomerSite $site)
    {
        abort_unless((int) $site->customer_id === (int) $customer->id, 404);

        $data = $this->validatedContact($request);

        if ($request->boolean('is_primary')) {
            $site->contacts()->update(['is_primary' => false]);
        }

        $data['is_primary'] = $request->boolean('is_primary');
        $data['customer_site_id'] = $site->id;
        $customer->contacts()->create($data);

        return redirect()->route('customers.show', $customer)->with('success', 'Site contact added for ' . $site->name . '.');
    }

    public function updateContact(Request $request, Customer $customer, CustomerContact $contact)
    {
        abort_unless((int) $contact->customer_id === (int) $customer->id, 404);

        $data = $this->validatedContact($request);

        if ($request->boolean('is_primary')) {
            $customer->contacts()->where('customer_site_id', $contact->customer_site_id)->update(['is_primary' => false]);
        }

        $data['is_primary'] = $request->boolean('is_primary');
        $contact->update($data);

        return redirect()->route('customers.show', $customer)->with('success', 'Contact updated.');
    }

    public function destroyContact(Customer $customer, CustomerContact $contact)
    {
        abort_unless((int) $contact->customer_id === (int) $customer->id, 404);

        $contact->delete();

        return redirect()->route('customers.show', $customer)->with('success', 'Contact removed.');
    }

    public function storeInteraction(Request $request, Customer $customer)
    {
        $data = $this->validatedInteraction($request, $customer);
        $data['created_by'] = $request->user()?->id;

        $customer->interactions()->create($data);

        return redirect()->route('customers.show', $customer)->with('success', 'Interaction logged.');
    }

    public function updateInteraction(Request $request, Customer $customer, CustomerInteraction $interaction)
    {
        abort_unless((int) $interaction->customer_id === (int) $customer->id, 404);

        $interaction->update($this->validatedInteraction($request, $customer));

        return redirect()->route('customers.show', $customer)->with('success', 'Interaction updated.');
    }

    public function destroyInteraction(Customer $customer, CustomerInteraction $interaction)
    {
        abort_unless((int) $interaction->customer_id === (int) $customer->id, 404);

        $interaction->delete();

        return redirect()->route('customers.show', $customer)->with('success', 'Interaction removed.');
    }

    private function assignableUsers()
    {
        return User::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    private function customerTypeValues(): array
    {
        return ['customer', 'prospect', 'supplier', 'partner', 'other'];
    }

    private function validated(Request $request, ?int $ignoreCustomerId = null): array
    {
        $uniqueCode = 'nullable|string|max:80|unique:customers,customer_code';
        if ($ignoreCustomerId) {
            $uniqueCode .= ',' . $ignoreCustomerId;
        }

        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'customer_code' => explode('|', $uniqueCode),
            'customer_type' => ['nullable', 'in:' . implode(',', $this->customerTypeValues())],
            'industry' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'website' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
            'account_manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['customer_type'] = $data['customer_type'] ?? 'customer';

        return $data;
    }

    private function validatedSite(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'site_code' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'in:active,inactive'],
            'location' => ['required', 'string', 'max:1000'],
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

    private function validatedInteraction(Request $request, Customer $customer): array
    {
        $data = $request->validate([
            'type' => ['required', 'in:' . implode(',', array_keys($this->interactionTypes()))],
            'subject' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'occurred_at' => ['required', 'date'],
            'follow_up_at' => ['nullable', 'date'],
            'customer_site_id' => ['nullable', 'integer', 'exists:customer_sites,id'],
            'customer_contact_id' => ['nullable', 'integer', 'exists:customer_contacts,id'],
        ]);

        if (!empty($data['customer_site_id'])) {
            abort_unless(
                $customer->sites()->whereKey($data['customer_site_id'])->exists(),
                422,
                'Selected site does not belong to this customer.'
            );
        }

        if (!empty($data['customer_contact_id'])) {
            abort_unless(
                $customer->contacts()->whereKey($data['customer_contact_id'])->exists(),
                422,
                'Selected contact does not belong to this customer.'
            );
        }

        return $data;
    }

    private function contactTypes(): array
    {
        return [
            'Engineer',
            'Foreman',
            'Stores',
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

    private function interactionTypes(): array
    {
        return [
            'call' => 'Call',
            'email' => 'Email',
            'meeting' => 'Meeting',
            'site_visit' => 'Site Visit',
            'task' => 'Task / Follow-up',
            'note' => 'Note',
        ];
    }
}

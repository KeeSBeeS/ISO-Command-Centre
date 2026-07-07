<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(Schema::hasTable('customers'), 404, 'Run the v2.6.10 update first.');

        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status', '');

        $customers = Customer::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('company_name', 'like', "%{$search}%")
                        ->orWhere('customer_code', 'like', "%{$search}%")
                        ->orWhere('contact_person', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('status', $status))
            ->orderBy('company_name')
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total' => Customer::count(),
            'active' => Customer::where('status', 'active')->count(),
            'inactive' => Customer::where('status', 'inactive')->count(),
        ];

        return view('customers.index', compact('customers', 'search', 'status', 'stats'));
    }

    public function create()
    {
        abort_unless(Schema::hasTable('customers'), 404, 'Run the v2.6.10 update first.');

        return view('customers.create', [
            'customer' => new Customer(['status' => 'active']),
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
        return view('customers.show', compact('customer'));
    }

    public function edit(Customer $customer)
    {
        return view('customers.edit', compact('customer'));
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

    private function validated(Request $request, ?int $ignoreCustomerId = null): array
    {
        $uniqueCode = 'nullable|string|max:80|unique:customers,customer_code';
        if ($ignoreCustomerId) {
            $uniqueCode .= ',' . $ignoreCustomerId;
        }

        return $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'customer_code' => explode('|', $uniqueCode),
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}

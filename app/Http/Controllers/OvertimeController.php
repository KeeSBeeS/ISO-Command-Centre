<?php

namespace App\Http\Controllers;

use App\Models\CrmClient;
use App\Models\CrmClientSite;
use App\Models\OvertimeEntry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class OvertimeController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(Schema::hasTable('overtime_entries'), 404, 'Run the v2.4 update first.');

        $query = OvertimeEntry::query()->with(['employee', 'client', 'site', 'creator']);

        if ($request->filled('employee_id')) {
            $query->where('user_id', $request->integer('employee_id'));
        }
        if ($request->filled('client_id')) {
            $query->where('crm_client_id', $request->integer('client_id'));
        }
        if ($request->filled('month')) {
            $month = Carbon::createFromFormat('Y-m-d', $request->input('month') . '-01');
            $query->whereBetween('overtime_date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()]);
        }

        $entries = $query->orderByDesc('overtime_date')->orderByDesc('id')->paginate(30)->withQueryString();

        return view('overtime.index', [
            'entries' => $entries,
            'employees' => $this->employees(),
            'clients' => CrmClient::query()->where('status', '!=', 'inactive')->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['employee_id', 'client_id', 'month']),
        ]);
    }

    public function create()
    {
        abort_unless(Schema::hasTable('overtime_entries'), 404, 'Run the v2.4 update first.');

        return view('overtime.create', [
            'employees' => $this->employees(),
            'sites' => $this->sites(),
            'entry' => new OvertimeEntry(['overtime_date' => now()->toDateString(), 'status' => 'approved']),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(Schema::hasTable('overtime_entries'), 404, 'Run the v2.4 update first.');

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'crm_client_site_id' => ['required', 'integer', 'exists:crm_client_sites,id'],
            'overtime_date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'hours' => ['nullable', 'numeric', 'min:0.1', 'max:48'],
            'is_installation' => ['nullable', 'boolean'],
            'is_service' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        $site = CrmClientSite::with('client')->findOrFail($data['crm_client_site_id']);
        $hours = $data['hours'] ?? null;

        if (!$hours && !empty($data['start_time']) && !empty($data['end_time'])) {
            $start = Carbon::parse($data['overtime_date'] . ' ' . $data['start_time']);
            $end = Carbon::parse($data['overtime_date'] . ' ' . $data['end_time']);
            if ($end->lessThanOrEqualTo($start)) {
                $end->addDay();
            }
            $hours = round($start->diffInMinutes($end) / 60, 2);
        }

        if (!$hours) {
            return back()->withInput()->withErrors(['hours' => 'Enter overtime hours, or enter start and end time so the system can calculate hours.']);
        }

        $entry = OvertimeEntry::create([
            'user_id' => $data['user_id'],
            'crm_client_id' => $site->crm_client_id,
            'crm_client_site_id' => $site->id,
            'overtime_date' => $data['overtime_date'],
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'hours' => $hours,
            'is_installation' => $request->boolean('is_installation'),
            'is_service' => $request->boolean('is_service'),
            'note' => $data['note'] ?? null,
            'status' => 'approved',
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('overtime.show', $entry)->with('success', 'Overtime entry recorded and added to the calendar.');
    }

    public function show(OvertimeEntry $overtime)
    {
        $overtime->load(['employee', 'client', 'site', 'creator']);

        return view('overtime.show', ['entry' => $overtime]);
    }

    public function destroy(OvertimeEntry $overtime)
    {
        $overtime->update(['status' => 'removed']);

        return redirect()->route('overtime.index')->with('success', 'Overtime entry removed from active records and calendar.');
    }

    private function employees()
    {
        return User::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'email', 'employee_code']);
    }

    private function sites()
    {
        return CrmClientSite::query()
            ->with('client:id,name,status')
            ->where('status', 'active')
            ->whereHas('client', fn ($q) => $q->where('status', '!=', 'inactive'))
            ->orderBy('name')
            ->get();
    }
}

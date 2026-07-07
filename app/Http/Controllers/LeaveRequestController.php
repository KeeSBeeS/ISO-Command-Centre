<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class LeaveRequestController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(Schema::hasTable('leave_requests'), 404, 'Run the v2.6.1 update first.');

        $user = $request->user();
        $query = LeaveRequest::with(['user', 'leaveType', 'reviewer'])->visibleTo($user)->latest('start_date');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('employee') && $user->hasPermission('leave.manage')) {
            $query->whereHas('user', function ($employeeQuery) use ($request) {
                $employeeQuery->where('name', 'like', '%' . $request->employee . '%')
                    ->orWhere('email', 'like', '%' . $request->employee . '%');
            });
        }

        return view('leave.index', [
            'leaveRequests' => $query->paginate(20)->withQueryString(),
            'pendingCount' => LeaveRequest::visibleTo($user)->where('status', 'pending')->count(),
            'approvedCount' => LeaveRequest::visibleTo($user)->where('status', 'approved')->count(),
            'declinedCount' => LeaveRequest::visibleTo($user)->where('status', 'declined')->count(),
        ]);
    }

    public function create(Request $request)
    {
        abort_unless(Schema::hasTable('leave_requests'), 404, 'Run the v2.6.1 update first.');

        return view('leave.create', [
            'leaveTypes' => LeaveType::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'employees' => $request->user()->hasPermission('leave.manage')
                ? User::where('status', 'active')->orderBy('name')->get()
                : collect([$request->user()]),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(Schema::hasTable('leave_requests'), 404, 'Run the v2.6.1 update first.');

        $canManage = $request->user()->hasPermission('leave.manage');
        $data = $request->validate([
            'user_id' => ['nullable', 'exists:users,id'],
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:3000'],
        ]);

        $employeeId = $canManage && !empty($data['user_id']) ? (int) $data['user_id'] : $request->user()->id;
        $leaveType = LeaveType::findOrFail($data['leave_type_id']);
        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->startOfDay();

        LeaveRequest::create([
            'user_id' => $employeeId,
            'leave_type_id' => $leaveType->id,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'total_days' => $start->diffInDays($end) + 1,
            'status' => $canManage ? 'approved' : 'pending',
            'is_deductible' => $leaveType->is_deductible,
            'reason' => $data['reason'] ?? null,
            'reviewed_by' => $canManage ? $request->user()->id : null,
            'reviewed_at' => $canManage ? now() : null,
        ]);

        return redirect()->route('leave.index')->with('success', $canManage ? 'Leave captured and approved.' : 'Leave request submitted for approval.');
    }

    public function show(Request $request, LeaveRequest $leaveRequest)
    {
        $this->authorizeLeaveAccess($request, $leaveRequest);

        return view('leave.show', compact('leaveRequest'));
    }

    public function approve(Request $request, LeaveRequest $leaveRequest)
    {
        abort_unless($request->user()->hasPermission('leave.manage'), 403);

        $data = $request->validate(['manager_notes' => ['nullable', 'string', 'max:3000']]);
        $leaveRequest->update([
            'status' => 'approved',
            'manager_notes' => $data['manager_notes'] ?? $leaveRequest->manager_notes,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return redirect()->route('leave.index')->with('success', 'Leave request approved.');
    }

    public function decline(Request $request, LeaveRequest $leaveRequest)
    {
        abort_unless($request->user()->hasPermission('leave.manage'), 403);

        $data = $request->validate(['manager_notes' => ['nullable', 'string', 'max:3000']]);
        $leaveRequest->update([
            'status' => 'declined',
            'manager_notes' => $data['manager_notes'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return redirect()->route('leave.index')->with('warning', 'Leave request declined.');
    }

    public function cancel(Request $request, LeaveRequest $leaveRequest)
    {
        $this->authorizeLeaveAccess($request, $leaveRequest);

        if (!$request->user()->hasPermission('leave.manage') && $leaveRequest->status !== 'pending') {
            abort(403, 'Only pending leave can be cancelled by the requester.');
        }

        $leaveRequest->update(['status' => 'cancelled']);

        return redirect()->route('leave.index')->with('warning', 'Leave request cancelled.');
    }

    private function authorizeLeaveAccess(Request $request, LeaveRequest $leaveRequest): void
    {
        if ($request->user()->hasPermission('leave.manage')) {
            return;
        }

        abort_unless($leaveRequest->user_id === $request->user()->id, 403);
    }
}

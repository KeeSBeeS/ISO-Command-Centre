<?php

namespace App\Http\Controllers;

use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class EmployeeDocumentController extends Controller
{
    public function create(User $employee)
    {
        return view('employee_documents.create', [
            'employee' => $employee,
            'types' => EmployeeDocument::TYPES,
        ]);
    }

    public function store(Request $request, User $employee)
    {
        $data = $request->validate([
            'document_type' => ['required', 'string', 'in:' . implode(',', array_keys(EmployeeDocument::TYPES))],
            'title' => ['required', 'string', 'max:255'],
            'attachment' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,csv,txt'],
            'has_expiry' => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date', 'required_if:has_expiry,1'],
            'remind_days_before' => ['nullable', 'integer', 'min:0', 'max:365'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $file = $request->file('attachment');
        $hasExpiry = (bool) $request->boolean('has_expiry');
        $expiresAt = $hasExpiry ? $data['expires_at'] : null;
        $remindDays = $hasExpiry ? (int) ($data['remind_days_before'] ?? 30) : null;
        $reminderDate = null;

        if ($hasExpiry && $expiresAt) {
            $reminderDate = Carbon::parse($expiresAt)->subDays($remindDays)->toDateString();
        }

        $safeName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $extension = strtolower($file->getClientOriginalExtension() ?: 'file');
        $storedName = now()->format('YmdHis') . '-' . Str::random(8) . '-' . ($safeName ?: 'document') . '.' . $extension;
        $path = $file->storeAs('employee-documents/' . $employee->id, $storedName);

        EmployeeDocument::create([
            'user_id' => $employee->id,
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

        return redirect()->route('employees.show', $employee)->with('success', 'Employee document uploaded.');
    }

    public function download(EmployeeDocument $document)
    {
        if (!Storage::exists($document->file_path)) {
            abort(404, 'Document file not found.');
        }

        return Storage::download($document->file_path, $document->original_filename);
    }

    public function markInactive(Request $request, EmployeeDocument $document)
    {
        $document->update(['status' => 'inactive']);

        return back()->with('success', 'Document marked as inactive.');
    }

    public function reminders(Request $request)
    {
        $filter = $request->get('filter', 'due');

        $documents = EmployeeDocument::query()
            ->with(['employee.departments', 'uploader'])
            ->where('has_expiry', true)
            ->when($filter === 'due', function ($query) {
                $query->where('status', 'active')
                    ->whereDate('reminder_date', '<=', now()->toDateString());
            })
            ->when($filter === 'expired', function ($query) {
                $query->where('status', 'active')
                    ->whereDate('expires_at', '<', now()->toDateString());
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

        return view('employee_documents.reminders', compact('documents', 'filter'));
    }

    public function sendReminderSummary(string $key)
    {
        $expected = (string) env('DOCUMENT_REMINDER_KEY', '');

        if (!$expected || !hash_equals($expected, $key)) {
            abort(403, 'Invalid document reminder key.');
        }

        $to = trim((string) env('DOCUMENT_REMINDER_EMAIL_TO', ''));
        if (!$to) {
            return response('DOCUMENT_REMINDER_EMAIL_TO is not configured.', 422);
        }

        $documents = EmployeeDocument::query()
            ->with('employee')
            ->reminderDue()
            ->orderBy('expires_at')
            ->limit(100)
            ->get();

        if ($documents->isEmpty()) {
            return response('No employee document reminders due.', 200);
        }

        $lines = [
            'ISO Admin Employee Document Reminder',
            'Generated: ' . now()->format('Y-m-d H:i'),
            '',
            'Documents needing attention:',
        ];

        foreach ($documents as $document) {
            $lines[] = sprintf(
                '- %s | %s | %s | expires %s | reminder %s',
                optional($document->employee)->name ?? 'Unknown employee',
                $document->type_label,
                $document->title,
                optional($document->expires_at)->format('Y-m-d') ?? 'No date',
                optional($document->reminder_date)->format('Y-m-d') ?? 'No date'
            );
        }

        Mail::raw(implode("\n", $lines), function ($message) use ($to) {
            $message->to($to)->subject('ISO Admin: Employee documents needing attention');
        });

        EmployeeDocument::whereIn('id', $documents->pluck('id'))->update(['last_reminder_sent_at' => now()]);

        return response('Document reminder summary sent. Count: ' . $documents->count(), 200);
    }
}

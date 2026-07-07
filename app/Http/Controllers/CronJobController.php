<?php

namespace App\Http\Controllers;

use App\Services\AttendanceMailboxImporter;
use Illuminate\Support\Facades\Schema;

class CronJobController extends Controller
{
    public function index()
    {
        return view('cron_jobs.index', [
            'attendanceKeyConfigured' => (bool) env('ATTENDANCE_IMPORT_KEY'),
            'documentReminderKeyConfigured' => (bool) env('DOCUMENT_REMINDER_KEY'),
            'attendanceRouteReady' => Schema::hasTable('attendance_imports'),
            'documentReminderRouteReady' => Schema::hasTable('employee_documents'),
            'attendanceCronUrl' => env('ATTENDANCE_IMPORT_KEY') ? url('/attendance-email-import/' . env('ATTENDANCE_IMPORT_KEY')) : null,
            'documentCronUrl' => env('DOCUMENT_REMINDER_KEY') ? url('/document-reminders/send/' . env('DOCUMENT_REMINDER_KEY')) : null,
        ]);
    }

    public function runAttendanceEmailImport(AttendanceMailboxImporter $mailboxImporter)
    {
        abort_unless(Schema::hasTable('attendance_imports'), 404, 'Attendance tables are not installed.');

        $result = $mailboxImporter->importUnread();

        if (!$result['ok']) {
            return back()->withErrors(['attendance_email_import' => $result['message']]);
        }

        return back()->with('success', $result['message']);
    }
}

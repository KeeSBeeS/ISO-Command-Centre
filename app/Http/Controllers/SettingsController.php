<?php

namespace App\Http\Controllers;

use App\Models\PlatformSetting;
use App\Services\AttendancePolicyService;
use App\Services\SouthAfricanHolidaySync;
use App\Support\PlatformVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class SettingsController extends Controller
{
    public function index()
    {
        return view('settings.index', [
            'version' => PlatformVersion::VERSION,
            'settings' => [
                'attendance.work_start' => PlatformSetting::getValue('attendance.work_start', '06:00'),
                'attendance.work_end' => PlatformSetting::getValue('attendance.work_end', '15:00'),
                'attendance.workdays' => PlatformSetting::getValue('attendance.workdays', 'monday,tuesday,wednesday,thursday,friday'),
                'calendar.sa_holidays_ics_url' => PlatformSetting::getValue('calendar.sa_holidays_ics_url', SouthAfricanHolidaySync::DEFAULT_ICS_URL),
                'company.office_address' => PlatformSetting::getValue('company.office_address', ''),
                'maps.google_api_key' => PlatformSetting::getValue('maps.google_api_key', ''),
            ],
            'holidayCount' => Schema::hasTable('public_holidays') ? \App\Models\PublicHoliday::count() : 0,
        ]);
    }

    public function update(Request $request, AttendancePolicyService $policyService)
    {
        $data = $request->validate([
            'work_start' => ['required', 'date_format:H:i'],
            'work_end' => ['required', 'date_format:H:i'],
            'workdays' => ['required', 'array', 'min:1'],
            'workdays.*' => ['in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'sa_holidays_ics_url' => ['required', 'url', 'max:1000'],
            'office_address' => ['nullable', 'string', 'max:1000'],
            'google_maps_api_key' => ['nullable', 'string', 'max:255'],
        ]);

        PlatformSetting::setValue('attendance.work_start', $data['work_start'], 'attendance', 'time', 'Attendance expected workday start time.');
        PlatformSetting::setValue('attendance.work_end', $data['work_end'], 'attendance', 'time', 'Attendance expected workday end time.');
        PlatformSetting::setValue('attendance.workdays', implode(',', $data['workdays']), 'attendance', 'csv', 'Comma-separated scheduled workdays.');
        PlatformSetting::setValue('calendar.sa_holidays_ics_url', $data['sa_holidays_ics_url'], 'calendar', 'url', 'South African public holiday iCal feed URL.');
        PlatformSetting::setValue('company.office_address', $data['office_address'] ?? '', 'company', 'address', 'Main office address used for distance calculations.');
        PlatformSetting::setValue('maps.google_api_key', $data['google_maps_api_key'] ?? '', 'maps', 'secret', 'Google Maps API key used for distance calculation.');

        $rebuilt = $policyService->rebuildExistingDays();

        return redirect()->route('settings.index')->with('success', 'Core settings saved. Attendance policy recalculated for ' . $rebuilt . ' existing day(s).');
    }

    public function syncHolidays(SouthAfricanHolidaySync $sync, AttendancePolicyService $policyService)
    {
        $result = $sync->sync();
        $rebuilt = $policyService->rebuildExistingDays();

        return redirect()->route('settings.index')->with('success', $result['message'] . ' Attendance days recalculated: ' . $rebuilt . '. Source: ' . $result['source']);
    }
}

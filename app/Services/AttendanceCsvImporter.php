<?php

namespace App\Services;

use App\Models\AttendanceDay;
use App\Models\AttendanceImport;
use App\Models\AttendanceRawRecord;
use App\Models\PublicHoliday;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AttendanceCsvImporter
{
    public function importString(string $csvContent, array $meta = []): AttendanceImport
    {
        return DB::transaction(function () use ($csvContent, $meta) {
            $import = AttendanceImport::create([
                'source' => $meta['source'] ?? 'upload',
                'source_identifier' => $meta['source_identifier'] ?? null,
                'filename' => $meta['filename'] ?? 'attendance.csv',
                'received_from' => $meta['received_from'] ?? null,
                'received_subject' => $meta['received_subject'] ?? null,
                'received_at' => $meta['received_at'] ?? now(),
                'imported_by' => $meta['imported_by'] ?? null,
                'raw_rows' => 0,
                'matched_rows' => 0,
                'skipped_rows' => 0,
                'day_rows' => 0,
                'status' => 'processing',
                'notes' => null,
            ]);

            [$headers, $rows] = $this->readCsv($csvContent);

            if (!$headers) {
                $import->update([
                    'status' => 'failed',
                    'notes' => 'CSV file is empty or the header row could not be read.',
                ]);

                return $import;
            }

            $result = $this->isDailySummaryCsv($headers)
                ? $this->importDailySummaryRows($headers, $rows, $import)
                : $this->importEventLogRows($headers, $rows, $import);

            $import->update([
                'raw_rows' => $result['raw_rows'],
                'matched_rows' => $result['matched_rows'],
                'skipped_rows' => $result['skipped_rows'],
                'day_rows' => $result['day_rows'],
                'status' => $result['failed'] ? 'failed' : 'completed',
                'notes' => $result['notes'],
            ]);

            return $import;
        });
    }

    private function importEventLogRows(array $headers, array $rows, AttendanceImport $import): array
    {
        $nameIndex = $this->findHeader($headers, ['name']);
        $timeIndex = $this->findHeader($headers, ['time']);
        $statusIndex = $this->findHeader($headers, ['attendance status', 'status']);

        if ($nameIndex === null || $timeIndex === null) {
            return [
                'raw_rows' => 0,
                'matched_rows' => 0,
                'skipped_rows' => count($rows),
                'day_rows' => 0,
                'failed' => true,
                'notes' => 'CSV must contain either Name + Time columns, or the daily export columns Name + Date + Check-In + Check-out.',
            ];
        }

        $rawRows = 0;
        $matchedRows = 0;
        $skippedRows = 0;
        $affected = [];

        foreach ($rows as $row) {
            if (!$this->rowHasValues($row)) {
                continue;
            }

            $rawRows++;
            $name = trim((string) ($row[$nameIndex] ?? ''));
            $timeValue = trim((string) ($row[$timeIndex] ?? ''));
            $status = $statusIndex !== null ? trim((string) ($row[$statusIndex] ?? '')) : null;

            if ($name === '' || $timeValue === '') {
                $skippedRows++;
                continue;
            }

            $employee = $this->findEmployee($name);
            if (!$employee) {
                $skippedRows++;
                continue;
            }

            $recordedAt = $this->parseDateTime($timeValue);
            if (!$recordedAt) {
                $skippedRows++;
                continue;
            }

            $created = $this->storeRawRecord($import, $employee, $name, $status, $recordedAt, 'event');
            if ($created) {
                $matchedRows++;
            }

            $affected[$employee->id . '|' . $recordedAt->toDateString()] = [
                'user_id' => $employee->id,
                'date' => $recordedAt->toDateString(),
            ];
        }

        $dayRows = $this->rebuildAffectedDays($affected);

        return [
            'raw_rows' => $rawRows,
            'matched_rows' => $matchedRows,
            'skipped_rows' => $skippedRows,
            'day_rows' => $dayRows,
            'failed' => false,
            'notes' => $this->importNotes($skippedRows, 'event-log CSV'),
        ];
    }

    private function importDailySummaryRows(array $headers, array $rows, AttendanceImport $import): array
    {
        $personIdIndex = $this->findHeader($headers, ['person id', 'employee id', 'employee code', 'code']);
        $nameIndex = $this->findHeader($headers, ['name']);
        $dateIndex = $this->findHeader($headers, ['date']);
        $statusIndex = $this->findHeader($headers, ['attendance status', 'status']);
        $checkInIndex = $this->findHeader($headers, ['check-in', 'check in', 'clock-in', 'clock in']);
        $checkOutIndex = $this->findHeader($headers, ['check-out', 'check out', 'checkout', 'clock-out', 'clock out']);

        if ($nameIndex === null || $dateIndex === null || $checkInIndex === null) {
            return [
                'raw_rows' => 0,
                'matched_rows' => 0,
                'skipped_rows' => count($rows),
                'day_rows' => 0,
                'failed' => true,
                'notes' => 'Daily attendance CSV must contain Name, Date and Check-In columns.',
            ];
        }

        $rawRows = 0;
        $matchedRows = 0;
        $skippedRows = 0;
        $affected = [];

        foreach ($rows as $row) {
            if (!$this->rowHasValues($row)) {
                continue;
            }

            $firstCell = trim((string) ($row[0] ?? ''));
            if (Str::startsWith(Str::lower($firstCell), ['check-in time:', 'check-out time:', 'attended duration:'])) {
                continue;
            }

            $name = trim((string) ($row[$nameIndex] ?? ''));
            $dateValue = trim((string) ($row[$dateIndex] ?? ''));
            if ($name === '' || $dateValue === '') {
                continue;
            }

            $rawRows++;
            $personId = $personIdIndex !== null ? $this->cleanPersonId((string) ($row[$personIdIndex] ?? '')) : null;
            $employee = $this->findEmployee($name, $personId);
            if (!$employee) {
                $skippedRows++;
                continue;
            }

            $date = $this->parseDate($dateValue);
            if (!$date) {
                $skippedRows++;
                continue;
            }

            $status = $statusIndex !== null ? trim((string) ($row[$statusIndex] ?? '')) : null;
            $checkInValues = $this->extractTimeValues((string) ($row[$checkInIndex] ?? ''));
            $checkOutValues = $checkOutIndex !== null ? $this->extractTimeValues((string) ($row[$checkOutIndex] ?? '')) : [];
            $timesStored = 0;

            foreach ($checkInValues as $timeValue) {
                $recordedAt = $this->combineDateAndTime($date, $timeValue);
                if ($recordedAt && $this->storeRawRecord($import, $employee, $name, trim(($status ?: 'Attendance') . ' Check-In'), $recordedAt, 'check-in')) {
                    $timesStored++;
                }
            }

            foreach ($checkOutValues as $timeValue) {
                $recordedAt = $this->combineDateAndTime($date, $timeValue);
                if ($recordedAt && $this->storeRawRecord($import, $employee, $name, trim(($status ?: 'Attendance') . ' Check-Out'), $recordedAt, 'check-out')) {
                    $timesStored++;
                }
            }

            $dateString = $date->toDateString();
            $affected[$employee->id . '|' . $dateString] = [
                'user_id' => $employee->id,
                'date' => $dateString,
                'import_id' => $import->id,
                'summary' => [
                    'status' => $status,
                    'name' => $name,
                ],
            ];

            if ($timesStored > 0 || $this->upsertNoPunchDay($employee, $dateString, $import->id, $name, $status)) {
                $matchedRows++;
            }
        }

        $dayRows = $this->rebuildAffectedDays($affected);

        return [
            'raw_rows' => $rawRows,
            'matched_rows' => $matchedRows,
            'skipped_rows' => $skippedRows,
            'day_rows' => $dayRows,
            'failed' => false,
            'notes' => $this->importNotes($skippedRows, 'daily summary CSV with Date, Check-In and Check-out columns'),
        ];
    }

    private function rebuildAffectedDays(array $affected): int
    {
        $dayRows = 0;
        foreach ($affected as $item) {
            $existingDay = AttendanceDay::query()
                ->where('user_id', $item['user_id'])
                ->whereDate('attendance_date', $item['date'])
                ->first();

            $this->rebuildDay((int) $item['user_id'], $item['date'], $item['import_id'] ?? $existingDay?->attendance_import_id);
            $dayRows++;
        }

        return $dayRows;
    }

    private function storeRawRecord(AttendanceImport $import, User $employee, string $name, ?string $status, Carbon $recordedAt, string $eventType): bool
    {
        $hash = $this->rowHash($employee->id, $name, $recordedAt->format('Y-m-d H:i:s'), $status, $eventType);

        $record = AttendanceRawRecord::firstOrCreate(
            ['source_row_hash' => $hash],
            [
                'attendance_import_id' => $import->id,
                'user_id' => $employee->id,
                'employee_name' => $name,
                'attendance_status' => $status ?: null,
                'recorded_at' => $recordedAt,
                'attendance_date' => $recordedAt->toDateString(),
            ]
        );

        return $record->wasRecentlyCreated;
    }

    private function upsertNoPunchDay(User $employee, string $date, ?int $importId, string $sourceName, ?string $status): bool
    {
        $recordsExist = AttendanceRawRecord::query()
            ->where('user_id', $employee->id)
            ->whereDate('attendance_date', $date)
            ->exists();

        if ($recordsExist) {
            return false;
        }

        $publicHoliday = $this->publicHolidayFor($date);
        $isPublicHoliday = (bool) $publicHoliday;
        $anomalies = [];

        if ($isPublicHoliday) {
            $anomalies[] = 'Public holiday / company closed: ' . $publicHoliday->name . '. Attendance is retained for audit only.';
        } else {
            $anomalies[] = 'No check-in or checkout time found in CSV.';
            if ($status) {
                $anomalies[] = 'CSV status: ' . $status . '.';
            }
        }

        $payload = [
            'start_time' => null,
            'end_time' => null,
            'first_status' => $status ?: 'No punch',
            'last_status' => null,
            'record_count' => 0,
            'work_minutes' => 0,
            'attendance_import_id' => $importId,
            'source_names' => $sourceName,
            'anomalies' => implode(' ', $anomalies),
        ];

        if (Schema::hasColumn('attendance_days', 'is_late')) {
            $payload['is_late'] = false;
            $payload['late_minutes'] = 0;
        }
        if (Schema::hasColumn('attendance_days', 'is_public_holiday')) {
            $payload['is_public_holiday'] = $isPublicHoliday;
            $payload['public_holiday_name'] = $publicHoliday?->name;
        }

        AttendanceDay::updateOrCreate(
            ['user_id' => $employee->id, 'attendance_date' => $date],
            $payload
        );

        return true;
    }

    private function readCsv(string $content): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content ?? '');
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return [[], []];
        }

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return [$headers, $rows];
    }

    private function isDailySummaryCsv(array $headers): bool
    {
        return $this->findHeader($headers, ['date']) !== null
            && $this->findHeader($headers, ['check-in', 'check in', 'clock-in', 'clock in']) !== null
            && $this->findHeader($headers, ['name']) !== null;
    }

    private function findHeader(array $headers, array $names): ?int
    {
        $normalised = array_map(fn ($value) => $this->normaliseHeader((string) $value), $headers);
        foreach ($names as $name) {
            $index = array_search($this->normaliseHeader($name), $normalised, true);
            if ($index !== false) {
                return $index;
            }
        }

        return null;
    }

    private function normaliseHeader(string $value): string
    {
        $value = Str::lower(trim($value));
        $value = str_replace(['_', '–', '—'], [' ', '-', '-'], $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return $value;
    }

    private function rowHasValues(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function findEmployee(string $attendanceName, ?string $personId = null): ?User
    {
        $name = trim($attendanceName);
        $query = User::query()->where('status', 'active');

        if ($personId) {
            $personId = $this->cleanPersonId($personId);
            if (Schema::hasColumn('users', 'employee_code')) {
                $employee = (clone $query)->where('employee_code', $personId)->first();
                if ($employee) {
                    return $employee;
                }
            }
            if (Schema::hasColumn('users', 'attendance_employee_code')) {
                $employee = (clone $query)->where('attendance_employee_code', $personId)->first();
                if ($employee) {
                    return $employee;
                }
            }
        }

        if (Schema::hasColumn('users', 'attendance_name')) {
            $employee = (clone $query)->where('attendance_name', $name)->first();
            if ($employee) {
                return $employee;
            }
        }

        $employee = (clone $query)->where('name', $name)->first();
        if ($employee) {
            return $employee;
        }

        $normalised = Str::lower($name);
        if (Schema::hasColumn('users', 'attendance_name')) {
            $employee = (clone $query)->whereRaw('LOWER(attendance_name) = ?', [$normalised])->first();
            if ($employee) {
                return $employee;
            }
        }

        return (clone $query)->whereRaw('LOWER(name) = ?', [$normalised])->first();
    }

    private function cleanPersonId(string $value): ?string
    {
        $value = trim($value);
        $value = trim($value, "' \t\n\r\0\x0B");

        return $value !== '' ? $value : null;
    }

    private function parseDate(string $value): ?Carbon
    {
        $value = trim($value);
        $formats = ['Y-m-d', 'Y/m/d', 'd/m/Y', 'd-m-Y', 'm/d/Y'];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->startOfDay();
            } catch (\Throwable $e) {
                // Try next format.
            }
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function extractTimeValues(string $value): array
    {
        $value = trim($value);
        if ($value === '' || $value === '-') {
            return [];
        }

        preg_match_all('/\b\d{1,2}:\d{2}(?::\d{2})?\b/', $value, $matches);

        return array_values(array_unique($matches[0] ?? []));
    }

    private function combineDateAndTime(Carbon $date, string $time): ?Carbon
    {
        foreach (['H:i:s', 'H:i', 'G:i:s', 'G:i'] as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $time);
                return $date->copy()->setTime((int) $parsed->format('H'), (int) $parsed->format('i'), (int) $parsed->format('s'));
            } catch (\Throwable $e) {
                // Try next format.
            }
        }

        return null;
    }

    private function parseDateTime(string $value): ?Carbon
    {
        $value = trim($value);
        $formats = [
            'Y-m-d H:i:s',
            'Y/m/d H:i:s',
            'd/m/Y H:i:s',
            'd-m-Y H:i:s',
            'Y-m-d H:i',
            'd/m/Y H:i',
            'Y-m-d H:i:sP',
            'Y-m-d H:iP',
        ];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $value);
            } catch (\Throwable $e) {
                // Try next format.
            }
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function rowHash(int $userId, string $name, string $time, ?string $status, string $eventType = 'event'): string
    {
        return hash('sha256', implode('|', [$userId, Str::lower(trim($name)), trim($time), Str::lower(trim((string) $status)), $eventType]));
    }

    private function importNotes(int $skippedRows, string $format): ?string
    {
        $notes = 'Detected ' . $format . '.';
        if ($skippedRows > 0) {
            $notes .= ' ' . $skippedRows . ' row(s) skipped because the employee did not exist or the row was invalid.';
        }

        return $notes;
    }

    public function rebuildAllExistingDays(): int
    {
        if (!Schema::hasTable('attendance_raw_records')) {
            return 0;
        }

        $items = AttendanceRawRecord::query()
            ->select('user_id', 'attendance_date')
            ->whereNotNull('user_id')
            ->distinct()
            ->get();

        $rebuilt = 0;
        foreach ($items as $item) {
            $existingDay = AttendanceDay::query()
                ->where('user_id', $item->user_id)
                ->whereDate('attendance_date', $item->attendance_date)
                ->first();

            $this->rebuildDay((int) $item->user_id, $item->attendance_date->toDateString(), $existingDay?->attendance_import_id);
            $rebuilt++;
        }

        return $rebuilt;
    }

    public function rebuildDay(int $userId, string $date, ?int $importId = null): void
    {
        $records = AttendanceRawRecord::query()
            ->where('user_id', $userId)
            ->whereDate('attendance_date', $date)
            ->orderBy('recorded_at')
            ->get();

        if ($records->isEmpty()) {
            return;
        }

        $cutoff = Carbon::parse($date . ' 09:00:00');
        $publicHoliday = $this->publicHolidayFor($date);
        $isPublicHoliday = (bool) $publicHoliday;

        $recordsBeforeCutoff = $records->filter(function (AttendanceRawRecord $record) use ($cutoff) {
            return $record->recorded_at && $record->recorded_at->lte($cutoff);
        })->values();

        // Clock-in is only accepted until 09:00. If there are multiple records before 09:00,
        // only the earliest one becomes the clock-in. If none exist before 09:00, the earliest
        // available record becomes the late clock-in and the day is flagged.
        $first = $recordsBeforeCutoff->isNotEmpty() ? $recordsBeforeCutoff->first() : $records->first();
        $last = $records->last();

        $endTime = null;
        $minutes = 0;
        if ($first->recorded_at && $last->recorded_at && !$first->recorded_at->equalTo($last->recorded_at)) {
            $endTime = $last->recorded_at;
            $minutes = max(0, $first->recorded_at->diffInMinutes($last->recorded_at, false));
        }

        $isLate = false;
        $lateMinutes = 0;
        if (!$isPublicHoliday && $first->recorded_at && $first->recorded_at->gt($cutoff)) {
            $isLate = true;
            $lateMinutes = max(1, $cutoff->diffInMinutes($first->recorded_at, false));
        }

        if ($isPublicHoliday) {
            // The company is closed on public holidays. Keep the raw record for audit, but do
            // not count it as a normal working day or late clock-in.
            $minutes = 0;
            $isLate = false;
            $lateMinutes = 0;
        }

        $anomalies = [];
        if ($isPublicHoliday) {
            $anomalies[] = 'Public holiday / company closed: ' . $publicHoliday->name . '. Attendance is retained for audit only.';
        }
        if ($recordsBeforeCutoff->count() > 1) {
            $anomalies[] = 'Multiple clock-ins before 09:00; earliest time kept as check-in.';
        }
        if ($records->count() === 1) {
            $anomalies[] = 'Only one attendance record found for this day; checkout could not be calculated.';
        }
        if ($first->recorded_at && $last->recorded_at && $first->recorded_at->equalTo($last->recorded_at)) {
            $anomalies[] = 'Clock-in and checkout time were the same; checkout was left blank.';
        }
        if ($isLate) {
            $anomalies[] = 'Late clock-in after 09:00.';
        }

        $payload = [
            'start_time' => $first->recorded_at,
            'end_time' => $endTime,
            'first_status' => $first->attendance_status,
            'last_status' => $endTime ? $last->attendance_status : null,
            'record_count' => $records->count(),
            'work_minutes' => $minutes,
            'attendance_import_id' => $importId,
            'source_names' => $records->pluck('employee_name')->unique()->implode(', '),
            'anomalies' => implode(' ', $anomalies) ?: null,
        ];

        if (Schema::hasColumn('attendance_days', 'is_late')) {
            $payload['is_late'] = $isLate;
            $payload['late_minutes'] = $lateMinutes;
        }
        if (Schema::hasColumn('attendance_days', 'is_public_holiday')) {
            $payload['is_public_holiday'] = $isPublicHoliday;
            $payload['public_holiday_name'] = $publicHoliday?->name;
        }

        AttendanceDay::updateOrCreate(
            ['user_id' => $userId, 'attendance_date' => $date],
            $payload
        );
    }

    private function publicHolidayFor(string $date): ?PublicHoliday
    {
        if (!Schema::hasTable('public_holidays')) {
            return null;
        }

        return PublicHoliday::query()
            ->whereDate('holiday_date', $date)
            ->where('is_company_closed', true)
            ->first();
    }
}

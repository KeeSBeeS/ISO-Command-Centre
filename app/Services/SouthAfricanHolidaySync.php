<?php

namespace App\Services;

use App\Models\PlatformSetting;
use App\Models\PublicHoliday;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class SouthAfricanHolidaySync
{
    public const DEFAULT_ICS_URL = 'https://calendar.google.com/calendar/ical/en.sa%23holiday%40group.v.calendar.google.com/public/basic.ics';

    public function sync(?string $sourceUrl = null): array
    {
        if (!Schema::hasTable('public_holidays')) {
            return ['imported' => 0, 'source' => 'none', 'message' => 'Public holidays table is not installed.'];
        }

        $sourceUrl = $sourceUrl ?: (string) PlatformSetting::getValue('calendar.sa_holidays_ics_url', self::DEFAULT_ICS_URL);
        $imported = 0;
        $source = $sourceUrl;

        try {
            $ics = $this->fetch($sourceUrl);
            $imported = $this->importIcs($ics, $sourceUrl);
        } catch (\Throwable $e) {
            $source = 'fallback_seed';
            $imported = $this->seedKnownHolidays();
        }

        return [
            'imported' => $imported,
            'source' => $source,
            'message' => 'South African holiday sync completed. Imported/updated: ' . $imported . '.',
        ];
    }

    private function fetch(string $url): string
    {
        if (class_exists(Http::class)) {
            $response = Http::timeout(12)->get($url);
            if ($response->successful() && trim($response->body()) !== '') {
                return $response->body();
            }
        }

        $context = stream_context_create(['http' => ['timeout' => 12]]);
        $content = @file_get_contents($url, false, $context);
        if (!is_string($content) || trim($content) === '') {
            throw new \RuntimeException('Could not fetch holiday calendar.');
        }

        return $content;
    }

    private function importIcs(string $ics, string $source): int
    {
        $count = 0;
        preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $ics, $matches);

        foreach ($matches[1] ?? [] as $event) {
            $title = $this->matchLine($event, 'SUMMARY');
            $uid = $this->matchLine($event, 'UID');
            $date = $this->matchDate($event);

            if (!$title || !$date) {
                continue;
            }

            PublicHoliday::updateOrCreate(
                ['holiday_date' => $date],
                [
                    'title' => $this->cleanIcsText($title),
                    'source' => $source,
                    'source_uid' => $uid,
                    'imported_at' => now(),
                ]
            );
            $count++;
        }

        return $count;
    }

    private function matchLine(string $event, string $key): ?string
    {
        if (preg_match('/^' . preg_quote($key, '/') . '(?:;[^:]*)?:(.*)$/mi', $event, $match)) {
            return trim($match[1]);
        }
        return null;
    }

    private function matchDate(string $event): ?string
    {
        if (preg_match('/^DTSTART(?:;VALUE=DATE)?:(\d{8})/mi', $event, $match)) {
            return Carbon::createFromFormat('Ymd', $match[1])->toDateString();
        }
        return null;
    }

    private function cleanIcsText(string $value): string
    {
        return trim(str_replace(['\\,', '\\n', '\\;'], [',', ' ', ';'], $value));
    }

    private function seedKnownHolidays(): int
    {
        $items = [
            ['2026-01-01', 'New Year’s Day'], ['2026-03-21', 'Human Rights Day'], ['2026-04-03', 'Good Friday'], ['2026-04-06', 'Family Day'], ['2026-04-27', 'Freedom Day'], ['2026-05-01', 'Workers\' Day'], ['2026-06-16', 'Youth Day'], ['2026-08-09', 'National Women’s Day'], ['2026-08-10', 'Public holiday National Women’s Day observed'], ['2026-09-24', 'Heritage Day'], ['2026-12-16', 'Day of Reconciliation'], ['2026-12-25', 'Christmas Day'], ['2026-12-26', 'Day of Goodwill'],
            ['2027-01-01', 'New Year’s Day'], ['2027-03-21', 'Human Rights Day'], ['2027-03-22', 'Public holiday Human Rights Day observed'], ['2027-03-26', 'Good Friday'], ['2027-03-29', 'Family Day'], ['2027-04-27', 'Freedom Day'], ['2027-05-01', 'Workers\' Day'], ['2027-06-16', 'Youth Day'], ['2027-08-09', 'National Women’s Day'], ['2027-09-24', 'Heritage Day'], ['2027-12-16', 'Day of Reconciliation'], ['2027-12-25', 'Christmas Day'], ['2027-12-26', 'Day of Goodwill'],
        ];

        foreach ($items as [$date, $title]) {
            PublicHoliday::updateOrCreate(
                ['holiday_date' => $date],
                ['title' => $title, 'source' => 'fallback_seed', 'source_uid' => null, 'imported_at' => now()]
            );
        }

        return count($items);
    }
}

<?php

namespace App\Services;

use App\Models\PlatformSetting;

class GoogleMapsDistanceService
{
    public function configured(): bool
    {
        return trim((string) PlatformSetting::getValue('maps.google_api_key', '')) !== ''
            && trim((string) PlatformSetting::getValue('company.office_address', '')) !== '';
    }

    public function distanceFromOffice(?string $destination): array
    {
        $office = trim((string) PlatformSetting::getValue('company.office_address', ''));
        $apiKey = trim((string) PlatformSetting::getValue('maps.google_api_key', ''));
        $destination = trim((string) $destination);

        if ($office === '') {
            return ['ok' => false, 'message' => 'Office address is not configured under Core Settings.'];
        }

        if ($apiKey === '') {
            return ['ok' => false, 'message' => 'Google Maps API key is not configured under Core Settings.'];
        }

        if ($destination === '') {
            return ['ok' => false, 'message' => 'Destination location is empty.'];
        }

        $query = http_build_query([
            'origins' => $office,
            'destinations' => $destination,
            'units' => 'metric',
            'key' => $apiKey,
        ]);

        $url = 'https://maps.googleapis.com/maps/api/distancematrix/json?' . $query;

        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 12,
                    'ignore_errors' => true,
                ],
            ]);

            $response = @file_get_contents($url, false, $context);
            if ($response === false) {
                return ['ok' => false, 'message' => 'Could not reach Google Maps Distance Matrix service from this hosting account.'];
            }

            $payload = json_decode($response, true);
            if (!is_array($payload)) {
                return ['ok' => false, 'message' => 'Google Maps returned an invalid response.'];
            }

            if (($payload['status'] ?? '') !== 'OK') {
                return ['ok' => false, 'message' => 'Google Maps error: ' . ($payload['error_message'] ?? $payload['status'] ?? 'Unknown error')];
            }

            $element = $payload['rows'][0]['elements'][0] ?? null;
            if (!is_array($element) || ($element['status'] ?? '') !== 'OK') {
                return ['ok' => false, 'message' => 'Google Maps could not calculate this route: ' . ($element['status'] ?? 'Unknown route error')];
            }

            $meters = (float) ($element['distance']['value'] ?? 0);
            $seconds = (int) ($element['duration']['value'] ?? 0);

            return [
                'ok' => true,
                'distance_km' => round($meters / 1000, 1),
                'duration_minutes' => (int) ceil($seconds / 60),
                'origin' => $payload['origin_addresses'][0] ?? $office,
                'destination' => $payload['destination_addresses'][0] ?? $destination,
                'message' => 'Distance calculated successfully.',
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Google Maps calculation failed: ' . $e->getMessage()];
        }
    }

    public function directionsUrl(?string $destination): string
    {
        $office = trim((string) PlatformSetting::getValue('company.office_address', ''));
        $destination = trim((string) $destination);

        return 'https://www.google.com/maps/dir/?' . http_build_query([
            'api' => 1,
            'origin' => $office,
            'destination' => $destination,
            'travelmode' => 'driving',
        ]);
    }
}

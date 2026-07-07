<?php

namespace App\Services;

use App\Models\Vehicle;
use App\Models\VehicleFuelUp;
use App\Models\User;
use Illuminate\Support\Carbon;
use RuntimeException;

class VehicleFuelCsvImporter
{
    public function import(string $path, Vehicle $vehicle, ?User $importedBy = null, string $source = 'csv_upload'): array
    {
        if (!is_readable($path)) {
            throw new RuntimeException('Fuel CSV file cannot be read.');
        }

        $handle = fopen($path, 'r');
        if (!$handle) {
            throw new RuntimeException('Fuel CSV file could not be opened.');
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            throw new RuntimeException('Fuel CSV file is empty.');
        }

        $columns = [];
        foreach ($header as $index => $name) {
            $columns[$index] = $this->normaliseHeader((string) $name);
        }

        $required = ['car_name', 'model', 'odometer', 'litres', 'fuelup_date'];
        foreach ($required as $field) {
            if (!in_array($field, $columns, true)) {
                fclose($handle);
                throw new RuntimeException('Fuel CSV missing required column: ' . $field);
            }
        }

        $rawRows = 0;
        $created = 0;
        $duplicates = 0;
        $skipped = 0;
        $latestOdo = $vehicle->odo;

        while (($line = fgetcsv($handle)) !== false) {
            $rawRows++;
            $row = $this->combine($columns, $line);

            $fuelDate = $this->parseDate($row['fuelup_date'] ?? null);
            $odometer = $this->asInt($row['odometer'] ?? null);
            $litres = $this->asFloat($row['litres'] ?? null);

            if (!$fuelDate || !$odometer || !$litres) {
                $skipped++;
                continue;
            }

            $price = $this->asFloat($row['price'] ?? null);
            $km = $this->asFloat($row['km'] ?? null);
            $kmPerLitre = $this->asFloat($row['km_l'] ?? null);
            $dateAdded = $this->parseDateTime($row['date_added'] ?? null);
            $hash = hash('sha256', implode('|', [
                $vehicle->id,
                $row['car_name'] ?? '',
                $row['model'] ?? '',
                $odometer,
                $litres,
                $price,
                $fuelDate,
                $dateAdded ?: '',
            ]));

            if (VehicleFuelUp::where('source_row_hash', $hash)->exists()) {
                $duplicates++;
                continue;
            }

            VehicleFuelUp::create([
                'vehicle_id' => $vehicle->id,
                'uploaded_by' => $importedBy?->id,
                'source' => $source,
                'car_name' => $this->clean($row['car_name'] ?? null),
                'model_name' => $this->clean($row['model'] ?? null),
                'km_per_litre' => $kmPerLitre,
                'odometer' => $odometer,
                'km' => $km,
                'litres' => $litres,
                'price_per_litre' => $price,
                'total_cost' => ($price && $litres) ? round($price * $litres, 2) : null,
                'city_percentage' => $this->asFloat($row['city_percentage'] ?? null),
                'fuelup_date' => $fuelDate,
                'date_added' => $dateAdded,
                'tags' => $this->clean($row['tags'] ?? null),
                'notes' => $this->clean($row['notes'] ?? null),
                'missed_fuelup' => $this->asBool($row['missed_fuelup'] ?? null),
                'partial_fuelup' => $this->asBool($row['partial_fuelup'] ?? null),
                'latitude' => $this->asFloat($row['latitude'] ?? null),
                'longitude' => $this->asFloat($row['longitude'] ?? null),
                'brand' => $this->clean($row['brand'] ?? null),
                'source_row_hash' => $hash,
            ]);

            $created++;
            if (!$latestOdo || $odometer > $latestOdo) {
                $latestOdo = $odometer;
            }
        }

        fclose($handle);

        if ($latestOdo && $latestOdo > (int) $vehicle->odo) {
            $vehicle->update(['odo' => $latestOdo]);
        }

        return [
            'raw_rows' => $rawRows,
            'created' => $created,
            'duplicates' => $duplicates,
            'skipped' => $skipped,
        ];
    }

    private function combine(array $columns, array $line): array
    {
        $row = [];
        foreach ($columns as $index => $column) {
            $row[$column] = $line[$index] ?? null;
        }
        return $row;
    }

    private function normaliseHeader(string $header): string
    {
        $header = strtolower(trim($header));
        $header = str_replace([' ', '/', '-'], '_', $header);
        $header = preg_replace('/_+/', '_', $header);
        $header = trim($header, '_');

        return match ($header) {
            'km_l', 'kml', 'km_per_litre', 'km_per_liter' => 'km_l',
            'odometer' => 'odometer',
            'model' => 'model',
            default => $header,
        };
    }

    private function clean($value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function asFloat($value): ?float
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        return is_numeric($value) ? (float) $value : null;
    }

    private function asInt($value): ?int
    {
        $float = $this->asFloat($value);
        return $float === null ? null : (int) round($float);
    }

    private function asBool($value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'y'], true);
    }

    private function parseDate($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function parseDateTime($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable $e) {
            return null;
        }
    }
}

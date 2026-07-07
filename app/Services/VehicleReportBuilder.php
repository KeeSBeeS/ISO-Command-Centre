<?php

namespace App\Services;

use App\Models\Vehicle;

class VehicleReportBuilder
{
    public function buildPdf(Vehicle $vehicle, array $analytics): string
    {
        $lines = [];
        $lines[] = 'ISO Admin Vehicle Report';
        $lines[] = 'Generated: ' . now()->format('Y-m-d H:i');
        $lines[] = '';
        $lines[] = 'Vehicle: ' . $vehicle->display_name;
        $lines[] = 'Registration: ' . ($vehicle->registration_number ?: 'Not set');
        $lines[] = 'Current ODO: ' . number_format((int) ($vehicle->latest_odometer ?? $vehicle->odo ?? 0));
        $lines[] = 'Assigned To: ' . ($vehicle->currentAssignment?->user?->name ?: 'Unassigned');
        $lines[] = '';
        $lines[] = 'Fuel Summary';
        foreach ($analytics['periods'] as $period) {
            $lines[] = $period['label'] . ': ' .
                'Fuel-ups ' . $period['fuelups'] .
                ' | KM ' . number_format((float) $period['km'], 1) .
                ' | Litres ' . number_format((float) $period['litres'], 2) .
                ' | Cost R ' . number_format((float) $period['cost'], 2) .
                ' | KM/L ' . ($period['km_per_litre'] !== null ? number_format((float) $period['km_per_litre'], 2) : '-');
        }
        $lines[] = '';
        $lines[] = 'Service Status';
        $summary = $analytics['service_summary'];
        $lines[] = 'Status: ' . ($summary['label'] ?? 'Not available');
        $lines[] = 'Last Service ODO: ' . (($summary['last_service_odo'] ?? null) !== null ? number_format((int) $summary['last_service_odo']) : '-');
        $lines[] = 'Next Service ODO: ' . (($summary['next_service_odo'] ?? null) !== null ? number_format((int) $summary['next_service_odo']) : '-');
        $lines[] = 'KM Remaining: ' . (($summary['km_remaining'] ?? null) !== null ? number_format((int) $summary['km_remaining']) : '-');
        $lines[] = '';
        $lines[] = 'Recent Fuel-ups';

        foreach ($vehicle->fuelUps()->orderByDesc('fuelup_date')->limit(18)->get() as $fuel) {
            $lines[] = optional($fuel->fuelup_date)->format('Y-m-d') .
                ' | ODO ' . ($fuel->odometer ? number_format((int) $fuel->odometer) : '-') .
                ' | KM ' . ($fuel->km ?? '-') .
                ' | Litres ' . ($fuel->litres ?? '-') .
                ' | KM/L ' . ($fuel->km_per_litre ?? '-') .
                ' | Total R ' . ($fuel->total_cost ?? '-');
        }

        return $this->minimalPdf($lines);
    }

    private function minimalPdf(array $lines): string
    {
        $pages = array_chunk($lines, 44);
        $objects = [];
        $pageObjectNumbers = [];
        $fontObjectNumber = 3;
        $catalogObjectNumber = 1;
        $pagesObjectNumber = 2;
        $nextObject = 4;

        foreach ($pages as $pageLines) {
            $pageNumber = $nextObject++;
            $contentNumber = $nextObject++;
            $pageObjectNumbers[] = $pageNumber;

            $content = "BT\n/F1 10 Tf\n50 790 Td\n14 TL\n";
            foreach ($pageLines as $line) {
                $content .= '(' . $this->pdfText($line) . ") Tj\nT*\n";
            }
            $content .= "ET";

            $objects[$pageNumber] = "<< /Type /Page /Parent {$pagesObjectNumber} 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 {$fontObjectNumber} 0 R >> >> /Contents {$contentNumber} 0 R >>";
            $objects[$contentNumber] = "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
        }

        $kids = implode(' ', array_map(fn ($number) => $number . ' 0 R', $pageObjectNumbers));
        $objects[$catalogObjectNumber] = "<< /Type /Catalog /Pages {$pagesObjectNumber} 0 R >>";
        $objects[$pagesObjectNumber] = "<< /Type /Pages /Kids [{$kids}] /Count " . count($pageObjectNumbers) . " >>";
        $objects[$fontObjectNumber] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $number => $object) {
            $offsets[$number] = strlen($pdf);
            $pdf .= "{$number} 0 obj\n{$object}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (max(array_keys($objects)) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= max(array_keys($objects)); $i++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$i] ?? 0) . "\n";
        }
        $pdf .= "trailer\n<< /Size " . (max(array_keys($objects)) + 1) . " /Root {$catalogObjectNumber} 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private function pdfText(string $text): string
    {
        $text = iconv('UTF-8', 'Windows-1252//TRANSLIT', $text) ?: $text;
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}

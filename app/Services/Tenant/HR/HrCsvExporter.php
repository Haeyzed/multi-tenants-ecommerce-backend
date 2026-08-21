<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams HR report and payment-register rows as CSV.
 */
class HrCsvExporter
{
    /**
     * Rows.
     *
     * @param  string  $filename
     * @param  list<array<string, scalar|null>>  $rows
     * @param  list<array<string, scalar|null>>  $rows
     * @param  list<string>|null  $headings
     * @return StreamedResponse
     */
    public function rows(string $filename, array $rows, ?array $headings = null): StreamedResponse
    {
        $headings ??= $rows === [] ? [] : array_keys($rows[0]);

        return response()->streamDownload(function () use ($rows, $headings): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            if ($headings !== []) {
                fputcsv($handle, $headings);
            }

            foreach ($rows as $row) {
                $ordered = $headings === [] ? $row : array_map(
                    fn (string $heading): mixed => $row[$heading] ?? '',
                    $headings,
                );

                fputcsv($handle, array_map(
                    fn (mixed $value): string => $value === null ? '' : (string) $value,
                    $ordered,
                ));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}

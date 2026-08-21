<?php

declare(strict_types=1);

namespace App\Services\Tenant\Analytics;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams analytics payloads as CSV without buffering the whole report in memory.
 */
class AnalyticsCsvExporter
{
    /**
     * Stream a list of uniform rows as CSV.
     *
     * @param  string  $filename
     * @param  list<array<string, scalar|null>>  $rows
     * @param  ?array  $headings
     * @return StreamedResponse
     */
    public function rows(string $filename, array $rows, ?array $headings = null): StreamedResponse
    {
        $headings ??= $rows === [] ? [] : array_keys($rows[0]);

        return $this->stream($filename, function () use ($rows, $headings): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            if ($headings !== []) {
                fputcsv($handle, $headings);
            }

            foreach ($rows as $row) {
                fputcsv($handle, array_map(
                    fn (mixed $value): string => $value === null ? '' : (string) $value,
                    $row,
                ));
            }

            fclose($handle);
        });
    }

    /**
     * Stream a flat summary as a two column metric/value CSV.
     *
     * @param  string  $filename
     * @param  array<string, scalar|null>  $summary
     * @return StreamedResponse
     */
    public function summary(string $filename, array $summary): StreamedResponse
    {
        $rows = [];

        foreach ($summary as $metric => $value) {
            $rows[] = ['metric' => $metric, 'value' => $value];
        }

        return $this->rows($filename, $rows, ['metric', 'value']);
    }

    /**
     * Stream.
     *
     * @param  string  $filename
     * @param  callable(): void  $writer
     * @return StreamedResponse
     */
    protected function stream(string $filename, callable $writer): StreamedResponse
    {
        return response()->streamDownload($writer, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}

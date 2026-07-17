<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvStream
{
    public static function escape(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $value = (string) $value;

        if (preg_match('/^[=+\-@]/', $value)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * @param  iterable<int, array<int, scalar|null>>  $rows
     */
    public static function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, array_map(
                    fn ($value) => is_string($value) ? static::escape($value) : $value,
                    $row
                ));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}

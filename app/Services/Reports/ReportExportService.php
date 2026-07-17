<?php

namespace App\Services\Reports;

use App\Support\CsvStream;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportService
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $definition
     */
    public function download(array $definition, array $data, string $periodLabel, string $format): Response|StreamedResponse
    {
        $name = str($definition['name'] ?? 'report')->slug();
        $filename = "{$name}-{$periodLabel}";

        return match ($format) {
            'xlsx' => $this->xlsx($filename, $definition, $data, $periodLabel),
            'pdf' => $this->pdf($filename, $definition, $data, $periodLabel),
            default => $this->csv($filename, $definition, $data, $periodLabel),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function countRows(array $data): int
    {
        $rows = 0;

        foreach ($data['sections'] ?? [] as $section) {
            $rows += count($section['rows'] ?? []);
        }

        foreach (['months', 'reasons', 'expiring', 'sample', 'agents', 'bands', 'preview', 'by_payer', 'rows'] as $key) {
            if (! empty($data[$key]) && is_array($data[$key])) {
                $rows += count($data[$key]);
            }
        }

        return max($rows, count($data['kpis'] ?? []));
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $data
     */
    protected function csv(string $filename, array $definition, array $data, string $periodLabel): StreamedResponse
    {
        $sheets = $this->flatten($definition, $data, $periodLabel);

        return response()->streamDownload(function () use ($definition, $periodLabel, $sheets) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [$definition['name'] ?? 'Report', $periodLabel]);
            fputcsv($out, []);

            foreach ($sheets as $sheet) {
                fputcsv($out, [$sheet['title']]);
                if (! empty($sheet['headers'])) {
                    fputcsv($out, $sheet['headers']);
                }
                foreach ($sheet['rows'] as $row) {
                    fputcsv($out, array_map(fn ($v) => CsvStream::escape(is_scalar($v) ? (string) $v : json_encode($v)), $row));
                }
                fputcsv($out, []);
            }

            fclose($out);
        }, "{$filename}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $data
     */
    protected function xlsx(string $filename, array $definition, array $data, string $periodLabel): StreamedResponse
    {
        $sheets = $this->flatten($definition, $data, $periodLabel);

        return response()->streamDownload(function () use ($definition, $periodLabel, $sheets) {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle(substr($definition['name'] ?? 'Report', 0, 31));
            $row = 1;
            $sheet->setCellValue("A{$row}", ($definition['name'] ?? 'Report').' · '.$periodLabel);
            $row += 2;

            foreach ($sheets as $block) {
                $sheet->setCellValue("A{$row}", $block['title']);
                $row++;
                if (! empty($block['headers'])) {
                    foreach ($block['headers'] as $col => $header) {
                        $cell = Coordinate::stringFromColumnIndex($col + 1).$row;
                        $sheet->setCellValue($cell, $header);
                    }
                    $row++;
                }
                foreach ($block['rows'] as $dataRow) {
                    foreach ($dataRow as $col => $value) {
                        $cell = Coordinate::stringFromColumnIndex($col + 1).$row;
                        $sheet->setCellValue($cell, is_scalar($value) ? $value : json_encode($value));
                    }
                    $row++;
                }
                $row++;
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, "{$filename}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $data
     */
    protected function pdf(string $filename, array $definition, array $data, string $periodLabel): Response
    {
        $pdf = Pdf::loadView('pages.reports.export.pdf', [
            'definition' => $definition,
            'data' => $data,
            'periodLabel' => $periodLabel,
            'sheets' => $this->flatten($definition, $data, $periodLabel),
        ])->setPaper('a4', 'landscape');

        return $pdf->download("{$filename}.pdf");
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $data
     * @return list<array{title: string, headers: list<string>, rows: list<list<string>>}>
     */
    public function flatten(array $definition, array $data, string $periodLabel): array
    {
        $blocks = [];

        if (! empty($data['kpis'])) {
            $blocks[] = [
                'title' => 'Key metrics',
                'headers' => ['Metric', 'Value', 'Detail'],
                'rows' => collect($data['kpis'])->map(fn ($k) => [
                    $k['label'] ?? '',
                    $k['value'] ?? '',
                    $k['sub'] ?? '',
                ])->all(),
            ];
        }

        foreach ($data['sections'] ?? [] as $section) {
            $blocks[] = [
                'title' => $section['title'] ?? 'Section',
                'headers' => $section['headers'] ?? [],
                'rows' => $this->normalizeRows($section['rows'] ?? [], $section['headers'] ?? []),
            ];
        }

        $this->appendLegacyTable($blocks, 'By month', ['Month', 'Billed', 'Collected', 'Outstanding', 'Rate', 'Claims'], $data['months'] ?? [], function ($row) {
            return [
                $row['month'] ?? '',
                $this->money($row['billed'] ?? 0),
                $this->money($row['collected'] ?? 0),
                $this->money($row['outstanding'] ?? 0),
                ($row['rate'] ?? '').'%',
                (string) ($row['claims'] ?? ''),
            ];
        });

        $this->appendLegacyTable($blocks, 'Rejection reasons', ['Reason', 'Count', 'Impact', 'Channel', 'Status'], $data['reasons'] ?? [], fn ($r) => [
            $r['reason'] ?? '', (string) ($r['count'] ?? ''), $this->money($r['impact'] ?? 0), $r['channel'] ?? '', $r['status'] ?? '',
        ]);

        $this->appendLegacyTable($blocks, 'By agent', ['Agent', 'Tasks', 'Auto %', 'Escalated', 'Miss-rate', 'Status'], $data['agents'] ?? [], fn ($r) => [
            $r['name'] ?? '', (string) ($r['tasks'] ?? ''), ($r['auto_pct'] ?? '').'%', (string) ($r['escalated'] ?? ''), ($r['miss_rate'] ?? '').'%', $r['status'] ?? '',
        ]);

        $this->appendLegacyTable($blocks, 'Caregiver sample', ['Caregiver', 'Hours', 'Wage', 'Gross', 'Status'], $data['sample'] ?? [], fn ($r) => [
            $r['name'] ?? '', (string) ($r['hours'] ?? ''), '$'.($r['wage'] ?? ''), $this->money($r['gross'] ?? 0), $r['status'] ?? '',
        ]);

        $this->appendLegacyTable($blocks, 'Expiring authorizations', ['Client', 'Program', 'Auth', 'Expires', 'Renewal'], $data['expiring'] ?? [], fn ($r) => [
            $r['client'] ?? '', $r['program'] ?? '', $r['auth'] ?? '', $r['expires'] ?? '', $r['renewal'] ?? '',
        ]);

        $this->appendLegacyTable($blocks, 'Utilization bands', ['Band', 'Caregivers', 'Share', 'Note'], $data['bands'] ?? [], fn ($r) => [
            $r['band'] ?? '', (string) ($r['count'] ?? ''), $r['share'] ?? '', $r['note'] ?? '',
        ]);

        $this->appendLegacyTable($blocks, 'Preview', ['Client', 'Program', 'County'], $data['preview'] ?? [], fn ($r) => [
            $r['client'] ?? '', $r['program'] ?? '', $r['county'] ?? ($r['hours_delta'] ?? '—'),
        ]);

        if (empty($blocks)) {
            $blocks[] = [
                'title' => $definition['name'] ?? 'Report',
                'headers' => ['Note'],
                'rows' => [[$data['note'] ?? $data['message'] ?? 'No exportable rows.']],
            ];
        }

        return $blocks;
    }

    /**
     * @param  list<array{title: string, headers: list<string>, rows: list<list<string>>}>  $blocks
     * @param  list<string>  $headers
     * @param  iterable<mixed>  $rows
     * @param  callable(mixed): list<string>  $mapper
     */
    protected function appendLegacyTable(array &$blocks, string $title, array $headers, iterable $rows, callable $mapper): void
    {
        $mapped = [];
        foreach ($rows as $row) {
            $mapped[] = $mapper($row);
        }
        if ($mapped !== []) {
            $blocks[] = ['title' => $title, 'headers' => $headers, 'rows' => $mapped];
        }
    }

    /**
     * @param  list<mixed>  $rows
     * @param  list<string>  $headers
     * @return list<list<string>>
     */
    protected function normalizeRows(array $rows, array $headers): array
    {
        return collect($rows)->map(function ($row) use ($headers) {
            if (is_array($row) && array_is_list($row)) {
                return array_map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v), $row);
            }
            if (is_array($row)) {
                if ($headers) {
                    return collect($headers)->map(function ($header, $i) use ($row) {
                        $keys = array_keys($row);
                        $val = $row[$keys[$i] ?? $i] ?? ($row[strtolower(str_replace(' ', '_', $header))] ?? '');

                        return is_scalar($val) ? (string) $val : json_encode($val);
                    })->all();
                }

                return array_map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v), array_values($row));
            }

            return [(string) $row];
        })->all();
    }

    protected function money(mixed $amount): string
    {
        return '$'.number_format((float) $amount, 0);
    }
}

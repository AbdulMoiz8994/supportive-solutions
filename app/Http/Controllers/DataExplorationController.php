<?php

namespace App\Http\Controllers;

use App\Services\DataExplorationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataExplorationController extends Controller
{
    public function __construct(
        protected DataExplorationService $exploration,
    ) {}

    public function index(Request $request)
    {
        return view('pages.data-exploration.index', $this->exploration->pageData(
            $this->organizationId(),
            $request,
            $request->user(),
        ));
    }

    public function query(Request $request): JsonResponse
    {
        $dataset = $request->input('dataset', 'visits');
        $config = [
            'date_preset' => $request->input('date_preset'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'status' => $request->input('status'),
            'county' => $request->input('county'),
            'program' => $request->input('program'),
            'employee_id' => $request->integer('employee_id') ?: null,
            'client_id' => $request->integer('client_id') ?: null,
            'group_by' => $request->input('group_by'),
            'aggregate' => $request->input('aggregate', 'count'),
            'chart_type' => $request->input('chart_type', 'bar'),
        ];

        $result = $this->exploration->query(
            $this->organizationId(),
            $dataset,
            $config,
            $request->user(),
        );

        return response()->json(['ok' => true, ...$result]);
    }

    public function saveView(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'dataset' => 'required|string',
            'config' => 'required|array',
            'schedule_frequency' => 'nullable|in:daily,weekly',
        ]);

        $view = $this->exploration->saveView(
            $this->organizationId(),
            $request->user(),
            $validated['name'],
            $validated['dataset'],
            $validated['config'],
            $validated['schedule_frequency'] ?? null,
        );

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => 'View saved.', 'view_id' => $view->id]);
        }

        return back()->with('success', 'View saved.');
    }

    public function deleteView(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $deleted = $this->exploration->deleteView(
            $this->organizationId(),
            $request->user(),
            $id,
        );

        if (! $deleted) {
            abort(404);
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => 'View deleted.']);
        }

        return back()->with('success', 'View deleted.');
    }

    public function export(Request $request): Response|StreamedResponse
    {
        $dataset = $request->input('dataset', 'visits');
        $format = strtolower((string) $request->input('format', 'csv'));
        $config = $request->only([
            'date_preset', 'date_from', 'date_to', 'status', 'county', 'program',
            'employee_id', 'client_id', 'group_by', 'aggregate', 'chart_type',
        ]);
        $orgId = $this->organizationId();
        $user = $request->user();

        [$headers, $rows] = $this->exploration->exportCsv($orgId, $dataset, $config, $user);
        $this->exploration->logExport($orgId, $user, $dataset, $format, count($rows), $config);

        if ($format === 'xlsx') {
            return $this->exploration->exportXlsx($orgId, $dataset, $config, $user);
        }

        if ($format === 'pdf') {
            return $this->exploration->exportPdf($orgId, $dataset, $config, $user);
        }

        $filename = 'data-exploration-'.$dataset.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);

            foreach ($rows as $row) {
                fputcsv($out, $row);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function organizationId(): ?int
    {
        $user = auth()->user();

        return $user?->isSuperAdmin() ? null : $user?->organization_id;
    }
}

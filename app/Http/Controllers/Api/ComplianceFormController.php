<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesCaregiver;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ComplianceFormResource;
use App\Models\ComplianceForm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

/**
 * Monthly compliance certification: list what's due, review a form, submit the
 * yes/no certification with a signature, and see the 12-month history.
 */
class ComplianceFormController extends Controller
{
    use ResolvesCaregiver;

    /**
     * The caregiver's compliance forms (newest first). Query: ?status=, ?per_page=.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $caregiver = $this->caregiver();

        $forms = ComplianceForm::query()
            ->where('employee_id', $caregiver->id)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->orderByDesc('period')
            ->paginate(min((int) $request->integer('per_page', 25) ?: 25, 100));

        return ComplianceFormResource::collection($forms);
    }

    /**
     * Last-12-months history with the "Submitted / Overdue / On-Time %" header.
     */
    public function history(Request $request): JsonResponse
    {
        $caregiver = $this->caregiver();
        $since = now()->copy()->subMonths(12)->format('Y-m');

        $forms = ComplianceForm::query()
            ->where('employee_id', $caregiver->id)
            ->where('period', '>=', $since)
            ->orderByDesc('period')
            ->get();

        $records = ComplianceFormResource::collection($forms)->toArray($request);

        $submitted = collect($records)->where('submitted', true)->count();
        $overdue = collect($records)->where('is_overdue', true)->count();
        $total = count($records);

        return response()->json([
            'data' => [
                'summary' => [
                    'submitted' => $submitted,
                    'overdue' => $overdue,
                    'on_time_pct' => $total > 0 ? (int) round($submitted / $total * 100) : 0,
                ],
                'records' => $records,
            ],
        ]);
    }

    /**
     * One form plus the certification questions (month interpolated).
     */
    public function show(Request $request, ComplianceForm $complianceForm): JsonResponse
    {
        $this->authorizeForm($complianceForm);

        $base = (new ComplianceFormResource($complianceForm))->toArray($request);
        $month = $base['period_label'] ?? 'this period';

        $questions = collect(ComplianceForm::certificationQuestions())
            ->map(fn ($q) => [
                'key' => $q['key'],
                'text' => str_replace('{month}', $month, $q['text']),
            ])
            ->all();

        return response()->json([
            'data' => array_merge($base, ['questions' => $questions]),
        ]);
    }

    /**
     * Submit the certification: yes/no answers + optional note + signature image.
     */
    public function submit(Request $request, ComplianceForm $complianceForm): JsonResponse
    {
        $this->authorizeForm($complianceForm);

        $validKeys = collect(ComplianceForm::certificationQuestions())->pluck('key')->all();

        $data = $request->validate([
            'answers' => ['required', 'array', 'min:1'],
            'additional_notes' => ['nullable', 'string', 'max:2000'],
            'signature' => ['required', 'string'], // base64 PNG (data URI accepted)
        ]);

        $answers = collect($data['answers'])
            ->only($validKeys)
            ->map(fn ($v) => filter_var($v, FILTER_VALIDATE_BOOLEAN))
            ->all();

        $signaturePath = $this->storeSignature($complianceForm, $data['signature']);

        $complianceForm->update([
            'certification' => $answers,
            'additional_notes' => $data['additional_notes'] ?? null,
            'signature_path' => $signaturePath,
            'status' => ComplianceForm::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'submitted_via' => 'mobile',
            'acknowledgments_initialed' => true,
        ]);

        return response()->json([
            'message' => 'Compliance form submitted.',
            'data' => (new ComplianceFormResource($complianceForm->fresh()))->toArray($request),
        ]);
    }

    /**
     * Decode a base64 (optionally data-URI) signature and store it on the
     * public disk, returning the relative path.
     */
    private function storeSignature(ComplianceForm $form, string $signature): ?string
    {
        if (str_contains($signature, ',')) {
            $signature = substr($signature, strpos($signature, ',') + 1);
        }

        $binary = base64_decode(strtr($signature, ' ', '+'), true);

        if ($binary === false || strlen($binary) === 0) {
            abort(422, 'Signature is not valid base64 image data.');
        }

        $path = "compliance/signatures/{$form->id}-".now()->timestamp.'.png';
        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    private function authorizeForm(ComplianceForm $form): void
    {
        abort_unless((int) $form->employee_id === (int) $this->caregiver()->id, 403);
    }
}

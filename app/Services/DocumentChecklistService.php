<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Document;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;

/**
 * Derives a document checklist for a client or caregiver from the documents
 * already on file. Uploading a document whose name/type/category matches a
 * required item auto-checks that item — no separate checklist table to maintain.
 *
 * Backend automation (no LLM). The required-item lists are sensible defaults
 * and can later be overridden per coverage type from admin settings.
 */
class DocumentChecklistService
{
    /** @return array<int, array{key:string,label:string,checked:bool,document_id:?int}> */
    public function forClient(Client $client): array
    {
        return $this->build($client, $this->clientItems());
    }

    /** @return array<int, array{key:string,label:string,checked:bool,document_id:?int}> */
    public function forCaregiver(Employee $employee): array
    {
        return $this->build($employee, $this->caregiverItems());
    }

    /** @return array{done:int,total:int,complete:bool,percent:int} */
    public function summary(array $checklist): array
    {
        $total = count($checklist);
        $done = count(array_filter($checklist, fn ($i) => $i['checked']));

        return [
            'done' => $done,
            'total' => $total,
            'complete' => $total > 0 && $done === $total,
            'percent' => $total > 0 ? (int) round(($done / $total) * 100) : 0,
        ];
    }

    /**
     * @param  array<int, array{key:string,label:string,keywords:array<int,string>}>  $items
     * @return array<int, array{key:string,label:string,checked:bool,document_id:?int}>
     */
    private function build(Model $entity, array $items): array
    {
        $documents = $entity->relationLoaded('documents') ? $entity->documents : $entity->documents()->get();

        return array_map(function ($item) use ($documents) {
            $match = $documents->first(fn (Document $d) => $this->matches($d, $item['keywords']));

            return [
                'key' => $item['key'],
                'label' => $item['label'],
                'checked' => (bool) $match,
                'document_id' => $match?->id,
            ];
        }, $items);
    }

    private function matches(Document $document, array $keywords): bool
    {
        $haystack = strtolower(trim(
            ($document->name ?? '').' '.($document->type ?? '').' '.($document->category ?? '')
        ));

        foreach ($keywords as $kw) {
            if ($kw !== '' && str_contains($haystack, strtolower($kw))) {
                return true;
            }
        }

        return false;
    }

    /** Default client checklist (mirrors the "Client Checklist Profile"). */
    private function clientItems(): array
    {
        return [
            ['key' => 'application', 'label' => 'Client Application', 'keywords' => ['application']],
            ['key' => 'dhs_390', 'label' => 'DHS-390', 'keywords' => ['dhs-390', 'dhs 390']],
            ['key' => 'msa_4676', 'label' => 'MSA-4676', 'keywords' => ['4676']],
            ['key' => 'medical_needs', 'label' => 'Medical Needs Form', 'keywords' => ['medical needs']],
            ['key' => 'id', 'label' => 'ID', 'keywords' => ['id card', 'identification', 'state id', 'driver']],
            ['key' => 'ssn', 'label' => 'SSN', 'keywords' => ['ssn', 'social security']],
            ['key' => 'health_insurance', 'label' => 'Health Insurance', 'keywords' => ['insurance']],
        ];
    }

    /** Default caregiver checklist (mirrors the "Caregiver Checklist Profile"). */
    private function caregiverItems(): array
    {
        return [
            ['key' => 'i9', 'label' => 'I-9 Form', 'keywords' => ['i-9', 'i9']],
            ['key' => 'w4', 'label' => 'W-4 Form', 'keywords' => ['w-4', 'w4']],
            ['key' => 'id_card', 'label' => 'ID Card', 'keywords' => ['id card', 'identification', 'state id', 'driver']],
            ['key' => 'ssn', 'label' => 'SSN', 'keywords' => ['ssn', 'social security']],
            ['key' => 'direct_deposit', 'label' => 'Direct Deposit Form', 'keywords' => ['direct deposit']],
            ['key' => 'msa_204', 'label' => 'MSA-204', 'keywords' => ['msa-204', 'msa 204', '204']],
        ];
    }
}

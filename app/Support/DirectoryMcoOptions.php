<?php

namespace App\Support;

use App\Models\Contact;

/**
 * MCO / insurer dropdown options for client forms (client review B2).
 * Sourced from Directory → Payers / MCOs so agencies manage the list in one
 * place; falls back to the well-known Michigan plans until payers are added.
 */
class DirectoryMcoOptions
{
    /** @var list<string> */
    public const FALLBACK = [
        'Aetna Better Health (via Availity)',
        'Meridian Health Plan',
        'Molina Healthcare',
        'UnitedHealthcare Community Plan',
        'Blue Cross Complete',
    ];

    /**
     * @return list<string>
     */
    public static function list(): array
    {
        $payers = Contact::query()
            ->where('type', Contact::TYPE_INSURANCE)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name')
            ->filter()
            ->unique()
            ->values();

        return $payers->isNotEmpty() ? $payers->all() : self::FALLBACK;
    }
}

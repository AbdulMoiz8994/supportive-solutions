@props([
    'prefixes' => [],
    'keys' => [],
])

@php
    use Illuminate\Support\Arr;

    $prefixList = collect(Arr::wrap($prefixes))->filter()->values();
    $keyList = collect(Arr::wrap($keys))->filter()->values();

    $matches = static function (string $field) use ($prefixList, $keyList): bool {
        if ($keyList->contains($field)) {
            return true;
        }

        foreach ($prefixList as $prefix) {
            if ($field === $prefix || str_starts_with($field, $prefix.'.')) {
                return true;
            }
        }

        return false;
    };

    $messages = collect($errors->getMessages())
        ->filter(fn (array $msgs, string $field) => $matches($field))
        ->flatten()
        ->unique()
        ->values();
@endphp

@if($messages->isNotEmpty())
    <div {{ $attributes->merge(['class' => 'rounded-xl border border-[#fecaca] bg-[#fef2f2] px-4 py-3 text-sm text-[#b91c1c]']) }} role="alert">
        <ul class="list-disc list-inside space-y-0.5 font-medium">
            @foreach($messages as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

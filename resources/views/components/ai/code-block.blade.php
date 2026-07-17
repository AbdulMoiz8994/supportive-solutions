@props([
    'code' => '',
    'language' => 'javascript',
    'showLineNumbers' => false,
    'className' => ''
])

<div class="w-full flex-1" x-data="{ copied: false }">
    <!-- Header -->
    <div class="flex items-center justify-between px-5 py-4 border-b dark:border-gray-800 border-gray-200">
        <div class="flex items-center gap-1.5 text-gray-500 dark:text-gray-400">
            <!-- Icon -->
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18" fill="none">
                <path
                    d="M5.0625 5.99976L2.0625 9.00003L5.0625 12M12.9375 5.99976L15.9375 9.00003L12.9375 12M10.3329 2.99994L7.66626 14.9999"
                    stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <p class="text-gray-500 text-sm dark:text-gray-400">{{ strtoupper($language) }}</p>
        </div>

        <!-- Copy Button -->
        <button @click="navigator.clipboard.writeText('{{ addslashes($code) }}').then(() => { copied = true; setTimeout(() => copied = false, 1500); })"
            class="flex gap-1 text-xs font-medium items-center dark:text-gray-400 dark:border-white/5 bg-white dark:bg-white/3 h-8 rounded-full px-3 py-1.5 border text-gray-700 border-gray-100">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18" fill="none">
                <path fill-rule="evenodd" clip-rule="evenodd"
                    d="M5.85442 4.12576C5.85442 3.83581 6.08947 3.60076 6.37942 3.60076H13.8739C14.1638 3.60076 14.3989 3.83581 14.3989 4.12576L14.3989 11.6217C14.3989 11.9117 14.1638 12.1467 13.8739 12.1467H6.37942C6.08947 12.1467 5.85442 11.9117 5.85442 11.6217V4.12576ZM6.37942 2.40076C5.42673 2.40076 4.65442 3.17307 4.65442 4.12576V4.65991H4.12649C3.1738 4.65991 2.40149 5.43222 2.40149 6.38491V13.8747C2.40149 14.8273 3.1738 15.5997 4.12649 15.5997H11.6162C12.5689 15.5997 13.3412 14.8273 13.3412 13.8747V13.3467H13.8739C14.8266 13.3467 15.5989 12.5744 15.5989 11.6217L15.5989 4.12575C15.5989 3.17306 14.8266 2.40076 13.8739 2.40076H6.37942ZM12.1412 13.3467H6.37942C5.42673 13.3467 4.65442 12.5744 4.65442 11.6217V5.85991H4.12649C3.83654 5.85991 3.60149 6.09496 3.60149 6.38491V13.8747C3.60149 14.1646 3.83654 14.3997 4.12649 14.3997H11.6162C11.9062 14.3997 12.1412 14.1646 12.1412 13.8747L12.1412 13.3467Z"
                    fill="#98A2B3" />
            </svg>
            <span x-text="copied ? 'Copied!' : 'Copy'"></span>
        </button>
    </div>

    <!-- Code Block -->
    <div class="py-4 px-5 max-h-[350px] w-full overflow-y-auto custom-scrollbar">
        <pre class="rounded-lg overflow-x-auto p-4 text-white text-sm {{ $showLineNumbers ? 'line-numbers' : '' }} {{ $className }}"><code class="language-{{ $language }}">{{ $code }}</code></pre>
    </div>
</div>
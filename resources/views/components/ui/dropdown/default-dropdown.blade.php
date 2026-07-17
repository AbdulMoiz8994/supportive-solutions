
@php
    // You can override $menuItems by passing it from the parent view.
    $menuItems = $menuItems ?? [
        ['text' => 'Edit Profile', 'href' => '#'],
        ['text' => 'Account Settings', 'href' => '#'],
        ['text' => 'License', 'href' => '#'],
        ['text' => 'Support', 'href' => '#'],
    ];
@endphp

<div
  x-data="{ isOpen: false }"
  x-cloak
  class="relative inline-block"
  x-ref="dropdownRoot"
>
  <a
    href="#"
    @click.prevent="isOpen = !isOpen"
    class="inline-flex items-center gap-2 px-4 py-3 text-sm font-medium text-white rounded-lg bg-brand-500 hover:bg-brand-600"
  >
    Account Menu
    <svg
      class="duration-200 ease-in-out transform"
      :class="{ 'rotate-180': isOpen }"
      width="20"
      height="20"
      viewBox="0 0 20 20"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      aria-hidden="true"
      focusable="false"
    >
      <path
        d="M4.79199 7.396L10.0003 12.6043L15.2087 7.396"
        stroke="currentColor"
        stroke-width="1.5"
        stroke-linecap="round"
        stroke-linejoin="round"
      />
    </svg>
  </a>

  <div
    x-show="isOpen"
    @click.away="isOpen = false"
    x-transition:enter="transition duration-200 ease-out"
    x-transition:enter-start="transform scale-95 opacity-0"
    x-transition:enter-end="transform scale-100 opacity-100"
    x-transition:leave="transition duration-75 ease-in"
    x-transition:leave-start="transform scale-100 opacity-100"
    x-transition:leave-end="transform scale-95 opacity-0"
    class="absolute left-0 top-full z-40 mt-2 w-full min-w-[260px] rounded-2xl border border-gray-200 bg-white p-3 shadow-theme-lg dark:border-gray-800 dark:bg-[#1E2635]"
  >
    <ul class="flex flex-col gap-1">
      @foreach ($menuItems as $item)
        <li>
          <a
            href="{{ $item['href'] }}"
            class="flex rounded-lg px-3 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5"
          >
            {{ $item['text'] }}
          </a>
        </li>
      @endforeach
    </ul>
  </div>
</div>

<style>
    [x-cloak] { display: none; }
</style>


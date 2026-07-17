@extends('layouts.app')


@section('content')
    {{-- Page Breadcrumb --}}
    <x-common.page-breadcrumb pageTitle="Ribbons" />

    <div class="grid grid-cols-1 gap-5 sm:gap-6 lg:grid-cols-2">

        <x-common.component-card title="Rounded Ribbon">
            <div class="relative overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800 dark:bg-white/[0.03]">
                <span class="absolute -left-px mt-3 inline-block rounded-r-full bg-brand-500 px-4 py-1.5 text-sm font-medium text-white">Popular</span>
                <div class="p-5 pt-16">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Lorem ipsum dolor sit amet consectetur. Eget nulla suscipit arcu rutrum amet vel nec
                        fringilla vulputate. Sed aliquam fringilla vulputate imperdiet arcu natoque purus ac nec
                        ultricies nulla ultrices.
                    </p>
                </div>
            </div>
        </x-common.component-card>

        <x-common.component-card title="Rounded with shape">
            <div class="relative overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800 dark:bg-white/[0.03]">
                <span class="after:bottom-0-0 absolute -left-px mt-3 inline-block bg-brand-500 px-4 py-1.5 text-sm font-medium text-white before:absolute before:-right-4 before:top-0 before:border-[13px] before:border-transparent before:border-l-brand-500 before:border-t-brand-500 before:content-[''] after:absolute after:-right-4 after:border-[13px] after:border-transparent after:border-b-brand-500 after:border-l-brand-500 after:content-['']">Popular</span>
                <div class="p-5 pt-16">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Lorem ipsum dolor sit amet consectetur. Eget nulla suscipit arcu rutrum amet vel nec
                        fringilla vulputate. Sed aliquam fringilla vulputate imperdiet arcu natoque purus ac nec
                        ultricies nulla ultrices.
                    </p>
                </div>
            </div>
        </x-common.component-card>

        <x-common.component-card title="Filled Ribbon">
            <div class="relative overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800 dark:bg-white/[0.03]">
                <span class="absolute -left-9 -top-7 mt-3 flex h-14 w-24 -rotate-45 items-end justify-center bg-brand-500 px-4 py-1.5 text-sm font-medium text-white shadow-theme-xs">New</span>
                <div class="p-5 pt-16">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Lorem ipsum dolor sit amet consectetur. Eget nulla suscipit arcu rutrum amet vel nec
                        fringilla vulputate. Sed aliquam fringilla vulputate imperdiet arcu natoque purus ac nec
                        ultricies nulla ultrices.
                    </p>
                </div>
            </div>
        </x-common.component-card>

        <x-common.component-card title="Ribbon in hover">
            <div class="group relative overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800 dark:bg-white/[0.03]">
                <span class="after:bottom-0-0 group absolute -left-px mt-3 flex -translate-x-[55px] items-center gap-1 bg-brand-500 px-4 py-1.5 text-sm font-medium text-white transition-transform duration-500 ease-in-out before:absolute before:-right-4 before:top-0 before:border-[16px] before:border-transparent before:border-l-brand-500 before:border-t-brand-500 before:content-[''] after:absolute after:-right-4 after:border-[16px] after:border-transparent after:border-b-brand-500 after:border-l-brand-500 after:content-[''] group-hover:translate-x-0">
                    <span class="transition-opacity duration-300 ease-linear opacity-0 group-hover:opacity-100">Popular</span>
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M10.679 2.2915L3.98828 11.6958H9.32224L9.32224 17.7082L16.013 8.30385L10.679 8.30385V2.2915Z" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </span>
                <div class="p-5 pt-16">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Lorem ipsum dolor sit amet consectetur. Eget nulla suscipit arcu rutrum amet vel nec
                        fringilla vulputate. Sed aliquam fringilla vulputate imperdiet arcu natoque purus ac nec
                        ultricies nulla ultrices.
                    </p>
                </div>
            </div>
        </x-common.component-card>

    </div>
@endsection

@extends('layouts.app')

@section('content')
    {{-- Page Breadcrumb --}}
    <x-common.page-breadcrumb pageTitle="Links" />

    <div class="grid grid-cols-1 gap-5 sm:gap-6 lg:grid-cols-2">

        <x-common.component-card title="Colored Links">
            <div class="flex flex-col space-y-3">
                <a href="/ecommerce" class="text-sm font-normal text-gray-500 dark:text-gray-400">Primary link</a>
                <a href="/ecommerce" class="text-sm font-normal text-brand-500 dark:text-brand-500">Secondary link</a>
                <a href="/ecommerce" class="text-sm font-normal text-success-500">Success link</a>
                <a href="/ecommerce" class="text-sm font-normal text-error-500">Danger link</a>
                <a href="/ecommerce" class="text-sm font-normal text-warning-500">Warning link</a>
                <a href="/ecommerce" class="text-sm font-normal text-blue-light-500">Primary link</a>
                <a href="/ecommerce" class="text-sm font-normal text-gray-400">Primary link</a>
                <a href="/ecommerce" class="text-sm font-normal text-gray-800 dark:text-white/90">Primary link</a>
            </div>
        </x-common.component-card>

        <x-common.component-card title="Colored links with Underline">
            <div class="flex flex-col space-y-3">
                <a href="/ecommerce" class="text-sm font-normal text-gray-500 underline dark:text-gray-400">Primary link</a>
                <a href="/ecommerce" class="text-sm font-normal underline text-brand-500">Secondary link</a>
                <a href="/ecommerce" class="text-sm font-normal underline text-success-500">Success link</a>
                <a href="/ecommerce" class="text-sm font-normal underline text-error-500">Danger link</a>
                <a href="/ecommerce" class="text-sm font-normal underline text-warning-500">Warning link</a>
                <a href="/ecommerce" class="text-sm font-normal underline text-blue-light-500">Primary link </a>
                <a href="/ecommerce" class="text-sm font-normal text-gray-400 underline">Primary link</a>
                <a href="/ecommerce" class="text-sm font-normal text-gray-800 underline dark:text-white/90">Primary link</a>
            </div>
        </x-common.component-card>


        <x-common.component-card title="Link Opacity">
            <div class="flex flex-col space-y-3">
                <a href="/ecommerce" class="text-sm font-normal text-gray-500/10 dark:text-gray-400/10">
                    Link opacity 10
                </a>
                <a href="/ecommerce" class="text-sm font-normal text-gray-500/25 dark:text-gray-400/25">
                    Link opacity 25
                </a>
                <a href="/ecommerce" class="text-sm font-normal text-gray-500/50 dark:text-gray-400/50">
                    Link opacity 50
                </a>
                <a href="/ecommerce" class="text-sm font-normal text-gray-500/75 dark:text-gray-400/75">
                    Link opacity 75
                </a>
                <a href="/ecommerce" class="text-sm font-normal text-gray-500 dark:text-gray-400">
                    Link opacity 100
                </a>
            </div>
        </x-common.component-card>

        <x-common.component-card title="Link Opacity with Hover">
            <div class="flex flex-col space-y-3">
                <a href="/ecommerce"
                    class="text-sm font-normal text-gray-500 transition-colors hover:text-gray-500/10 dark:hover:text-gray-400/10">
                    Link opacity 10
                </a>
                <a href="/ecommerce"
                    class="text-sm font-normal text-gray-500 transition-colors hover:text-gray-500/25 dark:hover:text-gray-400/25">
                    Link opacity 25
                </a>
                <a href="/ecommerce"
                    class="text-sm font-normal text-gray-500 transition-colors hover:text-gray-500/50 dark:hover:text-gray-400/50">
                    Link opacity 50
                </a>
                <a href="/ecommerce"
                    class="text-sm font-normal text-gray-500 transition-colors hover:text-gray-500/75 dark:hover:text-gray-400/75">
                    Link opacity 75
                </a>
                <a href="/ecommerce"
                    class="text-sm font-normal text-gray-500 transition-colors dark:hover:text-gray-400/100">
                    Link opacity 50
                </a>
            </div>
        </x-common.component-card>
    </div>
@endsection

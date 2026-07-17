@extends('layouts.fullscreen-layout')

@section('content')
    <div class="z-1 bg-white p-6 sm:p-0 dark:bg-gray-900">
        <div class="relative flex h-screen w-full flex-col justify-center sm:p-0 lg:flex-row dark:bg-gray-900">
            <!-- Form -->
            <div class="flex w-full flex-1 flex-col lg:w-1/2">
                <div class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center">
                    <div class="mb-5 sm:mb-8">
                        <h1 class="text-title-sm sm:text-title-md mb-2 font-semibold text-gray-800 dark:text-white/90">
                            Setup Your Account
                        </h1>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Welcome! Please set your password to activate your account.
                        </p>
                    </div>
                    <div x-data="{ loading: false }">
                        <form action="{{ route('setup-account.store') }}" method="POST" @submit="loading = true">
                            @csrf
                            <input type="hidden" name="token" value="{{ $token }}">
                            <div class="space-y-6">
                                <!-- Email (Disabled) -->
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                        Email Address
                                    </label>
                                    <input type="email" value="{{ $email }}" disabled
                                        class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-gray-100 px-4 py-2.5 text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400" />
                                    <input type="hidden" name="email" value="{{ $email }}">
                                </div>

                                <!-- Password -->
                                <x-form.input label="Password" type="password" name="password" id="password"
                                    placeholder="Enter your password" required />

                                <!-- Confirm Password -->
                                <x-form.input label="Confirm Password" type="password" name="password_confirmation" id="password_confirmation"
                                    placeholder="Confirm your password" required />

                                @if ($errors->any())
                                    <div class="text-red-500 text-sm">
                                        {{ $errors->first() }}
                                    </div>
                                @endif

                                <!-- Button -->
                                <div>
                                    <button
                                        type="submit" :disabled="loading"
                                        class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 flex w-full items-center justify-center rounded-lg px-4 py-3 text-sm font-medium text-white transition disabled:opacity-70 disabled:cursor-not-allowed">
                                        <svg x-show="loading" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" x-cloak>
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span x-text="loading ? 'Activating...' : 'Activate & Sign In'"></span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="bg-brand-950 relative hidden h-full w-full items-center lg:grid lg:w-1/2 dark:bg-white/5">
                <div class="z-1 flex items-center justify-center">
                    <x-common.common-grid-shape />
                    <div class="flex max-w-xs flex-col items-center">
                        <a href="/" class="mb-4 block">
                            <img src="./images/logo/auth-logo.svg" alt="Logo" />
                        </a>
                        <p class="text-center text-gray-400 dark:text-white/60">
                            Get started with our premium EMR/CRM platform.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

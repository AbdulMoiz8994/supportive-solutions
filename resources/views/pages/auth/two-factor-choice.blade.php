@extends('layouts.fullscreen-layout')

@section('content')
    <div class="z-1 bg-white p-6 sm:p-0 dark:bg-gray-900">
        <div class="relative flex h-screen w-full flex-col justify-center sm:p-0 lg:flex-row dark:bg-gray-900">
            <!-- Form -->
            <div class="flex w-full flex-1 flex-col lg:w-1/2">
                <div class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center">
                    <div class="mb-5 sm:mb-8">
                        <h1 class="text-title-sm sm:text-title-md mb-2 font-semibold text-gray-800 dark:text-white/90">
                            Two-Factor Authentication
                        </h1>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Please select where you would like to receive your verification code.
                        </p>
                    </div>
                    <div x-data="{ loading: false }">
                        <form action="{{ route('two-factor.send') }}" method="POST" @submit="loading = true">
                            @csrf
                            <div class="space-y-4">
                                <!-- Email Option -->
                                <div class="flex items-center p-4 border border-gray-200 rounded-lg dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                                    <input type="radio" id="email" name="method" value="email" class="w-4 h-4 text-brand-600 bg-gray-100 border-gray-300 focus:ring-brand-500 dark:focus:ring-brand-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600" checked>
                                    <label for="email" class="ms-3 text-sm font-medium text-gray-900 dark:text-gray-300 flex flex-col">
                                        <span>Email Address</span>
                                        <span class="text-xs text-gray-500">{{ auth()->user()->email }}</span>
                                    </label>
                                </div>

                                <!-- Phone Option -->
                                <div class="flex items-center p-4 border border-gray-200 rounded-lg dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                                    <input type="radio" id="phone" name="method" value="phone" class="w-4 h-4 text-brand-600 bg-gray-100 border-gray-300 focus:ring-brand-500 dark:focus:ring-brand-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                    <label for="phone" class="ms-3 text-sm font-medium text-gray-900 dark:text-gray-300 flex flex-col">
                                        <span>Phone Number / SMS</span>
                                        <span class="text-xs text-gray-500">{{ auth()->user()->phone ?? 'Add phone to your profile' }}</span>
                                    </label>
                                </div>

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
                                        <span x-text="loading ? 'Sending...' : 'Send Verification Code'"></span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="bg-brand-950 relative hidden min-h-screen w-full items-center lg:grid lg:w-1/2">
                <div class="z-1 flex items-center justify-center">
                    <x-common.common-grid-shape />
                    <div class="flex max-w-xs flex-col items-center">
                        <div class="mb-4 block">
                            <div class="flex items-center justify-center w-16 h-16 rounded-xl bg-white/10 text-white font-bold text-2xl shadow-lg border border-white/20 backdrop-blur-sm">
                                B
                            </div>
                        </div>
                        <h2 class="text-2xl font-bold text-white mb-2 text-center">BeydounTech Home Care</h2>
                        <p class="text-center text-gray-400">
                            Two-factor authentication adds an extra layer of security to your account.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

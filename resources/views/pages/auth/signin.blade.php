@extends('layouts.fullscreen-layout')

@section('content')
    <div class="relative z-1 bg-white p-6 sm:p-0">
        <div class="flex min-h-screen w-full flex-col justify-center sm:p-0 lg:flex-row">
            <!-- Form -->
            <div class="flex w-full flex-1 flex-col lg:w-1/2 pb-5">
                <div class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center">
                    <div class="mb-5 sm:mb-8 text-center lg:text-left">
                        <h1 class="text-title-sm sm:text-title-md mb-2 font-semibold text-gray-800">
                            Sign In
                        </h1>
                        <p class="text-sm text-gray-500">
                            Enter your email and password to sign in!
                        </p>
                    </div>

                    <div class="w-full max-w-md mx-auto" x-data="{ loading: false }">
                        <form action="{{ route('signin.store') }}" method="POST" @submit="loading = true">
                            @csrf
                            <div class="space-y-5">
                                <!-- Email -->
                                <x-form.input label="Email" type="email" name="email" id="email"
                                    placeholder="Enter your email" value="{{ old('email') }}" required />

                                <!-- Password -->
                                <x-form.input label="Password" type="password" name="password" id="password"
                                    placeholder="Enter your password" required />
                                
                                <!-- Checkbox -->
                                <div class="flex items-center justify-between">
                                    <div x-data="{ checkboxToggle: false }">
                                        <label for="remember"
                                            class="flex cursor-pointer items-center text-sm font-normal text-gray-700 select-none">
                                            <div class="relative">
                                                <input type="checkbox" name="remember" id="remember" class="sr-only"
                                                    @change="checkboxToggle = !checkboxToggle" />
                                                <div :class="checkboxToggle ? 'border-brand-500 bg-brand-500' :
                                                    'bg-transparent border-gray-300'"
                                                    class="mr-3 flex h-5 w-5 items-center justify-center rounded-md border-[1.25px] transition-colors">
                                                    <span :class="checkboxToggle ? '' : 'opacity-0'">
                                                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                            <path d="M11.6666 3.5L5.24992 9.91667L2.33325 7" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                                        </svg>
                                                    </span>
                                                </div>
                                            </div>
                                            Keep me logged in
                                        </label>
                                    </div>
                                </div>

                                <!-- Button -->
                                <div>
                                    <button type="submit" :disabled="loading"
                                        class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 flex w-full items-center justify-center rounded-lg px-4 py-3 text-sm font-medium text-white transition-all active:scale-[0.98] disabled:opacity-70 disabled:cursor-not-allowed">
                                        <svg x-show="loading" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" x-cloak>
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span x-text="loading ? 'Signing In...' : 'Sign In'"></span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right Panel -->
            <div class="bg-brand-950 relative hidden min-h-screen w-full items-center lg:grid lg:w-1/2">
                <div class="z-1 flex items-center justify-center">
                    <!-- ===== Common Grid Shape Start ===== -->
                    <x-common.common-grid-shape />
                    <div class="flex max-w-xs flex-col items-center">
                        <div class="mb-4 block">
                            <div class="flex items-center justify-center w-16 h-16 rounded-xl bg-white/10 text-white font-bold text-2xl shadow-lg border border-white/20 backdrop-blur-sm">
                                B
                            </div>
                        </div>
                        <h2 class="text-2xl font-bold text-white mb-2 text-center">BeydounTech Home Care</h2>
                        <p class="text-center text-gray-400">
                            Comprehensive Business Management & Patient Care Platform
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-gray-50">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Dashboard' }} | {{ config('app.name', 'beydountech Home Care') }}</title>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Theme Store -->
    <style>
        [x-cloak] {
            display: none !important;
        }
        
        /* Anti-flicker classes for sidebar expansion */
        .sidebar-expanded aside#sidebar { width: 260px !important; }
        @media (min-width: 1024px) {
            .sidebar-expanded #main-content-wrap { padding-left: 260px !important; }
        }
        .sidebar-collapsed aside#sidebar { width: 80px !important; }
        @media (min-width: 1024px) {
            .sidebar-collapsed #main-content-wrap { padding-left: 80px !important; }
        }
        
        /* Hide text when collapsed */
        .sidebar-collapsed aside#sidebar .sidebar-text-hide { display: none !important; }
        .sidebar-collapsed aside#sidebar .sidebar-icon-center { justify-content: center !important; width: 46px !important; height: 46px !important; margin-left: auto !important; margin-right: auto !important; padding: 0 !important; }
    </style>

    <!-- Anti-flicker script (Blocking) -->
    <script>
        (function() {
            const isExpanded = localStorage.getItem('sidebarExpanded') === 'true';
            document.documentElement.classList.add(isExpanded ? 'sidebar-expanded' : 'sidebar-collapsed');
        })();
    </script>

    <!-- Global Utilities -->
    <script>
        // Phone number formatter: produces (313) 555-0000 as user types
        window.formatPhone = function(input) {
            let raw = input.value.replace(/\D/g, '').substring(0, 10);
            if (raw.length > 6) {
                input.value = '(' + raw.substring(0,3) + ') ' + raw.substring(3,6) + '-' + raw.substring(6);
            } else if (raw.length > 3) {
                input.value = '(' + raw.substring(0,3) + ') ' + raw.substring(3);
            } else if (raw.length > 0) {
                input.value = '(' + raw;
            } else {
                input.value = '';
            }
        };
        // Auto-apply mask to any input with data-phone attribute on DOM-ready
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('input[data-phone]').forEach(function(el) {
                el.addEventListener('input', function() { window.formatPhone(this); });
            });
        });
    </script>

    <!-- Alpine Store Initialization -->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('sidebar', {
                expanded: localStorage.getItem('sidebarExpanded') === 'true',
                isMobileOpen: false,
                toggle() {
                    this.expanded = !this.expanded;
                    localStorage.setItem('sidebarExpanded', this.expanded ? 'true' : 'false');
                    
                    // Update root classes for immediate CSS response
                    if (this.expanded) {
                        document.documentElement.classList.add('sidebar-expanded');
                        document.documentElement.classList.remove('sidebar-collapsed');
                    } else {
                        document.documentElement.classList.add('sidebar-collapsed');
                        document.documentElement.classList.remove('sidebar-expanded');
                    }
                },
                setMobile(val) {
                    this.isMobileOpen = val;
                }
            })
        })
    </script>
</head>

<body class="bg-[#eff6ff] font-onest text-[#1e293b] overflow-x-hidden selection:bg-[#2563eb]/10 selection:text-[#2563eb]" x-data="{ aiPanelOpen: false }">

    <div class="flex min-h-screen bg-[#eff6ff]">
        <!-- Sidebar -->
        @include('layouts.sidebar')

        <!-- Main Content Area -->
        <div id="main-content-wrap" class="flex-1 flex flex-col min-w-0 transition-all duration-300 ease-in-out" 
             :class="$store.sidebar.expanded ? 'lg:pl-[260px]' : 'lg:pl-[80px]'"
             @open-ai-panel.window="aiPanelOpen = true">
            
            <!-- Header --> 
            @include('layouts.app-header')

            <!-- Page Content -->
            <main class="flex-1 px-4 lg:px-8 py-5 bg-[#f6f9fe]">
                @yield('content')
            </main>
        </div>


        <!-- AI Side Panel -->
        <template x-if="aiPanelOpen">
            <div class="fixed inset-0 z-999999 flex justify-end">
                <div @click="aiPanelOpen = false" class="absolute inset-0 bg-gray-900/40 backdrop-blur-sm"></div>
                <div class="relative w-full max-w-sm bg-white h-full shadow-2xl flex flex-col"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="translate-x-full"
                     x-transition:enter-end="translate-x-0">
                    <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-brand-500 rounded-lg flex items-center justify-center text-white">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            </div>
                            <h2 class="text-sm font-black text-gray-900 uppercase tracking-widest">AI Assistant</h2>
                        </div>
                        <button @click="aiPanelOpen = false" class="p-2 text-gray-400 hover:text-gray-900 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                    <div class="flex-1 overflow-y-auto p-6 space-y-6">
                        <!-- AI Content -->
                        <div class="p-4 bg-brand-50 rounded-2xl border border-brand-100 italic">
                            <p class="text-xs text-brand-900 leading-relaxed font-bold">
                                "I've analyzed the notes for Client #082: Authorization is expiring in 5 days. Should I draft an extension request?"
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>


</body>

<x-flash-toastr />
<x-ui.dialog-host />

@stack('scripts')

</html>

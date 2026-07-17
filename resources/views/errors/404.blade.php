<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Page Not Found | {{ config('app.name', 'BeydounTech Home Care') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#eff6ff] font-onest text-[#1e293b]">
    <div class="min-h-screen flex items-center justify-center px-6">
        <div class="w-full max-w-xl rounded-3xl border border-[#dbe7fb] bg-white p-10 text-center shadow-sm">
            <img src="/images/logo/logo.svg" alt="BeydounTech" class="mx-auto h-12 w-auto mb-5">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-[#64748b]">Error 404</p>
            <h1 class="mt-2 text-3xl font-extrabold text-[#0f172a]">Page not found</h1>
            <p class="mt-3 text-sm text-[#64748b]">
                The page you requested does not exist or was moved.
            </p>
            <a href="{{ url('/dashboard') }}"
               class="mt-7 inline-flex items-center justify-center rounded-[10px] bg-[#2563eb] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#1d4ed8] transition-colors">
                Return to dashboard
            </a>
        </div>
    </div>
</body>
</html>

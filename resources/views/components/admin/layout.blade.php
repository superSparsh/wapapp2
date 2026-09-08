@props(['title' => null, 'active' => ''])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Admin — '.config('app.name', 'WapApp') }}</title>
    <x-layouts.theme-boot />
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head-scripts')
</head>
<body class="h-screen overflow-hidden bg-surface font-display text-text-primary antialiased">
    <div class="flex h-screen">
        <div class="relative hidden h-screen shrink-0 overflow-visible lg:block">
            <x-admin.sidebar :active="$active ?? ''" :mobile="false" />
        </div>
        <div id="mobile-sidebar" class="fixed inset-y-0 left-0 z-50 w-[243px] -translate-x-full overflow-hidden transition-transform lg:hidden">
            <x-admin.sidebar :active="$active ?? ''" :mobile="true" />
        </div>
        <div id="sidebar-backdrop" class="fixed inset-0 z-40 hidden bg-overlay lg:hidden" aria-hidden="true"></div>
        <div class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
            <x-admin.header />
            <main class="min-h-0 flex-1 overflow-y-auto bg-surface">
                @if (session('status'))
                    <div class="mx-4 mt-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
                        {{ session('status') }}
                    </div>
                @endif
                @if (session('error'))
                    <div class="mx-4 mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-600">
                        {{ session('error') }}
                    </div>
                @endif
                {{ $slot }}
            </main>
        </div>
    </div>
    <script>
      document.addEventListener('DOMContentLoaded', () => {
        const sidebar = document.getElementById('mobile-sidebar');
        const backdrop = document.getElementById('sidebar-backdrop');
        const toggle = () => {
          sidebar?.classList.toggle('-translate-x-full');
          backdrop?.classList.toggle('hidden');
        };
        backdrop?.addEventListener('click', toggle);
        document.querySelector('[data-mobile-menu]')?.addEventListener('click', toggle);
      });
    </script>
    <x-ui.confirm-dialog />
    <x-ui.page-loader />
</body>
</html>

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />

        <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @fluxAppearance
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-900 dark:text-zinc-100">
        <header class="border-b border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800">
            <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-3">
                <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-2 font-semibold">
                    <flux:icon.wrench-screwdriver class="size-5" />
                    {{ config('app.name') }}
                </a>

                <nav class="flex flex-wrap items-center gap-1 text-sm">
                    <flux:button :href="route('reports.index')" wire:navigate variant="ghost" size="sm">Daftar Laporan</flux:button>
                    <flux:button :href="route('reports.create')" wire:navigate variant="primary" size="sm" icon="plus">Buat Laporan</flux:button>

                    @auth
                        <flux:button :href="route('reports.mine')" wire:navigate variant="ghost" size="sm">Laporan Saya</flux:button>
                        @if (auth()->user()->isAdmin())
                            <flux:button :href="route('admin.dashboard')" variant="ghost" size="sm">Admin</flux:button>
                        @endif
                        <flux:button :href="route('profile.edit')" variant="ghost" size="sm">Pengaturan</flux:button>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <flux:button type="submit" variant="ghost" size="sm">Keluar</flux:button>
                        </form>
                    @else
                        <flux:button :href="route('login')" variant="ghost" size="sm">Masuk</flux:button>
                        <flux:button :href="route('register')" variant="ghost" size="sm">Daftar</flux:button>
                    @endauth
                </nav>
            </div>
        </header>

        <main class="mx-auto w-full max-w-5xl px-4 py-6 sm:py-8">
            {{ $slot }}
        </main>

        <footer class="mx-auto max-w-5xl px-4 py-6 text-center text-xs text-zinc-500 dark:text-zinc-400">
            {{ config('app.name') }} · Pelaporan kerusakan fasilitas kantor
        </footer>

        @fluxScripts
    </body>
</html>

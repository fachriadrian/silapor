@props(['active' => 'laporan'])

<nav class="flex flex-wrap gap-2 border-b border-zinc-200 pb-3 dark:border-zinc-700">
    <flux:button
        :href="route('admin.dashboard')"
        size="sm"
        :variant="$active === 'dashboard' ? 'primary' : 'ghost'"
    >
        Dashboard
    </flux:button>

    <flux:button
        :href="route('admin.reports.index')"
        size="sm"
        :variant="$active === 'laporan' ? 'primary' : 'ghost'"
    >
        Kelola Laporan
    </flux:button>
</nav>

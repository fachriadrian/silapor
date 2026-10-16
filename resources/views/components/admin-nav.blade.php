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

    <flux:button
        :href="route('admin.statistics')"
        size="sm"
        :variant="$active === 'statistik' ? 'primary' : 'ghost'"
    >
        Statistik
    </flux:button>

    <flux:button
        :href="route('admin.categories.index')"
        size="sm"
        :variant="$active === 'kategori' ? 'primary' : 'ghost'"
    >
        Kategori
    </flux:button>

    <flux:button
        :href="route('admin.users.index')"
        size="sm"
        :variant="$active === 'pengguna' ? 'primary' : 'ghost'"
    >
        Pengguna
    </flux:button>
</nav>

<x-layouts::public :title="__('Dashboard Admin')">
    <div class="space-y-6">
        <x-admin-nav active="dashboard" />

        <flux:heading size="xl" level="1">{{ __('Dashboard Admin') }}</flux:heading>

        <flux:text>
            {{ __('Halo, :name. Ringkasan dan statistik laporan akan dibangun di Fase 3.', ['name' => auth()->user()->name]) }}
        </flux:text>

        <flux:button :href="route('admin.reports.index')" variant="primary">Kelola Laporan</flux:button>
    </div>
</x-layouts::public>

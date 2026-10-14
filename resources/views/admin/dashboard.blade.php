<x-layouts::app :title="__('Dashboard Admin')">
    <div class="flex flex-col gap-4">
        <flux:heading size="xl">{{ __('Dashboard Admin') }}</flux:heading>

        <flux:text>
            {{ __('Halo, :name. Halaman ini masih sementara; dashboard laporan dibangun di Fase 3.', ['name' => auth()->user()->name]) }}
        </flux:text>
    </div>
</x-layouts::app>

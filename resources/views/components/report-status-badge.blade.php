@props(['status'])

<flux:badge :color="$status->color()" size="sm">{{ $status->label() }}</flux:badge>

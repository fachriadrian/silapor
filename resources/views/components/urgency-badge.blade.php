@props(['urgency'])

<flux:badge :color="$urgency->color()" size="sm">Urgensi {{ $urgency->label() }}</flux:badge>

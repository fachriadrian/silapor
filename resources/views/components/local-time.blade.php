@props(['value' => null])

@if ($value)
    {{ $value->copy()->timezone(config('silapor.display_timezone', 'Asia/Jakarta'))->translatedFormat('j M Y, H:i') }} WIB
@endif

<x-mail::message>
@if (! empty($greeting))
# {{ $greeting }}
@elseif ($level === 'error' && count($introLines ?? []) === 0)
# Atsiprašome!
@endif

{{-- Intro Lines --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

{{-- Action Button --}}
@isset($actionText)
<?php
    $color = match ($level) {
        'success', 'error' => $level,
        default => 'primary',
    };
?>
<x-mail::button :url="$actionUrl" :color="$color">
{{ $actionText }}
</x-mail::button>
@endisset

{{-- Outro Lines --}}
@foreach ($outroLines as $line)
{{ $line }}

@endforeach

{{-- Salutation --}}
@if (! empty($salutation))
{{ $salutation }}
@else
Pagarbiai,<br>
{{ config('mail.brand_name') }}
@endif

{{-- Subcopy --}}
@isset($actionText)
<x-slot:subcopy>
Jei mygtukas neveikia: [{{ config('mail.brand_name') }}]({{ $actionUrl }})
</x-slot:subcopy>
@endisset
</x-mail::message>

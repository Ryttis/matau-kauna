<x-mail::layout>
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('mail.brand_name') }}
</x-mail::header>
</x-slot:header>

{{ $slot }}

@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{{ $subcopy }}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ config('mail.brand_name') }}. Visos teisės saugomos.
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>

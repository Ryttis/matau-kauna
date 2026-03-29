{{ $contentText }}

@if (!empty($actionUrl))
{{ $actionLabel }}: {{ $actionUrl }}
@endif

--
© {{ date('Y') }} {{ $mailBrandName }}

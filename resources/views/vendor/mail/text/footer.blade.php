@props([
    'manageNotificationsUrl' => null,
    'unsubscribeUrl' => null,
])
@if ($manageNotificationsUrl)
{{ __('Manage notifications') }}: {{ $manageNotificationsUrl }}
@endif
@if ($unsubscribeUrl)
{{ __('Unsubscribe') }}: {{ $unsubscribeUrl }}
@endif
{{ $slot }}

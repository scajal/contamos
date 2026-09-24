@props([
    'manageNotificationsUrl' => null,
    'unsubscribeUrl' => null,
])
<tr>
<td>
<table class="footer" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
@if ($manageNotificationsUrl || $unsubscribeUrl)
<tr>
<td class="content-cell footer-links" align="center">
@if ($manageNotificationsUrl)
<a href="{{ $manageNotificationsUrl }}">{{ __('Manage notifications') }}</a>
@endif
@if ($manageNotificationsUrl && $unsubscribeUrl)
<span class="footer-separator">&middot;</span>
@endif
@if ($unsubscribeUrl)
<a href="{{ $unsubscribeUrl }}">{{ __('Unsubscribe') }}</a>
@endif
</td>
</tr>
@endif
<tr>
<td class="content-cell" align="center">
{{ Illuminate\Mail\Markdown::parse($slot) }}
</td>
</tr>
</table>
</td>
</tr>

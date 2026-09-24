@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" class="brand">
<img src="{{ asset('images/iso-logo.png') }}" class="brand-mark" width="28" height="28" alt="">
<span class="brand-wordmark">{{ trim($slot) }}</span>
</a>
</td>
</tr>

@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
<span style="font-size: 19px; font-weight: bold; color: #1946a0;">{{ $slot }}</span>
</a>
</td>
</tr>

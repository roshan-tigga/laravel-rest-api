<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if(session('organizationLogo'))
    <img src="{{ session('organizationLogo') }}" class="logo" alt="Logo" style="width:100%;">
@elseif(session('organizationName'))
    {{ session('organizationName') }}
@else
    @if (trim($slot) === 'Laravel')
    <img src="https://laravel.com/img/notification-logo.png" class="logo" alt="Laravel Logo">
    @else
    {{ $slot }}
    @endif
@endif
</a>
</td>
</tr>

@component('mail::message')

<h1 style="font-size:25px;">Hi {{ $mailData['firstName'] }} {{ $mailData['lastName'] }},<br></h1>
<p>{{ $mailData['messageBody'] }}</p>

<br><br>
Thanks,<br>
@if(isset($mailData['organizationName']))
    {{ $mailData['organizationName'] }}
@else
    {{ config('app.name') }}
@endif
@endcomponent
<!DOCTYPE html>
<html>
<head>
    <title>{{ $mailData['subject'] }}</title>
</head>
<body>
    <p><center><img src="{{ $mailData['organizationLogo'] }}" style="width: 200px;height: 100px;"></center></p>
    <p>{{ $mailData['messageBody'] }}</p>
     
    <p>Thank you.</p>

    <br>
    <p>Regards </p>
    <p>{{ $mailData['organizationName'] }}</p>
</body>
</html>
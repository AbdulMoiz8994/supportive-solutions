<x-mail::message>
# Verification Code

Your verification code is: **{{ $otp }}**

This code will expire in 10 minutes. If you did not request this code, please ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>

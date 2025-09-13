@component('mail::message')
# Hi {{ $name }},

Use this verification code to reset your password:

# **{{ $otp }}**

This code expires in 10 minutes. If you didn’t request this, you can ignore this email.

Thanks,  
{{ config('app.name') }}
@endcomponent

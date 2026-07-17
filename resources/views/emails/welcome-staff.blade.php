<x-mail::message>
# Welcome, {{ $user->name }}!

You have been added as a staff member to the {{ config('app.name') }} portal. To get started, please activate your account and set up your password by clicking the button below.

<x-mail::button :url="$url">
Activate Account
</x-mail::button>

**Account Details:**
- **Username/Email:** {{ $user->email }}

This setup link will expire in 24 hours.

If you did not expect this invitation, please ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>

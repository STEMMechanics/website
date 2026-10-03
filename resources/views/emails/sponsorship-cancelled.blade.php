@component('mail::message', ['email' => $email])
@php($firstName = trim((string) strtok($recipientName, ' ')))
# Monthly sponsorship cancelled

Hi {{ $firstName !== '' ? $firstName : 'there' }},

Your monthly sponsorship has been cancelled. No future monthly {{ $billingMethod === 'invoice' ? 'invoices will be sent' : 'payments will be scheduled' }}.

**Monthly amount:** {{ $amount }}  
**Cancelled on:** {{ $cancelledAt }}

Thank you for supporting STEMMechanics. Your support has helped us keep our programs and projects moving, and we hope you’ll subscribe again in the future.

There are other ways to help out and stay involved too. You can join our [STEMCraft Minecraft server]({{ route('stemcraft.join') }}), connect with us [on Discord](https://stemmech.com.au/discord), or explore and contribute to our [open-source projects](https://github.com/stemmechanics).

Your payment history and invoices remain available from your sponsorship management page.

@component('mail::button', ['url' => $manageUrl])
Manage my sponsorship
@endcomponent

If you didn’t request this change or have any questions, please contact us.

Thanks,  
{{ config('app.name') }}
@endcomponent

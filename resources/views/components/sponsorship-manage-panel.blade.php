@props(['sponsor'])
<section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
    <h2 class="text-xl font-semibold text-gray-900">{{ $sponsor->company_name ?: $sponsor->contact_name }}</h2>
    <p class="mt-1 text-sm text-gray-600">Recognition is {{ $sponsor->recognition_public ? 'public' : 'private' }}.</p>
    <a href="{{ route('sponsor.account.manage') }}" class="mt-3 inline-block font-medium text-primary-color hover:underline">Manage recognition and payment history</a>
</section>

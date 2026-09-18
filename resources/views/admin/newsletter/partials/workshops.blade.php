<section data-newsletter-panel="workshops" class="mb-8">
    <div class="mt-5">
        @if($contentOrder === 'store' && $newsletterWorkshops->isNotEmpty())
        <h3 class="text-2xl font-black tracking-tight text-slate-900">Upcoming workshops</h3>
        <p class="mt-2 text-sm leading-6 text-slate-500">Book a hands-on session and keep the making going.</p>
        @endif
        <div class="mt-5 space-y-4">
            @forelse($newsletterWorkshops as $workshop)
                @include('admin.newsletter.partials.workshop-card', ['hidden' => false])
            @empty
                <div class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-center text-sm text-gray-500">No workshops are currently selected for the newsletter.</div>
            @endforelse
        </div>
    </div>
    @if($newsletterWorkshops->isNotEmpty())
        <p class="my-7 text-center"><a href="{{ url('/workshops') }}" class="inline-block rounded-3xl bg-slate-900 px-8 py-4 text-lg font-extrabold text-white">{{ config('newsletter.upcoming_workshops.button_label', 'View All Workshops') }}</a></p>
    @endif
    @if($hiddenNewsletterWorkshops->isNotEmpty())
        <details class="mt-4 rounded-xl border border-dashed border-gray-200 bg-gray-50 p-4">
            <summary class="cursor-pointer font-semibold text-gray-700">Hidden workshops ({{ $hiddenNewsletterWorkshops->count() }})</summary>
            <div class="mt-5 space-y-4">
                @foreach($hiddenNewsletterWorkshops as $workshop)
                    @include('admin.newsletter.partials.workshop-card', ['hidden' => true])
                @endforeach
            </div>
        </details>
    @endif
</section>

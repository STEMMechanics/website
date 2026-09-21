@if(isset($checkoutWorkshops) && $checkoutWorkshops->count() > 1)
    <div class="mb-5 rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm">
        <h3 class="mb-2 font-semibold">Your workshops</h3>
        <ul class="space-y-2">
            @foreach($checkoutWorkshops as $selectedWorkshop)
                <li><span class="font-semibold">{{ $selectedWorkshop->title }}</span><span class="block text-gray-600">{{ $selectedWorkshop->getTicketTimeRangeLabel() }} · {{ $selectedWorkshop->getLocationDisplay(true) }}</span></li>
            @endforeach
        </ul>
        <p class="mt-3 text-gray-600">{{ $session['participant_count'] ?? 1 }} participant(s) at each workshop. One checkout for all selected sessions.</p>
    </div>
@endif

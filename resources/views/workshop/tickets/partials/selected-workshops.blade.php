@if(isset($checkoutWorkshops) && $checkoutWorkshops->count() > 1)
    <div class="mb-5 rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm">
        <h3 class="mb-4 font-semibold">Your workshops</h3>
        <ul class="divide-y divide-sky-200">
            @foreach($checkoutWorkshops as $selectedWorkshop)
                <li class="py-4 first:pt-0 last:pb-0">
                    <div class="flex flex-wrap items-baseline gap-x-4 gap-y-2">
                        <span class="min-w-0 flex-1 basis-48 font-semibold">{{ $selectedWorkshop->title }}</span>
                        @if(isset($workshopPricing))
                            <div class="ml-auto shrink-0 space-y-1 text-right text-gray-700">
                                @foreach($workshopPricing->where('workshop_id', $selectedWorkshop->id) as $price)
                                    <p>{{ $price['value'] }}</p>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <p class="mt-2 text-gray-600">{{ $selectedWorkshop->getTicketTimeRangeLabel() }}</p>
                    <p class="mt-1 text-gray-600">{{ $selectedWorkshop->getLocationDisplay(true) }}</p>
                </li>
            @endforeach
        </ul>
        @unless(isset($workshopPricing))<p class="mt-3 text-gray-600">One booking for your selected participants and workshops.</p>@endunless
    </div>
@endif
